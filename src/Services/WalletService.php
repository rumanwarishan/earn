<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * The only code path allowed to mutate wallet balances. Every mutation is
 * paired with an append-only wallet_ledger row inside a DB transaction with
 * a row lock on the wallet, so balances can always be reconstructed from
 * (and verified against) the ledger.
 */
final class WalletService
{
    private const BALANCE_COLUMNS = ['deposited', 'cashback', 'referral', 'pending_cashback', 'reserved'];

    public static function getOrCreateWallet(int $userId, PDO $pdo, bool $lock = false): array
    {
        $sql = 'SELECT * FROM wallets WHERE user_id = ?' . ($lock ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();

        if ($wallet) {
            return $wallet;
        }

        $pdo->prepare('INSERT INTO wallets (user_id) VALUES (?)')->execute([$userId]);

        $stmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    /**
     * Apply a single signed ledger entry to one balance column of a user's wallet.
     * $amount must be a bcmath-safe decimal string; positive credits, negative debits.
     */
    public static function applyLedgerEntry(
        int $userId,
        string $balanceField,
        string $amount,
        string $type,
        ?string $referenceType,
        ?int $referenceId,
        string $description,
        string $createdByType = 'system',
        ?int $createdById = null,
        ?string $adminReason = null,
        ?string $ip = null,
        array $metadata = [],
        bool $allowNegative = false,
    ): array {
        if (!in_array($balanceField, self::BALANCE_COLUMNS, true)) {
            throw new RuntimeException("Invalid balance field: {$balanceField}");
        }

        return Database::transaction(function (PDO $pdo) use (
            $userId, $balanceField, $amount, $type, $referenceType, $referenceId,
            $description, $createdByType, $createdById, $adminReason, $ip, $metadata, $allowNegative
        ) {
            $wallet = self::getOrCreateWallet($userId, $pdo, lock: true);

            if ((bool) $wallet['is_frozen'] && $type !== 'admin_credit' && $type !== 'admin_debit') {
                throw new RuntimeException('Wallet is frozen. Contact support.');
            }

            $column = $balanceField . '_balance';
            $previous = $wallet[$column];
            $resulting = bcadd($previous, $amount, 2);

            if (!$allowNegative && bccomp($resulting, '0', 2) < 0) {
                throw new InsufficientBalanceException('Insufficient balance for this operation.');
            }

            $update = "UPDATE wallets SET {$column} = ?";
            $params = [$resulting];

            if (bccomp($amount, '0', 2) > 0 && in_array($type, ['cashback_release', 'referral_credit', 'welcome_bonus'], true)) {
                $update .= ', total_earned = total_earned + ?';
                $params[] = $amount;
            }
            if ($type === 'deposit_credit') {
                $update .= ', total_deposited = total_deposited + ?';
                $params[] = $amount;
            }
            if ($type === 'withdrawal_paid') {
                $update .= ', total_withdrawn = total_withdrawn + ?';
                $params[] = bcmul($amount, '-1', 2);
            }

            $update .= ' WHERE user_id = ?';
            $params[] = $userId;
            $pdo->prepare($update)->execute($params);

            $uuid = uuid4();
            $pdo->prepare('INSERT INTO wallet_ledger
                (transaction_uuid, user_id, type, balance_field, amount, previous_balance, resulting_balance,
                 currency, reference_type, reference_id, description, created_by_type, created_by_id,
                 admin_reason, ip_address, metadata)
                VALUES (?,?,?,?,?,?,?,"USD",?,?,?,?,?,?,?,?)')
                ->execute([
                    $uuid, $userId, $type, $balanceField, $amount, $previous, $resulting,
                    $referenceType, $referenceId, $description, $createdByType, $createdById,
                    $adminReason, $ip, $metadata ? json_encode($metadata) : null,
                ]);

            return [
                'ledger_uuid' => $uuid,
                'previous_balance' => $previous,
                'resulting_balance' => $resulting,
            ];
        });
    }

    public static function spendableBalance(array $wallet): string
    {
        return bcadd(bcadd($wallet['deposited_balance'], $wallet['cashback_balance'], 2), $wallet['referral_balance'], 2);
    }

    /**
     * Debit spendable balances in priority order (referral, then cashback, then
     * deposited) and move the total into the reserved balance for a pending
     * withdrawal. Returns the per-bucket breakdown for audit purposes.
     */
    public static function holdForWithdrawal(int $userId, string $amount, int $withdrawalId, string $ip): array
    {
        return Database::transaction(function (PDO $pdo) use ($userId, $amount, $withdrawalId, $ip) {
            $wallet = self::getOrCreateWallet($userId, $pdo, lock: true);

            if ((bool) $wallet['is_frozen']) {
                throw new RuntimeException('Wallet is frozen. Contact support.');
            }

            $remaining = $amount;
            $breakdown = [];

            foreach (['referral', 'cashback', 'deposited'] as $bucket) {
                if (bccomp($remaining, '0', 2) <= 0) {
                    break;
                }
                $available = $wallet[$bucket . '_balance'];
                if (bccomp($available, '0', 2) <= 0) {
                    continue;
                }
                $take = bccomp($available, $remaining, 2) < 0 ? $available : $remaining;

                self::applyLedgerEntry(
                    $userId, $bucket, bcmul($take, '-1', 2), 'withdrawal_hold',
                    'withdrawal', $withdrawalId, 'Funds held for pending withdrawal request',
                    'user', $userId, null, $ip
                );

                $breakdown[$bucket] = $take;
                $remaining = bcsub($remaining, $take, 2);
            }

            if (bccomp($remaining, '0', 2) > 0) {
                throw new InsufficientBalanceException('Insufficient withdrawable balance.');
            }

            self::applyLedgerEntry(
                $userId, 'reserved', $amount, 'withdrawal_hold',
                'withdrawal', $withdrawalId, 'Reserved for pending withdrawal request',
                'user', $userId, null, $ip
            );

            return $breakdown;
        });
    }

    /** Release a previously held withdrawal amount back to its original buckets (rejection/cancellation). */
    public static function releaseWithdrawalHold(int $userId, array $breakdown, string $totalAmount, int $withdrawalId, string $reason, ?int $adminId): void
    {
        Database::transaction(function () use ($userId, $breakdown, $totalAmount, $withdrawalId, $reason, $adminId) {
            foreach ($breakdown as $bucket => $take) {
                self::applyLedgerEntry(
                    $userId, $bucket, $take, 'withdrawal_release',
                    'withdrawal', $withdrawalId, 'Withdrawal hold released: ' . $reason,
                    $adminId ? 'admin' : 'system', $adminId
                );
            }

            self::applyLedgerEntry(
                $userId, 'reserved', bcmul($totalAmount, '-1', 2), 'withdrawal_release',
                'withdrawal', $withdrawalId, 'Withdrawal hold released: ' . $reason,
                $adminId ? 'admin' : 'system', $adminId
            );
        });
    }

    /** Finalize a paid withdrawal: permanently remove the reserved hold. */
    public static function finalizeWithdrawal(int $userId, string $totalAmount, int $withdrawalId, int $adminId): void
    {
        self::applyLedgerEntry(
            $userId, 'reserved', bcmul($totalAmount, '-1', 2), 'withdrawal_paid',
            'withdrawal', $withdrawalId, 'Withdrawal paid out by admin',
            'admin', $adminId
        );
    }
}
