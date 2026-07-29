<?php

if (php_sapi_name() !== 'cli') {
    echo "This script can only be executed from the command line interface." . PHP_EOL;
    exit(1);
}

// Reject any CLI argument that looks like a password or parameter attempt
if ($argc > 1) {
    echo "Error: Passwords and options must not be passed via command line arguments." . PHP_EOL;
    echo "Usage: php scripts/create_admin.php" . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../bootstrap/init.php';

use App\Support\Database;

function promptInput(string $prompt): string {
    echo $prompt;
    $line = fgets(STDIN);
    return trim((string)$line);
}

function promptHiddenInput(string $prompt): string {
    echo $prompt;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        if (@stream_isatty(STDIN)) {
            $vbscript = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prompt_pwd.vbs';
            file_put_contents($vbscript, 'WScript.Echo CreateObject("ScriptPW.Password").GetPassword()');
            $password = shell_exec('cscript //nologo ' . escapeshellarg($vbscript));
            @unlink($vbscript);
            if ($password !== null && trim($password) !== '') {
                echo PHP_EOL;
                return trim($password);
            }
        }
        $password = fgets(STDIN);
        return trim((string)$password);
    } else {
        if (@stream_isatty(STDIN)) {
            system('stty -echo');
        }
        $password = fgets(STDIN);
        if (@stream_isatty(STDIN)) {
            system('stty echo');
            echo PHP_EOL;
        }
        return trim((string)$password);
    }
}

echo "=== Interactive Administrator Account Manager ===" . PHP_EOL;

$name = promptInput("Enter Full Name: ");
if (empty($name)) {
    echo "Error: Full name is required." . PHP_EOL;
    exit(1);
}

$email = strtolower(promptInput("Enter Email Address: "));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: Invalid email format." . PHP_EOL;
    exit(1);
}

$username = strtolower(promptInput("Enter Username: "));
if (empty($username) || !preg_match('/^[a-z0-9_\-\.]+$/', $username)) {
    echo "Error: Username must contain alphanumeric characters, underscores, or hyphens only." . PHP_EOL;
    exit(1);
}

$roleInput = strtolower(promptInput("Enter Role (super_admin / supervisor / report_viewer) [supervisor]: "));
$role = empty($roleInput) ? 'supervisor' : $roleInput;

if (!in_array($role, ['super_admin', 'supervisor', 'report_viewer'], true)) {
    echo "Error: Invalid role specified. Allowed roles: super_admin, supervisor, report_viewer." . PHP_EOL;
    exit(1);
}

$password = promptHiddenInput("Enter Password (min 12 chars, upper, lower, digit, symbol): ");
if (strlen($password) < 12 ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[^A-Za-z0-9]/', $password)) {
    echo "Error: Password fails strength policy (min 12 chars, upper, lower, digit, symbol)." . PHP_EOL;
    exit(1);
}

$confirmPassword = promptHiddenInput("Confirm Password: ");
if ($password !== $confirmPassword) {
    echo "Error: Password confirmation does not match." . PHP_EOL;
    exit(1);
}

try {
    $pdo = Database::getInstance();

    // Reject duplicate normalized email or username
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE LOWER(email) = :email OR LOWER(username) = :username");
    $checkStmt->execute([':email' => $email, ':username' => $username]);
    if ($checkStmt->fetchColumn() > 0) {
        echo "Error: Account with specified email or username already exists." . PHP_EOL;
        exit(1);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare("
        INSERT INTO admins (name, email, username, password_hash, role, is_active, created_at, updated_at)
        VALUES (:name, :email, :username, :password_hash, :role, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
    ");

    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':username' => $username,
        ':password_hash' => $passwordHash,
        ':role' => $role
    ]);

    echo "Administrator account '{$username}' ({$email}) successfully created with role '{$role}'." . PHP_EOL;
} catch (\PDOException $e) {
    echo "Database Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
