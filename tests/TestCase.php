<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_ENV'] = 'test';
        $_ENV['DB_DATABASE'] = 'employee_assessment_test';
        require_once __DIR__ . '/../bootstrap/init.php';

        $appEnv = $_ENV['APP_ENV'] ?? '';
        if ($appEnv !== 'test' && $appEnv !== 'testing') {
            die("CRITICAL SAFETY ERROR: APP_ENV must be 'test' or 'testing'. Actual: '{$appEnv}'. Aborting execution.\n");
        }

        $pdo = \App\Support\Database::getInstance();
        $dbName = (string) $pdo->query("SELECT DATABASE()")->fetchColumn();

        if (!str_ends_with($dbName, '_test') || $dbName === 'employee_assessment') {
            die("CRITICAL SAFETY ERROR: Active database '{$dbName}' is NOT a safe test database. Aborting execution.\n");
        }
    }
}
