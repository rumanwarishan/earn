<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\GamePointService;
use App\Services\GameSettingsService;
use App\Support\ValidationException;
use RuntimeException;

final class AdminGameController
{
    public function index(Request $request): void
    {
        require_admin_permission('games.view');
        $pdo = Database::connection();

        $stats = [
            'total_players' => (int) $pdo->query('SELECT COUNT(*) FROM game_point_wallets')->fetchColumn(),
            'active_players' => (int) $pdo->query('SELECT COUNT(DISTINCT user_id) FROM game_bets WHERE placed_at >= (NOW() - INTERVAL 24 HOUR)')->fetchColumn(),
            'total_played' => (string) $pdo->query('SELECT COALESCE(SUM(stake),0) FROM game_bets')->fetchColumn(),
            'total_won' => (string) $pdo->query("SELECT COALESCE(SUM(payout),0) FROM game_bets WHERE status = 'cashed_out'")->fetchColumn(),
            'total_lost' => (string) $pdo->query("SELECT COALESCE(SUM(stake),0) FROM game_bets WHERE status = 'lost'")->fetchColumn(),
            'rounds_played' => (int) $pdo->query("SELECT COUNT(*) FROM game_rounds WHERE status IN ('crashed','completed')")->fetchColumn(),
            'todays_games' => (int) $pdo->query('SELECT COUNT(*) FROM game_bets WHERE DATE(placed_at) = CURDATE()')->fetchColumn(),
            'total_exchanged_bs' => (string) $pdo->query("SELECT COALESCE(SUM(-amount),0) FROM game_point_ledger WHERE type = 'exchange'")->fetchColumn(),
            'total_exchanged_usd' => (string) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_ledger WHERE type = 'game_exchange'")->fetchColumn(),
        ];

        $activeRound = $pdo->query("SELECT * FROM game_rounds WHERE status IN ('waiting','running') ORDER BY id DESC LIMIT 1")->fetch();

        echo view('layouts.admin', [
            'pageTitle' => 'Billions Flight',
            'content' => view('admin.game.dashboard', [
                'stats' => $stats,
                'activeRound' => $activeRound ?: null,
            ]),
        ]);
    }

