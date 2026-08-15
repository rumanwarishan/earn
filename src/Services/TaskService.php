<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\ValidationException;
use PDO;
use RuntimeException;

/**
 * Admin-configurable "welcome" (once per account, ever), "daily" (once per
 * calendar day), and "one_time" (once per account, ever) claimable quests.
 * Deliberately separate from cashback/referral rewards - a task reward is a
 * manual claim, not tied to any purchase or deposit.
 */
final class TaskService
{
    public static function availableFor(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT t.*,
            (SELECT COUNT(*) FROM task_completions tc WHERE tc.task_id = t.id AND tc.user_id = ? AND tc.completion_date = CURDATE()) AS completed_today,
            (SELECT COUNT(*) FROM task_completions tc WHERE tc.task_id = t.id AND tc.user_id = ?) AS completed_ever
            FROM tasks t
            WHERE t.is_active = 1
            ORDER BY FIELD(t.type, "welcome", "daily", "one_time"), t.sort_order ASC');
        $stmt->execute([$userId, $userId]);
        $tasks = $stmt->fetchAll();

        return array_map(static function (array $task): array {
            $task['is_claimable'] = match ($task['type']) {
                'daily' => (int) $task['completed_today'] === 0,
                'welcome', 'one_time' => (int) $task['completed_ever'] === 0,
                default => false,
            };
            return $task;
        }, $tasks);
    }

    public static function claim(int $userId, int $taskId, string $ip): array
    {
        return Database::transaction(function (PDO $pdo) use ($userId, $taskId, $ip) {
            $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ? AND is_active = 1 FOR UPDATE');
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();

            if (!$task) {
                throw new RuntimeException('This task is no longer available.');
            }

            if ($task['type'] === 'welcome' || $task['type'] === 'one_time') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM task_completions WHERE task_id = ? AND user_id = ?');
                $stmt->execute([$taskId, $userId]);
                if ((int) $stmt->fetchColumn() > 0) {
                    throw new RuntimeException('You already claimed this task.');
                }
            }

            try {
                $pdo->prepare('INSERT INTO task_completions (task_id, user_id, completion_date, reward_amount) VALUES (?,?,CURDATE(),?)')
                    ->execute([$taskId, $userId, $task['reward_amount']]);
            } catch (\PDOException $e) {
                if ((int) $e->getCode() === 23000) {
                    // Unique key hit (task_id, user_id, completion_date) - already claimed today/ever.
                    throw new RuntimeException('You already claimed this task.');
                }
                throw $e;
            }

            if (bccomp((string) $task['reward_amount'], '0', 2) > 0) {
                WalletService::applyLedgerEntry(
                    $userId, 'referral', (string) $task['reward_amount'], 'task_reward',
                    'task', $taskId, 'Task reward: ' . $task['title'],
                    'system', null, null, $ip
                );
            }

            NotificationService::notify($userId, 'task_reward', 'Task reward claimed',
                'You earned ' . money($task['reward_amount']) . ' for completing "' . $task['title'] . '".');
            AuditLogger::log('user', $userId, 'task.claimed', 'task', $taskId, null,
                ['reward_amount' => $task['reward_amount']], null, $ip);

            return ['reward_amount' => $task['reward_amount'], 'title' => $task['title']];
        });
    }
}
