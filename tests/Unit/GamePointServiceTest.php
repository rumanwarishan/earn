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
            daily_bonus_enabled = 1, daily_bonus_amount = 100.00, daily_bonus_max_per_day = 1 WHERE id = 1");
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
}
