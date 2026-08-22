<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Billions Flight round engine. Fully server-authoritative: the crash point
 * is generated with random_int() the instant a round is created and is
 * never exposed to the client until the round has actually crashed. There
 * is no background worker/daemon (plain PHP shared hosting) - instead the
 * round is lazily advanced on read, inside a locked, idempotent
 * double-checked transaction, so whichever of the many concurrent pollers
 * happens to cross a state-transition boundary first performs it safely and
 * every other request just observes the resulting state.
 */
final class GameRoundService
{
    private const HOUSE_EDGE_BP = 300; // 3.00%
    private const MAX_MULTIPLIER = '1000.00';

    /** Public, client-safe snapshot of the current round + this user's bet in it. */
    public static function currentState(int $userId): array
    {
        $pdo = Database::connection();
        $settings = GameSettingsService::current($pdo);

        $round = self::peekRound($pdo);
        if (self::needsTransition($round, $settings)) {
            $round = self::advanceRound($pdo, $settings);
        }

        $bet = null;
        $stmt = $pdo->prepare('SELECT * FROM game_bets WHERE round_id = ? AND user_id = ?');
        $stmt->execute([$round['id'], $userId]);
        $bet = $stmt->fetch() ?: null;

        return self::publicView($round, $settings, $bet);
    }

    private static function peekRound(PDO $pdo): ?array
    {
        $round = $pdo->query('SELECT * FROM game_rounds ORDER BY id DESC LIMIT 1')->fetch();
        return $round ?: null;
    }

    /** What status this round SHOULD be right now, purely from wall-clock time - no writes. */
    private static function impliedStatus(array $round, array $settings): string
    {
        $now = time();
        $startsAt = strtotime($round['starts_at']);

        if ($round['status'] === 'waiting') {
            return $now >= $startsAt ? 'running' : 'waiting';
        }
        if ($round['status'] === 'running') {
            $elapsed = max(0, $now - $startsAt);
            $current = self::multiplierAt((float) $elapsed, (string) $settings['growth_rate']);
            return bccomp($current, $round['crash_multiplier'], 2) >= 0 ? 'crashed' : 'running';
        }
        if ($round['status'] === 'crashed') {
            $crashedAt = $round['crashed_at'] ? strtotime($round['crashed_at']) : $now;
            return ($now - $crashedAt) >= (int) $settings['round_grace_seconds'] ? 'completed' : 'crashed';
        }
        return 'completed';
    }

    /**
     * A 'completed' round is a terminal state for ITSELF, but never terminal
     * for the game - the next request must always get a fresh round. Comparing
     * impliedStatus() to the stored status alone misses this ('completed' ===
     * 'completed' looks like "nothing to do"), so it's checked explicitly.
     */
    private static function needsTransition(?array $round, array $settings): bool
    {
        if ($round === null || $round['status'] === 'completed') {
            return true;
        }
        return self::impliedStatus($round, $settings) !== $round['status'];
    }

    /** Locked, idempotent state transition - safe under concurrent callers. */
    private static function advanceRound(PDO $pdo, array $settings): array
    {
        return Database::transaction(function (PDO $pdo) use ($settings) {
            $round = $pdo->query('SELECT * FROM game_rounds ORDER BY id DESC LIMIT 1 FOR UPDATE')->fetch() ?: null;
            return self::advanceRoundLocked($pdo, $settings, $round);
        });
    }

