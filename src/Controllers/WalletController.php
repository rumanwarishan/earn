<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\NotificationService;
use App\Services\WalletService;

final class WalletController
{
    private const TABS = [
        'all' => null,
        'deposits' => ['deposit_credit'],
        'cashback' => ['cashback_pending', 'cashback_release', 'cashback_reversal'],
        'referral' => ['referral_credit', 'referral_reversal', 'welcome_bonus'],
        'withdrawals' => ['withdrawal_hold', 'withdrawal_release', 'withdrawal_paid'],
    ];

    public function index(Request $request): void
    {
        $user = Session::get('user');
        $pdo = Database::connection();
        $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);

        $tab = (string) $request->query('tab', 'all');
        if (!array_key_exists($tab, self::TABS)) {
            $tab = 'all';
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $types = self::TABS[$tab];
        if ($types) {
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $stmt = $pdo->prepare("SELECT * FROM wallet_ledger WHERE user_id = ? AND type IN ({$placeholders})
                ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
            $stmt->execute([$user['id'], ...$types]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM wallet_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
            $stmt->execute([$user['id']]);
        }
        $entries = $stmt->fetchAll();

        echo view('layouts.app', [
            'pageTitle' => 'Wallet',
            'unreadCount' => NotificationService::unreadCount((int) $user['id']),
            'content' => view('wallet.index', [
                'wallet' => $wallet,
                'tab' => $tab,
                'entries' => $entries,
                'page' => $page,
                'hasMore' => count($entries) === $perPage,
            ]),
        ]);
    }
}
