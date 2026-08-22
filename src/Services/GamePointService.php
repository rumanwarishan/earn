<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * The only code path allowed to mutate a game_point_wallets balance -
 * mirrors WalletService's discipline (row lock + append-only ledger inside
 * one DB transaction) but on entirely separate tables. This NEVER touches
 * wallets/wallet_ledger (the real financial wallet) in any way. Game
 * Points have no cash value and cannot be deposited, withdrawn, or
 * converted to/from USD/BTC/USDT - there is intentionally no method here
 * that bridges to WalletService.
 */
final class GamePointService
{
    public static function getOrCreateWallet(int $userId, PDO $pdo, bool $lock = false): array
    {
        $sql = 'SELECT * FROM game_point_wallets WHERE user_id = ?' . ($lock ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();

        if ($wallet) {
            return $wallet;
        }

        $starting = GameSettingsService::current($pdo)['starting_balance'];

        $pdo->prepare('INSERT INTO game_point_wallets (user_id, balance) VALUES (?, ?)')->execute([$userId, $starting]);
        $walletId = (int) $pdo->lastInsertId();

        if (bccomp($starting, '0', 2) > 0) {
            $pdo->prepare('INSERT INTO game_point_ledger (user_id, wallet_id, type, amount, balance_before, balance_after, description)
                VALUES (?,?,"admin_grant",?,"0.00",?,"Starting Game Points balance")')
                ->execute([$userId, $walletId, $starting, $starting]);
        }

        $stmt = $pdo->prepare('SELECT * FROM game_point_wallets WHERE user_id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public static function balance(int $userId): string
    {
        $pdo = Database::connection();
        return self::getOrCreateWallet($userId, $pdo)['balance'];
    }

    /**
     * Apply one signed ledger entry to a user's Game Point balance.
     * $amount must be a bcmath-safe decimal string; positive credits, negative debits.
     */
    public static function applyLedgerEntry(
        int $userId,
        string $amount,
        string $type,
        ?string $referenceType,
        ?int $referenceId,
        string $description,
        ?int $adminId = null,
        ?string $adminReason = null,
        bool $allowNegative = false,
    ): array {
        $validTypes = ['game_entry', 'game_cashout', 'game_loss', 'admin_grant', 'admin_adjustment', 'daily_bonus'];
        if (!in_array($type, $validTypes, true)) {
            throw new RuntimeException("Invalid game point ledger type: {$type}");
        }

        return Database::transaction(function (PDO $pdo) use (
            $userId, $amount, $type, $referenceType, $referenceId, $description, $adminId, $adminReason, $allowNegative
        ) {
            $wallet = self::getOrCreateWallet($userId, $pdo, lock: true);

            $previous = $wallet['balance'];
            $resulting = bcadd($previous, $amount, 2);

            if (!$allowNegative && bccomp($resulting, '0', 2) < 0) {
                throw new InsufficientBalanceException('Insufficient Game Points for this action.');
            }

            $pdo->prepare('UPDATE game_point_wallets SET balance = ? WHERE id = ?')
                ->execute([$resulting, $wallet['id']]);

            $pdo->prepare('INSERT INTO game_point_ledger
                (user_id, wallet_id, type, amount, balance_before, balance_after, reference_type, reference_id, description, admin_id, admin_reason)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $userId, $wallet['id'], $type, $amount, $previous, $resulting,
                    $referenceType, $referenceId, $description, $adminId, $adminReason,
                ]);

            return ['previous_balance' => $previous, 'resulting_balance' => $resulting];
        });
    }

    public static function history(int $userId, int $limit = 30): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM game_point_ledger WHERE user_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function claimDailyBonus(int $userId): array
    {
        $settings = GameSettingsService::current(Database::connection());
        if (!$settings['daily_bonus_enabled']) {
            throw new RuntimeException('The daily Game Points bonus is currently disabled.');
        }

        return Database::transaction(function (PDO $pdo) use ($userId, $settings) {
            // Lock the wallet row FIRST so concurrent claims from the same
            // user serialize on this - a COUNT(*) FOR UPDATE alone would
            // lock nothing when zero prior rows match, letting two
            // simultaneous requests both pass the check before either
            // inserts its ledger row.
            self::getOrCreateWallet($userId, $pdo, lock: true);

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM game_point_ledger
                WHERE user_id = ? AND type = 'daily_bonus' AND created_at >= (NOW() - INTERVAL 24 HOUR)");
            $stmt->execute([$userId]);
            if ((int) $stmt->fetchColumn() >= (int) $settings['daily_bonus_max_per_day']) {
                throw new RuntimeException('You have already claimed your daily Game Points bonus. Come back later.');
            }

            $result = self::applyLedgerEntry(
                $userId, $settings['daily_bonus_amount'], 'daily_bonus',
                null, null, 'Daily free Game Points bonus'
            );

            return ['amount' => $settings['daily_bonus_amount']] + $result;
        });
    }
}
