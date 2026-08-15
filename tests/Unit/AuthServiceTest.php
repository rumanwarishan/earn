<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\AuthService;
use App\Support\ValidationException;
use Tests\TestCase;
use Tests\TestSeed;

final class AuthServiceTest extends TestCase
{
    public function test_registration_requires_valid_invitation_code(): void
    {
        $this->expectException(ValidationException::class);
        AuthService::register([
            'full_name' => 'New Person',
            'email' => 'newperson-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => 'password123',
            'confirm_password' => 'password123',
            'invitation_code' => 'NOT-A-REAL-CODE',
        ], '127.0.0.1');
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $existing = TestSeed::createUser('dup-email');

        $this->expectException(ValidationException::class);
        AuthService::register([
            'full_name' => 'Duplicate',
            'email' => $existing['email'],
            'password' => 'password123',
            'confirm_password' => 'password123',
            'invitation_code' => 'TESTROOT1',
        ], '127.0.0.1');
    }

    public function test_registration_rejects_mismatched_passwords(): void
    {
        $this->expectException(ValidationException::class);
        AuthService::register([
            'full_name' => 'Mismatch',
            'email' => 'mismatch-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => 'password123',
            'confirm_password' => 'different456',
            'invitation_code' => 'TESTROOT1',
        ], '127.0.0.1');
    }

    public function test_successful_registration_creates_unique_referral_code_and_wallet(): void
    {
        $user = AuthService::register([
            'full_name' => 'Valid Person',
            'email' => 'validperson-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => 'password123',
            'confirm_password' => 'password123',
            'invitation_code' => 'TESTROOT1',
        ], '127.0.0.1');

        $this->assertNotEmpty($user['referral_code']);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM wallets WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = TestSeed::createUser('login-wrongpass');

        $this->expectException(ValidationException::class);
        AuthService::login($user['email'], 'totally-wrong-password', '127.0.0.1', 'phpunit');
    }

    public function test_login_blocked_until_email_verified(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('login-unverified');
        $pdo->prepare('UPDATE users SET email_verified_at = NULL WHERE id = ?')->execute([$user['id']]);

        $this->expectException(ValidationException::class);
        AuthService::login($user['email'], 'password123', '127.0.0.1', 'phpunit');
    }

    public function test_password_reset_token_is_single_use(): void
    {
        $user = TestSeed::createUser('reset-single-use');
        $pdo = Database::connection();

        $token = bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)')
            ->execute([$user['id'], hash('sha256', $token)]);

        $first = AuthService::resetPassword((int) $user['id'], $token, 'newpassword123', '127.0.0.1');
        $second = AuthService::resetPassword((int) $user['id'], $token, 'anotherpassword456', '127.0.0.1');

        $this->assertTrue($first);
        $this->assertFalse($second, 'A consumed reset token must not work twice');
    }
}
