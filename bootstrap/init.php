<?php

use App\Middleware\SessionMiddleware;

// Load Composer Vendor Autoloader
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    die("Composer vendor autoloader not found. Please run 'composer install' first.");
}
require_once $vendorAutoload;

// Load Environment Variables using vlucas/phpdotenv (Industry Standard)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

// Set PHP Default Timezone to UTC for all internal date/time logic
date_default_timezone_set('UTC');

// Error Handling Settings
$isDev = (($_ENV['APP_ENV'] ?? 'production') === 'development');
error_reporting(E_ALL);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/app.log');

// Include global helper functions
require_once __DIR__ . '/../app/Support/HelperFunctions.php';

// Initialize Session Middleware if web request
if (php_sapi_name() !== 'cli') {
    SessionMiddleware::handle();
}
