<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\DepositService;
use App\Services\NotificationService;
use App\Services\QrCodeService;
use App\Support\ValidationException;

final class DepositController
{
    public function show(Request $request): void
    {
        $user = Session::get('user');
        $address = (string) setting('btc_deposit_address', '');

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$user['id']]);
        $recentDeposits = $stmt->fetchAll();

        echo view('layouts.app', [
            'pageTitle' => 'Deposit',
            'unreadCount' => NotificationService::unreadCount((int) $user['id']),
            'content' => view('wallet.deposit', [
                'address' => $address,
                'qrDataUri' => $address ? QrCodeService::dataUri($address) : null,
                'minDeposit' => setting('min_deposit_usd', '50'),
                'networkNote' => setting('btc_deposit_network_note', ''),
                'recentDeposits' => $recentDeposits,
            ]),
        ]);
    }

    public function submit(Request $request): void
    {
        $user = Session::get('user');
        try {
            DepositService::submit(
                (int) $user['id'],
                (string) $request->input('btc_amount_claimed', ''),
                (string) $request->input('txid', ''),
                $request->ip()
            );
            flash_success('Deposit submitted! An admin will review it shortly.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        }
        redirect('/wallet/deposit');
    }
}
