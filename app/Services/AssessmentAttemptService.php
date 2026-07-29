<?php

declare(strict_types=1);

namespace App\Services;

use App\Security\HashManager;
use App\Support\Database;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;
use PDOException;
use Throwable;

class AssessmentAttemptService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Start a new assessment attempt.
     *
     * @return array{
     *     success: bool,
     *     code?: string,
     *     attempt_id?: int,
     *     session_token?: string
     * }
     */
    public function startAttempt(
        int $employeeId,
        int $assignmentId,
        string $ipAddress,
        string $userAgent
    ): array {
        try {
            $this->pdo->beginTransaction();

            $assignment = $this->lockAssignment(
                $employeeId,
                $assignmentId
            );

            if (!$assignment) {
                throw new DomainException('invalid_assignment');
            }

            if ((int) $assignment['test_is_active'] !== 1) {
                throw new DomainException('test_not_available');
            }

            if ((int) $assignment['is_available'] !== 1) {
                throw new DomainException('test_not_available');
            }

            if (!in_array(
                $assignment['status'],
                ['PENDING', 'RETAKE_AUTHORIZED'],
                true
            )) {
                throw new DomainException('invalid_assignment_status');
            }

            $retakeAuthorizationId = null;
            if ((string) $assignment['status'] === 'RETAKE_AUTHORIZED') {
                $retakeStmt = $this->pdo->prepare("
                    SELECT id
                    FROM retake_authorizations
                    WHERE assignment_id = :assignment_id
                      AND consumed_at IS NULL
                    ORDER BY authorized_at DESC, id DESC
                    LIMIT 1
                    FOR UPDATE
                ");
                $retakeStmt->execute([
                    ':assignment_id' => (int) $assignment['id'],
                ]);
                $retakeValue = $retakeStmt->fetchColumn();
                if ($retakeValue === false) {
                    throw new DomainException('invalid_retake_authorization');
                }
                $retakeAuthorizationId = (int) $retakeValue;
            }

            $activeAttempt = $this->lockActiveAttempt($employeeId);

            if ($activeAttempt) {
                throw new DomainException('active_attempt_exists');
            }

            $lastAttemptNumber = $this->getLastAttemptNumber(
                (int) $assignment['id']
            );

            $attemptNumber = $lastAttemptNumber + 1;
            $maxAttempts = (int) $assignment['max_attempts'];

            if ($attemptNumber > $maxAttempts) {
                throw new DomainException('attempt_limit_reached');
            }

            $questionCount = (int) $assignment['question_count'];
            $durationMinutes = (int) $assignment['duration_minutes'];

            if ($questionCount <= 0 || $durationMinutes <= 0) {
                throw new DomainException('invalid_test_configuration');
            }

            $questions = $this->loadQuestions(
                (int) $assignment['test_id'],
                (bool) $assignment['randomize_questions'],
                (bool) $assignment['randomize_options'],
                $questionCount
            );

            if (count($questions) < $questionCount) {
                throw new DomainException('insufficient_questions');
            }

            $totalScore = array_sum(
                array_column($questions, 'score')
            );

            $utc = new DateTimeZone('UTC');
            $startedAt = new DateTimeImmutable('now', $utc);
            $durationSeconds = $durationMinutes * 60;
            $expiresAt = $startedAt->modify(
                "+{$durationSeconds} seconds"
            );

            $attemptId = $this->insertAttempt(
                $employeeId,
                (int) $assignment['test_id'],
                (int) $assignment['id'],
                $attemptNumber,
                $startedAt,
                $expiresAt,
                $durationSeconds,
                (float) $totalScore,
                $ipAddress,
                $userAgent
            );

            $this->insertAttemptQuestions(
                $attemptId,
                $questions
            );

            if ($retakeAuthorizationId !== null) {
                $consumeRetake = $this->pdo->prepare("
                    UPDATE retake_authorizations
                    SET consumed_at = UTC_TIMESTAMP(),
                        consumed_by_attempt_id = :attempt_id
                    WHERE id = :authorization_id
                      AND assignment_id = :assignment_id
                      AND consumed_at IS NULL
                ");
                $consumeRetake->execute([
                    ':attempt_id' => $attemptId,
                    ':authorization_id' => $retakeAuthorizationId,
                    ':assignment_id' => (int) $assignment['id'],
                ]);
                if ($consumeRetake->rowCount() !== 1) {
                    throw new DomainException('invalid_retake_authorization');
                }
            }

            $sessionToken = HashManager::generateRandomToken(32);
            $sessionTokenHash = HashManager::hashToken($sessionToken);

            $this->insertTestSession(
                $employeeId,
                $attemptId,
                $sessionTokenHash,
                $ipAddress
            );

            $assignmentUpdate = $this->pdo->prepare("
                UPDATE test_assignments
                SET status = 'IN_PROGRESS'
                WHERE id = :assignment_id
                  AND employee_id = :employee_id
            ");

            $assignmentUpdate->execute([
                ':assignment_id' => $assignmentId,
                ':employee_id' => $employeeId,
            ]);

            $progressStmt = $this->pdo->prepare("
                INSERT INTO assessment_progress
                    (
                        employee_id,
                        overall_status,
                        current_test_id,
                        completed_at,
                        updated_at
                    )
                VALUES
                    (
                        :employee_id,
                        'IN_PROGRESS',
                        :test_id,
                        NULL,
                        UTC_TIMESTAMP()
                    )
                ON DUPLICATE KEY UPDATE
                    overall_status = 'IN_PROGRESS',
                    current_test_id = VALUES(current_test_id),
                    completed_at = NULL,
                    updated_at = UTC_TIMESTAMP()
            ");

            $progressStmt->execute([
                ':employee_id' => $employeeId,
                ':test_id' => (int) $assignment['test_id'],
            ]);

            $auditStmt = $this->pdo->prepare("
                INSERT INTO audit_logs
                    (
                        actor_type,
                        employee_id,
                        admin_id,
                        action,
                        entity_type,
                        entity_id,
                        old_values,
                        new_values,
                        ip_address,
                        user_agent,
                        created_at
                    )
                VALUES
                    (
                        'EMPLOYEE',
                        :employee_id,
                        NULL,
                        'ASSESSMENT_ATTEMPT_STARTED',
                        'test_attempt',
                        :attempt_id,
                        NULL,
                        :new_values,
                        :ip_address,
                        :user_agent,
                        UTC_TIMESTAMP()
                    )
            ");

            $auditStmt->execute([
                ':employee_id' => $employeeId,
                ':attempt_id' => $attemptId,
                ':new_values' => json_encode([
                    'assignment_id' => $assignmentId,
                    'test_id' => (int) $assignment['test_id'],
                    'attempt_number' => $attemptNumber,
                    'question_count' => count($questions),
                    'duration_seconds' => $durationSeconds,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'attempt_id' => $attemptId,
                'session_token' => $sessionToken,
            ];
        } catch (DomainException $e) {
            $this->rollBack();

            return [
                'success' => false,
                'code' => $e->getMessage(),
            ];
        } catch (PDOException $e) {
            $this->rollBack();

            if ($e->getCode() === '23000') {
                return [
                    'success' => false,
                    'code' => 'active_attempt_exists',
                ];
            }

            return [
                'success' => false,
                'code' => 'attempt_start_failed',
            ];
        } catch (Throwable $e) {
            $this->rollBack();

            return [
                'success' => false,
                'code' => 'attempt_start_failed',
            ];
        }
    }

    public function getActiveAttempt(
        int $employeeId,
        ?string $sessionToken
    ): ?array {
        if (
            $employeeId <= 0 ||
            !is_string($sessionToken) ||
            $sessionToken === ''
        ) {
            return null;
        }

        $tokenHash = HashManager::hashToken($sessionToken);

        $stmt = $this->pdo->prepare("
            SELECT
                ta.*,
                t.code AS test_code,
                t.name_ar AS test_name_ar,
                t.name_en AS test_name_en,
                t.instructions_ar,
                t.instructions_en,
                t.allow_back_navigation,
                t.show_result_to_employee,
                ts.id AS test_session_id,
                TIMESTAMPDIFF(
                    SECOND,
                    UTC_TIMESTAMP(),
                    ta.expires_at
                ) AS remaining_seconds
            FROM test_attempts ta
            JOIN tests t
                ON t.id = ta.test_id
            JOIN test_sessions ts
                ON ts.attempt_id = ta.id
               AND ts.employee_id = ta.employee_id
            WHERE ta.employee_id = :employee_id
              AND ta.status = 'IN_PROGRESS'
              AND ts.status = 'ACTIVE'
              AND ts.session_token_hash = :token_hash
              AND ta.expires_at > UTC_TIMESTAMP()
            ORDER BY ta.id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':employee_id' => $employeeId,
            ':token_hash' => $tokenHash,
        ]);

        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            return null;
        }

        $activityStmt = $this->pdo->prepare("
            UPDATE test_sessions
            SET last_activity_at = UTC_TIMESTAMP()
            WHERE id = :session_id
              AND status = 'ACTIVE'
        ");

        $activityStmt->execute([
            ':session_id' => (int) $attempt['test_session_id'],
        ]);

        return $attempt;
    }

    public function getAttemptQuestions(int $attemptId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                aq.id,
                aq.attempt_id,
                aq.original_question_id,
                aq.question_snapshot,
                aq.options_snapshot,
                aq.score_snapshot,
                aq.display_order,
                aa.selected_option_key,
                aa.updated_at AS last_saved_at
            FROM attempt_questions aq
            LEFT JOIN attempt_answers aa
                ON aa.attempt_question_id = aq.id
            WHERE aq.attempt_id = :attempt_id
            ORDER BY aq.display_order ASC
        ");

        $stmt->execute([
            ':attempt_id' => $attemptId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save or update one answer without exposing correctness.
     *
     * @return array{
     *     success: bool,
     *     code?: string,
     *     attempt_question_id?: int,
     *     selected_option_key?: string,
     *     remaining_seconds?: int
     * }
     */
    public function saveAnswer(
        int $employeeId,
        int $attemptQuestionId,
        string $selectedOptionKey,
        string $sessionToken
    ): array {
        $selectedOptionKey = trim($selectedOptionKey);
        $sessionToken = trim($sessionToken);

        if (
            $employeeId <= 0 ||
            $attemptQuestionId <= 0 ||
            $selectedOptionKey === ''
        ) {
            return [
                'success' => false,
                'code' => 'invalid_answer',
            ];
        }

        if ($sessionToken === '') {
            return [
                'success' => false,
                'code' => 'assessment_session_unavailable',
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $tokenHash = HashManager::hashToken(
                $sessionToken
            );

            $questionStmt = $this->pdo->prepare("
                SELECT
                    aq.id AS attempt_question_id,
                    aq.attempt_id,
                    aq.options_snapshot,
                    aq.correct_option_key_snapshot,
                    aq.score_snapshot,
                    ta.expires_at,
                    ts.id AS test_session_id,
                    TIMESTAMPDIFF(
                        SECOND,
                        UTC_TIMESTAMP(),
                        ta.expires_at
                    ) AS remaining_seconds
                FROM attempt_questions aq
                JOIN test_attempts ta
                    ON ta.id = aq.attempt_id
                JOIN test_sessions ts
                    ON ts.attempt_id = ta.id
                   AND ts.employee_id = ta.employee_id
                WHERE aq.id = :attempt_question_id
                  AND ta.employee_id = :employee_id
                  AND ta.status = 'IN_PROGRESS'
                  AND ts.status = 'ACTIVE'
                  AND ts.session_token_hash = :token_hash
                LIMIT 1
                FOR UPDATE
            ");

            $questionStmt->execute([
                ':attempt_question_id' =>
                    $attemptQuestionId,

                ':employee_id' =>
                    $employeeId,

                ':token_hash' =>
                    $tokenHash,
            ]);

            $question = $questionStmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$question) {
                throw new DomainException(
                    'assessment_session_unavailable'
                );
            }

            $remainingSeconds =
                (int) $question['remaining_seconds'];

            if ($remainingSeconds <= 0) {
                throw new DomainException(
                    'attempt_expired'
                );
            }

            $options = json_decode(
                (string) $question['options_snapshot'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($options)) {
                throw new DomainException(
                    'invalid_question_configuration'
                );
            }

            $validOption = false;

            foreach ($options as $option) {
                if (
                    is_array($option) &&
                    isset($option['key']) &&
                    (string) $option['key'] ===
                        $selectedOptionKey
                ) {
                    $validOption = true;
                    break;
                }
            }

            if (!$validOption) {
                throw new DomainException(
                    'invalid_answer'
                );
            }

            $isCorrect =
                hash_equals(
                    (string) $question[
                        'correct_option_key_snapshot'
                    ],
                    $selectedOptionKey
                );

            $awardedScore = $isCorrect
                ? (float) $question['score_snapshot']
                : 0.00;

            $answerStmt = $this->pdo->prepare("
                INSERT INTO attempt_answers
                    (
                        attempt_question_id,
                        selected_option_key,
                        answer_text,
                        is_correct,
                        awarded_score,
                        answered_at,
                        created_at,
                        updated_at
                    )
                VALUES
                    (
                        :attempt_question_id,
                        :selected_option_key,
                        NULL,
                        :is_correct,
                        :awarded_score,
                        UTC_TIMESTAMP(),
                        UTC_TIMESTAMP(),
                        UTC_TIMESTAMP()
                    )
                ON DUPLICATE KEY UPDATE
                    selected_option_key = :update_selected_option_key,
                    answer_text = NULL,
                    is_correct = :update_is_correct,
                    awarded_score = :update_awarded_score,
                    updated_at = UTC_TIMESTAMP()
            ");

            $answerStmt->execute([
                ':attempt_question_id' =>
                    $attemptQuestionId,

                ':selected_option_key' =>
                    $selectedOptionKey,

                ':is_correct' =>
                    $isCorrect ? 1 : 0,

                ':awarded_score' =>
                    $awardedScore,

                ':update_selected_option_key' =>
                    $selectedOptionKey,

                ':update_is_correct' =>
                    $isCorrect ? 1 : 0,

                ':update_awarded_score' =>
                    $awardedScore,
            ]);

            $sessionStmt = $this->pdo->prepare("
                UPDATE test_sessions
                SET last_activity_at = UTC_TIMESTAMP()
                WHERE id = :session_id
                  AND status = 'ACTIVE'
            ");

            $sessionStmt->execute([
                ':session_id' =>
                    (int) $question['test_session_id'],
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'attempt_question_id' =>
                    $attemptQuestionId,

                'selected_option_key' =>
                    $selectedOptionKey,

                'remaining_seconds' =>
                    $remainingSeconds,
            ];
        } catch (DomainException $e) {
            $this->rollBack();

            return [
                'success' => false,
                'code' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            $this->rollBack();

            return [
                'success' => false,
                'code' => 'attempt_answer_save_failed',
            ];
        }
    }
    /**
     * Complete an active attempt and calculate its final result.
     *
     * @return array{
     *     success: bool,
     *     code?: string,
     *     attempt_id?: int,
     *     score?: float,
     *     total_score?: float,
     *     percentage?: float,
     *     passed?: bool,
     *     answered_questions?: int,
     *     total_questions?: int,
     *     all_assignments_completed?: bool
     * }
     */
    public function submitAttempt(
        int $employeeId,
        string $sessionToken,
        string $ipAddress,
        string $userAgent
    ): array {
        $sessionToken = trim($sessionToken);

        if ($employeeId <= 0 || $sessionToken === '') {
            return [
                'success' => false,
                'code' => 'assessment_session_unavailable',
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $tokenHash = HashManager::hashToken(
                $sessionToken
            );

            $attemptStmt = $this->pdo->prepare("
                SELECT
                    ta.id AS attempt_id,
                    ta.employee_id,
                    ta.test_id,
                    ta.assignment_id,
                    ta.status AS attempt_status,
                    ta.total_score,
                    ta.expires_at,
                    t.passing_percentage,
                    ts.id AS test_session_id,
                    ts.status AS session_status,
                    TIMESTAMPDIFF(
                        SECOND,
                        UTC_TIMESTAMP(),
                        ta.expires_at
                    ) AS remaining_seconds
                FROM test_attempts ta
                JOIN tests t
                    ON t.id = ta.test_id
                JOIN test_sessions ts
                    ON ts.attempt_id = ta.id
                   AND ts.employee_id = ta.employee_id
                WHERE ta.employee_id = :employee_id
                  AND ts.session_token_hash = :token_hash
                ORDER BY ta.id DESC
                LIMIT 1
                FOR UPDATE
            ");

            $attemptStmt->execute([
                ':employee_id' => $employeeId,
                ':token_hash' => $tokenHash,
            ]);

            $attempt = $attemptStmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$attempt) {
                throw new DomainException(
                    'assessment_session_unavailable'
                );
            }

            if (
                $attempt['attempt_status'] !== 'IN_PROGRESS' ||
                $attempt['session_status'] !== 'ACTIVE'
            ) {
                throw new DomainException(
                    'attempt_already_submitted'
                );
            }

            if ((int) $attempt['remaining_seconds'] <= 0) {
                throw new DomainException(
                    'attempt_expired'
                );
            }

            $resultStmt = $this->pdo->prepare("
                SELECT
                    COUNT(aq.id) AS total_questions,
                    COUNT(aa.id) AS answered_questions,
                    COALESCE(
                        SUM(aa.awarded_score),
                        0.00
                    ) AS calculated_score
                FROM attempt_questions aq
                LEFT JOIN attempt_answers aa
                    ON aa.attempt_question_id = aq.id
                WHERE aq.attempt_id = :attempt_id
            ");

            $resultStmt->execute([
                ':attempt_id' =>
                    (int) $attempt['attempt_id'],
            ]);

            $result = $resultStmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$result) {
                throw new DomainException(
                    'attempt_configuration_error'
                );
            }

            $totalQuestions =
                (int) $result['total_questions'];

            $answeredQuestions =
                (int) $result['answered_questions'];

            $score = round(
                (float) $result['calculated_score'],
                2
            );

            $totalScore = round(
                (float) $attempt['total_score'],
                2
            );

            if (
                $totalQuestions <= 0 ||
                $totalScore <= 0
            ) {
                throw new DomainException(
                    'attempt_configuration_error'
                );
            }

            $percentage = round(
                min(
                    100,
                    max(
                        0,
                        ($score / $totalScore) * 100
                    )
                ),
                2
            );

            $passingPercentage =
                (float) $attempt['passing_percentage'];

            $passed =
                $percentage >= $passingPercentage;

            $updateAttempt = $this->pdo->prepare("
                UPDATE test_attempts
                SET
                    submitted_at = UTC_TIMESTAMP(),
                    score = :score,
                    percentage = :percentage,
                    passed = :passed,
                    status = 'COMPLETED',
                    submission_reason = 'MANUAL_SUBMIT',
                    updated_at = UTC_TIMESTAMP()
                WHERE id = :attempt_id
                  AND employee_id = :employee_id
                  AND status = 'IN_PROGRESS'
            ");

            $updateAttempt->execute([
                ':score' => $score,
                ':percentage' => $percentage,
                ':passed' => $passed ? 1 : 0,
                ':attempt_id' =>
                    (int) $attempt['attempt_id'],
                ':employee_id' => $employeeId,
            ]);

            if ($updateAttempt->rowCount() !== 1) {
                throw new DomainException(
                    'attempt_already_submitted'
                );
            }

            $updateAssignment = $this->pdo->prepare("
                UPDATE test_assignments
                SET status = 'COMPLETED'
                WHERE id = :assignment_id
                  AND employee_id = :employee_id
                  AND status = 'IN_PROGRESS'
            ");

            $updateAssignment->execute([
                ':assignment_id' =>
                    (int) $attempt['assignment_id'],
                ':employee_id' => $employeeId,
            ]);

            $closeSessions = $this->pdo->prepare("
                UPDATE test_sessions
                SET
                    status = 'CLOSED',
                    ended_at = UTC_TIMESTAMP(),
                    last_activity_at = UTC_TIMESTAMP()
                WHERE attempt_id = :attempt_id
                  AND employee_id = :employee_id
                  AND status = 'ACTIVE'
            ");

            $closeSessions->execute([
                ':attempt_id' =>
                    (int) $attempt['attempt_id'],
                ':employee_id' => $employeeId,
            ]);

            $pendingStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM test_assignments ta
                JOIN tests t
                    ON t.id = ta.test_id
                WHERE ta.employee_id = :employee_id
                  AND ta.id <> :assignment_id
                  AND ta.status IN (
                      'PENDING',
                      'IN_PROGRESS',
                      'RETAKE_AUTHORIZED'
                  )
                  AND t.is_active = 1
            ");

            $pendingStmt->execute([
                ':employee_id' => $employeeId,
                ':assignment_id' =>
                    (int) $attempt['assignment_id'],
            ]);

            $remainingAssignments =
                (int) $pendingStmt->fetchColumn();

            $allAssignmentsCompleted =
                $remainingAssignments === 0;

            if ($allAssignmentsCompleted) {
                $progressStmt = $this->pdo->prepare("
                    INSERT INTO assessment_progress
                        (
                            employee_id,
                            overall_status,
                            current_test_id,
                            completed_at,
                            updated_at
                        )
                    VALUES
                        (
                            :employee_id,
                            'COMPLETED',
                            NULL,
                            UTC_TIMESTAMP(),
                            UTC_TIMESTAMP()
                        )
                    ON DUPLICATE KEY UPDATE
                        overall_status = 'COMPLETED',
                        current_test_id = NULL,
                        completed_at = UTC_TIMESTAMP(),
                        updated_at = UTC_TIMESTAMP()
                ");

                $progressStmt->execute([
                    ':employee_id' => $employeeId,
                ]);
            } else {
                $progressStmt = $this->pdo->prepare("
                    INSERT INTO assessment_progress
                        (
                            employee_id,
                            overall_status,
                            current_test_id,
                            completed_at,
                            updated_at
                        )
                    VALUES
                        (
                            :employee_id,
                            'IN_PROGRESS',
                            NULL,
                            NULL,
                            UTC_TIMESTAMP()
                        )
                    ON DUPLICATE KEY UPDATE
                        overall_status = 'IN_PROGRESS',
                        current_test_id = NULL,
                        completed_at = NULL,
                        updated_at = UTC_TIMESTAMP()
                ");

                $progressStmt->execute([
                    ':employee_id' => $employeeId,
                ]);
            }

            if ($allAssignmentsCompleted) {
                (new AssessmentCompletionNotificationService($this->pdo))
                    ->enqueueIfEligible(
                        $employeeId,
                        (int) $attempt['attempt_id']
                    );
            }

            $auditStmt = $this->pdo->prepare("
                INSERT INTO audit_logs
                    (
                        actor_type,
                        employee_id,
                        admin_id,
                        action,
                        entity_type,
                        entity_id,
                        old_values,
                        new_values,
                        ip_address,
                        user_agent,
                        created_at
                    )
                VALUES
                    (
                        'EMPLOYEE',
                        :employee_id,
                        NULL,
                        'ASSESSMENT_ATTEMPT_SUBMITTED',
                        'test_attempt',
                        :attempt_id,
                        NULL,
                        :new_values,
                        :ip_address,
                        :user_agent,
                        UTC_TIMESTAMP()
                    )
            ");

            $auditValues = json_encode([
                'assignment_id' =>
                    (int) $attempt['assignment_id'],

                'test_id' =>
                    (int) $attempt['test_id'],

                'score' =>
                    $score,

                'total_score' =>
                    $totalScore,

                'percentage' =>
                    $percentage,

                'passing_percentage' =>
                    $passingPercentage,

                'passed' =>
                    $passed,

                'answered_questions' =>
                    $answeredQuestions,

                'total_questions' =>
                    $totalQuestions,

                'all_assignments_completed' =>
                    $allAssignmentsCompleted,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($auditValues === false) {
                throw new DomainException(
                    'attempt_submit_failed'
                );
            }

            $auditStmt->execute([
                ':employee_id' => $employeeId,
                ':attempt_id' =>
                    (int) $attempt['attempt_id'],
                ':new_values' => $auditValues,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'attempt_id' =>
                    (int) $attempt['attempt_id'],

                'score' =>
                    $score,

                'total_score' =>
                    $totalScore,

                'percentage' =>
                    $percentage,

                'passed' =>
                    $passed,

                'answered_questions' =>
                    $answeredQuestions,

                'total_questions' =>
                    $totalQuestions,

                'all_assignments_completed' =>
                    $allAssignmentsCompleted,
            ];
        } catch (DomainException $e) {
            $this->rollBack();

            return [
                'success' => false,
                'code' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            $this->rollBack();

            return [
                'success' => false,
                'code' => 'attempt_submit_failed',
            ];
        }
    }
    /**
     * Finalize the employee's active attempt after its time expires.
     *
     * This method is safe to call repeatedly. If there is no expired
     * in-progress attempt, it performs no database changes.
     *
     * @return array{
     *     success: bool,
     *     timed_out?: bool,
     *     code?: string,
     *     attempt_id?: int,
     *     score?: float,
     *     total_score?: float,
     *     percentage?: float,
     *     passed?: bool,
     *     answered_questions?: int,
     *     total_questions?: int,
     *     all_assignments_completed?: bool
     * }
     */
    /**
     * Finalize the employee's active attempt after its time expires (Employee Wrapper).
     */
    public function timeoutExpiredAttempt(
        int $employeeId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'HTTP_BROWSER'
    ): array {
        if ($employeeId <= 0) {
            return [
                'success' => false,
                'code' => 'invalid_employee',
            ];
        }

        $attemptId = $this->findActiveAttemptIdByEmployee($employeeId);
        if ($attemptId === null) {
            return [
                'success' => true,
                'timed_out' => false,
            ];
        }

        return $this->finalizeAttemptTimeout($attemptId, $employeeId, $ipAddress, $userAgent);
    }

    /**
     * Finalize an expired attempt by explicit attempt ID (CLI/Cron Wrapper).
     */
    public function timeoutExpiredAttemptById(
        int $attemptId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'CLI_CRON_WORKER'
    ): array {
        if ($attemptId <= 0) {
            return [
                'success' => false,
                'code' => 'invalid_attempt_id',
            ];
        }

        return $this->finalizeAttemptTimeout($attemptId, 0, $ipAddress, $userAgent);
    }

    /**
     * Canonical single implementation for timeout finalization.
     */
    private function finalizeAttemptTimeout(
        int $attemptId,
        int $employeeId = 0,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'CLI_CRON_WORKER'
    ): array {
        try {
            $this->pdo->beginTransaction();

            $sql = "
                SELECT
                    ta.id AS attempt_id,
                    ta.employee_id,
                    ta.test_id,
                    ta.assignment_id,
                    ta.total_score,
                    ta.expires_at,
                    t.passing_percentage,
                    TIMESTAMPDIFF(
                        SECOND,
                        UTC_TIMESTAMP(),
                        ta.expires_at
                    ) AS remaining_seconds
                FROM test_attempts ta
                JOIN tests t
                    ON t.id = ta.test_id
                WHERE ta.id = :attempt_id
                  AND ta.status = 'IN_PROGRESS'
            ";

            $params = [':attempt_id' => $attemptId];

            if ($employeeId > 0) {
                $sql .= " AND ta.employee_id = :employee_id";
                $params[':employee_id'] = $employeeId;
            }

            $sql .= " ORDER BY ta.id DESC LIMIT 1 FOR UPDATE";

            $attemptStmt = $this->pdo->prepare($sql);
            $attemptStmt->execute($params);
            $attempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);

            if (!$attempt) {
                $this->pdo->commit();
                return [
                    'success' => true,
                    'timed_out' => false,
                ];
            }

            $targetEmployeeId = (int) $attempt['employee_id'];

            if ((int) $attempt['remaining_seconds'] > 0) {
                $this->pdo->commit();
                return [
                    'success' => true,
                    'timed_out' => false,
                    'attempt_id' => (int) $attempt['attempt_id'],
                ];
            }

            $resultStmt = $this->pdo->prepare("
                SELECT
                    COUNT(aq.id) AS total_questions,
                    COUNT(aa.id) AS answered_questions,
                    COALESCE(SUM(aa.awarded_score), 0.00) AS calculated_score
                FROM attempt_questions aq
                LEFT JOIN attempt_answers aa
                    ON aa.attempt_question_id = aq.id
                WHERE aq.attempt_id = :attempt_id
            ");

            $resultStmt->execute([':attempt_id' => $attemptId]);
            $result = $resultStmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                throw new DomainException('attempt_configuration_error');
            }

            $totalQuestions = (int) $result['total_questions'];
            $answeredQuestions = (int) $result['answered_questions'];
            $score = round((float) $result['calculated_score'], 2);
            $totalScore = round((float) $attempt['total_score'], 2);

            if ($totalQuestions <= 0 || $totalScore <= 0) {
                throw new DomainException('attempt_configuration_error');
            }

            $percentage = round(min(100, max(0, ($score / $totalScore) * 100)), 2);
            $passingPercentage = (float) $attempt['passing_percentage'];
            $passed = $percentage >= $passingPercentage;

            $updateAttempt = $this->pdo->prepare("
                UPDATE test_attempts
                SET
                    submitted_at = UTC_TIMESTAMP(),
                    score = :score,
                    percentage = :percentage,
                    passed = :passed,
                    status = 'TIMED_OUT',
                    submission_reason = 'AUTO_TIMEOUT',
                    updated_at = UTC_TIMESTAMP()
                WHERE id = :attempt_id
                  AND status = 'IN_PROGRESS'
                  AND expires_at <= UTC_TIMESTAMP()
            ");

            $updateAttempt->execute([
                ':score' => $score,
                ':percentage' => $percentage,
                ':passed' => $passed ? 1 : 0,
                ':attempt_id' => $attemptId,
            ]);

            if ($updateAttempt->rowCount() !== 1) {
                $this->pdo->rollBack();
                return [
                    'success' => true,
                    'timed_out' => false,
                ];
            }

            $updateAssignment = $this->pdo->prepare("
                UPDATE test_assignments
                SET status = 'COMPLETED'
                WHERE id = :assignment_id
                  AND employee_id = :employee_id
                  AND status = 'IN_PROGRESS'
            ");

            $updateAssignment->execute([
                ':assignment_id' => (int) $attempt['assignment_id'],
                ':employee_id' => $targetEmployeeId,
            ]);

            $expireSessions = $this->pdo->prepare("
                UPDATE test_sessions
                SET
                    status = 'EXPIRED',
                    ended_at = UTC_TIMESTAMP(),
                    last_activity_at = UTC_TIMESTAMP()
                WHERE attempt_id = :attempt_id
                  AND employee_id = :employee_id
                  AND status = 'ACTIVE'
            ");

            $expireSessions->execute([
                ':attempt_id' => $attemptId,
                ':employee_id' => $targetEmployeeId,
            ]);

            $pendingStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM test_assignments ta
                JOIN tests t ON t.id = ta.test_id
                WHERE ta.employee_id = :employee_id
                  AND ta.id <> :assignment_id
                  AND ta.status IN ('PENDING', 'IN_PROGRESS', 'RETAKE_AUTHORIZED')
                  AND t.is_active = 1
            ");

            $pendingStmt->execute([
                ':employee_id' => $targetEmployeeId,
                ':assignment_id' => (int) $attempt['assignment_id'],
            ]);

            $remainingAssignments = (int) $pendingStmt->fetchColumn();
            $allAssignmentsCompleted = ($remainingAssignments === 0);

            $progressStmt = $this->pdo->prepare("
                INSERT INTO assessment_progress
                    (employee_id, overall_status, current_test_id, completed_at, updated_at)
                VALUES
                    (:employee_id, :status, :current_test_id, :completed_at, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                    overall_status = VALUES(overall_status),
                    current_test_id = VALUES(current_test_id),
                    completed_at = VALUES(completed_at),
                    updated_at = UTC_TIMESTAMP()
            ");

            $progressStmt->execute([
                ':employee_id' => $targetEmployeeId,
                ':status' => $allAssignmentsCompleted ? 'COMPLETED' : 'IN_PROGRESS',
                ':current_test_id' => null,
                ':completed_at' => $allAssignmentsCompleted ? date('Y-m-d H:i:s') : null,
            ]);

            if ($allAssignmentsCompleted) {
                (new AssessmentCompletionNotificationService($this->pdo))
                    ->enqueueIfEligible(
                        $targetEmployeeId,
                        $attemptId
                    );
            }

            $auditValues = json_encode([
                'assignment_id' => (int) $attempt['assignment_id'],
                'test_id' => (int) $attempt['test_id'],
                'score' => $score,
                'total_score' => $totalScore,
                'percentage' => $percentage,
                'passing_percentage' => $passingPercentage,
                'passed' => $passed,
                'answered_questions' => $answeredQuestions,
                'total_questions' => $totalQuestions,
                'all_assignments_completed' => $allAssignmentsCompleted,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($auditValues === false) {
                throw new DomainException('attempt_timeout_failed');
            }

            // SYSTEM Audit Log Record (admin_id = NULL, employee_id = NULL per chk_audit_actor)
            $auditStmt = $this->pdo->prepare("
                INSERT INTO audit_logs
                    (actor_type, employee_id, admin_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
                VALUES
                    ('SYSTEM', NULL, NULL, 'ASSESSMENT_ATTEMPT_TIMED_OUT', 'test_attempt', :attempt_id, NULL, :new_values, :ip_address, :user_agent, UTC_TIMESTAMP())
            ");

            $auditStmt->execute([
                ':attempt_id' => $attemptId,
                ':new_values' => $auditValues,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'timed_out' => true,
                'attempt_id' => $attemptId,
                'score' => $score,
                'total_score' => $totalScore,
                'percentage' => $percentage,
                'passed' => $passed,
                'answered_questions' => $answeredQuestions,
                'total_questions' => $totalQuestions,
                'all_assignments_completed' => $allAssignmentsCompleted,
            ];
        } catch (DomainException $e) {
            $this->rollBack();
            return [
                'success' => false,
                'code' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            $this->rollBack();
            return [
                'success' => false,
                'code' => 'attempt_timeout_failed',
            ];
        }
    }

    /**
     * Helper to find active attempt ID by employee ID.
     */
    private function findActiveAttemptIdByEmployee(int $employeeId): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM test_attempts
            WHERE employee_id = :employee_id
              AND status = 'IN_PROGRESS'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([':employee_id' => $employeeId]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : (int) $val;
    }

    /**
     * Batch process expired attempts with MySQL named lock & bounded batch size.
     */
    public function finalizeAllExpiredAttempts(
        int $batchSize = 50,
        bool $dryRun = false,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'CLI_CRON_WORKER'
    ): array {
        $lockStmt = $this->pdo->query("SELECT GET_LOCK('employee_assessment_finalize_expired', 0)");
        $lockAcquired = (int) $lockStmt->fetchColumn() === 1;

        if (!$lockAcquired) {
            return [
                'scanned' => 0,
                'finalized' => 0,
                'skipped' => 0,
                'failed' => 0,
                'lock_acquired' => false,
            ];
        }

        try {
            $batchSize = max(1, min(500, (int) $batchSize));

            $query = $this->pdo->prepare("
                SELECT id
                FROM test_attempts
                WHERE status = 'IN_PROGRESS'
                  AND expires_at <= UTC_TIMESTAMP()
                ORDER BY expires_at ASC
                LIMIT :batch_size
            ");
            $query->bindValue(':batch_size', $batchSize, PDO::PARAM_INT);
            $query->execute();

            $attemptIds = $query->fetchAll(PDO::FETCH_COLUMN);

            $scanned = count($attemptIds);
            $finalized = 0;
            $skipped = 0;
            $failed = 0;

            if ($dryRun) {
                return [
                    'scanned' => $scanned,
                    'finalized' => 0,
                    'skipped' => $scanned,
                    'failed' => 0,
                    'dry_run' => true,
                    'lock_acquired' => true,
                ];
            }

            foreach ($attemptIds as $attemptId) {
                try {
                    $res = $this->timeoutExpiredAttemptById((int) $attemptId, $ipAddress, $userAgent);
                    if (($res['success'] ?? false) !== true) {
                        $failed++;
                    } elseif (($res['timed_out'] ?? false) === true) {
                        $finalized++;
                    } else {
                        $skipped++;
                    }
                } catch (Throwable $e) {
                    $failed++;
                }
            }

            return [
                'scanned' => $scanned,
                'finalized' => $finalized,
                'skipped' => $skipped,
                'failed' => $failed,
                'lock_acquired' => true,
            ];
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('employee_assessment_finalize_expired')");
        }
    }

    /**
     * Record focus loss with transaction-safe 5-second rate limiting.
     */
    public function recordFocusLoss(
        int $employeeId,
        string $sessionToken,
        string $ipAddress,
        string $userAgent
    ): array {
        if ($employeeId <= 0 || trim($sessionToken) === '') {
            return ['success' => false, 'code' => 'assessment_session_unavailable'];
        }

        try {
            $this->pdo->beginTransaction();

            $tokenHash = HashManager::hashToken($sessionToken);

            $stmt = $this->pdo->prepare("
                SELECT ta.id AS attempt_id, ta.focus_loss_count
                FROM test_attempts ta
                JOIN test_sessions ts ON ts.attempt_id = ta.id AND ts.employee_id = ta.employee_id
                WHERE ta.employee_id = :employee_id
                  AND ta.status = 'IN_PROGRESS'
                  AND ts.status = 'ACTIVE'
                  AND ts.session_token_hash = :token_hash
                  AND ta.expires_at > UTC_TIMESTAMP()
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':employee_id' => $employeeId, ':token_hash' => $tokenHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->pdo->rollBack();
                return ['success' => false, 'code' => 'assessment_session_unavailable'];
            }

            $attemptId = (int) $row['attempt_id'];
            $currentCount = (int) $row['focus_loss_count'];

            $lastEvStmt = $this->pdo->prepare("
                SELECT TIMESTAMPDIFF(SECOND, created_at, UTC_TIMESTAMP()) AS seconds_since_last
                FROM security_events
                WHERE attempt_id = :attempt_id
                  AND event_type = 'ASSESSMENT_FOCUS_LOST'
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ");
            $lastEvStmt->execute([':attempt_id' => $attemptId]);
            $lastEv = $lastEvStmt->fetch(PDO::FETCH_ASSOC);

            if ($lastEv !== false && ((int) $lastEv['seconds_since_last']) < 5) {
                $this->pdo->commit();
                return [
                    'success' => true,
                    'rate_limited' => true,
                    'focus_loss_count' => $currentCount,
                ];
            }

            $newCount = $currentCount + 1;

            $updStmt = $this->pdo->prepare("
                UPDATE test_attempts
                SET focus_loss_count = :count, updated_at = UTC_TIMESTAMP()
                WHERE id = :attempt_id AND status = 'IN_PROGRESS'
            ");
            $updStmt->execute([':count' => $newCount, ':attempt_id' => $attemptId]);

            $secStmt = $this->pdo->prepare("
                INSERT INTO security_events
                    (employee_id, attempt_id, identifier_hash, event_type, metadata, ip_address, user_agent, created_at)
                VALUES
                    (:employee_id, :attempt_id, NULL, 'ASSESSMENT_FOCUS_LOST', :metadata, :ip_address, :user_agent, UTC_TIMESTAMP())
            ");
            $secStmt->execute([
                ':employee_id' => $employeeId,
                ':attempt_id' => $attemptId,
                ':metadata' => json_encode(['focus_loss_count' => $newCount]),
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'rate_limited' => false,
                'focus_loss_count' => $newCount,
            ];
        } catch (Throwable $e) {
            $this->rollBack();
            return ['success' => false, 'code' => 'focus_loss_record_failed'];
        }
    }
    private function lockAssignment(
        int $employeeId,
        int $assignmentId
    ): ?array {
        $stmt = $this->pdo->prepare("
            SELECT
                ta.*,
                t.code AS test_code,
                t.duration_minutes,
                t.question_count,
                t.randomize_questions,
                t.randomize_options,
                t.is_active AS test_is_active,
                (
                    (
                        ta.available_from IS NULL OR
                        ta.available_from <= UTC_TIMESTAMP()
                    )
                    AND
                    (
                        ta.available_until IS NULL OR
                        ta.available_until >= UTC_TIMESTAMP()
                    )
                    AND
                    (
                        t.available_from IS NULL OR
                        t.available_from <= UTC_TIMESTAMP()
                    )
                    AND
                    (
                        t.available_until IS NULL OR
                        t.available_until >= UTC_TIMESTAMP()
                    )
                ) AS is_available
            FROM test_assignments ta
            JOIN tests t
                ON t.id = ta.test_id
            WHERE ta.id = :assignment_id
              AND ta.employee_id = :employee_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            ':assignment_id' => $assignmentId,
            ':employee_id' => $employeeId,
        ]);

        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $assignment ?: null;
    }

    private function lockActiveAttempt(int $employeeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM test_attempts
            WHERE employee_id = :employee_id
              AND status = 'IN_PROGRESS'
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            ':employee_id' => $employeeId,
        ]);

        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        return $attempt ?: null;
    }

    private function getLastAttemptNumber(int $assignmentId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT attempt_number
            FROM test_attempts
            WHERE assignment_id = :assignment_id
            ORDER BY attempt_number DESC
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            ':assignment_id' => $assignmentId,
        ]);

        $value = $stmt->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    private function loadQuestions(
        int $testId,
        bool $randomizeQuestions,
        bool $randomizeOptions,
        int $questionCount
    ): array {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                type,
                text_ar,
                text_en,
                difficulty,
                score
            FROM questions
            WHERE test_id = :test_id
              AND is_active = 1
              AND archived_at IS NULL
            ORDER BY id ASC
        ");

        $stmt->execute([
            ':test_id' => $testId,
        ]);

        $questionRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($randomizeQuestions) {
            shuffle($questionRows);
        }

        $questionRows = array_slice(
            $questionRows,
            0,
            $questionCount
        );

        $optionStmt = $this->pdo->prepare("
            SELECT
                option_key,
                text_ar,
                text_en,
                is_correct,
                display_order
            FROM question_options
            WHERE question_id = :question_id
            ORDER BY display_order ASC
        ");

        $result = [];

        foreach ($questionRows as $question) {
            $optionStmt->execute([
                ':question_id' => (int) $question['id'],
            ]);

            $options = $optionStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($options) < 2) {
                throw new DomainException(
                    'invalid_question_configuration'
                );
            }

            $correctOptions = array_values(array_filter(
                $options,
                static fn (array $option): bool =>
                    (int) $option['is_correct'] === 1
            ));

            if (count($correctOptions) !== 1) {
                throw new DomainException(
                    'invalid_question_configuration'
                );
            }

            if ($randomizeOptions) {
                shuffle($options);
            }

            $safeOptions = [];

            foreach ($options as $index => $option) {
                $safeOptions[] = [
                    'key' => $option['option_key'],
                    'text_ar' => $option['text_ar'],
                    'text_en' => $option['text_en'],
                    'display_order' => $index + 1,
                ];
            }

            $result[] = [
                'id' => (int) $question['id'],
                'type' => $question['type'],
                'text_ar' => $question['text_ar'],
                'text_en' => $question['text_en'],
                'difficulty' => $question['difficulty'],
                'score' => (float) $question['score'],
                'correct_option_key' =>
                    $correctOptions[0]['option_key'],
                'options' => $safeOptions,
            ];
        }

        return $result;
    }

    private function insertAttempt(
        int $employeeId,
        int $testId,
        int $assignmentId,
        int $attemptNumber,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $expiresAt,
        int $durationSeconds,
        float $totalScore,
        string $ipAddress,
        string $userAgent
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO test_attempts
                (
                    employee_id,
                    test_id,
                    assignment_id,
                    attempt_number,
                    started_at,
                    submitted_at,
                    expires_at,
                    duration_seconds,
                    score,
                    total_score,
                    percentage,
                    passed,
                    status,
                    submission_reason,
                    ip_address,
                    user_agent,
                    focus_loss_count,
                    created_at,
                    updated_at
                )
            VALUES
                (
                    :employee_id,
                    :test_id,
                    :assignment_id,
                    :attempt_number,
                    :started_at,
                    NULL,
                    :expires_at,
                    :duration_seconds,
                    0.00,
                    :total_score,
                    0.00,
                    0,
                    'IN_PROGRESS',
                    NULL,
                    :ip_address,
                    :user_agent,
                    0,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
        ");

        $stmt->execute([
            ':employee_id' => $employeeId,
            ':test_id' => $testId,
            ':assignment_id' => $assignmentId,
            ':attempt_number' => $attemptNumber,
            ':started_at' => $startedAt->format('Y-m-d H:i:s'),
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ':duration_seconds' => $durationSeconds,
            ':total_score' => $totalScore,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertAttemptQuestions(
        int $attemptId,
        array $questions
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO attempt_questions
                (
                    attempt_id,
                    original_question_id,
                    question_snapshot,
                    options_snapshot,
                    correct_option_key_snapshot,
                    score_snapshot,
                    display_order,
                    created_at
                )
            VALUES
                (
                    :attempt_id,
                    :original_question_id,
                    :question_snapshot,
                    :options_snapshot,
                    :correct_option_key,
                    :score_snapshot,
                    :display_order,
                    UTC_TIMESTAMP()
                )
        ");

        foreach ($questions as $index => $question) {
            $questionSnapshot = json_encode([
                'id' => $question['id'],
                'type' => $question['type'],
                'text_ar' => $question['text_ar'],
                'text_en' => $question['text_en'],
                'difficulty' => $question['difficulty'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $optionsSnapshot = json_encode(
                $question['options'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if (
                $questionSnapshot === false ||
                $optionsSnapshot === false
            ) {
                throw new DomainException(
                    'invalid_question_configuration'
                );
            }

            $stmt->execute([
                ':attempt_id' => $attemptId,
                ':original_question_id' => $question['id'],
                ':question_snapshot' => $questionSnapshot,
                ':options_snapshot' => $optionsSnapshot,
                ':correct_option_key' =>
                    $question['correct_option_key'],
                ':score_snapshot' => $question['score'],
                ':display_order' => $index + 1,
            ]);
        }
    }

    private function insertTestSession(
        int $employeeId,
        int $attemptId,
        string $tokenHash,
        string $ipAddress
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO test_sessions
                (
                    employee_id,
                    attempt_id,
                    session_token_hash,
                    device_fingerprint_hash,
                    ip_address,
                    started_at,
                    last_activity_at,
                    ended_at,
                    status
                )
            VALUES
                (
                    :employee_id,
                    :attempt_id,
                    :token_hash,
                    NULL,
                    :ip_address,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP(),
                    NULL,
                    'ACTIVE'
                )
        ");

        $stmt->execute([
            ':employee_id' => $employeeId,
            ':attempt_id' => $attemptId,
            ':token_hash' => $tokenHash,
            ':ip_address' => $ipAddress,
        ]);
    }

    private function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}