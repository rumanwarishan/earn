<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\WalletService;

final class AdminUserController
{
    public function index(Request $request): void
    {
        require_admin_permission('users.view');
        $pdo = Database::connection();

        $q = trim((string) $request->query('q', ''));
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = 'WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.referral_code LIKE ?';
            $params = ["%{$q}%", "%{$q}%", "%{$q}%"];
        }

        $stmt = $pdo->prepare("SELECT u.*, m.name AS level_name, w.deposited_balance, w.cashback_balance, w.referral_balance
            FROM users u
            LEFT JOIN membership_levels m ON m.id = u.membership_level_id
            LEFT JOIN wallets w ON w.user_id = u.id
            {$where} ORDER BY u.created_at DESC LIMIT 100");
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Users',
            'content' => view('admin.users.index', ['users' => $users, 'q' => $q]),
        ]);
    }

    public function show(Request $request): void
    {
        require_admin_permission('users.view');
        $id = (int) $request->param('id');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT u.*, m.name AS level_name FROM users u LEFT JOIN membership_levels m ON m.id = u.membership_level_id WHERE u.id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            abort(404, 'User not found');
        }

        $wallet = WalletService::getOrCreateWallet($id, $pdo);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = "approved"');
        $stmt->execute([$id]);
        $approvedDeposits = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status IN ("paid","completed")');
        $stmt->execute([$id]);
        $completedWithdrawals = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = ?');
        $stmt->execute([$user['referred_by_user_id']]);
        $referrer = $stmt->fetch();

        echo view('layouts.admin', [
            'pageTitle' => $user['full_name'],
            'content' => view('admin.users.show', [
                'user' => $user, 'wallet' => $wallet, 'approvedDeposits' => $approvedDeposits,
                'completedWithdrawals' => $completedWithdrawals, 'referrer' => $referrer ?: null,
            ]),
        ]);
    }

    public function toggleStatus(Request $request): void
    {
        require_admin_permission('users.update');
        $id = (int) $request->param('id');
        $newStatus = (string) $request->input('status', 'active');
        $admin = Session::get('admin');

        if (!in_array($newStatus, ['active', 'suspended', 'banned'], true)) {
            abort(404);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $oldStatus = $stmt->fetchColumn();

        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $id]);

        AuditLogger::log('admin', $admin['id'], 'user.status_changed', 'user', $id,
            ['status' => $oldStatus], ['status' => $newStatus], (string) $request->input('reason', ''), $request->ip());

        flash_success('User status updated to ' . $newStatus . '.');
        redirect('/admin/users/' . $id);
    }
}
