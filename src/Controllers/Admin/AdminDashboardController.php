<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;

final class AdminDashboardController
{
    public function index(Request $request): void
    {
        $pdo = Database::connection();

        $stats = [
            'total_users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'verified_users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL')->fetchColumn(),
            'new_users_7d' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE created_at > NOW() - INTERVAL 7 DAY')->fetchColumn(),
            'total_deposits_usd' => (string) $pdo->query('SELECT COALESCE(SUM(usd_amount_credited),0) FROM deposits WHERE status = "approved"')->fetchColumn(),
            'pending_deposits' => (int) $pdo->query('SELECT COUNT(*) FROM deposits WHERE status = "pending"')->fetchColumn(),
            'pending_withdrawals' => (int) $pdo->query('SELECT COUNT(*) FROM withdrawals WHERE status = "pending"')->fetchColumn(),
            'total_withdrawn_usd' => (string) $pdo->query('SELECT COALESCE(SUM(amount_usd),0) FROM withdrawals WHERE status IN ("paid","completed")')->fetchColumn(),
            'total_cashback_usd' => (string) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM cashback_transactions WHERE status = "released"')->fetchColumn(),
            'total_referral_usd' => (string) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM referral_rewards WHERE status = "credited" AND reward_type = "deposit_bonus"')->fetchColumn(),
            'total_orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'total_products' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_published = 1')->fetchColumn(),
        ];

        $stmt = $pdo->query('SELECT d.*, u.full_name, u.email FROM deposits d JOIN users u ON u.id = d.user_id
            WHERE d.status = "pending" ORDER BY d.created_at ASC LIMIT 5');
        $pendingDeposits = $stmt->fetchAll();

        $stmt = $pdo->query('SELECT w.*, u.full_name, u.email FROM withdrawals w JOIN users u ON u.id = w.user_id
            WHERE w.status = "pending" ORDER BY w.created_at ASC LIMIT 5');
        $pendingWithdrawals = $stmt->fetchAll();

        $stmt = $pdo->query('SELECT mk.name, COUNT(o.id) AS order_count, COALESCE(SUM(o.cashback_amount),0) AS cashback_total
            FROM marketplaces mk LEFT JOIN orders o ON o.marketplace_id = mk.id
            GROUP BY mk.id ORDER BY order_count DESC LIMIT 6');
        $marketplacePerformance = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Dashboard',
            'content' => view('admin.dashboard.index', [
                'stats' => $stats,
                'pendingDeposits' => $pendingDeposits,
                'pendingWithdrawals' => $pendingWithdrawals,
                'marketplacePerformance' => $marketplacePerformance,
            ]),
        ]);
    }
}
