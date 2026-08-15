<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\WalletService;
use App\Services\WithdrawalService;
use RuntimeException;
use Tests\TestCase;
use Tests\TestSeed;

/** IDOR-style checks: a user must never be able to act on another user's records. */
final class AuthorizationTest extends TestCase
{
    public function test_user_cannot_cancel_another_users_withdrawal(): void
    {
        $owner = TestSeed::createUser('idor-owner');
        $attacker = TestSeed::createUser('idor-attacker');

        WalletService::applyLedgerEntry($owner['id'], 'deposited', '5000.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $withdrawal = WithdrawalService::request($owner['id'], '1200.00', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', '127.0.0.1');

        $this->expectException(RuntimeException::class);
        WithdrawalService::cancel($withdrawal['id'], $attacker['id'], '127.0.0.1');
    }
}
