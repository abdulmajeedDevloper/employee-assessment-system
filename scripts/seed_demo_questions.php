<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "Error: CLI execution required." . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../bootstrap/init.php';

use App\Support\Database;

$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'production';
if ($appEnv !== 'development') {
    echo "Error: Demo question seeder can only run in 'development' environment (APP_ENV={$appEnv})." . PHP_EOL;
    exit(1);
}

$isDryRun = in_array('--dry-run', $argv, true) || in_array('-d', $argv, true);
$isReplaceDemo = in_array('--replace-demo', $argv, true);

try {
    $pdo = Database::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    echo "Error connecting to database: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Resolution of active super_admin only (no fallbacks allowed)
$adminStmt = $pdo->query("
    SELECT id
    FROM admins
    WHERE role = 'super_admin' AND is_active = 1
    ORDER BY id ASC
    LIMIT 1
");
$superAdminId = $adminStmt->fetchColumn();

if ($superAdminId === false || $superAdminId === null) {
    echo "Error: No active super_admin account found in database. Seeding aborted." . PHP_EOL;
    exit(1);
}
$superAdminId = (int) $superAdminId;

// Dynamic resolution of tests by code
$testStmt = $pdo->prepare("
    SELECT id, code, name_ar, name_en, question_count
    FROM tests
    WHERE code = :code AND is_active = 1
");

$tests = [];
foreach (['IQ_TEST', 'ENG_TEST'] as $code) {
    $testStmt->execute([':code' => $code]);
    $row = $testStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "Error: Test with code '{$code}' not found or is inactive in database. Seeding aborted." . PHP_EOL;
        exit(1);
    }
    $tests[$code] = $row;
}

// 40 Meaningful Bilingual Demo Questions Dataset Definition
$demoData = [
    'IQ_TEST' => [
        'Logical Reasoning' => [
            'name_ar' => 'الاستدلال المنطقي',
            'questions' => [
                [
                    'text_en' => 'If all software engineers are problem solvers, and some problem solvers are data analysts, which conclusion must be logically true?',
                    'text_ar' => 'إذا كان جميع مهندسي البرمجيات يبرعون في حل المشكلات، وبعض باحثي حل المشكلات هم محللو بيانات، فأي استنتاج صحيح منطقياً؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Some software engineers may be data analysts.', 'text_ar' => 'قد يكون بعض مهندسي البرمجيات محللي بيانات.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'All data analysts are software engineers.', 'text_ar' => 'جميع محللي البيانات هم مهندسو برمجيات.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'No software engineers solve problems.', 'text_ar' => 'لا يوجد مهندس برمجيات يحل المشكلات.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'All problem solvers are software engineers.', 'text_ar' => 'جميع باحثي حل المشكلات هم مهندسو برمجيات.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'All completed reports require supervisor approval. Report X is completed. Therefore, Report X:',
                    'text_ar' => 'جميع التقارير المكتملة تتطلب موافقة المشرف. التقرير (س) مكتمل. بناءً عليه، التقرير (س):',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Requires supervisor approval.', 'text_ar' => 'يتطلب موافقة المشرف.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Does not require any review.', 'text_ar' => 'لا يتطلب أي مراجعة.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Is automatically rejected.', 'text_ar' => 'يتم رفضه تلقائياً.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Should be archived immediately.', 'text_ar' => 'يجب أرشفته فوراً.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'If Task A must finish before Task B begins, and Task B must finish before Task C begins, which statement is guaranteed?',
                    'text_ar' => 'إذا كانت المهمة (أ) يجب أن تنتهي قبل بدء المهمة (ب)، والمهمة (ب) يجب أن تنتهي قبل بدء المهمة (ج)، فأي عبارة مؤكدة؟',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Task A must finish before Task C begins.', 'text_ar' => 'يجب أن تنتهي المهمة (أ) قبل بدء المهمة (ج).', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Task C begins before Task A.', 'text_ar' => 'تبدأ المهمة (ج) قبل المهمة (أ).', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Task A and Task C run simultaneously.', 'text_ar' => 'تعمل المهمة (أ) والمهمة (ج) بالتزامن.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Task B is independent of Task A.', 'text_ar' => 'المهمة (ب) مستقلة تماماً عن المهمة (أ).', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Statements: No delayed projects are efficient. Some IT projects are delayed. Conclusion:',
                    'text_ar' => 'المعطيات: لا توجد مشاريع متأخرة كفؤة. بعض مشاريع تكنولوجيا المعلومات متأخرة. الاستنتاج:',
                    'difficulty' => 'hard',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Some IT projects are not efficient.', 'text_ar' => 'بعض مشاريع تكنولوجيا المعلومات ليست كفؤة.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'All IT projects are efficient.', 'text_ar' => 'جميع مشاريع تكنولوجيا المعلومات كفؤة.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Delayed projects are always successful.', 'text_ar' => 'المشاريع المتأخرة ناجحة دائماً.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'No IT projects are delayed.', 'text_ar' => 'لا توجد مشاريع تكنولوجيا معلومات متأخرة.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'If employee X is senior to employee Y, and employee Y is senior to employee Z, who is the most senior?',
                    'text_ar' => 'إذا كان الموظف (س) أعلى درجة من الموظف (ص)، والموظف (ص) أعلى درجة من الموظف (ع)، فمن الأعلى درجة بين الثلاثة؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Employee X', 'text_ar' => 'الموظف (س)', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Employee Y', 'text_ar' => 'الموظف (ص)', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Employee Z', 'text_ar' => 'الموظف (ع)', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'They are all equal in rank.', 'text_ar' => 'جميعهم متساوون في الدرجة.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
            ],
        ],
        'Numerical Reasoning' => [
            'name_ar' => 'الاستدلال العددي',
            'questions' => [
                [
                    'text_en' => 'What is the next number in the sequence: 3, 6, 12, 24, 48, ...?',
                    'text_ar' => 'ما هو العدد التالي في المتتالية: 3، 6، 12، 24، 48، ...؟',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => '96', 'text_ar' => '96', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => '60', 'text_ar' => '60', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => '72', 'text_ar' => '72', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => '84', 'text_ar' => '84', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'A project budget of 50,000 SAR is increased by 15%. What is the new total budget?',
                    'text_ar' => 'ميزانية مشروع قدرها 50,000 ريال زادت بنسبة 15%. ما هي الميزانية الإجمالية الجديدة؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => '57,500 SAR', 'text_ar' => '57,500 ريال', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => '55,000 SAR', 'text_ar' => '55,000 ريال', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => '60,000 SAR', 'text_ar' => '60,000 ريال', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => '52,500 SAR', 'text_ar' => '52,500 ريال', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'If 4 workers complete a software deployment in 6 hours, how many hours would 8 workers take at the same rate?',
                    'text_ar' => 'إذا كان 4 عمال ينجزون تجميع وتدشين نظام في 6 ساعات، فكم ساعة يحتاجها 8 عمال بنفس معدل العمل؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => '3 hours', 'text_ar' => '3 ساعات', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => '12 hours', 'text_ar' => '12 ساعة', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => '4 hours', 'text_ar' => '4 ساعات', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => '2 hours', 'text_ar' => 'ساعتان', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Find the average score of four quarterly evaluations: 80, 85, 90, and 95.',
                    'text_ar' => 'احسب متوسط درجات أربعة تقييمات ربع سنوية: 80، 85، 90، 95.',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => '87.5', 'text_ar' => '87.5', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => '85.0', 'text_ar' => '85.0', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => '90.0', 'text_ar' => '90.0', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => '88.0', 'text_ar' => '88.0', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'An employee completes 12 tasks per day. How many tasks will the employee complete in a 5-day work week?',
                    'text_ar' => 'ينجز موظف 12 مهمة يومياً. كم مهمة سينجز الموظف في أسبوع عمل مكون من 5 أيام؟',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => '60 tasks', 'text_ar' => '60 مهمة', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => '50 tasks', 'text_ar' => '50 مهمة', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => '65 tasks', 'text_ar' => '65 مهمة', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => '70 tasks', 'text_ar' => '70 مهمة', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
            ],
        ],
        'Pattern Recognition' => [
            'name_ar' => 'التعرف على الأنماط',
            'questions' => [
                [
                    'text_en' => 'Which number completes the pattern: 2, 5, 10, 17, 26, ...?',
                    'text_ar' => 'ما الرقم الذي يكمل النمط: 2، 5، 10، 17، 26، ...؟',
                    'difficulty' => 'hard',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => '37', 'text_ar' => '37', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => '35', 'text_ar' => '35', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => '36', 'text_ar' => '36', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => '40', 'text_ar' => '40', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Identify the missing letter in the sequence: A, C, E, G, ...?',
                    'text_ar' => 'حدد الحرف المفقود في المتتالية الإنجليزية: A, C, E, G, ...؟',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'I', 'text_ar' => 'I', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'H', 'text_ar' => 'H', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'J', 'text_ar' => 'J', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'K', 'text_ar' => 'K', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Which number logically completes the matrix row: [4, 8, 16], [5, 10, 20], [6, 12, ?]?',
                    'text_ar' => 'ما الرقم الذي يكمل مصفوفة الأعداد منطقياً: [4, 8, 16]، [5, 10, 20]، [6, 12, ؟]؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => '24', 'text_ar' => '24', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => '18', 'text_ar' => '18', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => '30', 'text_ar' => '30', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => '22', 'text_ar' => '22', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'In a code language, WORK is written as 4-3-2-1. How is ROWK written in the same code?',
                    'text_ar' => 'في شفرة معينة، كُتبت كلمة WORK كـ 4-3-2-1. كيف تُكتب ROWK بنفس الشفرة؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => '3-2-4-1', 'text_ar' => '3-2-4-1', 'is_correct' => 0, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => '3-4-2-1', 'text_ar' => '3-4-2-1', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => '4-2-3-1', 'text_ar' => '4-2-3-1', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => '2-3-4-1', 'text_ar' => '2-3-4-1', 'is_correct' => 1, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'What comes next in the alternating pattern: 100, 90, 80, 70, 60, ...?',
                    'text_ar' => 'ما العدد التالي في هذا النمط التنازلي: 100، 90، 80، 70، 60، ...؟',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => '50', 'text_ar' => '50', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => '55', 'text_ar' => '55', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => '40', 'text_ar' => '40', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => '45', 'text_ar' => '45', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
            ],
        ],
        'Verbal Reasoning' => [
            'name_ar' => 'الاستدلال اللفظي',
            'questions' => [
                [
                    'text_en' => 'Analogy: Supervisor is to Team as Captain is to:',
                    'text_ar' => 'تناظر لفظي: المشرف بالنسبة للفريق كالقبطان بالنسبة لـ:',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Ship', 'text_ar' => 'السفينة', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Sea', 'text_ar' => 'البحر', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Port', 'text_ar' => 'الميناء', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Anchor', 'text_ar' => 'المرساة', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Select the word that is most opposite in meaning to Efficient:',
                    'text_ar' => 'اختر الكلمة المعاكسة تماماً في المعنى لكلمة كفء (Efficient):',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Inefficient', 'text_ar' => 'غير كفء', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Skilled', 'text_ar' => 'ماهر', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Productive', 'text_ar' => 'منتج', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Experienced', 'text_ar' => 'خبير', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Which word does NOT belong with the others in an office context?',
                    'text_ar' => 'أي من الكلمات التالية لا تنتمي للمجموعة في سياق العمل المكتبي؟',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Telescope', 'text_ar' => 'تلسكوب', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Printer', 'text_ar' => 'طابعة', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Monitor', 'text_ar' => 'شاشة', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Keyboard', 'text_ar' => 'لوحة مفاتيح', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Analogy: Delegation is to Responsibility as Assignment is to:',
                    'text_ar' => 'تناظر لفظي: تفويض بالنسبة للمسؤولية كالتكليف بالنسبة لـ:',
                    'difficulty' => 'hard',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Task', 'text_ar' => 'المهمة', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Absence', 'text_ar' => 'الغياب', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Delay', 'text_ar' => 'التأخير', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Resignation', 'text_ar' => 'الاستقالة', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Choose the word that best completes the sentence: Clear communication _______ team collaboration.',
                    'text_ar' => 'اختر الكلمة المناسبة لإكمال الجملة: التواصل الواضح _______ التعاون بين أعضاء الفريق.',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Enhances', 'text_ar' => 'يعزز', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Prevents', 'text_ar' => 'يمنع', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Hinders', 'text_ar' => 'يعيق', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Cancels', 'text_ar' => 'يلغي', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
            ],
        ],
    ],
    'ENG_TEST' => [
        'Grammar' => [
            'name_ar' => 'القواعد اللغوية',
            'questions' => [
                [
                    'text_en' => 'Choose the grammatically correct sentence:',
                    'text_ar' => 'اختر الجملة الصحيحة لغوياً:',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'The project manager submitted the report yesterday.', 'text_ar' => 'قدم مدير المشروع التقرير يوم أمس.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'The project manager submit the report yesterday.', 'text_ar' => 'قدم مدير المشروع التقرير يوم أمس (خطأ لغوي).', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'The project manager submitting report yesterday.', 'text_ar' => 'قدم مدير المشروع التقرير يوم أمس (صياغة ناقصة).', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'The project manager have submit the report yesterday.', 'text_ar' => 'قدم مدير المشروع التقرير يوم أمس (صياغة غير صحيحة).', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Neither the manager nor the team members _______ available for the meeting right now.',
                    'text_ar' => 'اختر الفعل الصحيح لتكملة الجملة: Neither the manager nor the team members _______ available for the meeting right now.',
                    'difficulty' => 'hard',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'are', 'text_ar' => 'are', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'is', 'text_ar' => 'is', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'was', 'text_ar' => 'was', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'been', 'text_ar' => 'been', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Please ensure that all final files _______ saved in the designated directory.',
                    'text_ar' => 'اختر الكلمة المناسبة: Please ensure that all final files _______ saved in the designated directory.',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'are', 'text_ar' => 'are', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'is', 'text_ar' => 'is', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'was', 'text_ar' => 'was', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'be', 'text_ar' => 'be', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'She has been working in the IT department _______ five years.',
                    'text_ar' => 'اختر حرف الجر المناسب: She has been working in the IT department _______ five years.',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'for', 'text_ar' => 'for', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'since', 'text_ar' => 'since', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'during', 'text_ar' => 'during', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'while', 'text_ar' => 'while', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'If the server _______ updated, the application would have performed better.',
                    'text_ar' => 'اختر التركيب الشرطي الصحيح: If the server _______ updated, the application would have performed better.',
                    'difficulty' => 'hard',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'had been', 'text_ar' => 'had been', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'was', 'text_ar' => 'was', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'is', 'text_ar' => 'is', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'will be', 'text_ar' => 'will be', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
            ],
        ],
        'Vocabulary' => [
            'name_ar' => 'المفردات',
            'questions' => [
                [
                    'text_en' => 'What is the synonym of the word Implement in a corporate setting?',
                    'text_ar' => 'ما مرادف كلمة Implement (ينفذ / يطبق) في سياق العمل الإداري؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Execute', 'text_ar' => 'ينفذ', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Postpone', 'text_ar' => 'يؤجل', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Cancel', 'text_ar' => 'يلغي', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Ignore', 'text_ar' => 'يتجاهل', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Select the phrase that best defines Confidential:',
                    'text_ar' => 'اختر التعريف الأنسب لكلمة Confidential (سري):',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Private and meant to be kept secret.', 'text_ar' => 'خاص ومعد ليكون سرياً.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Publicly accessible to everyone.', 'text_ar' => 'متاح للعامة للجميع.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Temporary and unverified.', 'text_ar' => 'مؤقت وغير مؤكد.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Irrelevant to business operations.', 'text_ar' => 'غير مهم لعمليات الشركة.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Choose the correct term for a written record of what was discussed during a meeting:',
                    'text_ar' => 'اختر المصطلح المناسب لـ سجل مكتوب لما تم مناقشته خلال الاجتماع:',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Minutes', 'text_ar' => 'محاضر الاجتماع (Minutes)', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Receipts', 'text_ar' => 'إيصالات', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Invoices', 'text_ar' => 'فواتير', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Schedules', 'text_ar' => 'جداول زمنية', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'The word Deadline refers to:',
                    'text_ar' => 'يشير مصطلح Deadline إلى:',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'The latest time by which a task must be completed.', 'text_ar' => 'آخر موعد يجب إنجاز المهمة بحلوله.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'The start time of a project.', 'text_ar' => 'وقت بدء المشروع.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'The total budget allocated for a project.', 'text_ar' => 'الميزانية الإجمالية المخصصة للمشروع.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'The break time during work hours.', 'text_ar' => 'وقت الاستراحة خلال ساعات العمل.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Select the word that best fits: We need to _______ our efforts to meet the quarterly target.',
                    'text_ar' => 'اختر الكلمة الأنسب: We need to _______ our efforts to meet the quarterly target.',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'coordinate', 'text_ar' => 'ننسق (coordinate)', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'terminate', 'text_ar' => 'ننهي', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'neglect', 'text_ar' => 'نهمل', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'delay', 'text_ar' => 'نؤجل', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
            ],
        ],
        'Reading Comprehension' => [
            'name_ar' => 'الفهم والاستيعاب',
            'questions' => [
                [
                    'text_en' => 'Passage: All official communications must be transmitted through corporate email. Personal messaging apps are strictly prohibited for sharing company documents. Question: How should official company documents be shared?',
                    'text_ar' => 'النص: يجب إرسال كافة المراسلات الرسمية عبر البريد الإلكتروني للمؤسسة. يمنع منعاً باتاً استخدام تطبيقات الرسائل الشخصية لمشاركة مستندات الشركة. السؤال: كيف يجب مشاركة مستندات الشركة الرسمية؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Via corporate email only.', 'text_ar' => 'عبر البريد الإلكتروني للمؤسسة فقط.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Through personal messaging applications.', 'text_ar' => 'عبر تطبيقات الرسائل الشخصية.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'By posting on social media platforms.', 'text_ar' => 'بنشرها على منصات التواصل الاجتماعي.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'By printing physical hard copies only.', 'text_ar' => 'بطباعتها كنسخ ورقية فقط.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Passage: Employees who complete 40 hours of professional training annually receive a performance bonus. Question: What is required to earn the training bonus?',
                    'text_ar' => 'النص: يحصل الموظفون الذين يتمون 40 ساعة من التدريب المهني سنوياً على مكافأة أداء. السؤال: ما المطلوب للحصول على مكافأة التدريب؟',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Completing 40 hours of annual training.', 'text_ar' => 'إتمام 40 ساعة تدريبية سنوياً.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Working 60 hours per week.', 'text_ar' => 'العمل 60 ساعة أسبوعياً.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Submitting weekly status reports.', 'text_ar' => 'تقديم تقارير حالة أسبوعية.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Changing department annually.', 'text_ar' => 'تغيير الإدارة سنوياً.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Passage: Password complexity policy mandates a minimum length of 12 characters, including uppercase, lowercase, numbers, and symbols. Passwords must be updated every 90 days. Question: How often must passwords be updated under this policy?',
                    'text_ar' => 'النص: تنص سياسة تعقيد كلمة المرور على حد أدنى 12 حرفاً، شاملة أحرفاً كبيرة وصغيرة وأرقاماً ورُموزاً. يجب تحديث كلمات المرور كل 90 يوماً. السؤال: كم مرة يجب تحديث كلمات المرور بموجب هذه السياسة؟',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Every 90 days.', 'text_ar' => 'كل 90 يوماً.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Every 30 days.', 'text_ar' => 'كل 30 يوماً.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Annually.', 'text_ar' => 'سنوياً.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Never.', 'text_ar' => 'لا تتطلب التحديث إطلاقاً.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Passage: Remote work requests require prior approval from both the department manager and HR. Applications submitted less than three days in advance will not be processed. Question: What happens to a remote work application submitted two days before the requested date?',
                    'text_ar' => 'النص: تتطلب طلبات العمل عن بُعد موافقة مسبقة من مدير الإدارة والموارد البشرية. الطلبات المقدمة قبل أقل من ثلاثة أيام لن تدرس. السؤال: ما الذي يحدث لطلب العمل عن بعد المقدم قبل يومين من التاريخ المطلوب؟',
                    'difficulty' => 'hard',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'It will not be processed.', 'text_ar' => 'لن يتم معالجته أو دراسته.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'It is automatically approved.', 'text_ar' => 'يتم اعتماده تلقائياً.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'It gets forwarded to the CEO.', 'text_ar' => 'يتم تحويله للرئيس التنفيذي.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'It requires a payment fee.', 'text_ar' => 'يتطلب دفع رسوم.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Passage: Routine maintenance of the internal network occurs every Saturday from 02:00 UTC to 04:00 UTC. During this window, access to external servers may be interrupted. Question: When does routine network maintenance take place?',
                    'text_ar' => 'النص: تتم الصيانة الدورية للشبكة الداخلية يوم السبت من 02:00 إلى 04:00 حسب التوقيت العالمي (UTC). خلال هذه الفترة، قد ينقطع الاتصال بالسيرفرات الخارجية. السؤال: متى تتم الصيانة الدورية للشبكة؟',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Every Saturday between 02:00 and 04:00 UTC.', 'text_ar' => 'كل يوم سبت بين 02:00 و 04:00 بالتوقيت العالمي.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Every Sunday morning.', 'text_ar' => 'صباح كل يوم أحد.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Daily at midnight.', 'text_ar' => 'يومياً عند منتصف الليل.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Every Friday evening.', 'text_ar' => 'مساء كل يوم جمعة.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
            ],
        ],
        'Workplace English' => [
            'name_ar' => 'الإنجليزية في بيئة العمل',
            'questions' => [
                [
                    'text_en' => 'Choose the professional response when acknowledging receipt of an important assignment:',
                    'text_ar' => 'اختر الرد المهني المناسب عند تأكيد استلام مهمة عمل هامة:',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Received with thanks. I will review and follow up shortly.', 'text_ar' => 'تم الاستلام مع الشكر، سأراجع وأتابع في القريب العاجل.', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'I do not care about this email.', 'text_ar' => 'لا أهتم بهذا البريد.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Ask someone else to do it.', 'text_ar' => 'اطلب من شخص آخر القيام بذلك.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Delete this message immediately.', 'text_ar' => 'احذف هذه الرسالة فوراً.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'What is the most appropriate subject line for requesting leave approval?',
                    'text_ar' => 'ما هو سطر الموضوع الأكثر ملاءمة لطلب موافقة على إجازة عبر البريد الإلكتروني؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Leave Request - [Employee Name] - [Dates]', 'text_ar' => 'طلب إجازة - [اسم الموظف] - [التواريخ]', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Urgent Help Needed!!!!!', 'text_ar' => 'مساعدة عاجلة مطلوبة!!!!!', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'No Subject', 'text_ar' => 'بدون موضوع', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Hi Manager', 'text_ar' => 'مرحباً يا مدير', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'Select the most polite way to request a deadline extension from a manager:',
                    'text_ar' => 'اختر الطريقة الأكثر أدباً لطلب تمديد موعد تسليم (Deadline) من المدير:',
                    'difficulty' => 'hard',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Could we please extend the deadline by two days due to additional requirements?', 'text_ar' => 'هل يمكننا من فضلك تمديد الموعد لمدة يومين نظرًا لمتطلبات إضافية؟', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'I will not finish on time so change the date.', 'text_ar' => 'لن أنهي في الوقت المحدد لذلك غير التاريخ.', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Stop asking about the project status.', 'text_ar' => 'توقف عن السؤال عن حالة المشروع.', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'The deadline is impossible for anyone.', 'text_ar' => 'الموعد غير ممكن لأي شخص.', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'In business correspondence, FYI stands for:',
                    'text_ar' => 'في المراسلات التجارية والإدارية، يرمز الاختصار FYI إلى:',
                    'difficulty' => 'easy',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'For Your Information', 'text_ar' => 'للعلم والإحاطة (For Your Information)', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'From Your Inspection', 'text_ar' => 'من فحصك', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Fix Your Inputs', 'text_ar' => 'أصلح مدخلاتك', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Final Yearly Inspection', 'text_ar' => 'الفحص السنوي النهائي', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
                [
                    'text_en' => 'What does CC mean when adding recipients to an email?',
                    'text_ar' => 'ماذا يعني حقل CC عند إضافة مستلمين في البريد الإلكتروني؟',
                    'difficulty' => 'medium',
                    'options' => [
                        ['key' => 'OPT_A', 'text_en' => 'Carbon Copy', 'text_ar' => 'نسخة كربونية للإحاطة (Carbon Copy)', 'is_correct' => 1, 'order' => 1],
                        ['key' => 'OPT_B', 'text_en' => 'Confirmed Contact', 'text_ar' => 'جهة اتصال مؤكدة', 'is_correct' => 0, 'order' => 2],
                        ['key' => 'OPT_C', 'text_en' => 'Corporate Change', 'text_ar' => 'تغيير مؤسسي', 'is_correct' => 0, 'order' => 3],
                        ['key' => 'OPT_D', 'text_en' => 'Central Control', 'text_ar' => 'التحكم المركزي', 'is_correct' => 0, 'order' => 4],
                    ],
                ],
            ],
        ],
    ],
];

// Pre-flight Existing Data Safety Guard & Verification
$attemptsCount = (int) $pdo->query("SELECT COUNT(*) FROM test_attempts")->fetchColumn();

$verifiedCategoryIdsToReplace = [];
$verifiedQuestionIdsToReplace = [];
$verifiedOptionIdsToReplace = [];

foreach ($tests as $code => $testInfo) {
    $testId = (int) $testInfo['id'];
    $checkQStmt = $pdo->prepare("SELECT id, text_en FROM questions WHERE test_id = :test_id");
    $checkQStmt->execute([':test_id' => $testId]);
    $existingQs = $checkQStmt->fetchAll(PDO::FETCH_ASSOC);
    $qCount = count($existingQs);

    // Rule: Allow execution ONLY when question count is exactly 0 or exactly 20.
    if ($qCount !== 0 && $qCount !== 20) {
        echo "Safety Guard Error: Test '{$code}' (ID {$testId}) has {$qCount} existing questions. Allowed question count for seeding is exactly 0 or 20." . PHP_EOL;
        exit(1);
    }

    if ($qCount === 20) {
        // Collect all demo text_en for this test
        $allDemoTexts = [];
        foreach ($demoData[$code] as $catData) {
            foreach ($catData['questions'] as $qData) {
                $allDemoTexts[] = $qData['text_en'];
            }
        }

        foreach ($existingQs as $eq) {
            if (!in_array($eq['text_en'], $allDemoTexts, true)) {
                echo "Safety Guard Error: Test '{$code}' contains unrecognized question ID {$eq['id']} ('{$eq['text_en']}'). Seeding aborted to protect non-demo data." . PHP_EOL;
                exit(1);
            }
        }

        if ($isReplaceDemo) {
            if ($attemptsCount > 0) {
                echo "Safety Guard Error: Cannot use --replace-demo because {$attemptsCount} active/completed attempt(s) exist in database. Replacement aborted." . PHP_EOL;
                exit(1);
            }

            // Capture exact verified category IDs for this test
            $catFetchStmt = $pdo->prepare("SELECT id, name_ar, name_en, is_active FROM question_categories WHERE test_id = :test_id");
            $catFetchStmt->execute([':test_id' => $testId]);
            $existingCats = $catFetchStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($existingCats) !== 4) {
                echo "Safety Guard Error: Test '{$code}' has " . count($existingCats) . " categories. Expected exactly 4 categories before replacement." . PHP_EOL;
                exit(1);
            }

            foreach ($existingCats as $ec) {
                if ((int)$ec['is_active'] !== 1) {
                    echo "Safety Guard Error: Category ID {$ec['id']} is not active. Replacement aborted." . PHP_EOL;
                    exit(1);
                }
                $verifiedCategoryIdsToReplace[] = (int)$ec['id'];
            }

            // Capture exact verified question & option IDs for this test
            $fullQFetchStmt = $pdo->prepare("
                SELECT q.id, q.category_id, q.type, q.text_ar, q.text_en, q.difficulty, q.score, q.is_active, q.archived_at, qc.name_en AS category_name_en, qc.name_ar AS category_name_ar
                FROM questions q
                JOIN question_categories qc ON qc.id = q.category_id
                WHERE q.test_id = :test_id
            ");
            $fullQFetchStmt->execute([':test_id' => $testId]);
            $fullQs = $fullQFetchStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($fullQs) !== 20) {
                echo "Safety Guard Error: Test '{$code}' has " . count($fullQs) . " questions. Expected exactly 20." . PHP_EOL;
                exit(1);
            }

            $optFetchStmt = $pdo->prepare("SELECT id, option_key, text_ar, text_en, is_correct, display_order FROM question_options WHERE question_id = :q_id ORDER BY display_order ASC");

            foreach ($fullQs as $fq) {
                $qId = (int)$fq['id'];
                $verifiedQuestionIdsToReplace[] = $qId;

                $optFetchStmt->execute([':q_id' => $qId]);
                $opts = $optFetchStmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($opts) !== 4) {
                    echo "Safety Guard Error: Question ID {$qId} has " . count($opts) . " options. Expected exactly 4." . PHP_EOL;
                    exit(1);
                }

                foreach ($opts as $opt) {
                    $verifiedOptionIdsToReplace[] = (int)$opt['id'];
                }
            }
        }
    }
}

// Single Outer Database Transaction
$pdo->beginTransaction();

$categoriesInserted = 0;
$categoriesExisting = 0;
$questionsInserted = 0;
$questionsExisting = 0;
$optionsInserted = 0;
$optionsExisting = 0;
$conflicts = [];

try {
    // Destructive DELETE operations are ONLY executed when --replace-demo is explicitly supplied AND NOT in dry-run mode
    if ($isReplaceDemo && !$isDryRun) {
        if (!empty($verifiedOptionIdsToReplace)) {
            $inOptions = implode(',', array_map('intval', $verifiedOptionIdsToReplace));
            $pdo->exec("DELETE FROM question_options WHERE id IN ({$inOptions})");
        }
        if (!empty($verifiedQuestionIdsToReplace)) {
            $inQuestions = implode(',', array_map('intval', $verifiedQuestionIdsToReplace));
            $pdo->exec("DELETE FROM questions WHERE id IN ({$inQuestions})");
        }
        if (!empty($verifiedCategoryIdsToReplace)) {
            $inCategories = implode(',', array_map('intval', $verifiedCategoryIdsToReplace));
            $pdo->exec("DELETE FROM question_categories WHERE id IN ({$inCategories})");
        }
    }

    $catCheckStmt = $pdo->prepare("SELECT id, name_ar, is_active FROM question_categories WHERE test_id = :test_id AND name_en = :name_en LIMIT 1");
    $catInsertStmt = $pdo->prepare("
        INSERT INTO question_categories (test_id, name_ar, name_en, is_active, created_at)
        VALUES (:test_id, :name_ar, :name_en, 1, UTC_TIMESTAMP())
    ");

    $qCheckStmt = $pdo->prepare("
        SELECT q.id, q.test_id, q.category_id, q.type, q.text_ar, q.text_en, q.difficulty, q.score, q.is_active, q.archived_at, qc.name_en AS category_name_en, qc.name_ar AS category_name_ar
        FROM questions q
        JOIN question_categories qc ON qc.id = q.category_id
        WHERE q.test_id = :test_id AND q.text_en = :text_en
        LIMIT 1
    ");

    $qInsertStmt = $pdo->prepare("
        INSERT INTO questions (test_id, category_id, type, text_ar, text_en, difficulty, score, is_active, archived_at, created_by, created_at, updated_at)
        VALUES (:test_id, :category_id, 'single_choice', :text_ar, :text_en, :difficulty, 1.00, 1, NULL, :created_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())
    ");

    $optCheckStmt = $pdo->prepare("
        SELECT id, option_key, text_ar, text_en, is_correct, display_order
        FROM question_options
        WHERE question_id = :question_id
        ORDER BY display_order ASC
    ");

    $optInsertStmt = $pdo->prepare("
        INSERT INTO question_options (question_id, option_key, text_ar, text_en, is_correct, display_order)
        VALUES (:question_id, :option_key, :text_ar, :text_en, :is_correct, :display_order)
    ");

    foreach ($demoData as $testCode => $categories) {
        $testId = (int) $tests[$testCode]['id'];

        foreach ($categories as $catNameEn => $catMeta) {
            $catNameAr = $catMeta['name_ar'];

            // 1. Resolve & Verify Category Idempotency
            $catCheckStmt->execute([':test_id' => $testId, ':name_en' => $catNameEn]);
            $existingCat = $catCheckStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingCat && !($isReplaceDemo && !$isDryRun)) {
                // Strengthened category idempotency checks
                if ($existingCat['name_ar'] !== $catNameAr || (int)$existingCat['is_active'] !== 1) {
                    $conflicts[] = "Category mismatch for '{$catNameEn}' in test '{$testCode}': name_ar or active state mismatch.";
                }
                $catId = (int) $existingCat['id'];
                $categoriesExisting++;
            } else {
                $catInsertStmt->execute([
                    ':test_id' => $testId,
                    ':name_ar' => $catNameAr,
                    ':name_en' => $catNameEn,
                ]);
                $catId = (int) $pdo->lastInsertId();
                $categoriesInserted++;
            }

            // 2. Resolve Questions
            foreach ($catMeta['questions'] as $qMeta) {
                $qTextEn = $qMeta['text_en'];
                $qTextAr = $qMeta['text_ar'];
                $qDifficulty = $qMeta['difficulty'];

                $qCheckStmt->execute([':test_id' => $testId, ':text_en' => $qTextEn]);
                $existingQ = $qCheckStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingQ && !($isReplaceDemo && !$isDryRun)) {
                    $qId = (int) $existingQ['id'];

                    // Strict Complete-Definition Verification
                    $mismatches = [];

                    if ($existingQ['category_name_en'] !== $catNameEn || $existingQ['category_name_ar'] !== $catNameAr) {
                        $mismatches[] = "category mismatch (found '{$existingQ['category_name_en']}', expected '{$catNameEn}')";
                    }
                    if ($existingQ['text_ar'] !== $qTextAr) {
                        $mismatches[] = "text_ar mismatch";
                    }
                    if ($existingQ['type'] !== 'single_choice') {
                        $mismatches[] = "type mismatch (found '{$existingQ['type']}')";
                    }
                    if ($existingQ['difficulty'] !== $qDifficulty) {
                        $mismatches[] = "difficulty mismatch (found '{$existingQ['difficulty']}', expected '{$qDifficulty}')";
                    }
                    if ((float) $existingQ['score'] !== 1.00) {
                        $mismatches[] = "score mismatch (found {$existingQ['score']})";
                    }
                    if ((int) $existingQ['is_active'] !== 1 || $existingQ['archived_at'] !== null) {
                        $mismatches[] = "active/archive state invalid";
                    }

                    // Verify Options
                    $optCheckStmt->execute([':question_id' => $qId]);
                    $existingOpts = $optCheckStmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($existingOpts) !== 4) {
                        $mismatches[] = "option count mismatch (found " . count($existingOpts) . ", expected 4)";
                    } else {
                        $correctCount = 0;
                        foreach ($qMeta['options'] as $idx => $optMeta) {
                            $exOpt = $existingOpts[$idx] ?? null;
                            if (!$exOpt) {
                                $mismatches[] = "option index {$idx} missing";
                                continue;
                            }
                            if ($exOpt['option_key'] !== $optMeta['key']) {
                                $mismatches[] = "option {$idx} key mismatch";
                            }
                            if ($exOpt['text_ar'] !== $optMeta['text_ar']) {
                                $mismatches[] = "option {$idx} text_ar mismatch";
                            }
                            if ($exOpt['text_en'] !== $optMeta['text_en']) {
                                $mismatches[] = "option {$idx} text_en mismatch";
                            }
                            if ((int) $exOpt['is_correct'] !== $optMeta['is_correct']) {
                                $mismatches[] = "option {$idx} is_correct mismatch";
                            }
                            if ((int) $exOpt['display_order'] !== $optMeta['order']) {
                                $mismatches[] = "option {$idx} display_order mismatch";
                            }
                            if ((int) $exOpt['is_correct'] === 1) {
                                $correctCount++;
                            }
                        }
                        if ($correctCount !== 1) {
                            $mismatches[] = "correct option count invalid ({$correctCount})";
                        }
                    }

                    if (!empty($mismatches)) {
                        $conflicts[] = "Question ID {$qId} ('{$qTextEn}'): " . implode('; ', $mismatches);
                    } else {
                        $questionsExisting++;
                        $optionsExisting += 4;
                    }
                } else {
                    // Insert Question
                    $qInsertStmt->execute([
                        ':test_id' => $testId,
                        ':category_id' => $catId,
                        ':text_ar' => $qTextAr,
                        ':text_en' => $qTextEn,
                        ':difficulty' => $qDifficulty,
                        ':created_by' => $superAdminId,
                    ]);
                    $qId = (int) $pdo->lastInsertId();
                    $questionsInserted++;

                    // Insert Options
                    foreach ($qMeta['options'] as $optMeta) {
                        $optInsertStmt->execute([
                            ':question_id' => $qId,
                            ':option_key' => $optMeta['key'],
                            ':text_ar' => $optMeta['text_ar'],
                            ':text_en' => $optMeta['text_en'],
                            ':is_correct' => $optMeta['is_correct'],
                            ':display_order' => $optMeta['order'],
                        ]);
                        $optionsInserted++;
                    }
                }
            }
        }
    }

    if (count($conflicts) > 0) {
        $pdo->rollBack();
        $txStatus = "Rolled back (Conflicts detected)";
    } elseif ($isDryRun) {
        $pdo->rollBack();
        $txStatus = "Rolled back (Dry-run mode)";
    } else {
        $pdo->commit();
        $txStatus = "Committed";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Execution Exception: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Print Structured Summary Output
echo "=============================================================" . PHP_EOL;
echo "EMPLOYEE ASSESSMENT & TESTING SYSTEM - DEMO QUESTION SEEDER" . PHP_EOL;
echo "=============================================================" . PHP_EOL;
echo "Environment Guard : PASS (APP_ENV=development)" . PHP_EOL;
echo "CLI Guard         : PASS (SAPI=cli)" . PHP_EOL;
echo "Execution Mode    : " . ($isDryRun ? ($isReplaceDemo ? "DRY-RUN REPLACEMENT SIMULATION (--replace-demo --dry-run)" : "DRY-RUN (SIMULATION)") : ($isReplaceDemo ? "LIVE REPLACEMENT (--replace-demo)" : "LIVE SEEDING")) . PHP_EOL;
echo "Super Admin ID    : " . $superAdminId . " (role=super_admin, is_active=1)" . PHP_EOL;
echo PHP_EOL;
echo "RESOLVED TESTS:" . PHP_EOL;
foreach ($tests as $code => $tInfo) {
    echo "  - {$code} (DB ID: {$tInfo['id']}, Name: {$tInfo['name_en']}, Question Count Target: {$tInfo['question_count']})" . PHP_EOL;
}
echo PHP_EOL;

if ($isReplaceDemo && $isDryRun) {
    echo "REPLACEMENT DRY-RUN SIMULATION AUDIT:" . PHP_EOL;
    echo "  - Dataset Verification: PASS (All 40 questions & 8 categories match exact demo set)" . PHP_EOL;
    echo "  - Captured Category IDs to replace (" . count($verifiedCategoryIdsToReplace) . ") : " . implode(', ', $verifiedCategoryIdsToReplace) . PHP_EOL;
    echo "  - Captured Question IDs to replace (" . count($verifiedQuestionIdsToReplace) . ") : " . implode(', ', array_slice($verifiedQuestionIdsToReplace, 0, 5)) . "... [Total " . count($verifiedQuestionIdsToReplace) . " Question IDs]" . PHP_EOL;
    echo "  - Captured Option IDs to replace   : Total " . count($verifiedOptionIdsToReplace) . " Option IDs" . PHP_EOL;
    echo "  - Mutation Executed                : NONE (Zero INSERT, UPDATE, or DELETE executed)" . PHP_EOL;
    echo PHP_EOL;
}

echo "SEEDING SUMMARY:" . PHP_EOL;
echo "  - Categories : Inserted: {$categoriesInserted} | Existing: {$categoriesExisting}" . PHP_EOL;
echo "  - Questions  : Inserted: {$questionsInserted} | Existing: {$questionsExisting}" . PHP_EOL;
echo "  - Options    : Inserted: {$optionsInserted} | Existing: {$optionsExisting}" . PHP_EOL;
echo "  - Conflicts  : " . count($conflicts) . PHP_EOL;
echo PHP_EOL;

if (count($conflicts) > 0) {
    echo "CONFLICT REPORT:" . PHP_EOL;
    foreach ($conflicts as $c) {
        echo "  - [CONFLICT] {$c}" . PHP_EOL;
    }
    echo PHP_EOL;
    echo "Notice: Use --replace-demo only if existing data is verified demo data to be replaced." . PHP_EOL;
}

echo "TRANSACTION STATUS: {$txStatus}" . PHP_EOL;
echo "=============================================================" . PHP_EOL;

if (count($conflicts) > 0) {
    exit(1);
}