    public function rounds(Request $request): void
    {
        require_admin_permission('games.view');
        $pdo = Database::connection();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("SELECT gr.*,
                (SELECT COUNT(*) FROM game_bets gb WHERE gb.round_id = gr.id) AS player_count,
                (SELECT COALESCE(SUM(stake),0) FROM game_bets gb WHERE gb.round_id = gr.id) AS total_entries,
                (SELECT COALESCE(SUM(payout),0) FROM game_bets gb WHERE gb.round_id = gr.id AND gb.status = 'cashed_out') AS total_payouts
            FROM game_rounds gr ORDER BY gr.id DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rounds = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Flight Rounds',
            'content' => view('admin.game.rounds', [
                'rounds' => $rounds,
                'page' => $page,
                'hasMore' => count($rounds) === $perPage,
            ]),
        ]);
    }

    public function settingsShow(Request $request): void
    {
        require_admin_permission('games.manage');
        echo view('layouts.admin', [
            'pageTitle' => 'Flight Settings',
            'content' => view('admin.game.settings', [
                'settings' => GameSettingsService::current(),
            ]),
        ]);
    }

    public function settingsUpdate(Request $request): void
    {
        require_admin_permission('games.manage');
        $admin = Session::get('admin');
        $data = $request->only([
            'enabled', 'maintenance_mode', 'minimum_entry', 'maximum_entry', 'countdown_seconds',
            'round_grace_seconds', 'growth_rate', 'starting_balance',
            'daily_bonus_enabled', 'daily_bonus_amount', 'daily_bonus_max_per_day',
            'exchange_enabled', 'exchange_rate', 'min_exchange_amount', 'max_exchange_per_day',
        ]);

        try {
            foreach (['minimum_entry', 'maximum_entry', 'starting_balance', 'daily_bonus_amount', 'min_exchange_amount', 'max_exchange_per_day'] as $field) {
                if (!preg_match('/^\d+(\.\d{1,2})?$/', (string) $data[$field])) {
                    throw new ValidationException([$field => 'Enter a valid amount.']);
                }
            }
            if (bccomp((string) $data['minimum_entry'], (string) $data['maximum_entry'], 2) > 0) {
                throw new ValidationException(['minimum_entry' => 'Minimum entry cannot be greater than maximum entry.']);
            }
            if (!preg_match('/^\d+(\.\d{1,4})?$/', (string) $data['growth_rate']) || (float) $data['growth_rate'] <= 0) {
                throw new ValidationException(['growth_rate' => 'Enter a valid positive growth rate.']);
            }
            if (!preg_match('/^\d+(\.\d{1,6})?$/', (string) $data['exchange_rate']) || (float) $data['exchange_rate'] <= 0) {
                throw new ValidationException(['exchange_rate' => 'Enter a valid positive exchange rate.']);
            }
            if (bccomp((string) $data['min_exchange_amount'], (string) $data['max_exchange_per_day'], 2) > 0) {
                throw new ValidationException(['min_exchange_amount' => 'Minimum exchange cannot be greater than the daily limit.']);
            }

            GameSettingsService::update([
                'enabled' => isset($data['enabled']) ? 1 : 0,
                'maintenance_mode' => isset($data['maintenance_mode']) ? 1 : 0,
                'minimum_entry' => $data['minimum_entry'],
                'maximum_entry' => $data['maximum_entry'],
                'countdown_seconds' => max(1, (int) $data['countdown_seconds']),
                'round_grace_seconds' => max(1, (int) $data['round_grace_seconds']),
                'growth_rate' => $data['growth_rate'],
                'starting_balance' => $data['starting_balance'],
                'daily_bonus_enabled' => isset($data['daily_bonus_enabled']) ? 1 : 0,
                'daily_bonus_amount' => $data['daily_bonus_amount'],
                'daily_bonus_max_per_day' => max(1, (int) $data['daily_bonus_max_per_day']),
                'exchange_enabled' => isset($data['exchange_enabled']) ? 1 : 0,
                'exchange_rate' => $data['exchange_rate'],
                'min_exchange_amount' => $data['min_exchange_amount'],
                'max_exchange_per_day' => $data['max_exchange_per_day'],
            ]);

            AuditLogger::log('admin', $admin['id'], 'game.settings_updated', 'game_settings', 1, null, $data, null, $request->ip());
            flash_success('Billions Flight settings updated.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        }

        redirect('/admin/game/settings');
    }

    public function adjustGamePoints(Request $request): void
    {
        require_admin_permission('games.manage');
        $admin = Session::get('admin');
        $email = trim((string) $request->input('email', ''));
        $amount = trim((string) $request->input('amount', ''));
        $reason = trim((string) $request->input('reason', ''));

        try {
            if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $amount) || bccomp($amount, '0', 2) === 0) {
                throw new ValidationException(['amount' => 'Enter a non-zero amount (negative to revoke).']);
            }
            if ($reason === '') {
                throw new ValidationException(['reason' => 'A reason is required for every manual adjustment.']);
            }

            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $userId = $stmt->fetchColumn();
            if (!$userId) {
                throw new ValidationException(['email' => 'No user found with that email.']);
            }

            $type = bccomp($amount, '0', 2) > 0 ? 'admin_grant' : 'admin_adjustment';
            $result = GamePointService::applyLedgerEntry(
                (int) $userId, $amount, $type, 'admin', (int) $admin['id'],
                'Admin manual adjustment', (int) $admin['id'], $reason,
            );

            AuditLogger::log(
                'admin', $admin['id'], 'game_points.adjusted', 'user', (int) $userId,
                ['balance_before' => $result['previous_balance']], ['balance_after' => $result['resulting_balance'], 'amount' => $amount, 'reason' => $reason],
                $reason, $request->ip(),
            );

            flash_success("Adjusted B\$ for {$email}: " . ($amount[0] === '-' ? '' : '+') . 'B$' . $amount . '.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        } catch (RuntimeException $e) {
            flash_errors(['amount' => $e->getMessage()]);
        }

        redirect('/admin/game');
    }
}
