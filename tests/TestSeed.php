<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;

/** Idempotent baseline config so tests don't depend on run order or a fresh DB. */
final class TestSeed
{
    public static function ensureBaseline(): void
    {
        $pdo = Database::connection();

        self::setting($pdo, 'welcome_bonus_amount', '20', 'decimal');
        self::setting($pdo, 'welcome_bonus_enabled', '1', 'bool');
        self::setting($pdo, 'min_deposit_usd', '50', 'decimal');
        self::setting($pdo, 'min_withdrawal_usd', '1000', 'decimal');
        self::setting($pdo, 'max_withdrawal_usd', '50000', 'decimal');
        self::setting($pdo, 'registration_enabled', '1', 'bool');
        self::setting($pdo, 'btc_deposit_address', 'bc1qtestaddressxxxxxxxxxxxxxxxxxxxxxxxxxx', 'string');

        if ((int) $pdo->query('SELECT COUNT(*) FROM referral_settings')->fetchColumn() === 0) {
            $pdo->exec('INSERT INTO referral_settings (level, bonus_type, bonus_amount, min_qualifying_deposit, is_active) VALUES (1, "fixed", 20.00, 50.00, 1)');
            $pdo->exec('INSERT INTO referral_settings (level, bonus_type, bonus_amount, min_qualifying_deposit, is_active) VALUES (2, "fixed", 5.00, 50.00, 0)');
        }

        if ((int) $pdo->query('SELECT COUNT(*) FROM membership_levels')->fetchColumn() === 0) {
            $ins = $pdo->prepare('INSERT INTO membership_levels (name, slug, icon, color, min_total_deposited, min_total_purchased, cashback_multiplier, referral_bonus_multiplier, sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
            $ins->execute(['Bronze', 'bronze', 'bronze', '#cd7f32', 0, 0, 1, 1, 1]);
            $ins->execute(['Silver', 'silver', 'silver', '#c0c0c0', 500, 250, 1.1, 1.1, 2]);
        }

        if ((int) $pdo->query('SELECT COUNT(*) FROM invitation_codes')->fetchColumn() === 0) {
            $pdo->exec('INSERT INTO invitation_codes (code, max_uses, uses_count, is_active) VALUES ("TESTROOT1", 999999, 0, 1)');
        }
    }

    private static function setting(\PDO $pdo, string $key, string $value, string $type): void
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM site_settings WHERE `key` = ?');
        $stmt->execute([$key]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }
        $pdo->prepare('INSERT INTO site_settings (`key`, `value`, `type`) VALUES (?,?,?)')->execute([$key, $value, $type]);
    }

    public static function createUser(string $emailPrefix, ?string $referredByCode = null): array
    {
        $pdo = Database::connection();
        $email = $emailPrefix . '-' . bin2hex(random_bytes(4)) . '@example.test';
        $code = strtoupper(bin2hex(random_bytes(4)));

        $referrerId = null;
        if ($referredByCode) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE referral_code = ?');
            $stmt->execute([$referredByCode]);
            $referrerId = $stmt->fetchColumn() ?: null;
        }

        $pdo->prepare('INSERT INTO users (uuid, full_name, email, password_hash, referral_code, referred_by_user_id, email_verified_at)
            VALUES (?,?,?,?,?,?,NOW())')
            ->execute([bin2hex(random_bytes(16)), 'Test User', $email, password_hash('password123', PASSWORD_BCRYPT), $code, $referrerId]);

        $id = (int) $pdo->lastInsertId();

        if ($referrerId) {
            \App\Services\ReferralService::buildRelationships($id, (int) $referrerId, $pdo);
        }

        \App\Services\WalletService::getOrCreateWallet($id, $pdo);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
