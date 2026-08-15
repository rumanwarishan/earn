<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\InsufficientBalanceException;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\ValidationException;
use RuntimeException;
use Tests\TestCase;
use Tests\TestSeed;

final class WithdrawalServiceTest extends TestCase
{
    private const BTC_ADDR = 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh';

    public function test_below_minimum_is_rejected(): void
    {
        $user = TestSeed::createUser('wd-below-min');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '5000.00', 'admin_credit', null, null, 'seed', 'admin', 1);

        $this->expectException(ValidationException::class);
        WithdrawalService::request($user['id'], '999.99', self::BTC_ADDR, '127.0.0.1');
    }

    public function test_exactly_minimum_is_accepted(): void
    {
        $user = TestSeed::createUser('wd-exact-min');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '5000.00', 'admin_credit', null, null, 'seed', 'admin', 1);

        $result = WithdrawalService::request($user['id'], '1000.00', self::BTC_ADDR, '127.0.0.1');
        $this->assertArrayHasKey('id', $result);
    }

    public function test_insufficient_balance_is_rejected_and_nothing_is_reserved(): void
    {
        $user = TestSeed::createUser('wd-insufficient');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '1000.00', 'admin_credit', null, null, 'seed', 'admin', 1);

        try {
            WithdrawalService::request($user['id'], '5000.00', self::BTC_ADDR, '127.0.0.1');
            $this->fail('Expected InsufficientBalanceException');
        } catch (InsufficientBalanceException) {
            // expected
        }

        $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
        $this->assertSame('1000.00', $wallet['deposited_balance'], 'Balance must be untouched on a failed request');
        $this->assertSame('0.00', $wallet['reserved_balance']);
    }

    public function test_duplicate_pending_withdrawal_is_blocked(): void
    {
        $user = TestSeed::createUser('wd-duplicate');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '10000.00', 'admin_credit', null, null, 'seed', 'admin', 1);

        WithdrawalService::request($user['id'], '2000.00', self::BTC_ADDR, '127.0.0.1');

        $this->expectException(ValidationException::class);
        WithdrawalService::request($user['id'], '2000.00', self::BTC_ADDR, '127.0.0.1');
    }

    public function test_reject_releases_funds_back_to_wallet(): void
    {
        $user = TestSeed::createUser('wd-reject');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '5000.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $withdrawal = WithdrawalService::request($user['id'], '1500.00', self::BTC_ADDR, '127.0.0.1');

        WithdrawalService::reject($withdrawal['id'], 1, 'Suspicious activity', '127.0.0.1');

        $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
        $this->assertSame('5000.00', $wallet['deposited_balance']);
        $this->assertSame('0.00', $wallet['reserved_balance']);
    }

    public function test_full_lifecycle_to_paid_updates_total_withdrawn(): void
    {
        $user = TestSeed::createUser('wd-lifecycle');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '5000.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $withdrawal = WithdrawalService::request($user['id'], '1200.00', self::BTC_ADDR, '127.0.0.1');

        WithdrawalService::approve($withdrawal['id'], 1, '127.0.0.1');
        WithdrawalService::markProcessing($withdrawal['id'], 1, '127.0.0.1');
        WithdrawalService::markPaid($withdrawal['id'], 1, '0.02', '60000.00', bin2hex(random_bytes(16)), '127.0.0.1');
        WithdrawalService::markCompleted($withdrawal['id'], 1, '127.0.0.1');

        $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
        $this->assertSame('0.00', $wallet['reserved_balance']);
        $this->assertSame('1200.00', $wallet['total_withdrawn']);

        $stmt = Database::connection()->prepare('SELECT status FROM withdrawals WHERE id = ?');
        $stmt->execute([$withdrawal['id']]);
        $this->assertSame('completed', $stmt->fetchColumn());
    }

    public function test_completed_withdrawal_cannot_be_paid_again(): void
    {
        $user = TestSeed::createUser('wd-double-pay');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '5000.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $withdrawal = WithdrawalService::request($user['id'], '1200.00', self::BTC_ADDR, '127.0.0.1');
        WithdrawalService::approve($withdrawal['id'], 1, '127.0.0.1');
        WithdrawalService::markPaid($withdrawal['id'], 1, '0.02', '60000.00', bin2hex(random_bytes(16)), '127.0.0.1');

        $this->expectException(RuntimeException::class);
        WithdrawalService::markPaid($withdrawal['id'], 1, '0.02', '60000.00', bin2hex(random_bytes(16)), '127.0.0.1');
    }
}
