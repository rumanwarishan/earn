<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Support\ValidationException;

final class AdminAuthService
{
    public static function login(string $email, string $password, string $ip, string $userAgent): array
    {
        $email = strtolower(trim($email));

        if (RateLimiter::tooManyLoginAttempts('admin:' . $email, $ip)) {
            throw new ValidationException(['login' => 'Too many login attempts. Please try again shortly.']);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT a.*, r.slug AS role_slug, r.name AS role_name, r.permissions FROM admin_users a
            JOIN roles r ON r.id = a.role_id WHERE a.email = ?');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            RateLimiter::recordAttempt('admin:' . $email, $ip, false);
            AuditLogger::log('admin', $admin['id'] ?? null, 'admin.login_failed', 'admin_user', $admin['id'] ?? null, null, null, null, $ip, $userAgent);
            throw new ValidationException(['login' => 'Invalid email or password.']);
        }

        if ($admin['status'] !== 'active') {
            RateLimiter::recordAttempt('admin:' . $email, $ip, false);
            throw new ValidationException(['login' => 'This admin account has been suspended.']);
        }

        RateLimiter::recordAttempt('admin:' . $email, $ip, true);
        $pdo->prepare('UPDATE admin_users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')->execute([$ip, $admin['id']]);

        Session::regenerate();
        Session::put('admin', [
            'id' => (int) $admin['id'],
            'uuid' => $admin['uuid'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'role_slug' => $admin['role_slug'],
            'role_name' => $admin['role_name'],
            'permissions' => json_decode($admin['permissions'], true) ?: [],
        ]);

        AuditLogger::log('admin', (int) $admin['id'], 'admin.login_success', 'admin_user', (int) $admin['id'], null, null, null, $ip, $userAgent);

        return $admin;
    }

    public static function logout(): void
    {
        $admin = Session::get('admin');
        if ($admin) {
            AuditLogger::log('admin', $admin['id'], 'admin.logout', 'admin_user', $admin['id']);
        }
        Session::forget('admin');
    }

    public static function can(string $permission): bool
    {
        $admin = Session::get('admin');
        if (!$admin) {
            return false;
        }
        $permissions = $admin['permissions'] ?? [];
        if (in_array('*', $permissions, true)) {
            return true;
        }
        foreach ($permissions as $p) {
            if ($p === $permission) {
                return true;
            }
            if (str_ends_with($p, '.*') && str_starts_with($permission, substr($p, 0, -1))) {
                return true;
            }
        }
        return false;
    }
}
