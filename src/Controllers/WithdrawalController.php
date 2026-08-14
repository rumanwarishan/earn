<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\NotificationService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\ValidationException;
use RuntimeException;

final class WithdrawalController
{
    public function show(Request $request): void
    {
        $user = Session::get('user');
        $pdo = Database::connection();
        $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);

        $stmt = $pdo->prepare('SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$user['id']]);
        $recentWithdrawals = $stmt->fetchAll();

        echo view('layouts.app', [
            'pageTitle' => 'Withdraw',
            'unreadCount' => NotificationService::unreadCount((int) $user['id']),
            'content' => view('wallet.withdraw', [
                'wallet' => $wallet,
                'spendable' => WalletService::spendableBalance($wallet),
                'minWithdrawal' => setting('min_withdrawal_usd', '1000'),
                'maxWithdrawal' => setting('max_withdrawal_usd', '50000'),
                'recentWithdrawals' => $recentWithdrawals,
            ]),
        ]);
    }

    public function submit(Request $request): void
    {
        $user = Session::get('user');
        try {
            WithdrawalService::request(
                (int) $user['id'],
                (string) $request->input('amount', ''),
                (string) $request->input('btc_address', ''),
                $request->ip()
            );
            flash_success('Withdrawal request submitted. You will be notified once it is reviewed.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        } catch (RuntimeException $e) {
            flash_errors(['withdrawal' => $e->getMessage()]);
        }
        redirect('/wallet/withdraw');
    }

    public function cancel(Request $request): void
    {
        $user = Session::get('user');
        $id = (int) $request->param('id');
        try {
            WithdrawalService::cancel($id, (int) $user['id'], $request->ip());
            flash_success('Withdrawal request cancelled.');
        } catch (RuntimeException $e) {
            flash_errors(['withdrawal' => $e->getMessage()]);
        }
        redirect('/wallet/withdraw');
    }
}
