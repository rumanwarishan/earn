<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\NotificationService;
use App\Services\WalletService;

final class DashboardController
{
    public function index(Request $request): void
    {
        $user = Session::get('user');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT u.*, m.name AS level_name, m.slug AS level_slug, m.color AS level_color,
            m.min_total_deposited AS level_next_deposit, m.cashback_multiplier
            FROM users u LEFT JOIN membership_levels m ON m.id = u.membership_level_id WHERE u.id = ?');
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();

        $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM wallet_ledger
            WHERE user_id = ? AND type IN ("cashback_release") AND DATE(created_at) = CURDATE() AND status = "posted"');
        $stmt->execute([$user['id']]);
        $todayCashback = (string) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $totalOrders = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM referral_relationships WHERE referrer_user_id = ? AND level = 1');
        $stmt->execute([$user['id']]);
        $directReferrals = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT * FROM wallet_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
        $stmt->execute([$user['id']]);
        $recentActivity = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT p.*, mk.name AS marketplace_name FROM products p
            JOIN marketplaces mk ON mk.id = p.marketplace_id
            WHERE p.is_published = 1 AND p.is_featured = 1 ORDER BY p.sort_order ASC LIMIT 4');
        $stmt->execute();
        $featuredProducts = $stmt->fetchAll();

        $nextLevel = null;
        if ($profile['level_slug'] ?? null) {
            $stmt = $pdo->prepare('SELECT * FROM membership_levels WHERE sort_order > (SELECT sort_order FROM membership_levels WHERE slug = ?) AND is_active = 1 ORDER BY sort_order ASC LIMIT 1');
            $stmt->execute([$profile['level_slug']]);
            $nextLevel = $stmt->fetch() ?: null;
        }

        echo view('layouts.app', [
            'pageTitle' => 'Dashboard',
            'unreadCount' => NotificationService::unreadCount((int) $user['id']),
            'content' => view('dashboard.index', [
                'profile' => $profile,
                'wallet' => $wallet,
                'todayCashback' => $todayCashback,
                'totalOrders' => $totalOrders,
                'directReferrals' => $directReferrals,
                'recentActivity' => $recentActivity,
                'featuredProducts' => $featuredProducts,
                'nextLevel' => $nextLevel,
            ]),
        ]);
    }
}
