<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\InsufficientBalanceException;
use App\Services\WalletService;
use Tests\TestCase;
use Tests\TestSeed;

final class WalletServiceTest extends TestCase
{
    public function test_credit_increases_balance_and_writes_ledger_row(): void
    {
        $user = TestSeed::createUser('wallet-credit');

        $result = WalletService::applyLedgerEntry(
            $user['id'], 'deposited', '100.00', 'deposit_credit', null, null, 'test credit'
        );

        $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
        $this->assertSame('100.00', $wallet['deposited_balance']);
        $this->assertSame('0.00', $result['previous_balance']);
        $this->assertSame('100.00', $result['resulting_balance']);

        $stmt = Database::connection()->prepare('SELECT * FROM wallet_ledger WHERE transaction_uuid = ?');
        $stmt->execute([$result['ledger_uuid']]);
        $row = $stmt->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('posted', $row['status']);
    }

    public function test_debit_beyond_balance_throws_and_does_not_mutate_balance(): void
    {
        $user = TestSeed::createUser('wallet-insufficient');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '50.00', 'deposit_credit', null, null, 'seed');

        $this->expectException(InsufficientBalanceException::class);
        try {
            WalletService::applyLedgerEntry($user['id'], 'deposited', '-100.00', 'admin_debit', null, null, 'overdraw attempt');
        } finally {
            $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
            $this->assertSame('50.00', $wallet['deposited_balance'], 'Balance must be unchanged after a rejected debit');
        }
    }

    public function test_frozen_wallet_blocks_user_and_system_mutations_but_not_admin(): void
    {
        $user = TestSeed::createUser('wallet-frozen');
        Database::connection()->prepare('UPDATE wallets SET is_frozen = 1 WHERE user_id = ?')->execute([$user['id']]);

        $this->expectExceptionMessage('Wallet is frozen');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '10.00', 'deposit_credit', null, null, 'blocked');
    }

    public function test_admin_credit_bypasses_freeze(): void
    {
        $user = TestSeed::createUser('wallet-admin-override');
        Database::connection()->prepare('UPDATE wallets SET is_frozen = 1 WHERE user_id = ?')->execute([$user['id']]);

        WalletService::applyLedgerEntry($user['id'], 'deposited', '10.00', 'admin_credit', null, null, 'admin override', 'admin', 1);

        $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
        $this->assertSame('10.00', $wallet['deposited_balance']);
    }

    public function test_ledger_balance_matches_sum_of_entries(): void
    {
        $user = TestSeed::createUser('wallet-integrity');
        WalletService::applyLedgerEntry($user['id'], 'cashback', '15.50', 'cashback_release', null, null, 'a');
        WalletService::applyLedgerEntry($user['id'], 'cashback', '4.25', 'cashback_release', null, null, 'b');
        WalletService::applyLedgerEntry($user['id'], 'cashback', '-2.00', 'admin_debit', null, null, 'c', 'admin', 1);

        $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
        $stmt = Database::connection()->prepare('SELECT COALESCE(SUM(amount),0) FROM wallet_ledger WHERE user_id = ? AND balance_field = "cashback"');
        $stmt->execute([$user['id']]);
        $sum = $stmt->fetchColumn();

        $this->assertSame(0, bccomp($wallet['cashback_balance'], $sum, 2), 'Wallet balance must always equal the sum of its ledger entries');
    }
}
