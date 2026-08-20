<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\TaskService;
use App\Services\WalletService;
use PDO;
use RuntimeException;
use Tests\TestCase;
use Tests\TestSeed;

final class TaskServiceTest extends TestCase
{
    private function makeTask(PDO $pdo, string $type, string $reward = '5.00'): int
    {
        $stmt = $pdo->prepare('INSERT INTO tasks (title, description, type, reward_amount, is_active, sort_order)
            VALUES (?,?,?,?,1,0)');
        $stmt->execute(['Test task ' . bin2hex(random_bytes(3)), 'A test task', $type, $reward]);
        return (int) $pdo->lastInsertId();
    }

    public function test_welcome_task_can_be_claimed_once(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-welcome');
        $taskId = $this->makeTask($pdo, 'welcome', '5.00');

        $result = TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
        $this->assertSame('5.00', $result['reward_amount']);

        $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);
        $this->assertSame('5.00', $wallet['referral_balance']);

        $this->expectException(RuntimeException::class);
        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
    }

    public function test_one_time_task_can_be_claimed_once(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-onetime');
        $taskId = $this->makeTask($pdo, 'one_time', '3.00');

        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');

        $this->expectException(RuntimeException::class);
        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
    }

    public function test_daily_task_can_only_be_claimed_once_per_day(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-daily');
        $taskId = $this->makeTask($pdo, 'daily', '0.50');

        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');

        $this->expectException(RuntimeException::class);
        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
    }

    public function test_inactive_task_cannot_be_claimed(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-inactive');
        $taskId = $this->makeTask($pdo, 'daily', '1.00');
        $pdo->prepare('UPDATE tasks SET is_active = 0 WHERE id = ?')->execute([$taskId]);

        $this->expectException(RuntimeException::class);
        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
    }

    public function test_available_for_marks_daily_task_unclaimable_after_claim(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-avail');
        $taskId = $this->makeTask($pdo, 'daily', '0.75');

        $before = TaskService::availableFor((int) $user['id']);
        $beforeTask = current(array_filter($before, fn ($t) => (int) $t['id'] === $taskId));
        $this->assertTrue($beforeTask['is_claimable']);

        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');

        $after = TaskService::availableFor((int) $user['id']);
        $afterTask = current(array_filter($after, fn ($t) => (int) $t['id'] === $taskId));
        $this->assertFalse($afterTask['is_claimable']);
    }

