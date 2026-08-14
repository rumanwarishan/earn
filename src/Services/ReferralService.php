<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\ValidationException;
use PDO;

final class ReferralService
{
    private const MAX_LEVEL_DEPTH = 10;

    public static function generateUniqueReferralCode(PDO $pdo): string
    {
        do {
            $code = random_code(8);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE referral_code = ?');
            $stmt->execute([$code]);
        } while ((int) $stmt->fetchColumn() > 0);

        return $code;
    }

    /**
     * Validate an invitation code (either an admin-issued invitation_codes.code
     * or an existing user's own referral_code). Returns ['referrer_user_id' => ?int,
     * 'invitation_code_id' => ?int]. Throws ValidationException if invalid.
     */
    public static function validateInvitationCode(string $code, PDO $pdo): array
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            throw new ValidationException(['invitation_code' => 'An invitation code is required to register.']);
        }

        $stmt = $pdo->prepare('SELECT id, uses_count, max_uses, is_active, expires_at FROM invitation_codes WHERE code = ?');
        $stmt->execute([$code]);
        $invite = $stmt->fetch();

        if ($invite) {
            if (!$invite['is_active']) {
                throw new ValidationException(['invitation_code' => 'This invitation code is no longer active.']);
            }
            if ($invite['expires_at'] && strtotime($invite['expires_at']) < time()) {
                throw new ValidationException(['invitation_code' => 'This invitation code has expired.']);
            }
            if ((int) $invite['uses_count'] >= (int) $invite['max_uses']) {
                throw new ValidationException(['invitation_code' => 'This invitation code has already been used.']);
            }
            return ['referrer_user_id' => null, 'invitation_code_id' => (int) $invite['id']];
        }

        $stmt = $pdo->prepare('SELECT id, status FROM users WHERE referral_code = ?');
        $stmt->execute([$code]);
        $referrer = $stmt->fetch();

        if (!$referrer || $referrer['status'] !== 'active') {
            throw new ValidationException(['invitation_code' => 'Invalid or expired invitation code.']);
        }

        return ['referrer_user_id' => (int) $referrer['id'], 'invitation_code_id' => null];
    }

    public static function consumeInvitationCode(?int $invitationCodeId, PDO $pdo): void
    {
        if ($invitationCodeId === null) {
            return;
        }
        // Row lock + conditional increment prevents a race from exceeding max_uses.
        $pdo->prepare('UPDATE invitation_codes SET uses_count = uses_count + 1 WHERE id = ? AND uses_count < max_uses')
            ->execute([$invitationCodeId]);
    }

    /**
     * Build the full ancestor chain (referral_relationships rows) for a newly
     * registered user based on their direct referrer's own chain.
     */
    public static function buildRelationships(int $newUserId, ?int $directReferrerId, PDO $pdo): void
    {
        if ($directReferrerId === null || $directReferrerId === $newUserId) {
            return;
        }

        $pdo->prepare('INSERT IGNORE INTO referral_relationships (referrer_user_id, referred_user_id, level) VALUES (?, ?, 1)')
            ->execute([$directReferrerId, $newUserId]);

        $stmt = $pdo->prepare('SELECT referrer_user_id, level FROM referral_relationships WHERE referred_user_id = ? AND level < ?');
        $stmt->execute([$directReferrerId, self::MAX_LEVEL_DEPTH]);

        foreach ($stmt->fetchAll() as $ancestor) {
            $level = (int) $ancestor['level'] + 1;
            if ($level > self::MAX_LEVEL_DEPTH || (int) $ancestor['referrer_user_id'] === $newUserId) {
                continue;
            }
            $pdo->prepare('INSERT IGNORE INTO referral_relationships (referrer_user_id, referred_user_id, level) VALUES (?, ?, ?)')
                ->execute([$ancestor['referrer_user_id'], $newUserId, $level]);
        }
    }

    /**
     * Idempotent: relies on the uniq_reward_dedupe key, so calling this twice
     * for the same user never double-credits.
     */
    public static function awardWelcomeBonus(int $userId, string $ip): bool
    {
        if (!(bool) setting('welcome_bonus_enabled', true)) {
            return false;
        }

        $amount = (string) setting('welcome_bonus_amount', '20');
        if (bccomp($amount, '0', 2) <= 0) {
            return false;
        }

        $pdo = Database::connection();

        try {
            return Database::transaction(function (PDO $pdo) use ($userId, $amount, $ip) {
                $pdo->prepare('INSERT INTO referral_rewards
                    (reward_uuid, referrer_user_id, referred_user_id, level, reward_type, amount, status)
                    VALUES (?,?,?,0,"welcome_bonus",?, "credited")')
                    ->execute([uuid4(), $userId, $userId, $amount]);

                WalletService::applyLedgerEntry(
                    $userId, 'referral', $amount, 'welcome_bonus',
                    'referral_reward', null, 'Welcome bonus for joining Billions Earn',
                    'system', null, null, $ip
                );

                return true;
            });
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                return false; // Already awarded - idempotent no-op.
            }
            throw $e;
        }
    }

    /**
     * Called when a deposit is approved. Awards the configured referral bonus
     * to each ancestor level for the referred user's FIRST qualifying deposit
     * only (enforced by the uniq_reward_dedupe key on referral_rewards).
     */
    public static function awardDepositBonuses(int $referredUserId, string $depositUsdAmount, int $depositId, string $ip): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT referrer_user_id, level FROM referral_relationships WHERE referred_user_id = ? ORDER BY level ASC');
        $stmt->execute([$referredUserId]);
        $ancestors = $stmt->fetchAll();

        if (!$ancestors) {
            return;
        }

        $levelStmt = $pdo->prepare('SELECT * FROM referral_settings WHERE level = ? AND is_active = 1');

        foreach ($ancestors as $ancestor) {
            $level = (int) $ancestor['level'];
            $levelStmt->execute([$level]);
            $config = $levelStmt->fetch();

            if (!$config) {
                continue;
            }
            if (bccomp($depositUsdAmount, $config['min_qualifying_deposit'], 2) < 0) {
                continue;
            }

            $bonus = $config['bonus_type'] === 'percentage'
                ? bcdiv(bcmul($depositUsdAmount, $config['bonus_amount'], 4), '100', 2)
                : (string) $config['bonus_amount'];

            if (bccomp($bonus, '0', 2) <= 0) {
                continue;
            }

            $referrerId = (int) $ancestor['referrer_user_id'];

            try {
                Database::transaction(function (PDO $pdo) use ($referrerId, $referredUserId, $level, $bonus, $depositId, $ip) {
                    $pdo->prepare('INSERT INTO referral_rewards
                        (reward_uuid, referrer_user_id, referred_user_id, level, reward_type, amount, status,
                         trigger_reference_type, trigger_reference_id)
                        VALUES (?,?,?,?,"deposit_bonus",?, "credited", "deposit", ?)')
                        ->execute([uuid4(), $referrerId, $referredUserId, $level, $bonus, $depositId]);

                    WalletService::applyLedgerEntry(
                        $referrerId, 'referral', $bonus, 'referral_credit',
                        'deposit', $depositId, "Level {$level} referral bonus for a referred member's qualifying deposit",
                        'system', null, null, $ip
                    );
                });
            } catch (\PDOException $e) {
                if ((int) $e->getCode() !== 23000) {
                    throw $e; // Anything other than a dedupe-key hit is a real error.
                }
                // Already credited for this referrer/referred/level - skip silently (idempotent).
            }
        }
    }
}
