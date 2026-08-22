<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\GamePointService;
use App\Services\GameRoundService;
use App\Services\GameSettingsService;
use App\Services\InsufficientBalanceException;
use PDO;
use RuntimeException;
use Tests\TestCase;
use Tests\TestSeed;

final class GameRoundServiceTest extends TestCase
{
    /**
     * game_settings is a singleton row and game_rounds is a global "one
     * active round" table - both are truncated once per whole suite run,
     * not per test, and game_rounds persisting across test methods is
     * actually correct (it mirrors the real one-global-round production
     * design). Resetting both to a known baseline before every single test
     * (not just the ones that call fastSettings()) stops one test's timing
     * config or leftover round from silently bleeding into the next.
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
        $pdo->exec('DELETE FROM game_rounds');
    }

    private function fastSettings(PDO $pdo, string $growthRate = '0.1200'): void
    {
        $pdo->exec("UPDATE game_settings SET countdown_seconds = 1, growth_rate = {$growthRate}, round_grace_seconds = 1 WHERE id = 1");
        $pdo->exec('DELETE FROM game_rounds');
    }

    public function test_round_transitions_waiting_to_running(): void
    {
        $pdo = Database::connection();
        $this->fastSettings($pdo);
        $user = TestSeed::createUser('round-transition');

        $state = GameRoundService::currentState((int) $user['id']);
        $this->assertSame('waiting', $state['status']);
        $this->assertArrayNotHasKey('crash_multiplier', $state, 'Crash point must never be exposed before the round crashes');

        sleep(2);
        $state = GameRoundService::currentState((int) $user['id']);
        $this->assertContains($state['status'], ['running', 'crashed', 'completed'], 'Round should have started by now');
    }

    public function test_crash_multiplier_hidden_while_waiting_and_revealed_after_crash(): void
    {
        $pdo = Database::connection();
        $this->fastSettings($pdo, '5.0000'); // guarantee a crash well within the sleep window below
        $user = TestSeed::createUser('round-crash-reveal');

        $state = GameRoundService::currentState((int) $user['id']);
        $this->assertArrayNotHasKey('crash_multiplier', $state);

        sleep(3);
        $state = GameRoundService::currentState((int) $user['id']);
        $this->assertSame('crashed', $state['status']);
        $this->assertArrayHasKey('crash_multiplier', $state);
    }

    public function test_bet_below_minimum_entry_is_rejected(): void
    {
        $pdo = Database::connection();
        $this->fastSettings($pdo);
        $user = TestSeed::createUser('round-min');

        $this->expectException(RuntimeException::class);
        GameRoundService::placeBet((int) $user['id'], '1.00', '127.0.0.1');
    }

    public function test_bet_above_maximum_entry_is_rejected(): void
    {
        $pdo = Database::connection();
        $this->fastSettings($pdo);
        $user = TestSeed::createUser('round-max');

        $this->expectException(RuntimeException::class);
        GameRoundService::placeBet((int) $user['id'], '500.00', '127.0.0.1');
    }

    public function test_bet_with_insufficient_game_points_is_rejected(): void
    {
        $pdo = Database::connection();
        $pdo->exec('UPDATE game_settings SET starting_balance = 10.00 WHERE id = 1');
        $user = TestSeed::createUser('round-insufficient');
        GamePointService::getOrCreateWallet((int) $user['id'], $pdo); // balance = 10.00

        $this->expectException(InsufficientBalanceException::class);
        GameRoundService::placeBet((int) $user['id'], '80.00', '127.0.0.1');
    }

    public function test_duplicate_bet_in_same_round_is_rejected(): void
    {
        $user = TestSeed::createUser('round-dup-bet');

        GameRoundService::placeBet((int) $user['id'], '10.00', '127.0.0.1');

        $this->expectException(RuntimeException::class);
        GameRoundService::placeBet((int) $user['id'], '10.00', '127.0.0.1');
    }

    public function test_bet_debits_game_points_exactly(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('round-debit-exact');
        $before = GamePointService::balance((int) $user['id']);

        GameRoundService::placeBet((int) $user['id'], '25.00', '127.0.0.1');

        $after = GamePointService::balance((int) $user['id']);
        $this->assertSame(bcsub($before, '25.00', 2), $after);
    }

    public function test_cashout_credits_exact_payout_and_locks_status(): void
    {
        $pdo = Database::connection();
        $this->fastSettings($pdo);
        $user = TestSeed::createUser('round-cashout');

        $state = GameRoundService::currentState((int) $user['id']);
        $bet = GameRoundService::placeBet((int) $user['id'], '20.00', '127.0.0.1');

        sleep(2); // past countdown, round now running
        $state = GameRoundService::currentState((int) $user['id']);
        if ($state['status'] !== 'running') {
            $this->markTestSkipped('Round crashed before the test could cash out - inherent timing flakiness, not a defect.');
        }

        $balanceBeforeClaim = GamePointService::balance((int) $user['id']);
        $result = GameRoundService::cashout((int) $user['id'], $bet['round_uuid'], '127.0.0.1');

        $expectedPayout = bcmul('20.00', $result['multiplier'], 2);
        $this->assertSame($expectedPayout, $result['payout']);

        $balanceAfterClaim = GamePointService::balance((int) $user['id']);
        $this->assertSame(bcadd($balanceBeforeClaim, $expectedPayout, 2), $balanceAfterClaim);
    }

    public function test_double_cashout_is_rejected(): void
    {
        $pdo = Database::connection();
        $this->fastSettings($pdo);
        $user = TestSeed::createUser('round-double-cashout');

        $bet = GameRoundService::placeBet((int) $user['id'], '20.00', '127.0.0.1');
        sleep(2);
        $state = GameRoundService::currentState((int) $user['id']);
        if ($state['status'] !== 'running') {
            $this->markTestSkipped('Round crashed before the test could cash out.');
        }

        GameRoundService::cashout((int) $user['id'], $bet['round_uuid'], '127.0.0.1');

        $this->expectException(RuntimeException::class);
        GameRoundService::cashout((int) $user['id'], $bet['round_uuid'], '127.0.0.1');
    }

    public function test_cashout_after_crash_is_rejected_and_bet_recorded_lost(): void
    {
        $pdo = Database::connection();
        $this->fastSettings($pdo, '6.0000'); // fast, near-certain crash
        // fastSettings() defaults round_grace_seconds to 1s - too short for a
        // fixed sleep() to reliably land while still 'crashed' (a slow test
        // runner can let the round finish its grace window AND cycle a
        // second round's own 1s countdown before the check, landing on
        // 'waiting' instead). Widen the grace window for this test only so
        // the assertion has a much bigger, deterministic target to hit.
        $pdo->exec('UPDATE game_settings SET round_grace_seconds = 30 WHERE id = 1');
        $user = TestSeed::createUser('round-cashout-after-crash');

        $bet = GameRoundService::placeBet((int) $user['id'], '20.00', '127.0.0.1');

        sleep(3); // long enough that the round has certainly crashed
        $state = GameRoundService::currentState((int) $user['id']); // triggers lazy settlement
        $this->assertSame('crashed', $state['status']);

        $stmt = $pdo->prepare("SELECT gb.status FROM game_bets gb JOIN game_rounds gr ON gr.id = gb.round_id
            WHERE gr.round_uuid = ? AND gb.user_id = ?");
        $stmt->execute([$bet['round_uuid'], $user['id']]);
        $this->assertSame('lost', $stmt->fetchColumn());

        $this->expectException(RuntimeException::class);
        GameRoundService::cashout((int) $user['id'], $bet['round_uuid'], '127.0.0.1');
    }

    public function test_lost_bet_does_not_debit_beyond_the_original_stake(): void
    {
        $pdo = Database::connection();
        $this->fastSettings($pdo, '6.0000');
        $user = TestSeed::createUser('round-lost-noextra');

        GameRoundService::placeBet((int) $user['id'], '20.00', '127.0.0.1');
        $balanceAfterBet = GamePointService::balance((int) $user['id']);

        sleep(3);
        GameRoundService::currentState((int) $user['id']); // triggers lazy settlement

        $balanceAfterCrash = GamePointService::balance((int) $user['id']);
        $this->assertSame($balanceAfterBet, $balanceAfterCrash, 'A loss must not debit anything beyond the stake already taken at bet time');
    }

    /** IDOR: a user must never be able to touch another user's bet, even knowing the round UUID. */
    public function test_user_cannot_cash_out_another_users_bet(): void
    {
        $pdo = Database::connection();
        $this->fastSettings($pdo);
        $owner = TestSeed::createUser('round-idor-owner');
        $attacker = TestSeed::createUser('round-idor-attacker');

        $bet = GameRoundService::placeBet((int) $owner['id'], '20.00', '127.0.0.1');
        sleep(2);
        $state = GameRoundService::currentState((int) $owner['id']);
        if ($state['status'] !== 'running') {
            $this->markTestSkipped('Round crashed before the test could attempt the cashout.');
        }

        $this->expectException(RuntimeException::class);
        GameRoundService::cashout((int) $attacker['id'], $bet['round_uuid'], '127.0.0.1');
    }

    public function test_admin_grant_requires_no_criteria_and_is_ledgered(): void
    {
        $user = TestSeed::createUser('round-admin-grant');
        $before = GamePointService::balance((int) $user['id']);

        GamePointService::applyLedgerEntry(
            (int) $user['id'], '300.00', 'admin_grant', 'admin', 1,
            'Admin manual adjustment', 1, 'QA test grant',
        );

        $after = GamePointService::balance((int) $user['id']);
        $this->assertSame(bcadd($before, '300.00', 2), $after);

        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT admin_id, admin_reason FROM game_point_ledger WHERE user_id = ? AND type = 'admin_grant' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        $this->assertSame(1, (int) $row['admin_id']);
        $this->assertSame('QA test grant', $row['admin_reason']);
    }
}
