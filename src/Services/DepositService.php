<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\ValidationException;
use PDO;
use RuntimeException;

final class DepositService
{
    public static function submit(int $userId, string $btcAmountClaimed, string $txid, string $ip): array
    {
        $btcAmountClaimed = trim($btcAmountClaimed);
        $txid = trim($txid);

        $errors = [];
        if (!preg_match('/^\d+(\.\d{1,8})?$/', $btcAmountClaimed) || bccomp($btcAmountClaimed, '0', 8) <= 0) {
            $errors['btc_amount_claimed'] = 'Enter a valid BTC amount (up to 8 decimal places).';
        }
        if ($txid === '' || !preg_match('/^[a-fA-F0-9]{16,128}$/', $txid)) {
            $errors['txid'] = 'Enter a valid transaction hash (TXID).';
        }
        $address = (string) setting('btc_deposit_address', '');
        if ($address === '') {
            $errors['address'] = 'Deposits are temporarily unavailable. Please contact support.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM deposits WHERE txid = ?');
        $stmt->execute([$txid]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidationException(['txid' => 'This transaction hash has already been submitted.']);
        }

        $uuid = uuid4();
        $pdo->prepare('INSERT INTO deposits (deposit_uuid, user_id, btc_address_shown, btc_amount_claimed, txid, ip_address)
            VALUES (?,?,?,?,?,?)')
            ->execute([$uuid, $userId, $address, $btcAmountClaimed, $txid, $ip]);

        $depositId = (int) $pdo->lastInsertId();

        NotificationService::notify($userId, 'deposit_submitted', 'Deposit submitted',
            'Your deposit is pending admin verification. We will notify you once it is reviewed.');

        AuditLogger::log('user', $userId, 'deposit.submitted', 'deposit', $depositId, null, ['txid' => $txid, 'btc_amount' => $btcAmountClaimed], null, $ip);

        return ['id' => $depositId, 'uuid' => $uuid];
    }

    public static function approve(int $depositId, int $adminId, string $btcUsdRate, string $ip, ?string $notes = null): array
    {
        if (bccomp($btcUsdRate, '0', 2) <= 0) {
            throw new ValidationException(['btc_usd_rate' => 'Enter a valid BTC/USD rate.']);
        }

        return Database::transaction(function (PDO $pdo) use ($depositId, $adminId, $btcUsdRate, $ip, $notes) {
            $stmt = $pdo->prepare('SELECT * FROM deposits WHERE id = ? FOR UPDATE');
            $stmt->execute([$depositId]);
            $deposit = $stmt->fetch();

            if (!$deposit) {
                throw new RuntimeException('Deposit not found.');
            }
            if ($deposit['status'] !== 'pending') {
                // Idempotent guard: a deposit can never be approved twice.
                throw new RuntimeException('This deposit has already been reviewed.');
            }

            $usdAmount = bcmul($deposit['btc_amount_claimed'], $btcUsdRate, 2);

            $pdo->prepare('UPDATE deposits SET status = "approved", btc_usd_rate = ?, rate_source = "manual",
                rate_captured_at = NOW(), usd_amount_credited = ?, admin_id = ?, admin_notes = ?, reviewed_at = NOW()
                WHERE id = ?')
                ->execute([$btcUsdRate, $usdAmount, $adminId, $notes, $depositId]);

            $pdo->prepare('INSERT INTO deposit_reviews (deposit_id, admin_id, action, notes) VALUES (?,?,"approved",?)')
                ->execute([$depositId, $adminId, $notes]);

            WalletService::applyLedgerEntry(
                (int) $deposit['user_id'], 'deposited', $usdAmount, 'deposit_credit',
                'deposit', $depositId, 'BTC deposit approved and credited',
                'admin', $adminId, null, $ip
            );

            ReferralService::awardDepositBonuses((int) $deposit['user_id'], $usdAmount, $depositId, $ip);
            MembershipService::refresh((int) $deposit['user_id']);

            NotificationService::notify((int) $deposit['user_id'], 'deposit_approved', 'Deposit approved',
                'Your deposit of ' . btc_amount($deposit['btc_amount_claimed']) . ' was approved and ' . money($usdAmount) . ' was credited to your wallet.');

            AuditLogger::log('admin', $adminId, 'deposit.approved', 'deposit', $depositId,
                ['status' => 'pending'], ['status' => 'approved', 'usd_amount' => $usdAmount], $notes, $ip);

            return ['usd_amount' => $usdAmount];
        });
    }

    public static function reject(int $depositId, int $adminId, string $reason, string $ip): void
    {
        if (trim($reason) === '') {
            throw new ValidationException(['reason' => 'A rejection reason is required.']);
        }

        Database::transaction(function (PDO $pdo) use ($depositId, $adminId, $reason, $ip) {
            $stmt = $pdo->prepare('SELECT * FROM deposits WHERE id = ? FOR UPDATE');
            $stmt->execute([$depositId]);
            $deposit = $stmt->fetch();

            if (!$deposit) {
                throw new RuntimeException('Deposit not found.');
            }
            if ($deposit['status'] !== 'pending') {
                throw new RuntimeException('This deposit has already been reviewed.');
            }

            $pdo->prepare('UPDATE deposits SET status = "rejected", rejection_reason = ?, admin_id = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$reason, $adminId, $depositId]);

            $pdo->prepare('INSERT INTO deposit_reviews (deposit_id, admin_id, action, notes) VALUES (?,?,"rejected",?)')
                ->execute([$depositId, $adminId, $reason]);

            NotificationService::notify((int) $deposit['user_id'], 'deposit_rejected', 'Deposit rejected',
                'Your deposit submission was rejected: ' . $reason);

            AuditLogger::log('admin', $adminId, 'deposit.rejected', 'deposit', $depositId,
                ['status' => 'pending'], ['status' => 'rejected'], $reason, $ip);
        });
    }
}
