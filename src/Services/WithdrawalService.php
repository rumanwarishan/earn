<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\ValidationException;
use PDO;
use RuntimeException;

final class WithdrawalService
{
    public static function request(int $userId, string $amount, string $btcAddress, string $ip): array
    {
        $amount = trim($amount);
        $btcAddress = trim($btcAddress);
        $min = (string) setting('min_withdrawal_usd', '1000');
        $max = (string) setting('max_withdrawal_usd', '50000');

        $errors = [];
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            $errors['amount'] = 'Enter a valid amount.';
        } elseif (bccomp($amount, $min, 2) < 0) {
            $errors['amount'] = 'Minimum withdrawal is ' . money($min) . '.';
        } elseif (bccomp($amount, $max, 2) > 0) {
            $errors['amount'] = 'Maximum withdrawal is ' . money($max) . '.';
        }
        if (!preg_match('/^(bc1[a-z0-9]{25,59}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/', $btcAddress)) {
            $errors['btc_address'] = 'Enter a valid BTC address.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status IN ("pending","approved","processing")');
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidationException(['withdrawal' => 'You already have a withdrawal in progress. Please wait for it to be processed.']);
        }

        return Database::transaction(function (PDO $pdo) use ($userId, $amount, $btcAddress, $ip) {
            $uuid = uuid4();
            $pdo->prepare('INSERT INTO withdrawals (withdrawal_uuid, user_id, amount_usd, destination_btc_address, ip_address)
                VALUES (?,?,?,?,?)')
                ->execute([$uuid, $userId, $amount, $btcAddress, $ip]);

            $withdrawalId = (int) $pdo->lastInsertId();

            // Debits the spendable balances and moves the amount into the reserved
            // bucket. Throws InsufficientBalanceException (which rolls back the
            // whole transaction, including the INSERT above) if funds are short.
            WalletService::holdForWithdrawal($userId, $amount, $withdrawalId, $ip);

            NotificationService::notify($userId, 'withdrawal_submitted', 'Withdrawal requested',
                'Your withdrawal request for ' . money($amount) . ' is pending review.');

            AuditLogger::log('user', $userId, 'withdrawal.requested', 'withdrawal', $withdrawalId, null,
                ['amount' => $amount, 'btc_address' => $btcAddress], null, $ip);

            return ['id' => $withdrawalId, 'uuid' => $uuid];
        });
    }

    private static function lockWithdrawal(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT * FROM withdrawals WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $withdrawal = $stmt->fetch();
        if (!$withdrawal) {
            throw new RuntimeException('Withdrawal not found.');
        }
        return $withdrawal;
    }

    private static function holdBreakdown(int $withdrawalId): array
    {
        $stmt = Database::connection()->prepare('SELECT balance_field, amount FROM wallet_ledger
            WHERE reference_type = "withdrawal" AND reference_id = ? AND type = "withdrawal_hold" AND balance_field != "reserved"');
        $stmt->execute([$withdrawalId]);
        $breakdown = [];
        foreach ($stmt->fetchAll() as $row) {
            $breakdown[$row['balance_field']] = bcmul($row['amount'], '-1', 2);
        }
        return $breakdown;
    }

    public static function approve(int $withdrawalId, int $adminId, string $ip, ?string $notes = null): void
    {
        Database::transaction(function (PDO $pdo) use ($withdrawalId, $adminId, $ip, $notes) {
            $w = self::lockWithdrawal($pdo, $withdrawalId);
            if ($w['status'] !== 'pending') {
                throw new RuntimeException('Only pending withdrawals can be approved.');
            }
            $pdo->prepare('UPDATE withdrawals SET status = "approved", admin_id = ?, admin_notes = ? WHERE id = ?')
                ->execute([$adminId, $notes, $withdrawalId]);
            $pdo->prepare('INSERT INTO withdrawal_reviews (withdrawal_id, admin_id, action, notes) VALUES (?,?,"approved",?)')
                ->execute([$withdrawalId, $adminId, $notes]);

            NotificationService::notify((int) $w['user_id'], 'withdrawal_approved', 'Withdrawal approved',
                'Your withdrawal of ' . money($w['amount_usd']) . ' was approved and is being processed.');
            AuditLogger::log('admin', $adminId, 'withdrawal.approved', 'withdrawal', $withdrawalId, ['status' => 'pending'], ['status' => 'approved'], $notes, $ip);
        });
    }

    public static function reject(int $withdrawalId, int $adminId, string $reason, string $ip): void
    {
        if (trim($reason) === '') {
            throw new ValidationException(['reason' => 'A rejection reason is required.']);
        }

        Database::transaction(function (PDO $pdo) use ($withdrawalId, $adminId, $reason, $ip) {
            $w = self::lockWithdrawal($pdo, $withdrawalId);
            if (!in_array($w['status'], ['pending', 'approved'], true)) {
                throw new RuntimeException('This withdrawal can no longer be rejected.');
            }

            $breakdown = self::holdBreakdown($withdrawalId);
            WalletService::releaseWithdrawalHold((int) $w['user_id'], $breakdown, $w['amount_usd'], $withdrawalId, $reason, $adminId);

            $pdo->prepare('UPDATE withdrawals SET status = "rejected", rejection_reason = ?, admin_id = ? WHERE id = ?')
                ->execute([$reason, $adminId, $withdrawalId]);
            $pdo->prepare('INSERT INTO withdrawal_reviews (withdrawal_id, admin_id, action, notes) VALUES (?,?,"rejected",?)')
                ->execute([$withdrawalId, $adminId, $reason]);

            NotificationService::notify((int) $w['user_id'], 'withdrawal_rejected', 'Withdrawal rejected',
                'Your withdrawal request was rejected: ' . $reason . '. The funds have been returned to your wallet.');
            AuditLogger::log('admin', $adminId, 'withdrawal.rejected', 'withdrawal', $withdrawalId, ['status' => $w['status']], ['status' => 'rejected'], $reason, $ip);
        });
    }

    public static function markProcessing(int $withdrawalId, int $adminId, string $ip, ?string $notes = null): void
    {
        Database::transaction(function (PDO $pdo) use ($withdrawalId, $adminId, $ip, $notes) {
            $w = self::lockWithdrawal($pdo, $withdrawalId);
            if ($w['status'] !== 'approved') {
                throw new RuntimeException('Only approved withdrawals can move to processing.');
            }
            $pdo->prepare('UPDATE withdrawals SET status = "processing", admin_id = ?, admin_notes = ? WHERE id = ?')
                ->execute([$adminId, $notes, $withdrawalId]);
            $pdo->prepare('INSERT INTO withdrawal_reviews (withdrawal_id, admin_id, action, notes) VALUES (?,?,"processing",?)')
                ->execute([$withdrawalId, $adminId, $notes]);
            AuditLogger::log('admin', $adminId, 'withdrawal.processing', 'withdrawal', $withdrawalId, ['status' => 'approved'], ['status' => 'processing'], $notes, $ip);
        });
    }

    public static function markPaid(int $withdrawalId, int $adminId, string $btcAmountPaid, ?string $btcRateUsed, ?string $payoutTxid, string $ip, ?string $notes = null): void
    {
        Database::transaction(function (PDO $pdo) use ($withdrawalId, $adminId, $btcAmountPaid, $btcRateUsed, $payoutTxid, $ip, $notes) {
            $w = self::lockWithdrawal($pdo, $withdrawalId);
            if (!in_array($w['status'], ['approved', 'processing'], true)) {
                throw new RuntimeException('This withdrawal is not ready to be marked paid.');
            }

            $pdo->prepare('UPDATE withdrawals SET status = "paid", btc_amount_paid = ?, btc_rate_used = ?, payout_txid = ?,
                admin_id = ?, admin_notes = ?, paid_at = NOW() WHERE id = ?')
                ->execute([$btcAmountPaid, $btcRateUsed, $payoutTxid, $adminId, $notes, $withdrawalId]);

            WalletService::finalizeWithdrawal((int) $w['user_id'], $w['amount_usd'], $withdrawalId, $adminId);

            $pdo->prepare('INSERT INTO withdrawal_reviews (withdrawal_id, admin_id, action, notes) VALUES (?,?,"paid",?)')
                ->execute([$withdrawalId, $adminId, $notes]);

            NotificationService::notify((int) $w['user_id'], 'withdrawal_paid', 'Withdrawal paid',
                'Your withdrawal of ' . money($w['amount_usd']) . ' has been paid out.');
            AuditLogger::log('admin', $adminId, 'withdrawal.paid', 'withdrawal', $withdrawalId,
                ['status' => $w['status']], ['status' => 'paid', 'btc_amount_paid' => $btcAmountPaid], $notes, $ip);
        });
    }

    public static function markCompleted(int $withdrawalId, int $adminId, string $ip): void
    {
        Database::transaction(function (PDO $pdo) use ($withdrawalId, $adminId, $ip) {
            $w = self::lockWithdrawal($pdo, $withdrawalId);
            if ($w['status'] !== 'paid') {
                throw new RuntimeException('Only paid withdrawals can be marked completed.');
            }
            $pdo->prepare('UPDATE withdrawals SET status = "completed" WHERE id = ?')->execute([$withdrawalId]);
            $pdo->prepare('INSERT INTO withdrawal_reviews (withdrawal_id, admin_id, action) VALUES (?,?,"completed")')
                ->execute([$withdrawalId, $adminId]);
            AuditLogger::log('admin', $adminId, 'withdrawal.completed', 'withdrawal', $withdrawalId, ['status' => 'paid'], ['status' => 'completed'], null, $ip);
        });
    }

    public static function cancel(int $withdrawalId, int $userId, string $ip): void
    {
        Database::transaction(function (PDO $pdo) use ($withdrawalId, $userId, $ip) {
            $w = self::lockWithdrawal($pdo, $withdrawalId);
            if ((int) $w['user_id'] !== $userId) {
                throw new RuntimeException('Not authorized.');
            }
            if ($w['status'] !== 'pending') {
                throw new RuntimeException('Only pending withdrawals can be cancelled.');
            }

            $breakdown = self::holdBreakdown($withdrawalId);
            WalletService::releaseWithdrawalHold($userId, $breakdown, $w['amount_usd'], $withdrawalId, 'Cancelled by user', null);

            $pdo->prepare('UPDATE withdrawals SET status = "cancelled" WHERE id = ?')->execute([$withdrawalId]);
            AuditLogger::log('user', $userId, 'withdrawal.cancelled', 'withdrawal', $withdrawalId, ['status' => 'pending'], ['status' => 'cancelled'], null, $ip);
        });
    }
}
