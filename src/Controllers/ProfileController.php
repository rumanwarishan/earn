<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Support\ValidationException;

final class ProfileController
{
    public function index(Request $request): void
    {
        $user = Session::get('user');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT u.*, m.name AS level_name, m.slug AS level_slug FROM users u
            LEFT JOIN membership_levels m ON m.id = u.membership_level_id WHERE u.id = ?');
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();

        echo view('layouts.app', [
            'pageTitle' => 'Profile',
            'unreadCount' => NotificationService::unreadCount((int) $user['id']),
            'content' => view('profile.index', ['profile' => $profile]),
        ]);
    }

    public function updatePassword(Request $request): void
    {
        $user = Session::get('user');
        $pdo = Database::connection();

        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('confirm_password', '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        try {
            if (!password_verify($current, $hash)) {
                throw new ValidationException(['current_password' => 'Current password is incorrect.']);
            }
            if (mb_strlen($new) < 8) {
                throw new ValidationException(['new_password' => 'New password must be at least 8 characters.']);
            }
            if ($new !== $confirm) {
                throw new ValidationException(['confirm_password' => 'Passwords do not match.']);
            }

            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);

            AuditLogger::log('user', (int) $user['id'], 'password.changed', 'user', (int) $user['id'], null, null, null, $request->ip());
            flash_success('Password updated successfully.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        }

        redirect('/profile');
    }
}
