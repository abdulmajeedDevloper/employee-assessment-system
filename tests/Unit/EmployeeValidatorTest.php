<?php

namespace Tests\Unit;

use App\Repositories\DepartmentRepository;
use App\Repositories\JobTitleRepository;
use App\Validators\EmployeeProfileValidator;
use App\Validators\EmployeeRequestValidator;
use PDO;
use Tests\TestCase;

class EmployeeValidatorTest extends TestCase
{
    private static PDO $pdo;
    private static DepartmentRepository $deptRepo;
    private static JobTitleRepository $jobRepo;

    public static function setUpBeforeClass(): void
    {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $testDb = 'employee_assessment_test';

        $dsn = "mysql:host={$host};port={$port};dbname={$testDb};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        \App\Support\Database::setInstance(self::$pdo);

        self::$deptRepo = new DepartmentRepository(self::$pdo);
        self::$jobRepo = new JobTitleRepository(self::$pdo);
    }

    public function testNationalIdValidation(): void
    {
        // Valid 10-digit numeric National ID
        $res1 = EmployeeRequestValidator::validateNationalId('1000000001');
        $this->assertTrue($res1['valid']);
        $this->assertEquals('1000000001', $res1['normalized']);

        // Invalid length or characters
        $res2 = EmployeeRequestValidator::validateNationalId('12345');
        $this->assertFalse($res2['valid']);

        $res3 = EmployeeRequestValidator::validateNationalId('100000000a');
        $this->assertFalse($res3['valid']);
    }

    public function testSaudiMobileNormalization(): void
    {
        // Format 1: 05XXXXXXXX
        $this->assertEquals('+966512345678', EmployeeProfileValidator::normalizeSaudiMobile('0512345678'));

        // Format 2: 9665XXXXXXXX
        $this->assertEquals('+966512345678', EmployeeProfileValidator::normalizeSaudiMobile('966512345678'));

        // Format 3: +9665XXXXXXXX
        $this->assertEquals('+966512345678', EmployeeProfileValidator::normalizeSaudiMobile('+966512345678'));

        // Format 4: 009665XXXXXXXX
        $this->assertEquals('+966512345678', EmployeeProfileValidator::normalizeSaudiMobile('00966512345678'));
    }

    public function testInvalidEmailValidation(): void
    {
        $validator = new EmployeeProfileValidator(self::$deptRepo, self::$jobRepo);
        $existing = ['department_id' => 1, 'section_id' => 1, 'job_title_id' => 1];

        $res = $validator->validateProfileUpdate([
            'full_name' => 'Valid Name',
            'email' => 'invalid-email-format',
            'phone' => '0512345678',
            'preferred_language' => 'ar'
        ], $existing);

        $this->assertFalse($res['valid']);
        $this->assertArrayHasKey('email', $res['errors']);
    }

    public function testInvalidSaudiMobileValidation(): void
    {
        $validator = new EmployeeProfileValidator(self::$deptRepo, self::$jobRepo);
        $existing = ['department_id' => 1, 'section_id' => 1, 'job_title_id' => 1];

        $res = $validator->validateProfileUpdate([
            'full_name' => 'Valid Name',
            'email' => 'valid@test.com',
            'phone' => '0123456789', // Invalid non-mobile format
            'preferred_language' => 'ar'
        ], $existing);

        $this->assertFalse($res['valid']);
        $this->assertArrayHasKey('phone', $res['errors']);
    }

    public function testInactiveDepartmentValidation(): void
    {
        $validator = new EmployeeProfileValidator(self::$deptRepo, self::$jobRepo);
        $existing = ['department_id' => 999, 'section_id' => 1, 'job_title_id' => 1];

        $res = $validator->validateProfileUpdate([
            'full_name' => 'Valid Name',
            'email' => 'valid@test.com',
            'phone' => '0512345678',
            'preferred_language' => 'ar'
        ], $existing);

        $this->assertFalse($res['valid']);
        $this->assertArrayHasKey('section_id', $res['errors']);
    }

    public function testInactiveSectionValidation(): void
    {
        $validator = new EmployeeProfileValidator(self::$deptRepo, self::$jobRepo);
        $existing = ['department_id' => 1, 'section_id' => 9999, 'job_title_id' => 1];

        $res = $validator->validateProfileUpdate([
            'full_name' => 'Valid Name',
            'email' => 'valid@test.com',
            'phone' => '0512345678',
            'preferred_language' => 'ar'
        ], $existing);

        $this->assertFalse($res['valid']);
        $this->assertArrayHasKey('section_id', $res['errors']);
    }

    public function testSectionBelongingToAnotherDepartmentValidation(): void
    {
        // Seed section 2 belonging to dept 2
        self::$pdo->exec("INSERT IGNORE INTO departments (id, name_ar, name_en, is_active, created_at, updated_at) VALUES (2, 'Dept 2', 'Dept 2', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");
        self::$pdo->exec("INSERT IGNORE INTO sections (id, department_id, name_ar, name_en, is_active, created_at, updated_at) VALUES (2, 2, 'Sec 2', 'Sec 2', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());");

        $validator = new EmployeeProfileValidator(self::$deptRepo, self::$jobRepo);
        // Employee belongs to Dept 1, but section_id points to Section 2 (Dept 2)
        $existing = ['department_id' => 1, 'section_id' => 2, 'job_title_id' => 1];

        $res = $validator->validateProfileUpdate([
            'full_name' => 'Valid Name',
            'email' => 'valid@test.com',
            'phone' => '0512345678',
            'preferred_language' => 'ar'
        ], $existing);

        $this->assertFalse($res['valid']);
        $this->assertArrayHasKey('section_id', $res['errors']);
    }

    public function testInactiveJobTitleValidation(): void
    {
        $validator = new EmployeeProfileValidator(self::$deptRepo, self::$jobRepo);
        $existing = ['department_id' => 1, 'section_id' => 1, 'job_title_id' => 9999];

        $res = $validator->validateProfileUpdate([
            'full_name' => 'Valid Name',
            'email' => 'valid@test.com',
            'phone' => '0512345678',
            'preferred_language' => 'ar'
        ], $existing);

        $this->assertFalse($res['valid']);
        $this->assertArrayHasKey('job_title_id', $res['errors']);
    }
}
