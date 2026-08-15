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
}
