<?php

namespace Database\Seeders;

use PDO;

class SystemSettingsSeeder
{
    public static function run(PDO $pdo): void
    {
        $settings = [
            ['setting_key' => 'organization_name_ar', 'setting_value' => 'الجهة التقييمية الرسمية', 'setting_type' => 'text', 'setting_group' => 'general'],
            ['setting_key' => 'organization_name_en', 'setting_value' => 'Official Assessment Portal', 'setting_type' => 'text', 'setting_group' => 'general'],
            ['setting_key' => 'show_result_to_employee', 'setting_value' => '0', 'setting_type' => 'boolean', 'setting_group' => 'test'],
            ['setting_key' => 'allow_back_navigation_default', 'setting_value' => '1', 'setting_type' => 'boolean', 'setting_group' => 'test'],
            ['setting_key' => 'rate_limit_max_attempts', 'setting_value' => '5', 'setting_type' => 'number', 'setting_group' => 'security'],
            ['setting_key' => 'rate_limit_lockout_minutes', 'setting_value' => '15', 'setting_type' => 'number', 'setting_group' => 'security'],
            ['setting_key' => 'rate_limit_retention_days', 'setting_value' => '30', 'setting_type' => 'number', 'setting_group' => 'security'],
            ['setting_key' => 'mail_queue_batch_size', 'setting_value' => '25', 'setting_type' => 'number', 'setting_group' => 'notification'],
            ['setting_key' => 'mail_queue_max_attempts', 'setting_value' => '5', 'setting_type' => 'number', 'setting_group' => 'notification'],
            ['setting_key' => 'finalize_expired_batch_size', 'setting_value' => '50', 'setting_type' => 'number', 'setting_group' => 'test'],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, updated_at)
            VALUES (:setting_key, :setting_value, :setting_type, :setting_group, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_type = VALUES(setting_type),
                setting_group = VALUES(setting_group),
                updated_at = UTC_TIMESTAMP()
        ");

        foreach ($settings as $s) {
            $stmt->execute($s);
        }

        // Seed Reference Departments
        $stmtDept = $pdo->prepare("
            INSERT INTO departments (id, name_ar, name_en, is_active, created_at, updated_at)
            VALUES (1, 'إدارة تكنولوجيا المعلومات', 'Information Technology Department', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (2, 'إدارة الموارد البشرية', 'Human Resources Department', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (3, 'إدارة الشؤون المالية والإدارية', 'Financial and Administrative Affairs', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), name_en=VALUES(name_en);
        ");
        $stmtDept->execute();

        // Seed Reference Sections
        $stmtSec = $pdo->prepare("
            INSERT INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at)
            VALUES (1, 1, 'قسم التطوير والبرمجيات', 'Software Development Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (2, 1, 'قسم البنية التحتية والأمن', 'Infrastructure & Security Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (3, 2, 'قسم التدريب والتطوير', 'Training & Development Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (4, 3, 'قسم المحاسبة والميزانية', 'Accounting & Budgeting Section', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), name_en=VALUES(name_en);
        ");
        $stmtSec->execute();

        // Seed Reference Job Titles
        $stmtJobs = $pdo->prepare("
            INSERT INTO job_titles (id, name_ar, name_en, is_active, created_at, updated_at)
            VALUES (1, 'مهندس برمجيات', 'Software Engineer', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (2, 'محلل نظم', 'Systems Analyst', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (3, 'أخصائي موارد بشرية', 'HR Specialist', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (4, 'محاسب', 'Accountant', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (5, 'مدير مشروع', 'Project Manager', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), name_en=VALUES(name_en);
        ");
        $stmtJobs->execute();

        // Seed Core Tests Definitions
        $stmtTests = $pdo->prepare("
            INSERT INTO tests (id, code, name_ar, name_en, description_ar, description_en, instructions_ar, instructions_en, duration_minutes, question_count, passing_percentage, display_order, randomize_questions, randomize_options, allow_back_navigation, show_result_to_employee, is_active, created_at, updated_at)
            VALUES (1, 'IQ_TEST', 'اختبار الذكاء والقدرات العامة', 'IQ & General Aptitude Test', 'تقييم القدرات الذهنية والتحليلية للموظفين', 'Evaluation of logical reasoning and analytical skills', 'يرجى قراءة الأسئلة بتركيز، يتيح لك الاختبار التراجع بين الأسئلة قبل الإرسال النهائي.', 'Please read questions carefully. You can navigate between questions before submission.', 30, 20, 60.00, 1, 1, 1, 1, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
                   (2, 'ENG_TEST', 'اختبار الكفاءة في اللغة الإنجليزية', 'English Language Proficiency Test', 'تقييم كفاءة ومستوى اللغة الإنجليزية في بيئة العمل', 'Evaluation of business English language proficiency', 'يتكون الاختبار من أسئلة مفردات وقواعد وفهم استيعابي.', 'The test consists of vocabulary, grammar, and comprehension questions.', 30, 20, 60.00, 2, 1, 1, 1, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), name_en=VALUES(name_en);
        ");
        $stmtTests->execute();
    }
}
