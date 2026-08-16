<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Watch & Earn: admin-configured advertisements that credit a reward into
 * the EXISTING wallet ledger once a user has genuinely waited out the
 * required watch duration. There is no separate ad/earning balance - a
 * completed ad is just another wallet_ledger row (type 'ad_reward').
 *
 * Anti-fraud model: the reward amount and required duration are decided by
 * the server at session-start time and snapshotted onto the session row, so
 * completion never trusts anything the client sends. The elapsed-time check
 * compares the server's own started_at timestamp against now() - a client
 * cannot shorten that window by lying about its local timer. The one-reward-
 * per-user-per-ad rule is enforced by a database unique constraint
 * (ad_completions), not just application logic.
 */
final class AdService
{
    private const SESSION_GRACE_SECONDS = 600;

    public static function activeAds(int $userId): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT a.*,
                (SELECT COUNT(*) FROM ad_completions ac WHERE ac.advertisement_id = a.id) AS completion_count,
                (SELECT COUNT(*) FROM ad_completions ac WHERE ac.advertisement_id = a.id AND ac.user_id = ?) AS user_completed
            FROM advertisements a
            WHERE a.is_active = 1
              AND (a.starts_at IS NULL OR a.starts_at <= NOW())
              AND (a.ends_at IS NULL OR a.ends_at >= NOW())
            ORDER BY a.sort_order ASC, a.id DESC');
        $stmt->execute([$userId]);
        $ads = $stmt->fetchAll();

        return array_values(array_filter(array_map(static function (array $ad): array {
            $ad['user_completed'] = (int) $ad['user_completed'] > 0;
            $ad['is_full'] = $ad['max_completions'] !== null && (int) $ad['completion_count'] >= (int) $ad['max_completions'];
            return $ad;
        }, $ads), static fn (array $ad): bool => !$ad['is_full'] || $ad['user_completed']));
    }

    public static function startSession(int $userId, int $adId, string $ip): array
    {
        return Database::transaction(function (PDO $pdo) use ($userId, $adId, $ip) {
            $stmt = $pdo->prepare('SELECT * FROM advertisements WHERE id = ? FOR UPDATE');
            $stmt->execute([$adId]);
            $ad = $stmt->fetch();

            if (!$ad || !$ad['is_active']) {
                throw new RuntimeException('This advertisement is not available right now.');
            }
            if ($ad['starts_at'] && strtotime((string) $ad['starts_at']) > time()) {
                throw new RuntimeException('This advertisement is not available right now.');
            }
            if ($ad['ends_at'] && strtotime((string) $ad['ends_at']) < time()) {
                throw new RuntimeException('This advertisement is not available right now.');
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM ad_completions WHERE user_id = ? AND advertisement_id = ?');
            $stmt->execute([$userId, $adId]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new RuntimeException('You have already earned a reward for this advertisement.');
            }

            if ($ad['max_completions'] !== null) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM ad_completions WHERE advertisement_id = ?');
                $stmt->execute([$adId]);
                if ((int) $stmt->fetchColumn() >= (int) $ad['max_completions']) {
                    throw new RuntimeException('This advertisement has reached its maximum number of rewards.');
                }
            }

            $watchSeconds = (int) $ad['watch_seconds'];
            $uuid = uuid4();
            $expiresAt = date('Y-m-d H:i:s', time() + $watchSeconds + self::SESSION_GRACE_SECONDS);

            $pdo->prepare('INSERT INTO ad_watch_sessions
                (session_uuid, user_id, advertisement_id, reward_amount, watch_seconds, started_at, expires_at, status, ip_address)
                VALUES (?,?,?,?,?,NOW(),?,"active",?)')
                ->execute([$uuid, $userId, $adId, $ad['reward_amount'], $watchSeconds, $expiresAt, $ip]);

            return [
                'session_uuid' => $uuid,
                'watch_seconds' => $watchSeconds,
                'reward_amount' => $ad['reward_amount'],
                'title' => $ad['title'],
            ];
        });
    }

    public static function completeSession(int $userId, string $sessionUuid, string $ip): array
    {
        return Database::transaction(function (PDO $pdo) use ($userId, $sessionUuid, $ip) {
            $stmt = $pdo->prepare('SELECT * FROM ad_watch_sessions WHERE session_uuid = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$sessionUuid, $userId]);
            $session = $stmt->fetch();

            if (!$session) {
                throw new RuntimeException('Watch session not found. Please start again.');
            }
            if ($session['status'] !== 'active') {
                throw new RuntimeException('This watch session is no longer valid.');
            }
            if (strtotime((string) $session['expires_at']) < time()) {
                $pdo->prepare('UPDATE ad_watch_sessions SET status = "expired" WHERE id = ?')->execute([$session['id']]);
                throw new RuntimeException('This watch session has expired. Please start again.');
            }

            $readyAt = strtotime((string) $session['started_at']) + (int) $session['watch_seconds'];
            if (time() < $readyAt) {
                throw new RuntimeException('Please finish watching the advertisement before claiming your reward.');
            }

            $stmt = $pdo->prepare('SELECT * FROM advertisements WHERE id = ? FOR UPDATE');
            $stmt->execute([$session['advertisement_id']]);
            $ad = $stmt->fetch();
            if (!$ad || !$ad['is_active']) {
                throw new RuntimeException('This advertisement is no longer available.');
            }

            try {
                $pdo->prepare('INSERT INTO ad_completions (user_id, advertisement_id, watch_session_id, reward_amount, completed_at)
                    VALUES (?,?,?,?,NOW())')
                    ->execute([$userId, $session['advertisement_id'], $session['id'], $session['reward_amount']]);
            } catch (\PDOException $e) {
                if ((int) $e->getCode() === 23000) {
                    throw new RuntimeException('You have already earned a reward for this advertisement.');
                }
                throw $e;
            }
            $completionId = (int) $pdo->lastInsertId();

            $pdo->prepare('UPDATE ad_watch_sessions SET status = "completed", completed_at = NOW() WHERE id = ?')
                ->execute([$session['id']]);

            $ledger = WalletService::applyLedgerEntry(
                $userId, 'referral', (string) $session['reward_amount'], 'ad_reward',
                'advertisement', (int) $session['advertisement_id'], 'Watch & Earn: ' . $ad['title'],
                'system', null, null, $ip
            );

            $pdo->prepare('UPDATE ad_completions SET ledger_uuid = ? WHERE id = ?')
                ->execute([$ledger['ledger_uuid'], $completionId]);

            NotificationService::notify($userId, 'ad_reward', 'Watch & Earn reward',
                'You earned ' . money($session['reward_amount']) . ' for watching "' . $ad['title'] . '".');
            AuditLogger::log('user', $userId, 'ad.completed', 'advertisement', (int) $session['advertisement_id'], null,
                ['reward_amount' => $session['reward_amount'], 'session_uuid' => $sessionUuid], null, $ip);

            return ['reward_amount' => $session['reward_amount'], 'title' => $ad['title']];
        });
    }

    public static function userHistory(int $userId, int $limit = 20): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT ac.*, a.title FROM ad_completions ac
            JOIN advertisements a ON a.id = ac.advertisement_id
            WHERE ac.user_id = ? ORDER BY ac.completed_at DESC LIMIT ' . (int) $limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
