<?php

if (PHP_SAPI !== 'cli') {
    die("CLI execution required.\n");
}

require_once __DIR__ . '/../bootstrap/init.php';
require_once __DIR__ . '/../database/seeders/SystemSettingsSeeder.php';

use App\Support\Database;
use Database\Seeders\SystemSettingsSeeder;

echo "=== Running Database Seeders ===" . PHP_EOL;

try {
    $pdo = Database::getInstance();
    SystemSettingsSeeder::run($pdo);
    echo "Reference system settings, departments, sections, job titles, and core test definitions seeded successfully." . PHP_EOL;
} catch (\Exception $e) {
    echo "Seeding Failure: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
