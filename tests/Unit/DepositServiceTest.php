<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\DepositService;
use App\Services\WalletService;
use App\Support\ValidationException;
use RuntimeException;
use Tests\TestCase;
use Tests\TestSeed;

final class DepositServiceTest extends TestCase
{
    public function test_approve_credits_wallet_with_permanent_conversion_record(): void
    {
        $user = TestSeed::createUser('deposit-approve');
        $deposit = DepositService::submit($user['id'], '0.01', bin2hex(random_bytes(20)), '127.0.0.1');

        $result = DepositService::approve($deposit['id'], 1, '60000.00', '127.0.0.1', 'looks good');

        $this->assertSame('600.00', $result['usd_amount']);

        $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
        $this->assertSame('600.00', $wallet['deposited_balance']);

        $stmt = Database::connection()->prepare('SELECT * FROM deposits WHERE id = ?');
        $stmt->execute([$deposit['id']]);
        $row = $stmt->fetch();
        $this->assertSame('approved', $row['status']);
        $this->assertSame('60000.00', $row['btc_usd_rate']);
    }

    public function test_deposit_cannot_be_approved_twice(): void
    {
        $user = TestSeed::createUser('deposit-double');
        $deposit = DepositService::submit($user['id'], '0.01', bin2hex(random_bytes(20)), '127.0.0.1');
        DepositService::approve($deposit['id'], 1, '60000.00', '127.0.0.1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already been reviewed');
        DepositService::approve($deposit['id'], 1, '65000.00', '127.0.0.1');
    }

    public function test_reject_does_not_credit_wallet(): void
    {
        $user = TestSeed::createUser('deposit-reject');
        $deposit = DepositService::submit($user['id'], '0.01', bin2hex(random_bytes(20)), '127.0.0.1');

        DepositService::reject($deposit['id'], 1, 'Could not verify transaction', '127.0.0.1');

        $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
        $this->assertSame('0.00', $wallet['deposited_balance']);

        $stmt = Database::connection()->prepare('SELECT status FROM deposits WHERE id = ?');
        $stmt->execute([$deposit['id']]);
        $this->assertSame('rejected', $stmt->fetchColumn());
    }

    public function test_duplicate_txid_is_rejected(): void
    {
        $user = TestSeed::createUser('deposit-duptxid');
        $txid = bin2hex(random_bytes(20));
        DepositService::submit($user['id'], '0.01', $txid, '127.0.0.1');

        $this->expectException(ValidationException::class);
        DepositService::submit($user['id'], '0.02', $txid, '127.0.0.1');
    }

    public function test_invalid_btc_amount_is_rejected(): void
    {
        $user = TestSeed::createUser('deposit-badamount');
        $this->expectException(ValidationException::class);
        DepositService::submit($user['id'], 'not-a-number', bin2hex(random_bytes(20)), '127.0.0.1');
    }
}
