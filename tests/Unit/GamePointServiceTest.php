<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\GamePointService;
use App\Services\GameSettingsService;
use App\Services\InsufficientBalanceException;
use App\Services\WalletService;
use PDO;
use RuntimeException;
use Tests\TestCase;
use Tests\TestSeed;

final class GamePointServiceTest extends TestCase
{
    /**
     * game_settings is a singleton row that's only truncated once per whole
     * suite run (not per test), so without resetting it here, one test's
     * UPDATE (e.g. disabling the daily bonus) would silently leak into
     * every test declared after it.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $pdo = Database::connection();
        GameSettingsService::current($pdo); // lazily creates the row on the very first test
        $pdo->exec("UPDATE game_settings SET enabled = 1, maintenance_mode = 0, minimum_entry = 5.00, maximum_entry = 80.00,
            countdown_seconds = 5, round_grace_seconds = 3, growth_rate = 0.1200, starting_balance = 1000.00,
            daily_bonus_enabled = 1, daily_bonus_amount = 100.00, daily_bonus_max_per_day = 1,
            exchange_enabled = 1, exchange_rate = 0.010000, min_exchange_amount = 100.00, max_exchange_per_day = 2000.00,
            topup_enabled = 1, topup_rate = 100.000000, min_topup_amount = 1.00, max_topup_per_day = 50.00
            WHERE id = 1");
    }

    public function test_wallet_auto_creates_with_configured_starting_balance(): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE game_settings SET starting_balance = 750.00 WHERE id = 1');

        $user = TestSeed::createUser('gp-create');
        $this->assertSame('750.00', GamePointService::balance((int) $user['id']));
    }

    public function test_credit_increases_balance_and_records_ledger(): void
    {
        $user = TestSeed::createUser('gp-credit');
        GamePointService::getOrCreateWallet((int) $user['id'], Database::connection());

        $before = GamePointService::balance((int) $user['id']);
        GamePointService::applyLedgerEntry((int) $user['id'], '50.00', 'admin_grant', null, null, 'test credit');
        $after = GamePointService::balance((int) $user['id']);

        $this->assertSame(bcadd($before, '50.00', 2), $after);

        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM game_point_ledger WHERE user_id = ? AND type = 'admin_grant' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        $this->assertSame('50.00', $row['amount']);
        $this->assertSame($before, $row['balance_before']);
        $this->assertSame($after, $row['balance_after']);
    }

    public function test_debit_decreases_balance_and_records_ledger(): void
    {
        $user = TestSeed::createUser('gp-debit');
        $before = GamePointService::balance((int) $user['id']);

        GamePointService::applyLedgerEntry((int) $user['id'], '-30.00', 'game_entry', null, null, 'test debit');
        $after = GamePointService::balance((int) $user['id']);

        $this->assertSame(bcsub($before, '30.00', 2), $after);
    }

    public function test_debit_beyond_balance_is_rejected(): void
    {
        $user = TestSeed::createUser('gp-overdraw');
        $balance = GamePointService::balance((int) $user['id']);

        $this->expectException(InsufficientBalanceException::class);
        GamePointService::applyLedgerEntry((int) $user['id'], bcadd($balance, '-1000000.00', 2), 'game_entry', null, null, 'overdraw');
    }

    public function test_daily_bonus_can_only_be_claimed_once_per_window(): void
    {
        $user = TestSeed::createUser('gp-bonus');
        $result = GamePointService::claimDailyBonus((int) $user['id']);
        $this->assertSame('100.00', $result['amount']);

        $this->expectException(RuntimeException::class);
        GamePointService::claimDailyBonus((int) $user['id']);
    }

    public function test_daily_bonus_disabled_is_rejected(): void
    {
        Database::connection()->exec('UPDATE game_settings SET daily_bonus_enabled = 0 WHERE id = 1');
        $user = TestSeed::createUser('gp-bonus-off');

        $this->expectException(RuntimeException::class);
        GamePointService::claimDailyBonus((int) $user['id']);
    }

    /**
     * The whole point of building a separate GamePointService/game_point_wallets
     * table: playing the game must never touch the real financial wallet.
     */
    public function test_game_points_activity_never_changes_the_financial_wallet(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('gp-isolation');

        $financialBefore = WalletService::getOrCreateWallet((int) $user['id'], $pdo);

        GamePointService::getOrCreateWallet((int) $user['id'], $pdo);
        GamePointService::applyLedgerEntry((int) $user['id'], '500.00', 'admin_grant', null, null, 'isolation test grant');
        GamePointService::applyLedgerEntry((int) $user['id'], '-200.00', 'game_entry', null, null, 'isolation test entry');
        GamePointService::applyLedgerEntry((int) $user['id'], '440.00', 'game_cashout', null, null, 'isolation test cashout');
        GamePointService::claimDailyBonus((int) $user['id']);

        $financialAfter = WalletService::getOrCreateWallet((int) $user['id'], $pdo);

        foreach (['deposited_balance', 'cashback_balance', 'referral_balance', 'pending_cashback_balance', 'reserved_balance'] as $field) {
            $this->assertSame($financialBefore[$field], $financialAfter[$field], "Financial wallet field {$field} changed after game activity");
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM wallet_ledger WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'No financial wallet_ledger row should exist for a user who only played the game');
    }

