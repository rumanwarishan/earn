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
 *
 * Some tasks (invite N friends, watch N ads, first purchase, first deposit)
 * have a real, checkable signal elsewhere in the app - those carry a
 * criteria_key/criteria_target and MUST have that verified server-side
 * before a claim is allowed, both when deciding what to show as claimable
 * and again inside claim() itself (never trust that the claim button was
 * only shown because the criteria passed client-side - the route is a
 * plain POST an attacker could hit directly). Tasks with no verifiable
 * signal (daily check-in, social share) keep criteria_key NULL and stay
 * honor-system, same as before.
 */
final class TaskService
{
    private const CRITERIA_QUERIES = [
        'referral_count' => 'SELECT COUNT(*) FROM referral_relationships WHERE referrer_user_id = ? AND level = 1',
        'ad_watch_count' => 'SELECT COUNT(*) FROM ad_completions WHERE user_id = ?',
        'first_purchase' => "SELECT COUNT(*) FROM orders WHERE user_id = ? AND status NOT IN ('cancelled','refunded')",
        'first_deposit' => "SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'approved'",
    ];

    private static function progressFor(PDO $pdo, ?string $criteriaKey, int $userId): ?int
    {
        if ($criteriaKey === null || !isset(self::CRITERIA_QUERIES[$criteriaKey])) {
            return null;
        }
        $stmt = $pdo->prepare(self::CRITERIA_QUERIES[$criteriaKey]);
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

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

        return array_map(static function (array $task) use ($pdo, $userId): array {
            $notYetClaimed = match ($task['type']) {
                'daily' => (int) $task['completed_today'] === 0,
                'welcome', 'one_time' => (int) $task['completed_ever'] === 0,
                default => false,
            };

            $progress = self::progressFor($pdo, $task['criteria_key'], $userId);
            $task['progress_count'] = $progress;
            $task['criteria_met'] = $progress === null || $progress >= (int) $task['criteria_target'];
            $task['is_claimable'] = $notYetClaimed && $task['criteria_met'];
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

            // Only criteria_key values with an actual verification query (see
            // CRITERIA_QUERIES) are enforced here - a key like 'social_share'
            // has no verifiable signal and is intentionally left honor-system,
            // same as a NULL criteria_key.
            if (isset(self::CRITERIA_QUERIES[$task['criteria_key'] ?? ''])) {
                $progress = self::progressFor($pdo, $task['criteria_key'], $userId);
                if ($progress === null || $progress < (int) $task['criteria_target']) {
                    throw new RuntimeException('You have not met the requirements for this task yet.');
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
