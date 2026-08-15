<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\DepositService;
use App\Support\ValidationException;
use RuntimeException;

final class AdminDepositController
{
    public function index(Request $request): void
    {
        require_admin_permission('deposits.view');
        $pdo = Database::connection();

        $status = (string) $request->query('status', 'pending');
        $where = $status !== 'all' ? 'WHERE d.status = ?' : '';
        $params = $status !== 'all' ? [$status] : [];

        $stmt = $pdo->prepare("SELECT d.*, u.full_name, u.email FROM deposits d JOIN users u ON u.id = d.user_id
            {$where} ORDER BY d.created_at DESC LIMIT 100");
        $stmt->execute($params);
        $deposits = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Deposits',
            'content' => view('admin.deposits.index', ['deposits' => $deposits, 'status' => $status]),
        ]);
    }

    public function show(Request $request): void
    {
        require_admin_permission('deposits.view');
        $id = (int) $request->param('id');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT d.*, u.full_name, u.email FROM deposits d JOIN users u ON u.id = d.user_id WHERE d.id = ?');
        $stmt->execute([$id]);
        $deposit = $stmt->fetch();
        if (!$deposit) {
            abort(404, 'Deposit not found');
        }

        $stmt = $pdo->prepare('SELECT dr.*, a.name AS admin_name FROM deposit_reviews dr LEFT JOIN admin_users a ON a.id = dr.admin_id WHERE dr.deposit_id = ? ORDER BY dr.created_at DESC');
        $stmt->execute([$id]);
        $reviews = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Deposit #' . $id,
            'content' => view('admin.deposits.show', ['deposit' => $deposit, 'reviews' => $reviews]),
        ]);
    }

    public function approve(Request $request): void
    {
        require_admin_permission('deposits.approve');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');

        try {
            DepositService::approve($id, $admin['id'], (string) $request->input('btc_usd_rate', ''), $request->ip(), (string) $request->input('notes', ''));
            flash_success('Deposit approved and credited.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        } catch (RuntimeException $e) {
            flash_errors(['deposit' => $e->getMessage()]);
        }
        redirect('/admin/deposits/' . $id);
    }

    public function reject(Request $request): void
    {
        require_admin_permission('deposits.approve');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');

        try {
            DepositService::reject($id, $admin['id'], (string) $request->input('reason', ''), $request->ip());
            flash_success('Deposit rejected.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        } catch (RuntimeException $e) {
            flash_errors(['deposit' => $e->getMessage()]);
        }
        redirect('/admin/deposits/' . $id);
    }
}
