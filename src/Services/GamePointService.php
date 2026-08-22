<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * The only code path allowed to mutate a game_point_wallets balance -
 * mirrors WalletService's discipline (row lock + append-only ledger inside
 * one DB transaction) but on entirely separate tables, so ordinary gameplay
 * (joining a round, winning, losing) never touches wallets/wallet_ledger.
 *
 * exchangeToWallet() and topUpFromWallet() below are the ONLY deliberate,
 * explicit bridges to the real financial wallet - both user-initiated,
 * never automatic, rate-limited by admin-configured settings, and atomic
 * across both ledgers.
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
                VALUES (?,?,"admin_grant",?,"0.00",?,"Starting B$ balance")')
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
        $validTypes = ['game_entry', 'game_cashout', 'game_loss', 'admin_grant', 'admin_adjustment', 'daily_bonus', 'exchange', 'topup'];
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
                throw new InsufficientBalanceException('Insufficient B$ for this action.');
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
            throw new RuntimeException('The daily B$ bonus is currently disabled.');
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
                throw new RuntimeException('You have already claimed your daily B$ bonus. Come back later.');
            }

            $result = self::applyLedgerEntry(
                $userId, $settings['daily_bonus_amount'], 'daily_bonus',
                null, null, 'Daily free B$ bonus'
            );

            return ['amount' => $settings['daily_bonus_amount']] + $result;
        });
    }

    /**
     * Converts a user-chosen B$ amount into real wallet balance at the
     * admin-configured rate. Atomic: the B$ debit and the wallet credit
     * happen in the same transaction (via Database::transaction()'s
     * reentrancy), so one can never happen without the other. This is the
     * only method in the whole game subsystem that calls WalletService.
     */
    public static function exchangeToWallet(int $userId, string $amount, string $ip): array
    {
        $pdo = Database::connection();
        $settings = GameSettingsService::current($pdo);

        if (!(bool) $settings['exchange_enabled']) {
            throw new RuntimeException('Exchanging B$ is currently unavailable.');
        }
        if (bccomp($amount, $settings['min_exchange_amount'], 2) < 0) {
            throw new RuntimeException('Minimum exchange amount is B$' . number_format((float) $settings['min_exchange_amount'], 2) . '.');
        }

        return Database::transaction(function (PDO $pdo) use ($userId, $amount, $ip, $settings) {
            // Lock the B$ wallet first - same reasoning as claimDailyBonus():
            // serializes concurrent exchange attempts from the same user
            // before the daily-cap COUNT below, which alone would lock
            // nothing if no prior rows exist yet.
            self::getOrCreateWallet($userId, $pdo, lock: true);

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(-amount), 0) FROM game_point_ledger
                WHERE user_id = ? AND type = 'exchange' AND created_at >= (NOW() - INTERVAL 24 HOUR)");
            $stmt->execute([$userId]);
            $exchangedToday = (string) $stmt->fetchColumn();
            if (bccomp(bcadd($exchangedToday, $amount, 2), $settings['max_exchange_per_day'], 2) > 0) {
                throw new RuntimeException('This would exceed your daily exchange limit of B$' . number_format((float) $settings['max_exchange_per_day'], 2) . '.');
            }

            $gpResult = self::applyLedgerEntry(
                $userId, bcmul($amount, '-1', 2), 'exchange',
                'wallet_exchange', null, 'Exchanged to wallet balance',
            );

            $usdAmount = bcmul($amount, (string) $settings['exchange_rate'], 2);

            $walletResult = WalletService::applyLedgerEntry(
                $userId, 'referral', $usdAmount, 'game_exchange',
                'game_point_wallet', null, 'Exchanged B$' . number_format((float) $amount, 2) . ' from Billions Flight',
                'system', null, null, $ip,
            );

            return [
                'bs_exchanged' => $amount,
                'usd_credited' => $usdAmount,
                'bs_balance' => $gpResult['resulting_balance'],
                'wallet_balance' => $walletResult['resulting_balance'],
            ];
        });
    }

    /**
     * The reverse bridge: converts real wallet USD into B$ balance so a
     * user can fund play, debiting the user's spendable wallet balance
     * (deposited, then cashback, then referral - same priority order as
     * any other purchase) and crediting B$ atomically in one transaction.
     * Same deliberate, rate-limited, user-initiated design as
     * exchangeToWallet() above, just in the opposite direction.
     */
    public static function topUpFromWallet(int $userId, string $usdAmount, string $ip): array
    {
        $pdo = Database::connection();
        $settings = GameSettingsService::current($pdo);

        if (!(bool) $settings['topup_enabled']) {
            throw new RuntimeException('Converting wallet balance to B$ is currently unavailable.');
        }
        if (bccomp($usdAmount, $settings['min_topup_amount'], 2) < 0) {
            throw new RuntimeException('Minimum top-up amount is ' . money($settings['min_topup_amount']) . '.');
        }

        return Database::transaction(function (PDO $pdo) use ($userId, $usdAmount, $ip, $settings) {
            $wallet = WalletService::getOrCreateWallet($userId, $pdo, lock: true);

            if ((bool) $wallet['is_frozen']) {
                throw new RuntimeException('Wallet is frozen. Contact support.');
            }

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(-amount), 0) FROM wallet_ledger
                WHERE user_id = ? AND type = 'game_topup' AND created_at >= (NOW() - INTERVAL 24 HOUR)");
            $stmt->execute([$userId]);
            $toppedUpToday = (string) $stmt->fetchColumn();
            if (bccomp(bcadd($toppedUpToday, $usdAmount, 2), $settings['max_topup_per_day'], 2) > 0) {
                throw new RuntimeException('This would exceed your daily top-up limit of ' . money($settings['max_topup_per_day']) . '.');
            }

            $remaining = $usdAmount;
            foreach (['deposited', 'cashback', 'referral'] as $bucket) {
                if (bccomp($remaining, '0', 2) <= 0) {
                    break;
                }
                $available = $wallet[$bucket . '_balance'];
                if (bccomp($available, '0', 2) <= 0) {
                    continue;
                }
                $take = bccomp($available, $remaining, 2) < 0 ? $available : $remaining;

                WalletService::applyLedgerEntry(
                    $userId, $bucket, bcmul($take, '-1', 2), 'game_topup',
                    'game_point_wallet', null, 'Converted to B$ for Billions Flight',
                    'user', $userId, null, $ip,
                );

                $remaining = bcsub($remaining, $take, 2);
            }

            if (bccomp($remaining, '0', 2) > 0) {
                throw new InsufficientBalanceException('Insufficient wallet balance for this top-up.');
            }

            $bsAmount = bcmul($usdAmount, (string) $settings['topup_rate'], 2);
            $gpResult = self::applyLedgerEntry(
                $userId, $bsAmount, 'topup',
                'wallet_topup', null, 'Converted ' . money($usdAmount) . ' from wallet',
            );

            return [
                'usd_spent' => $usdAmount,
                'bs_credited' => $bsAmount,
                'bs_balance' => $gpResult['resulting_balance'],
            ];
        });
    }
}
