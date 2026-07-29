# Sprint 4.4B — Question and Answer Option Management

## Delivered scope

- Bilingual single-choice question creation and editing.
- Two to eight answer options with server-generated option keys and display order.
- Exactly one correct answer enforced by server-side validation.
- Question difficulty, score, category, and activation controls.
- Bilingual question-category creation for an assessment.
- Session-bound, two-hour concurrency tokens for edit, archive, and restore actions.
- Transactional question/option persistence using PDO prepared statements.
- Question archive and restore without deleting historical records.
- Active-assessment integrity protection: a valid question cannot be deactivated or archived when doing so would leave the active assessment below its required valid-question count.
- Option rows retain their database IDs during edits; removal is blocked when an option has attached media.
- Audit payloads contain field names only and do not record question text, answer text, correct answers, scores, category values, or activation values.
- Arabic and English forms, validation messages, directory actions, and detail actions.

## Routes

### GET

- `/admin/questions/create`
- `/admin/questions/edit?id={question_id}`
- `/admin/questions/categories/create`

### POST

- `/admin/questions/create`
- `/admin/questions/edit`
- `/admin/questions/archive`
- `/admin/questions/restore`
- `/admin/questions/categories/create`

All state-changing routes require administrator authentication, `manage_questions`, and CSRF validation.

## Verification completed in the delivery environment

- PHP syntax validation passed for 111 PHP files.
- All 43 configured route handlers resolve to existing controller methods.
- Arabic/English translation parity passed with 341 unique keys per language and no duplicates.
- Create, detail, and category form views rendered using representative safe fixture data without PHP warnings.
- Question validator normalization was executed directly and produced two ordered options with exactly one correct answer.
- The sanitized project-wide source scan completed without regex warnings and found no critical hardcoded secrets, private keys, unsafe production SQL interpolation, debug calls, removed endpoint references, obsolete QA references, or platform-requirement bypass flags.

## Integration-test limitation in this environment

`AdminQuestionManagementTest.php` was added, but PHPUnit could not execute here because this container PHP build does not provide the required `dom`, `mbstring`, and `xmlwriter` extensions, and it does not provide `pdo_mysql`. Run the suite on the project PHP 8.2/MySQL test environment before release approval.

## Deferred to the next sprint

- Secure question/option image upload, MIME and dimension validation, randomized storage names, and protected media serving.
- Category edit/deactivate lifecycle controls.
- Visual question preview including secured images.