    private static function createWaitingRound(PDO $pdo, array $settings): array
    {
        $crash = self::generateCrashMultiplier();
        $uuid = uuid4();
        $startsAt = date('Y-m-d H:i:s', time() + (int) $settings['countdown_seconds']);

        $pdo->prepare('INSERT INTO game_rounds (round_uuid, status, crash_multiplier, starts_at) VALUES (?, "waiting", ?, ?)')
            ->execute([$uuid, $crash, $startsAt]);

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM game_rounds WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /** Marks every still-ACTIVE bet in a just-crashed round LOST, with a ledger record. */
    private static function settleLostBets(PDO $pdo, int $roundId): void
    {
        $stmt = $pdo->prepare("SELECT * FROM game_bets WHERE round_id = ? AND status = 'active' FOR UPDATE");
        $stmt->execute([$roundId]);
        $activeBets = $stmt->fetchAll();

        foreach ($activeBets as $bet) {
            $pdo->prepare("UPDATE game_bets SET status = 'lost', updated_at = NOW() WHERE id = ?")
                ->execute([$bet['id']]);

            GamePointService::applyLedgerEntry(
                (int) $bet['user_id'], '0.00', 'game_loss',
                'game_bet', (int) $bet['id'], 'Billions Flight: round crashed before cashout',
                allowNegative: true,
            );
        }
    }

    /** Cryptographically secure crash point with a house edge, never Math.random()-derived. */
    private static function generateCrashMultiplier(): string
    {
        $r = random_int(1, 1_000_000) / 1_000_000; // uniform in (0, 1]
        $edge = self::HOUSE_EDGE_BP / 10000;

        if ($r <= $edge) {
            return '1.00';
        }

        $raw = (1 - $edge) / (1 - $r);
        $crash = floor($raw * 100) / 100;
        $crash = max(1.00, min($crash, (float) self::MAX_MULTIPLIER));

        return number_format($crash, 2, '.', '');
    }

    /** Deterministic multiplier curve from elapsed seconds - not stored money, so plain float math is fine here. */
    private static function multiplierAt(float $elapsedSeconds, string $growthRate): string
    {
        $value = exp(((float) $growthRate) * max(0.0, $elapsedSeconds));
        return number_format($value, 2, '.', '');
    }

    private static function publicView(array $round, array $settings, ?array $bet): array
    {
        $now = time();
        $status = $round['status'];

        $view = [
            'round_uuid' => $round['round_uuid'],
            'status' => $status,
            'server_time' => $now,
            'settings' => [
                'minimum_entry' => $settings['minimum_entry'],
                'maximum_entry' => $settings['maximum_entry'],
                'enabled' => (bool) $settings['enabled'] && !(bool) $settings['maintenance_mode'],
                // Not sensitive on its own (unlike crash_multiplier) - lets the
                // client interpolate a smooth curve between polls using the
                // exact same formula the server uses, re-synced every poll.
                'growth_rate' => $settings['growth_rate'],
            ],
        ];

        if ($status === 'waiting') {
            $view['countdown_seconds'] = max(0, strtotime($round['starts_at']) - $now);
        }

        if ($status === 'running') {
            $elapsed = max(0, $now - strtotime($round['starts_at']));
            $view['multiplier'] = self::multiplierAt((float) $elapsed, (string) $settings['growth_rate']);
            $view['elapsed_seconds'] = $elapsed;
        }

        if ($status === 'crashed' || $status === 'completed') {
            $view['crash_multiplier'] = $round['crash_multiplier'];
        }

        if ($bet) {
            $view['my_bet'] = [
                'stake' => $bet['stake'],
                'status' => $bet['status'],
                'cashout_multiplier' => $bet['cashout_multiplier'],
                'payout' => $bet['payout'],
            ];
        }

        return $view;
    }

    public static function placeBet(int $userId, string $stake, string $ip): array
    {
        $pdo = Database::connection();
        $settings = GameSettingsService::current($pdo);

        if (!(bool) $settings['enabled'] || (bool) $settings['maintenance_mode']) {
            throw new RuntimeException('Billions Flight is currently unavailable.');
        }
        if (bccomp($stake, $settings['minimum_entry'], 2) < 0 || bccomp($stake, $settings['maximum_entry'], 2) > 0) {
            throw new RuntimeException("Entry must be between {$settings['minimum_entry']} and {$settings['maximum_entry']} GP.");
        }

        return Database::transaction(function (PDO $pdo) use ($userId, $stake, $ip, $settings) {
            $round = $pdo->query('SELECT * FROM game_rounds ORDER BY id DESC LIMIT 1 FOR UPDATE')->fetch() ?: null;

            if (self::needsTransition($round, $settings)) {
                $round = self::advanceRoundLocked($pdo, $settings, $round);
            }
            $implied = $round['status'];

            if ($implied !== 'waiting') {
                throw new RuntimeException('This round has already started. Wait for the next round to join.');
            }

            $existing = $pdo->prepare('SELECT id FROM game_bets WHERE round_id = ? AND user_id = ?');
            $existing->execute([$round['id'], $userId]);
            if ($existing->fetchColumn()) {
                throw new RuntimeException('You already joined this round.');
            }

            // Debit first (throws InsufficientBalanceException if short) - the
            // unique (round_id, user_id) key still blocks a genuine double
            // submit even if two requests both pass the check above.
            GamePointService::applyLedgerEntry(
                $userId, bcmul($stake, '-1', 2), 'game_entry',
                'game_round', (int) $round['id'], 'Billions Flight entry',
            );

            try {
                $pdo->prepare('INSERT INTO game_bets (round_id, user_id, stake, status) VALUES (?,?,?,"active")')
                    ->execute([$round['id'], $userId, $stake]);
            } catch (\PDOException $e) {
                if ((int) $e->getCode() === 23000) {
                    throw new RuntimeException('You already joined this round.');
                }
                throw $e;
            }

            return [
                'round_uuid' => $round['round_uuid'],
                'stake' => $stake,
                'countdown_seconds' => max(0, strtotime($round['starts_at']) - time()),
            ];
        });
    }

    /**
     * Performs ONE step (waiting->running, running->crashed, crashed->completed,
     * completed->fresh waiting round) and then RE-CHECKS from the result - a
     * single hop is not enough when a lot of wall-clock time has passed since
     * the last poll (e.g. growth_rate is high, or nobody polled for a while):
     * a round could need to jump straight from waiting to crashed in one call.
     * Bounded to 5 iterations - there are only 4 states, so this always
     * terminates well before that.
     */
    private static function advanceRoundLocked(PDO $pdo, array $settings, ?array $round): array
    {
        for ($i = 0; $i < 5; $i++) {
            if ($round === null || $round['status'] === 'completed') {
                $round = self::createWaitingRound($pdo, $settings);
                continue; // a fresh round is never immediately due for another hop, but loop once more to confirm
            }

            $implied = self::impliedStatus($round, $settings);
            if ($implied === $round['status']) {
                return $round;
            }

            if ($implied === 'running') {
                $pdo->prepare("UPDATE game_rounds SET status = 'running', started_at = starts_at WHERE id = ?")->execute([$round['id']]);
            } elseif ($implied === 'crashed') {
                $pdo->prepare("UPDATE game_rounds SET status = 'crashed', crashed_at = NOW() WHERE id = ?")->execute([$round['id']]);
                self::settleLostBets($pdo, (int) $round['id']);
            } elseif ($implied === 'completed') {
                $pdo->prepare("UPDATE game_rounds SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$round['id']]);
            }

            $stmt = $pdo->prepare('SELECT * FROM game_rounds WHERE id = ?');
            $stmt->execute([$round['id']]);
            $round = $stmt->fetch();
        }
        return $round;
    }

    public static function cashout(int $userId, string $roundUuid, string $ip): array
    {
        return Database::transaction(function (PDO $pdo) use ($userId, $roundUuid, $ip) {
            $settings = GameSettingsService::current($pdo);

            $stmt = $pdo->prepare('SELECT * FROM game_rounds WHERE round_uuid = ? FOR UPDATE');
            $stmt->execute([$roundUuid]);
            $round = $stmt->fetch();
            if (!$round) {
                throw new RuntimeException('Round not found.');
            }

            $stmt = $pdo->prepare('SELECT * FROM game_bets WHERE round_id = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$round['id'], $userId]);
            $bet = $stmt->fetch();
            if (!$bet) {
                throw new RuntimeException('You have no active entry in this round.');
            }
            if ($bet['status'] !== 'active') {
                // Idempotent: a double-click just observes the already-settled state, not an error state change.
                throw new RuntimeException($bet['status'] === 'cashed_out' ? 'You already claimed this round.' : 'This round already crashed.');
            }

            $implied = self::impliedStatus($round, $settings);
            if ($implied !== 'running' && $round['status'] !== 'running') {
                throw new RuntimeException('This round is not currently running.');
            }

            $now = time();
            $startedAt = $round['started_at'] ? strtotime($round['started_at']) : strtotime($round['starts_at']);
            $elapsed = max(0, $now - $startedAt);
            $multiplier = self::multiplierAt((float) $elapsed, (string) $settings['growth_rate']);

            if (bccomp($multiplier, $round['crash_multiplier'], 2) >= 0) {
                // Crossed the crash point in the time it took this request to
                // arrive - settle as a loss, same as the lazy-advance path would.
                $pdo->prepare("UPDATE game_bets SET status = 'lost', updated_at = NOW() WHERE id = ?")->execute([$bet['id']]);
                GamePointService::applyLedgerEntry(
                    $userId, '0.00', 'game_loss', 'game_bet', (int) $bet['id'],
                    'Billions Flight: round crashed before cashout', allowNegative: true,
                );
                throw new RuntimeException('Too late - the round already crashed.');
            }

            $payout = bcmul($bet['stake'], $multiplier, 2);

            $pdo->prepare("UPDATE game_bets SET status = 'cashed_out', cashout_multiplier = ?, payout = ?, cashed_out_at = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute([$multiplier, $payout, $bet['id']]);

            GamePointService::applyLedgerEntry(
                $userId, $payout, 'game_cashout', 'game_bet', (int) $bet['id'],
                "Billions Flight: claimed at {$multiplier}x",
            );

            return ['multiplier' => $multiplier, 'payout' => $payout, 'stake' => $bet['stake']];
        });
    }

    public static function recentRounds(int $limit = 12): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT round_uuid, crash_multiplier, crashed_at FROM game_rounds
            WHERE status IN ('crashed','completed') ORDER BY id DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function userHistory(int $userId, int $limit = 20): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT gb.*, gr.round_uuid, gr.crash_multiplier FROM game_bets gb
            JOIN game_rounds gr ON gr.id = gb.round_id
            WHERE gb.user_id = ? AND gb.status != "active" ORDER BY gb.id DESC LIMIT ?');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
