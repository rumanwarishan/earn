<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AdService;
use App\Services\NotificationService;
use App\Services\WalletService;
use RuntimeException;

final class WatchEarnController
{
    public function index(Request $request): void
    {
        $user = Session::get('user');
        $pdo = Database::connection();
        $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);

        echo view('layouts.app', [
            'pageTitle' => 'Watch & Earn',
            'unreadCount' => NotificationService::unreadCount((int) $user['id']),
            'content' => view('watch-earn.index', [
                'wallet' => $wallet,
                'spendable' => WalletService::spendableBalance($wallet),
                'ads' => AdService::activeAds((int) $user['id']),
                'history' => AdService::userHistory((int) $user['id']),
            ]),
        ]);
    }

    public function start(Request $request): void
    {
        $user = Session::get('user');
        $adId = (int) $request->param('id');

        try {
            $result = AdService::startSession((int) $user['id'], $adId, $request->ip());
            Response::json(['ok' => true] + $result);
        } catch (RuntimeException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function complete(Request $request): void
    {
        $user = Session::get('user');
        $sessionUuid = (string) $request->param('uuid');

        try {
            $result = AdService::completeSession((int) $user['id'], $sessionUuid, $request->ip());
            Response::json(['ok' => true] + $result);
        } catch (RuntimeException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
