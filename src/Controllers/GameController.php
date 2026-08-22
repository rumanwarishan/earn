<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\GamePointService;
use App\Services\GameRoundService;
use App\Services\GameSettingsService;
use App\Services\NotificationService;
use RuntimeException;

final class GameController
{
    public function index(Request $request): void
    {
        $user = Session::get('user');
        $pdo = Database::connection();
        GamePointService::getOrCreateWallet((int) $user['id'], $pdo);

        echo view('layouts.app', [
            'pageTitle' => 'Billions Flight',
            'unreadCount' => NotificationService::unreadCount((int) $user['id']),
            'content' => view('game.index', [
                'balance' => GamePointService::balance((int) $user['id']),
                'settings' => GameSettingsService::current($pdo),
                'recentRounds' => GameRoundService::recentRounds(12),
                'history' => GameRoundService::userHistory((int) $user['id'], 15),
            ]),
        ]);
    }

    public function state(Request $request): void
    {
        $user = Session::get('user');
        $state = GameRoundService::currentState((int) $user['id']);
        $state['balance'] = GamePointService::balance((int) $user['id']);
        Response::json($state);
    }

    public function join(Request $request): void
    {
        $user = Session::get('user');
        $stake = trim((string) $request->input('stake', ''));

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $stake)) {
            Response::json(['ok' => false, 'error' => 'Enter a valid B$ amount.'], 422);
            return;
        }

        try {
            $result = GameRoundService::placeBet((int) $user['id'], $stake, $request->ip());
            Response::json(['ok' => true] + $result + ['balance' => GamePointService::balance((int) $user['id'])]);
        } catch (RuntimeException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function cashout(Request $request): void
    {
        $user = Session::get('user');
        $roundUuid = (string) $request->param('uuid');

        try {
            $result = GameRoundService::cashout((int) $user['id'], $roundUuid, $request->ip());
            Response::json(['ok' => true] + $result + ['balance' => GamePointService::balance((int) $user['id'])]);
        } catch (RuntimeException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function dailyBonus(Request $request): void
    {
        $user = Session::get('user');
        try {
            $result = GamePointService::claimDailyBonus((int) $user['id']);
            Response::json(['ok' => true] + $result);
        } catch (RuntimeException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function exchange(Request $request): void
    {
        $user = Session::get('user');
        $amount = trim((string) $request->input('amount', ''));

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount) || bccomp($amount, '0', 2) <= 0) {
            flash_errors(['amount' => 'Enter a valid B$ amount to exchange.']);
            redirect('/game');
            return;
        }

        try {
            $result = GamePointService::exchangeToWallet((int) $user['id'], $amount, $request->ip());
            flash_success('Exchanged B$' . number_format((float) $result['bs_exchanged'], 2) . ' for ' . money($result['usd_credited']) . ' in your wallet.');
        } catch (\Throwable $e) {
            flash_errors(['amount' => $e->getMessage()]);
        }

        redirect('/game');
    }

    public function history(Request $request): void
    {
        $user = Session::get('user');
        Response::json(['history' => GameRoundService::userHistory((int) $user['id'], 30)]);
    }

    public function roundHistory(Request $request): void
    {
        Response::json(['rounds' => GameRoundService::recentRounds(20)]);
    }
}
