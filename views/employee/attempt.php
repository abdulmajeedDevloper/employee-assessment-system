<?php

declare(strict_types=1);

$currentLang = $_SESSION['lang'] ?? 'ar';

$pageTitle =
    trans('in_progress') .
    ' - ' .
    trans('app_name');

$remainingSeconds = max(
    0,
    (int) ($attempt['remaining_seconds'] ?? 0)
);

$totalQuestions = count($questions);

$answeredQuestions = count(array_filter(
    $questions,
    static fn (array $question): bool =>
        isset($question['selected_option_key']) &&
        is_string($question['selected_option_key']) &&
        $question['selected_option_key'] !== ''
));

$allowBackNavigation =
    (int) ($attempt['allow_back_navigation'] ?? 1) === 1;

ob_start();
?>

<div class="card-box" style="max-width: 820px;">
    <h1 class="card-title">
        <?= e(
            $currentLang === 'ar'
                ? (string) $attempt['test_name_ar']
                : (string) $attempt['test_name_en']
        ) ?>
    </h1>

    <div
        class="notice-box"
        style="
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        "
    >
        <div>
            <strong>
                <?= e(trans('remaining_time')) ?>
            </strong>

            <strong
                id="assessment-timer"
                dir="ltr"
                style="
                    display: inline-block;
                    margin-inline-start: 0.5rem;
                    font-size: 1.25rem;
                "
            >
                --:--
            </strong>
        </div>

        <div>
            <strong id="answered-counter">
                <?php if ($currentLang === 'ar'): ?>
                    تمت الإجابة عن
                    <?= e((string) $answeredQuestions) ?>
                    من
                    <?= e((string) $totalQuestions) ?>
                <?php else: ?>
                    Answered
                    <?= e((string) $answeredQuestions) ?>
                    of
                    <?= e((string) $totalQuestions) ?>
                <?php endif; ?>
            </strong>
        </div>
    </div>

    <div
        id="save-status"
        class="notice-box"
        style="
            margin-top: 1rem;
            min-height: 46px;
        "
        aria-live="polite"
    >
        <?= e(
            $currentLang === 'ar'
                ? 'اختر إجابة وسيتم حفظها تلقائيًا.'
                : 'Select an answer and it will be saved automatically.'
        ) ?>
    </div>

    <div
        style="
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            margin-bottom: 1rem;
        "
    >
        <strong id="question-counter">
            <?= e(trans('question')) ?>
            1
            <?= e(trans('of')) ?>
            <?= e((string) $totalQuestions) ?>
        </strong>

        <div
            style="
                width: 180px;
                max-width: 45%;
                height: 8px;
                overflow: hidden;
                border-radius: 999px;
                background: rgba(148, 163, 184, 0.2);
            "
        >
            <div
                id="question-progress"
                style="
                    width: <?= $totalQuestions > 0
                        ? e((string) (100 / $totalQuestions))
                        : '0' ?>%;
                    height: 100%;
                    background: #3b82f6;
                    transition: width 0.2s ease;
                "
            ></div>
        </div>
    </div>

    <div id="questions-container">
        <?php foreach ($questions as $index => $question): ?>
            <?php
            $attemptQuestionId =
                (int) $question['attempt_question_id'];

            $selectedOptionKey =
                isset($question['selected_option_key']) &&
                is_string($question['selected_option_key'])
                    ? $question['selected_option_key']
                    : '';
            ?>

            <section
                class="assessment-question"
                data-question-index="<?= e((string) $index) ?>"
                data-attempt-question-id="<?= e(
                    (string) $attemptQuestionId
                ) ?>"
                data-selected-option="<?= e($selectedOptionKey) ?>"
                data-answered="<?= $selectedOptionKey !== ''
                    ? '1'
                    : '0' ?>"
                <?= $index === 0 ? '' : 'hidden' ?>
                style="
                    background: rgba(15, 23, 42, 0.6);
                    padding: 1.25rem;
                    border-radius: 8px;
                "
            >
                <p
                    style="
                        color: var(--text-muted);
                        margin-bottom: 0.75rem;
                    "
                >
                    <?= e(trans('question')) ?>
                    <?= e((string) $question['display_order']) ?>

                    <?= e(trans('of')) ?>

                    <?= e((string) $totalQuestions) ?>
                </p>

                <h2
                    style="
                        font-size: 1.15rem;
                        line-height: 1.8;
                        margin-bottom: 1.25rem;
                    "
                >
                    <?= e(
                        $currentLang === 'ar'
                            ? (string) (
                                $question['question']['text_ar']
                                ?? ''
                            )
                            : (string) (
                                $question['question']['text_en']
                                ?? ''
                            )
                    ) ?>
                </h2>

                <div class="assessment-options">
                    <?php foreach ($question['options'] as $option): ?>
                        <?php
                        $optionKey =
                            (string) ($option['key'] ?? '');

                        $optionId =
                            'question_' .
                            $attemptQuestionId .
                            '_option_' .
                            $optionKey;
                        ?>

                        <label
                            for="<?= e($optionId) ?>"
                            style="
                                display: flex;
                                align-items: center;
                                gap: 0.75rem;
                                padding: 0.9rem;
                                margin-bottom: 0.7rem;
                                border: 1px solid rgba(148, 163, 184, 0.28);
                                border-radius: 8px;
                                cursor: pointer;
                            "
                        >
                            <input
                                id="<?= e($optionId) ?>"
                                class="assessment-answer"
                                type="radio"
                                name="answer_<?= e(
                                    (string) $attemptQuestionId
                                ) ?>"
                                value="<?= e($optionKey) ?>"
                                data-attempt-question-id="<?= e(
                                    (string) $attemptQuestionId
                                ) ?>"
                                <?= $selectedOptionKey === $optionKey
                                    ? 'checked'
                                    : '' ?>
                            >

                            <span>
                                <?= e(
                                    $currentLang === 'ar'
                                        ? (string) (
                                            $option['text_ar'] ?? ''
                                        )
                                        : (string) (
                                            $option['text_en'] ?? ''
                                        )
                                ) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div
        style="
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1.25rem;
        "
    >
        <button
            type="button"
            id="previous-question"
            class="btn-primary"
            disabled
        >
            <?= e(trans('previous')) ?>
        </button>

        <button
            type="button"
            id="next-question"
            class="btn-primary"
            <?= $totalQuestions <= 1 ? 'disabled' : '' ?>
        >
            <?= e(trans('next')) ?>
        </button>
    </div>
    <form
        id="submit-assessment-form"
        action="/employee/attempt/submit"
        method="POST"
        style="
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(148, 163, 184, 0.25);
            text-align: center;
        "
    >
        <?= csrf_field() ?>

        <button
            type="submit"
            id="submit-assessment-button"
            class="btn-primary"
        >
            <?= e(trans('submit_test')) ?>
        </button>
    </form>

