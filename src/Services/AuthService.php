<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Support\ValidationException;
use PDO;

final class AuthService
{
    public static function register(array $input, string $ip): array
    {
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['confirm_password'] ?? '');
        $inviteCode = (string) ($input['invitation_code'] ?? '');

        $errors = [];
        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
            $errors['full_name'] = 'Please enter your full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        if (!(bool) setting('registration_enabled', true)) {
            $errors['registration'] = 'Registration is temporarily closed.';
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidationException(['email' => 'An account with this email already exists.']);
        }

        // Invitation code is validated and consumed server-side inside the same
        // transaction as user creation - never trust the client's validation pass.
        $invite = ReferralService::validateInvitationCode($inviteCode, $pdo);

        $user = Database::transaction(function (PDO $pdo) use ($fullName, $email, $password, $invite) {
            $referralCode = ReferralService::generateUniqueReferralCode($pdo);
            $uuid = uuid4();
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $pdo->prepare('INSERT INTO users (uuid, full_name, email, password_hash, referral_code, referred_by_user_id)
                VALUES (?,?,?,?,?,?)')
                ->execute([$uuid, $fullName, $email, $hash, $referralCode, $invite['referrer_user_id']]);

            $userId = (int) $pdo->lastInsertId();

            ReferralService::consumeInvitationCode($invite['invitation_code_id'], $pdo);
            ReferralService::buildRelationships($userId, $invite['referrer_user_id'], $pdo);
            WalletService::getOrCreateWallet($userId, $pdo);

            $defaultLevel = $pdo->query('SELECT id FROM membership_levels WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1')->fetchColumn();
            if ($defaultLevel) {
                $pdo->prepare('UPDATE users SET membership_level_id = ? WHERE id = ?')->execute([$defaultLevel, $userId]);
            }

            $stmt = $pdo->prepare('SELECT id, uuid, full_name, email, referral_code FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            return $stmt->fetch();
        });

        self::issueEmailVerification((int) $user['id'], $email, $fullName, $ip);
        ReferralService::awardWelcomeBonus((int) $user['id'], $ip);

        AuditLogger::log('user', (int) $user['id'], 'user.registered', 'user', (int) $user['id'], null, null, null, $ip);

        return $user;
    }

    public static function issueEmailVerification(int $userId, string $email, string $fullName, string $ip): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        Database::connection()
            ->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 24 HOUR)')
            ->execute([$userId, $hash]);

        $link = app_url('/verify-email?token=' . $token . '&uid=' . $userId);
        $body = "<p>Hi {$fullName},</p><p>Welcome to Billions Earn. Please confirm your email address to activate your account:</p>"
            . "<p><a href=\"{$link}\" style=\"color:#3ecbff;\">Verify my email</a></p>"
            . "<p>This link expires in 24 hours.</p>";

        MailService::send($email, $fullName, 'Verify your Billions Earn account', MailService::layout('Verify your email', $body));
    }

    public static function verifyEmail(int $userId, string $token): bool
    {
        $hash = hash('sha256', $token);
        $pdo = Database::connection();

        return Database::transaction(function (PDO $pdo) use ($userId, $hash) {
            $stmt = $pdo->prepare('SELECT id FROM email_verifications
                WHERE user_id = ? AND token_hash = ? AND consumed_at IS NULL AND expires_at > NOW() FOR UPDATE');
            $stmt->execute([$userId, $hash]);
            $row = $stmt->fetch();

            if (!$row) {
                return false;
            }

            $pdo->prepare('UPDATE email_verifications SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);
            $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL')->execute([$userId]);

            return true;
        });
    }

    public static function login(string $email, string $password, string $ip, string $userAgent): array
    {
        $email = strtolower(trim($email));

        if (RateLimiter::tooManyLoginAttempts($email, $ip)) {
            throw new ValidationException(['login' => 'Too many login attempts. Please try again in a few minutes.']);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            RateLimiter::recordAttempt($email, $ip, false);
            AuditLogger::log('user', $user['id'] ?? null, 'login.failed', 'user', $user['id'] ?? null, null, null, null, $ip, $userAgent);
            throw new ValidationException(['login' => 'Invalid email or password.']);
        }

        if ($user['status'] !== 'active') {
            RateLimiter::recordAttempt($email, $ip, false);
            throw new ValidationException(['login' => 'This account has been suspended. Contact support.']);
        }

        if (!$user['email_verified_at']) {
            throw new ValidationException(['login' => 'Please verify your email before logging in.', 'unverified_user_id' => (string) $user['id']]);
        }

        RateLimiter::recordAttempt($email, $ip, true);

        $pdo->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')->execute([$ip, $user['id']]);

        Session::regenerate();
        Session::put('user', [
            'id' => (int) $user['id'],
            'uuid' => $user['uuid'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'referral_code' => $user['referral_code'],
        ]);

        AuditLogger::log('user', (int) $user['id'], 'login.success', 'user', (int) $user['id'], null, null, null, $ip, $userAgent);

        return $user;
    }

    public static function requestPasswordReset(string $email, string $ip): void
    {
        $email = strtolower(trim($email));
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always behave the same whether or not the account exists, to avoid
        // leaking which emails are registered.
        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)')
            ->execute([$user['id'], $hash]);

        $link = app_url('/reset-password?token=' . $token . '&uid=' . $user['id']);
        $body = "<p>Hi {$user['full_name']},</p><p>We received a request to reset your password. This link expires in 1 hour:</p>"
            . "<p><a href=\"{$link}\" style=\"color:#3ecbff;\">Reset my password</a></p>"
            . "<p>If you didn't request this, you can ignore this email - your password will not change.</p>";

        MailService::send($email, $user['full_name'], 'Reset your Billions Earn password', MailService::layout('Reset your password', $body));

        AuditLogger::log('user', (int) $user['id'], 'password_reset.requested', 'user', (int) $user['id'], null, null, null, $ip);
    }

    public static function resetPassword(int $userId, string $token, string $newPassword, string $ip): bool
    {
        if (mb_strlen($newPassword) < 8) {
            throw new ValidationException(['password' => 'Password must be at least 8 characters.']);
        }

        $hash = hash('sha256', $token);

        return Database::transaction(function (PDO $pdo) use ($userId, $hash, $newPassword, $ip) {
            $stmt = $pdo->prepare('SELECT id FROM password_resets
                WHERE user_id = ? AND token_hash = ? AND consumed_at IS NULL AND expires_at > NOW() FOR UPDATE');
            $stmt->execute([$userId, $hash]);
            $row = $stmt->fetch();

            if (!$row) {
                return false;
            }

            $pdo->prepare('UPDATE password_resets SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_BCRYPT), $userId]);

            AuditLogger::log('user', $userId, 'password_reset.completed', 'user', $userId, null, null, null, $ip);

            return true;
        });
    }

    public static function logout(): void
    {
        $user = Session::get('user');
        if ($user) {
            AuditLogger::log('user', $user['id'], 'logout', 'user', $user['id']);
        }
        Session::destroy();
    }
}
