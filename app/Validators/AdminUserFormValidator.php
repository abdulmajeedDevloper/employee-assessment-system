<?php

namespace App\Validators;

use App\Repositories\AdminUserRepository;

class AdminUserFormValidator
{
    private AdminUserRepository $userRepo;

    public function __construct(?AdminUserRepository $userRepo = null)
    {
        $this->userRepo = $userRepo ?? new AdminUserRepository();
    }

    /**
     * Enforce project password policy:
     * Minimum 12 characters, uppercase letter, lowercase letter, digit, special character.
     *
     * @param string $password
     * @return bool
     */
    public static function isPasswordPolicyCompliant(string $password): bool
    {
        if (strlen($password) < 12) {
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Validate secondary admin account creation.
     *
     * @param array $input
     * @return array ['valid' => bool, 'errors' => array, 'data' => array]
     */
    public function validateCreate(array $input): array
    {
        $errors = [];
        $validated = [];

        $displayName = trim((string)($input['display_name'] ?? ''));
        $username = strtolower(trim((string)($input['username'] ?? '')));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $passwordConfirm = (string)($input['password_confirm'] ?? '');
        $role = trim((string)($input['role'] ?? 'admin'));
        $isActiveRaw = $input['is_active'] ?? '1';
        $isActive = ($isActiveRaw === '1' || $isActiveRaw === 1 || $isActiveRaw === true) ? 1 : 0;

        // 1. Display Name
        if (empty($displayName) || mb_strlen($displayName) < 2 || mb_strlen($displayName) > 255) {
            $errors['display_name'] = 'Display name is required (between 2 and 255 characters).';
        } else {
            $validated['display_name'] = $displayName;
        }

        // 2. Username
        if (empty($username) || !preg_match('/^[a-z0-9_\-\.]+$/', $username) || strlen($username) > 100) {
            $errors['username'] = 'Username must contain only letters, numbers, underscores, or hyphens (max 100 chars).';
        } else {
            $existing = $this->userRepo->findByNormalizedUsername($username);
            if ($existing) {
                $errors['username'] = 'This username is already in use.';
            } else {
                $validated['username'] = $username;
            }
        }

        // 3. Email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email address is required.';
        } else {
            $existing = $this->userRepo->findByNormalizedEmail($email);
            if ($existing) {
                $errors['email'] = 'This email address is already in use.';
            } else {
                $validated['email'] = $email;
            }
        }

        // 4. Password
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (!self::isPasswordPolicyCompliant($password)) {
            $errors['password'] = 'Password fails security policy (minimum 12 characters, including uppercase, lowercase, digit, and symbol).';
        } elseif ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Password confirmation does not match.';
        } else {
            $validated['password'] = $password;
        }

        // 5. Role
        $allowedRoles = ['super_admin', 'admin'];
        if (!in_array($role, $allowedRoles, true)) {
            $errors['role'] = 'Invalid role specified.';
        } else {
            $validated['role'] = $role;
        }

        $validated['is_active'] = $isActive;

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated,
        ];
    }

    /**
     * Validate administrator account update.
     *
     * @param array $input
     * @param array $targetAdmin
     * @param array $actingAdmin
     * @return array ['valid' => bool, 'errors' => array, 'data' => array]
     */
    public function validateUpdate(array $input, array $targetAdmin, array $actingAdmin): array
    {
        $errors = [];
        $validated = [];

        $targetId = (int)$targetAdmin['id'];
        $actingId = (int)$actingAdmin['id'];
        $isSelf = ($targetId === $actingId);
        $isTargetPrimary = ((int)($targetAdmin['is_primary'] ?? 0) === 1);

        $displayName = trim((string)($input['display_name'] ?? ''));
        $username = strtolower(trim((string)($input['username'] ?? '')));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $role = trim((string)($input['role'] ?? $targetAdmin['role']));
        $isActiveRaw = $input['is_active'] ?? $targetAdmin['is_active'];
        $isActive = ($isActiveRaw === '1' || $isActiveRaw === 1 || $isActiveRaw === true) ? 1 : 0;

        // 1. Display Name
        if (empty($displayName) || mb_strlen($displayName) < 2 || mb_strlen($displayName) > 255) {
            $errors['display_name'] = 'Display name is required (between 2 and 255 characters).';
        } else {
            $validated['display_name'] = $displayName;
        }

        // 2. Username
        if (empty($username) || !preg_match('/^[a-z0-9_\-\.]+$/', $username) || strlen($username) > 100) {
            $errors['username'] = 'Username must contain only letters, numbers, underscores, or hyphens.';
        } else {
            $existing = $this->userRepo->findByNormalizedUsername($username, $targetId);
            if ($existing) {
                $errors['username'] = 'This username is already in use by another account.';
            } else {
                $validated['username'] = $username;
            }
        }

        // 3. Email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email address is required.';
        } else {
            $existing = $this->userRepo->findByNormalizedEmail($email, $targetId);
            if ($existing) {
                $errors['email'] = 'This email address is already in use by another account.';
            } else {
                $validated['email'] = $email;
            }
        }

        // 4. Role & Active Status Protection Rules
        $allowedRoles = ['super_admin', 'admin'];
        if (!in_array($role, $allowedRoles, true)) {
            $errors['role'] = 'Invalid role specified.';
        }

        // Prohibit Self-Demotion or Self-Deactivation
        if ($isSelf) {
            if ($isActive === 0) {
                $errors['is_active'] = 'You cannot deactivate your own active account session.';
            }
            if ($role !== 'super_admin') {
                $errors['role'] = 'You cannot demote or remove your own super administrator role.';
            }
        }

        // Prohibit Primary Account Demotion or Deactivation
        if ($isTargetPrimary) {
            if ($isActive === 0) {
                $errors['is_active'] = 'The primary administrator account cannot be deactivated.';
            }
            if ($role !== 'super_admin') {
                $errors['role'] = 'The primary administrator account cannot be demoted from super administrator.';
            }
        }

        $validated['role'] = $role;
        $validated['is_active'] = $isActive;

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated,
        ];
    }
}