    public function test_exchange_debits_bs_and_credits_wallet_atomically_at_configured_rate(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('gp-exchange');
        GamePointService::getOrCreateWallet((int) $user['id'], $pdo); // 1000.00 B$ starting balance

        $walletBefore = WalletService::getOrCreateWallet((int) $user['id'], $pdo);

        $result = GamePointService::exchangeToWallet((int) $user['id'], '200.00', '127.0.0.1');

        $this->assertSame('200.00', $result['bs_exchanged']);
        $this->assertSame('2.00', $result['usd_credited']); // 200 * 0.01 rate
        $this->assertSame('800.00', $result['bs_balance']);
        $this->assertSame(GamePointService::balance((int) $user['id']), $result['bs_balance']);

        $walletAfter = WalletService::getOrCreateWallet((int) $user['id'], $pdo);
        $this->assertSame(bcadd($walletBefore['referral_balance'], '2.00', 2), $walletAfter['referral_balance']);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_ledger WHERE user_id = ? AND type = 'game_exchange' AND amount = '2.00'");
        $stmt->execute([$user['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_exchange_disabled_is_rejected(): void
    {
        Database::connection()->exec('UPDATE game_settings SET exchange_enabled = 0 WHERE id = 1');
        $user = TestSeed::createUser('gp-exchange-off');
        GamePointService::getOrCreateWallet((int) $user['id'], Database::connection());

        $this->expectException(RuntimeException::class);
        GamePointService::exchangeToWallet((int) $user['id'], '200.00', '127.0.0.1');
    }

    public function test_exchange_below_minimum_is_rejected(): void
    {
        $user = TestSeed::createUser('gp-exchange-min');
        GamePointService::getOrCreateWallet((int) $user['id'], Database::connection());

        $this->expectException(RuntimeException::class);
        GamePointService::exchangeToWallet((int) $user['id'], '10.00', '127.0.0.1'); // below the 100.00 minimum
    }

    public function test_exchange_beyond_bs_balance_is_rejected_and_wallet_untouched(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('gp-exchange-overdraw');
        GamePointService::getOrCreateWallet((int) $user['id'], $pdo); // 1000.00 B$

        $walletBefore = WalletService::getOrCreateWallet((int) $user['id'], $pdo);

        try {
            GamePointService::exchangeToWallet((int) $user['id'], '5000.00', '127.0.0.1');
            $this->fail('Expected an exception for exchanging more B$ than the balance holds');
        } catch (\Throwable $e) {
            // expected - fall through to assert nothing was credited
        }

        $walletAfter = WalletService::getOrCreateWallet((int) $user['id'], $pdo);
        $this->assertSame($walletBefore['referral_balance'], $walletAfter['referral_balance'], 'A failed exchange must not credit the wallet');
    }

    public function test_exchange_daily_cap_is_enforced(): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE game_settings SET starting_balance = 5000.00, max_exchange_per_day = 300.00, min_exchange_amount = 50.00 WHERE id = 1');
        $user = TestSeed::createUser('gp-exchange-cap');
        GamePointService::getOrCreateWallet((int) $user['id'], $pdo);

        GamePointService::exchangeToWallet((int) $user['id'], '250.00', '127.0.0.1');

        $this->expectException(RuntimeException::class);
        GamePointService::exchangeToWallet((int) $user['id'], '100.00', '127.0.0.1'); // 250 + 100 > 300 daily cap
    }

    public function test_topup_debits_wallet_and_credits_bs_atomically_at_configured_rate(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('gp-topup');
        GamePointService::getOrCreateWallet((int) $user['id'], $pdo); // 1000.00 B$ starting balance
        WalletService::applyLedgerEntry((int) $user['id'], 'deposited', '50.00', 'deposit_credit', null, null, 'test funding');

        $bsBefore = GamePointService::balance((int) $user['id']);

        $result = GamePointService::topUpFromWallet((int) $user['id'], '10.00', '127.0.0.1');

        $this->assertSame('10.00', $result['usd_spent']);
        $this->assertSame('1000.00', $result['bs_credited']); // 10 * 100 rate
        $this->assertSame(bcadd($bsBefore, '1000.00', 2), $result['bs_balance']);
        $this->assertSame(GamePointService::balance((int) $user['id']), $result['bs_balance']);

        $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);
        $this->assertSame('40.00', $wallet['deposited_balance']);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_ledger WHERE user_id = ? AND type = 'game_topup' AND amount = '-10.00'");
        $stmt->execute([$user['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_topup_debits_across_multiple_buckets_in_priority_order(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('gp-topup-multi');
        GamePointService::getOrCreateWallet((int) $user['id'], $pdo);
        WalletService::applyLedgerEntry((int) $user['id'], 'deposited', '3.00', 'deposit_credit', null, null, 'test funding');
        WalletService::applyLedgerEntry((int) $user['id'], 'referral', '20.00', 'referral_credit', null, null, 'test funding');

        GamePointService::topUpFromWallet((int) $user['id'], '5.00', '127.0.0.1'); // 3 from deposited, 2 from referral

        $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);
        $this->assertSame('0.00', $wallet['deposited_balance']);
        $this->assertSame('18.00', $wallet['referral_balance']);
    }

    public function test_topup_disabled_is_rejected(): void
    {
        Database::connection()->exec('UPDATE game_settings SET topup_enabled = 0 WHERE id = 1');
        $user = TestSeed::createUser('gp-topup-off');
        GamePointService::getOrCreateWallet((int) $user['id'], Database::connection());

        $this->expectException(RuntimeException::class);
        GamePointService::topUpFromWallet((int) $user['id'], '10.00', '127.0.0.1');
    }

    public function test_topup_below_minimum_is_rejected(): void
    {
        $user = TestSeed::createUser('gp-topup-min');
        GamePointService::getOrCreateWallet((int) $user['id'], Database::connection());

        $this->expectException(RuntimeException::class);
        GamePointService::topUpFromWallet((int) $user['id'], '0.10', '127.0.0.1'); // below the 1.00 minimum
    }

    public function test_topup_beyond_wallet_balance_is_rejected_and_bs_untouched(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('gp-topup-overdraw');
        GamePointService::getOrCreateWallet((int) $user['id'], $pdo);
        $bsBefore = GamePointService::balance((int) $user['id']);

        try {
            GamePointService::topUpFromWallet((int) $user['id'], '25.00', '127.0.0.1'); // wallet has $0
            $this->fail('Expected an exception for topping up more than the wallet holds');
        } catch (\Throwable $e) {
            // expected - fall through to assert nothing was credited
        }

        $this->assertSame($bsBefore, GamePointService::balance((int) $user['id']), 'A failed top-up must not credit B$');
    }

    public function test_topup_daily_cap_is_enforced(): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE game_settings SET max_topup_per_day = 30.00 WHERE id = 1');
        $user = TestSeed::createUser('gp-topup-cap');
        GamePointService::getOrCreateWallet((int) $user['id'], $pdo);
        WalletService::applyLedgerEntry((int) $user['id'], 'deposited', '100.00', 'deposit_credit', null, null, 'test funding');

        GamePointService::topUpFromWallet((int) $user['id'], '25.00', '127.0.0.1');

        $this->expectException(RuntimeException::class);
        GamePointService::topUpFromWallet((int) $user['id'], '10.00', '127.0.0.1'); // 25 + 10 > 30 daily cap
    }
}