    public function test_reward_ledger_entry_is_recorded_as_task_reward(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-ledger');
        $taskId = $this->makeTask($pdo, 'welcome', '2.50');

        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_ledger WHERE user_id = ? AND type = 'task_reward' AND reference_id = ?");
        $stmt->execute([$user['id'], $taskId]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    private function makeMilestoneTask(PDO $pdo, string $criteriaKey, int $target, string $reward = '5.00'): int
    {
        $stmt = $pdo->prepare('INSERT INTO tasks (title, description, type, criteria_key, criteria_target, reward_amount, is_active, sort_order)
            VALUES (?,?,?,?,?,?,1,0)');
        $stmt->execute(['Milestone task ' . bin2hex(random_bytes(3)), 'A milestone test task', 'one_time', $criteriaKey, $target, $reward]);
        return (int) $pdo->lastInsertId();
    }

    public function test_milestone_task_is_not_claimable_before_criteria_met(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-ms-early');
        $taskId = $this->makeMilestoneTask($pdo, 'referral_count', 5);

        $available = TaskService::availableFor((int) $user['id']);
        $task = current(array_filter($available, fn ($t) => (int) $t['id'] === $taskId));
        $this->assertSame(0, $task['progress_count']);
        $this->assertFalse($task['is_claimable']);

        // Directly hitting claim() must reject too - the UI hiding the button
        // is not the security boundary, the server check inside claim() is.
        $this->expectException(RuntimeException::class);
        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
    }

    public function test_referral_count_milestone_still_locked_at_4_referred(): void
    {
        $pdo = Database::connection();
        $referrer = TestSeed::createUser('task-ms-ref');
        $taskId = $this->makeMilestoneTask($pdo, 'referral_count', 5, '5.00');

        for ($i = 0; $i < 4; $i++) {
            TestSeed::createUser('task-ms-ref-friend', $referrer['referral_code']);
        }
        $this->expectException(RuntimeException::class);
        TaskService::claim((int) $referrer['id'], $taskId, '127.0.0.1');
    }

    public function test_referral_count_milestone_claimable_once_5_referred(): void
    {
        $pdo = Database::connection();
        $referrer = TestSeed::createUser('task-ms-ref2');
        $taskId = $this->makeMilestoneTask($pdo, 'referral_count', 5, '5.00');

        for ($i = 0; $i < 5; $i++) {
            TestSeed::createUser('task-ms-ref2-friend', $referrer['referral_code']);
        }

        $available = TaskService::availableFor((int) $referrer['id']);
        $task = current(array_filter($available, fn ($t) => (int) $t['id'] === $taskId));
        $this->assertSame(5, $task['progress_count']);
        $this->assertTrue($task['is_claimable']);

        $result = TaskService::claim((int) $referrer['id'], $taskId, '127.0.0.1');
        $this->assertSame('5.00', $result['reward_amount']);
    }

    public function test_first_deposit_milestone_requires_an_approved_deposit(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-ms-dep');
        $taskId = $this->makeMilestoneTask($pdo, 'first_deposit', 1, '10.00');

        $this->expectException(RuntimeException::class);
        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
    }

    public function test_first_deposit_milestone_ignores_pending_deposits(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-ms-dep2');
        $taskId = $this->makeMilestoneTask($pdo, 'first_deposit', 1, '10.00');

        $pdo->prepare("INSERT INTO deposits (deposit_uuid, user_id, btc_address_shown, btc_amount_claimed, txid, status)
            VALUES (?,?,?,?,?,'pending')")
            ->execute([bin2hex(random_bytes(16)), $user['id'], 'bc1qtest', '0.001', 'txid-' . bin2hex(random_bytes(8))]);

        $this->expectException(RuntimeException::class);
        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
    }

    public function test_first_deposit_milestone_claimable_once_approved(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-ms-dep3');
        $taskId = $this->makeMilestoneTask($pdo, 'first_deposit', 1, '10.00');

        $pdo->prepare("INSERT INTO deposits (deposit_uuid, user_id, btc_address_shown, btc_amount_claimed, txid, status)
            VALUES (?,?,?,?,?,'approved')")
            ->execute([bin2hex(random_bytes(16)), $user['id'], 'bc1qtest', '0.001', 'txid-' . bin2hex(random_bytes(8))]);

        $result = TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
        $this->assertSame('10.00', $result['reward_amount']);
    }

    public function test_first_purchase_milestone_ignores_cancelled_orders(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-ms-purch');
        $taskId = $this->makeMilestoneTask($pdo, 'first_purchase', 1, '10.00');

        $marketplaceId = (int) $pdo->query('SELECT id FROM marketplaces LIMIT 1')->fetchColumn();
        if (!$marketplaceId) {
            $pdo->exec('INSERT INTO marketplaces (name, slug, is_active) VALUES ("Test Market", "test-market-' . bin2hex(random_bytes(3)) . '", 1)');
            $marketplaceId = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('INSERT INTO products (name, slug, marketplace_id, external_url, display_price, is_published) VALUES (?,?,?,?,?,1)')
            ->execute(['Test Product', 'test-product-' . bin2hex(random_bytes(6)), $marketplaceId, 'https://example.com', '25.00']);
        $productId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO orders (order_uuid, user_id, product_id, marketplace_id, product_price, status)
            VALUES (?,?,?,?,?,"cancelled")')
            ->execute([bin2hex(random_bytes(16)), $user['id'], $productId, $marketplaceId, '25.00']);

        $this->expectException(RuntimeException::class);
        TaskService::claim((int) $user['id'], $taskId, '127.0.0.1');
    }

    public function test_watch_ads_milestone_unlocks_after_5_completions(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('task-ms-ads');
        $taskId = $this->makeMilestoneTask($pdo, 'ad_watch_count', 5, '5.00');

        for ($i = 0; $i < 5; $i++) {
            $this->seedAdAndComplete($pdo, (int) $user['id']);
        }

        $available = TaskService::availableFor((int) $user['id']);
        $task = current(array_filter($available, fn ($t) => (int) $t['id'] === $taskId));
        $this->assertSame(5, $task['progress_count']);
        $this->assertTrue($task['is_claimable']);
    }

    private function seedAdAndComplete(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare('INSERT INTO advertisements (title, type, reward_amount, watch_seconds, is_active) VALUES (?,?,?,?,1)');
        $stmt->execute(['Ad ' . bin2hex(random_bytes(3)), 'image', '0.10', 5]);
        $adId = (int) $pdo->lastInsertId();

        $session = \App\Services\AdService::startSession($userId, $adId, '127.0.0.1');
        $pdo->prepare('UPDATE ad_watch_sessions SET started_at = DATE_SUB(NOW(), INTERVAL 30 SECOND) WHERE session_uuid = ?')
            ->execute([$session['session_uuid']]);
        \App\Services\AdService::completeSession($userId, $session['session_uuid'], '127.0.0.1');
    }
}
