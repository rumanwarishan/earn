<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\WithdrawalService;
use App\Support\ValidationException;
use RuntimeException;

final class AdminWithdrawalController
{
    public function index(Request $request): void
    {
        require_admin_permission('withdrawals.view');
        $pdo = Database::connection();

        $status = (string) $request->query('status', 'pending');
        $where = $status !== 'all' ? 'WHERE w.status = ?' : '';
        $params = $status !== 'all' ? [$status] : [];

        $stmt = $pdo->prepare("SELECT w.*, u.full_name, u.email FROM withdrawals w JOIN users u ON u.id = w.user_id
            {$where} ORDER BY w.created_at DESC LIMIT 100");
        $stmt->execute($params);
        $withdrawals = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Withdrawals',
            'content' => view('admin.withdrawals.index', ['withdrawals' => $withdrawals, 'status' => $status]),
        ]);
    }

    public function show(Request $request): void
    {
        require_admin_permission('withdrawals.view');
        $id = (int) $request->param('id');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT w.*, u.full_name, u.email FROM withdrawals w JOIN users u ON u.id = w.user_id WHERE w.id = ?');
        $stmt->execute([$id]);
        $withdrawal = $stmt->fetch();
        if (!$withdrawal) {
            abort(404, 'Withdrawal not found');
        }

        $stmt = $pdo->prepare('SELECT wr.*, a.name AS admin_name FROM withdrawal_reviews wr LEFT JOIN admin_users a ON a.id = wr.admin_id WHERE wr.withdrawal_id = ? ORDER BY wr.created_at DESC');
        $stmt->execute([$id]);
        $reviews = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Withdrawal #' . $id,
            'content' => view('admin.withdrawals.show', ['withdrawal' => $withdrawal, 'reviews' => $reviews]),
        ]);
    }

    private function act(Request $request, callable $action): void
    {
        require_admin_permission('withdrawals.approve');
        $id = (int) $request->param('id');
        try {
            $action($id);
            flash_success('Withdrawal updated.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        } catch (RuntimeException $e) {
            flash_errors(['withdrawal' => $e->getMessage()]);
        }
        redirect('/admin/withdrawals/' . $id);
    }

    public function approve(Request $request): void
    {
        $admin = Session::get('admin');
        $this->act($request, fn ($id) => WithdrawalService::approve($id, $admin['id'], $request->ip(), (string) $request->input('notes', '')));
    }

    public function reject(Request $request): void
    {
        $admin = Session::get('admin');
        $this->act($request, fn ($id) => WithdrawalService::reject($id, $admin['id'], (string) $request->input('reason', ''), $request->ip()));
    }

    public function processing(Request $request): void
    {
        $admin = Session::get('admin');
        $this->act($request, fn ($id) => WithdrawalService::markProcessing($id, $admin['id'], $request->ip(), (string) $request->input('notes', '')));
    }

    public function paid(Request $request): void
    {
        $admin = Session::get('admin');
        $this->act($request, fn ($id) => WithdrawalService::markPaid(
            $id, $admin['id'],
            (string) $request->input('btc_amount_paid', '0'),
            (string) $request->input('btc_rate_used', ''),
            (string) $request->input('payout_txid', ''),
            $request->ip(),
            (string) $request->input('notes', '')
        ));
    }

    public function completed(Request $request): void
    {
        $admin = Session::get('admin');
        $this->act($request, fn ($id) => WithdrawalService::markCompleted($id, $admin['id'], $request->ip()));
    }
}