</div>

<script>
(function () {
    'use strict';

    const csrfToken =
        <?= json_encode(
            csrf_token(),
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;

    const saveUrl = '/employee/attempt/answer';
    const timeoutUrl = '/employee/attempt/timeout';

    const timeoutFailedMessage =
        <?= json_encode(
            trans('attempt_timeout_failed'),
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>;

    const totalQuestions =
        <?= json_encode($totalQuestions) ?>;

    const allowBackNavigation =
        <?= json_encode($allowBackNavigation) ?>;

    let remainingSeconds = Math.max(
        0,
        Number(<?= json_encode($remainingSeconds) ?>) || 0
    );

    /*
     * استخدم موعد انتهاء مطلقًا بدل إنقاص عداد محلي كل ثانية.
     * بهذه الطريقة يظل المؤقت دقيقًا حتى عند تبديل التبويبات
     * أو تقليل المتصفح لوتيرة setInterval في الخلفية.
     */
    const timerDeadlineMs =
        Date.now() + (remainingSeconds * 1000);

    let currentQuestionIndex = 0;
    let pendingSaveCount = 0;
    let isSubmitting = false;
    let isTimingOut = false;
    let timerIntervalId = null;

    const questions = Array.from(
        document.querySelectorAll('.assessment-question')
    );

    const previousButton =
        document.getElementById('previous-question');

    const nextButton =
        document.getElementById('next-question');

    const counterElement =
        document.getElementById('question-counter');

    const progressElement =
        document.getElementById('question-progress');

    const answeredCounter =
        document.getElementById('answered-counter');

    const saveStatus =
        document.getElementById('save-status');

    const timerElement =
        document.getElementById('assessment-timer');

    const submitForm =
        document.getElementById('submit-assessment-form');

    const submitButton =
        document.getElementById('submit-assessment-button');

    const messages = {
        confirmSubmit:
            <?= json_encode(
                trans('confirm_submit'),
                JSON_UNESCAPED_UNICODE
            ) ?>,

        submitting:
            <?= json_encode(
                $currentLang === 'ar'
                    ? 'جارٍ إرسال الاختبار...'
                    : 'Submitting assessment...',
                JSON_UNESCAPED_UNICODE
            ) ?>,

        unanswered:
            <?= json_encode(
                $currentLang === 'ar'
                    ? 'عدد الأسئلة التي لم تتم الإجابة عنها:'
                    : 'Unanswered questions:',
                JSON_UNESCAPED_UNICODE
            ) ?>,

        saving:
            <?= json_encode(
                $currentLang === 'ar'
                    ? 'جارٍ حفظ الإجابة...'
                    : 'Saving answer...',
                JSON_UNESCAPED_UNICODE
            ) ?>,

        saved:
            <?= json_encode(
                trans('answer_saved_successfully'),
                JSON_UNESCAPED_UNICODE
            ) ?>,

        saveFailed:
            <?= json_encode(
                trans('attempt_answer_save_failed'),
                JSON_UNESCAPED_UNICODE
            ) ?>,

        expired:
            <?= json_encode(
                trans('attempt_expired'),
                JSON_UNESCAPED_UNICODE
            ) ?>,

        sessionUnavailable:
            <?= json_encode(
                trans('assessment_session_unavailable'),
                JSON_UNESCAPED_UNICODE
            ) ?>,

        answeredPrefix:
            <?= json_encode(
                $currentLang === 'ar'
                    ? 'تمت الإجابة عن'
                    : 'Answered',
                JSON_UNESCAPED_UNICODE
            ) ?>,

        answeredMiddle:
            <?= json_encode(
                $currentLang === 'ar'
                    ? 'من'
                    : 'of',
                JSON_UNESCAPED_UNICODE
            ) ?>,

        question:
            <?= json_encode(
                trans('question'),
                JSON_UNESCAPED_UNICODE
            ) ?>,

        of:
            <?= json_encode(
                trans('of'),
                JSON_UNESCAPED_UNICODE
            ) ?>
    };

    function updateSubmitButton() {
        if (!submitButton) {
            return;
        }

        submitButton.disabled =
            pendingSaveCount > 0 ||
            remainingSeconds <= 0 ||
            isSubmitting ||
            isTimingOut;
    }

    function setSaveStatus(message, state) {
        if (!saveStatus) {
            return;
        }

        saveStatus.textContent = message;

        if (state === 'success') {
            saveStatus.style.borderColor =
                'rgba(34, 197, 94, 0.55)';
        } else if (state === 'error') {
            saveStatus.style.borderColor =
                'rgba(239, 68, 68, 0.65)';
        } else {
            saveStatus.style.borderColor =
                'rgba(148, 163, 184, 0.28)';
        }
    }

    function updateAnsweredCounter() {
        const answered = questions.filter(function (question) {
            return question.dataset.answered === '1';
        }).length;

        if (answeredCounter) {
            answeredCounter.textContent =
                messages.answeredPrefix +
                ' ' +
                answered +
                ' ' +
                messages.answeredMiddle +
                ' ' +
                totalQuestions;
        }
    }

    function showQuestion(index) {
        if (
            index < 0 ||
            index >= questions.length
        ) {
            return;
        }

        currentQuestionIndex = index;

        questions.forEach(function (question, questionIndex) {
            question.hidden =
                questionIndex !== currentQuestionIndex;
        });

        if (counterElement) {
            counterElement.textContent =
                messages.question +
                ' ' +
                (currentQuestionIndex + 1) +
                ' ' +
                messages.of +
                ' ' +
                totalQuestions;
        }

        if (progressElement && totalQuestions > 0) {
            progressElement.style.width =
                (
                    (
                        currentQuestionIndex + 1
                    ) /
                    totalQuestions *
                    100
                ) +
                '%';
        }

        if (previousButton) {
            previousButton.disabled =
                !allowBackNavigation ||
                currentQuestionIndex === 0;
        }

        if (nextButton) {
            nextButton.disabled =
                currentQuestionIndex >=
                questions.length - 1;
        }

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    function disableAllAnswers() {
        document
            .querySelectorAll('.assessment-answer')
            .forEach(function (input) {
                input.disabled = true;
            });

        if (previousButton) {
            previousButton.disabled = true;
        }

        if (nextButton) {
            nextButton.disabled = true;
        }
    }

    async function saveAnswer(input) {
        const questionPanel =
            input.closest('.assessment-question');

        if (!questionPanel) {
            return;
        }

        const previousOption =
            questionPanel.dataset.selectedOption || '';

        const questionInputs = Array.from(
            questionPanel.querySelectorAll(
                '.assessment-answer'
            )
        );

        questionInputs.forEach(function (radio) {
            radio.disabled = true;
        });

        pendingSaveCount += 1;
        updateSubmitButton();

        setSaveStatus(
            messages.saving,
            'pending'
        );

        const requestBody = new URLSearchParams();

        requestBody.set(
            '_csrf_token',
            csrfToken
        );

        requestBody.set(
            'attempt_question_id',
            input.dataset.attemptQuestionId
        );

        requestBody.set(
            'selected_option_key',
            input.value
        );

        let shouldReenableAnswers = true;
        try {
            const response = await fetch(
                saveUrl,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type':
                            'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-Requested-With':
                            'XMLHttpRequest'
                    },
                    body: requestBody.toString()
                }
            );

            const data = await response
                .json()
                .catch(function () {
                    return {};
                });

            if (
                !response.ok ||
                data.success !== true
            ) {
                throw {
                    code:
                        data.code ||
                        'attempt_answer_save_failed',

                    message:
                        data.message ||
                        messages.saveFailed
                };
            }

            questionPanel.dataset.selectedOption =
                input.value;

            questionPanel.dataset.answered = '1';

            updateAnsweredCounter();

            setSaveStatus(
                data.message || messages.saved,
                'success'
            );
        } catch (error) {
            questionInputs.forEach(function (radio) {
                radio.checked =
                    radio.value === previousOption;
            });

            const errorCode =
                error && error.code
                    ? error.code
                    : '';

            if (errorCode === 'attempt_expired') {
                shouldReenableAnswers = false;
                setSaveStatus(
                    messages.expired,
                    'error'
                );

                disableAllAnswers();
                triggerAutoTimeout('attempt_expired');
                return;
            }

            if (
                errorCode ===
                'assessment_session_unavailable'
            ) {
                shouldReenableAnswers = false;
                setSaveStatus(
                    messages.sessionUnavailable,
                    'error'
                );

                disableAllAnswers();
                return;
            }

            setSaveStatus(
                error && error.message
                    ? error.message
                    : messages.saveFailed,
                'error'
            );
        } finally {
            pendingSaveCount = Math.max(
                0,
                pendingSaveCount - 1
            );

            if (
                shouldReenableAnswers &&
                remainingSeconds > 0 &&
                !isSubmitting &&
                !isTimingOut
            ) {
                questionInputs.forEach(function (radio) {
                    radio.disabled = false;
                });
            }

            updateSubmitButton();
        }
    }

    document
        .querySelectorAll('.assessment-answer')
        .forEach(function (input) {
            input.addEventListener(
                'change',
                function () {
                    saveAnswer(input);
                }
            );
        });

    if (previousButton) {
        previousButton.addEventListener(
            'click',
            function () {
                showQuestion(
                    currentQuestionIndex - 1
                );
            }
        );
    }

    if (nextButton) {
        nextButton.addEventListener(
            'click',
            function () {
                showQuestion(
                    currentQuestionIndex + 1
                );
            }
        );
    }

    if (submitForm) {
        submitForm.addEventListener(
            'submit',
            function (event) {
                event.preventDefault();

                if (
                    pendingSaveCount > 0 ||
                    isSubmitting ||
                    remainingSeconds <= 0
                ) {
                    return;
                }

                const answeredCount =
                    questions.filter(function (question) {
                        return question.dataset.answered === '1';
                    }).length;

                const unansweredCount =
                    Math.max(
                        0,
                        totalQuestions - answeredCount
                    );

                let confirmationMessage =
                    messages.confirmSubmit;

                if (unansweredCount > 0) {
                    confirmationMessage +=
                        "\n\n" +
                        messages.unanswered +
                        ' ' +
                        unansweredCount;
                }

                if (!window.confirm(confirmationMessage)) {
                    return;
                }

                isSubmitting = true;
                updateSubmitButton();
                disableAllAnswers();

                if (submitButton) {
                    submitButton.textContent =
                        messages.submitting;
                }

                submitForm.submit();
            }
        );
    }

    async function finalizeTimedOutAttempt() {
        if (isTimingOut || isSubmitting) {
            return;
        }

        isTimingOut = true;
        remainingSeconds = 0;

        if (timerIntervalId !== null) {
            window.clearInterval(timerIntervalId);
            timerIntervalId = null;
        }

        disableAllAnswers();
        updateSubmitButton();

        setSaveStatus(
            messages.expired,
            'error'
        );

        /*
         * منح طلب حفظ جارٍ فرصة قصيرة للانتهاء
         * قبل إغلاق المحاولة.
         */
        for (
            let waitAttempt = 0;
            waitAttempt < 20 && pendingSaveCount > 0;
            waitAttempt += 1
        ) {
            await new Promise(function (resolve) {
                window.setTimeout(resolve, 100);
            });
        }

        const requestBody = new URLSearchParams();

        requestBody.set(
            '_csrf_token',
            csrfToken
        );

        try {
            const response = await fetch(
                timeoutUrl,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type':
                            'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-Requested-With':
                            'XMLHttpRequest'
                    },
                    body: requestBody.toString()
                }
            );

            const data = await response
                .json()
                .catch(function () {
                    return {};
                });

            if (
                !response.ok ||
                data.success !== true
            ) {
                throw new Error(
                    data.message ||
                    timeoutFailedMessage
                );
            }

            if (data.timed_out === true) {
                setSaveStatus(
                    data.message ||
                    messages.expired,
                    'success'
                );

                window.location.assign(
                    data.redirect_url ||
                    '/employee/tests'
                );

                return;
            }

            /*
             * قد يصل مؤقت المتصفح إلى الصفر قبل
             * وقت قاعدة البيانات بفارق بسيط.
             */
            isTimingOut = false;

            window.setTimeout(
                finalizeTimedOutAttempt,
                1000
            );
        } catch (error) {
            setSaveStatus(
                error instanceof Error &&
                error.message
                    ? error.message
                    : timeoutFailedMessage,
                'error'
            );

            isTimingOut = false;

            /*
             * إبقاء الإجابات معطلة وإعادة المحاولة
             * بعد فشل مؤقت في الاتصال.
             */
            window.setTimeout(
                finalizeTimedOutAttempt,
                3000
            );
        }
    }

    function synchronizeRemainingSeconds() {
        remainingSeconds = Math.max(
            0,
            Math.ceil(
                (timerDeadlineMs - Date.now()) / 1000
            )
        );
    }

    function renderTimer() {
        if (!timerElement) {
            return;
        }

        synchronizeRemainingSeconds();

        const safeRemaining = remainingSeconds;

        const hours =
            Math.floor(safeRemaining / 3600);

        const minutes =
            Math.floor(
                (safeRemaining % 3600) / 60
            );

        const seconds =
            safeRemaining % 60;

        timerElement.textContent =
            (
                hours > 0
                    ? String(hours).padStart(2, '0') +
                        ':'
                    : ''
            ) +
            String(minutes).padStart(2, '0') +
            ':' +
            String(seconds).padStart(2, '0');

        if (remainingSeconds <= 0) {
            setSaveStatus(
                messages.expired,
                'error'
            );

            disableAllAnswers();
            updateSubmitButton();
            finalizeTimedOutAttempt();
        }
    }

    let lastFocusLossTime = 0;
    const focusLossUrl = '/employee/attempt/focus-loss';

    async function sendFocusLossPing() {
        if (document.hidden !== true || isSubmitting || isTimingOut || remainingSeconds <= 0) {
            return;
        }

        const now = Date.now();
        if (now - lastFocusLossTime < 5000) {
            return;
        }
        lastFocusLossTime = now;

        const requestBody = new URLSearchParams();
        requestBody.set('_csrf_token', csrfToken);

        try {
            await fetch(focusLossUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: requestBody.toString(),
                keepalive: true
            });
        } catch (e) {
            // Ignore temporary network errors on focus loss ping
        }
    }

    document.addEventListener('visibilitychange', function () {
        /*
         * حدّث المؤقت فورًا عند إخفاء الصفحة أو العودة إليها،
         * بدل انتظار نبضة setInterval التالية.
         */
        renderTimer();

        if (document.hidden === true) {
            sendFocusLossPing();
        }
    });

    showQuestion(0);
    updateAnsweredCounter();
    updateSubmitButton();
    renderTimer();

    if (!isTimingOut) {
        timerIntervalId = window.setInterval(
            renderTimer,
            1000
        );
    }
})();
</script>

<?php

$content = ob_get_clean();

require __DIR__ . '/../layouts/main.php';
?>