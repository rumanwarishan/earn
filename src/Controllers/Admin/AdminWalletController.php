<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\WalletService;
use App\Support\ValidationException;
use RuntimeException;

final class AdminWalletController
{
    public function index(Request $request): void
    {
        require_admin_permission('ledger.view');
        $pdo = Database::connection();

        $q = trim((string) $request->query('q', ''));
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = 'WHERE u.full_name LIKE ? OR u.email LIKE ?';
            $params = ["%{$q}%", "%{$q}%"];
        }

        $stmt = $pdo->prepare("SELECT wl.*, u.full_name, u.email FROM wallet_ledger wl JOIN users u ON u.id = wl.user_id
            {$where} ORDER BY wl.created_at DESC LIMIT 100");
        $stmt->execute($params);
        $entries = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Wallet Ledger',
            'content' => view('admin.wallets.index', ['entries' => $entries, 'q' => $q]),
        ]);
    }

    public function userWallet(Request $request): void
    {
        require_admin_permission('wallets.view');
        $userId = (int) $request->param('id');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            abort(404, 'User not found');
        }

        $wallet = WalletService::getOrCreateWallet($userId, $pdo);

        $stmt = $pdo->prepare('SELECT * FROM wallet_ledger WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$userId]);
        $entries = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Wallet: ' . $user['full_name'],
            'content' => view('admin.wallets.show', ['user' => $user, 'wallet' => $wallet, 'entries' => $entries]),
        ]);
    }

    public function adjust(Request $request): void
    {
        require_admin_permission('wallets.adjust');
        $userId = (int) $request->param('id');
        $admin = Session::get('admin');

        $direction = (string) $request->input('direction', 'credit');
        $balanceField = (string) $request->input('balance_field', 'deposited');
        $amount = (string) $request->input('amount', '0');
        $reason = (string) $request->input('reason', '');

        try {
            if (trim($reason) === '') {
                throw new ValidationException(['reason' => 'A reason is required for manual wallet adjustments.']);
            }
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount) || bccomp($amount, '0', 2) <= 0) {
                throw new ValidationException(['amount' => 'Enter a valid positive amount.']);
            }

            $signedAmount = $direction === 'debit' ? bcmul($amount, '-1', 2) : $amount;
            $type = $direction === 'debit' ? 'admin_debit' : 'admin_credit';

            WalletService::applyLedgerEntry(
                $userId, $balanceField, $signedAmount, $type,
                'manual_adjustment', null, 'Manual admin adjustment: ' . $reason,
                'admin', $admin['id'], $reason, $request->ip()
            );

            NotificationService::notify($userId, 'wallet_adjustment', 'Wallet adjusted by admin',
                ($direction === 'credit' ? 'You were credited ' : 'You were debited ') . money($amount) . '. Reason: ' . $reason);

            AuditLogger::log('admin', $admin['id'], 'wallet.manual_' . $direction, 'user', $userId, null,
                ['balance_field' => $balanceField, 'amount' => $amount], $reason, $request->ip());

            flash_success('Wallet adjusted successfully.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        } catch (RuntimeException $e) {
            flash_errors(['adjustment' => $e->getMessage()]);
        }

        redirect('/admin/wallets/' . $userId);
    }

    public function toggleFreeze(Request $request): void
    {
        require_admin_permission('wallets.adjust');
        $userId = (int) $request->param('id');
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $wallet = WalletService::getOrCreateWallet($userId, $pdo);
        $newState = $wallet['is_frozen'] ? 0 : 1;

        $pdo->prepare('UPDATE wallets SET is_frozen = ? WHERE user_id = ?')->execute([$newState, $userId]);

        AuditLogger::log('admin', $admin['id'], $newState ? 'wallet.frozen' : 'wallet.unfrozen', 'user', $userId,
            ['is_frozen' => (int) $wallet['is_frozen']], ['is_frozen' => $newState], null, $request->ip());

        flash_success($newState ? 'Wallet frozen.' : 'Wallet unfrozen.');
        redirect('/admin/wallets/' . $userId);
    }
}
