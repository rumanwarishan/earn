<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\OrderService;
use App\Support\ValidationException;
use RuntimeException;

final class AdminOrderController
{
    public function index(Request $request): void
    {
        require_admin_permission('orders.view');
        $pdo = Database::connection();

        $status = (string) $request->query('status', 'all');
        $where = $status !== 'all' ? 'WHERE o.status = ?' : '';
        $params = $status !== 'all' ? [$status] : [];

        $stmt = $pdo->prepare("SELECT o.*, u.full_name, u.email, p.name AS product_name, mk.name AS marketplace_name FROM orders o
            JOIN users u ON u.id = o.user_id JOIN products p ON p.id = o.product_id JOIN marketplaces mk ON mk.id = o.marketplace_id
            {$where} ORDER BY o.created_at DESC LIMIT 150");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Orders',
            'content' => view('admin.orders.index', ['orders' => $orders, 'status' => $status]),
        ]);
    }

    public function create(Request $request): void
    {
        require_admin_permission('orders.create');
        $pdo = Database::connection();
        echo view('layouts.admin', [
            'pageTitle' => 'Record order',
            'content' => view('admin.orders.form', [
                'users' => $pdo->query('SELECT id, full_name, email FROM users ORDER BY full_name LIMIT 500')->fetchAll(),
                'products' => $pdo->query('SELECT id, name FROM products WHERE is_published = 1 ORDER BY name')->fetchAll(),
            ]),
        ]);
    }

    public function store(Request $request): void
    {
        require_admin_permission('orders.create');
        $admin = Session::get('admin');

        try {
            $orderId = OrderService::create(
                (int) $request->input('user_id'),
                (int) $request->input('product_id'),
                null,
                (string) $request->input('external_order_reference', '') ?: null,
                $admin['id'],
                $request->ip(),
                (string) $request->input('notes', '')
            );
            flash_success('Order recorded.');
            redirect('/admin/orders/' . $orderId);
        } catch (ValidationException $e) {
            flash_errors($e->errors());
            redirect('/admin/orders/new');
        }
    }

    public function show(Request $request): void
    {
        require_admin_permission('orders.view');
        $id = (int) $request->param('id');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT o.*, u.full_name, u.email, p.name AS product_name, mk.name AS marketplace_name FROM orders o
            JOIN users u ON u.id = o.user_id JOIN products p ON p.id = o.product_id JOIN marketplaces mk ON mk.id = o.marketplace_id
            WHERE o.id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) {
            abort(404, 'Order not found');
        }

        echo view('layouts.admin', ['pageTitle' => 'Order #' . $id, 'content' => view('admin.orders.show', ['order' => $order])]);
    }

    public function updateStatus(Request $request): void
    {
        require_admin_permission('orders.update');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');

        try {
            OrderService::updateStatus($id, (string) $request->input('status'), $admin['id'], $request->ip(), (string) $request->input('notes', ''));
            flash_success('Order status updated.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        } catch (RuntimeException $e) {
            flash_errors(['order' => $e->getMessage()]);
        }
        redirect('/admin/orders/' . $id);
    }

    public function reverseCashback(Request $request): void
    {
        require_admin_permission('orders.update');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');

        try {
            OrderService::reverseCashback($id, $admin['id'], (string) $request->input('reason', ''), $request->ip());
            flash_success('Cashback reversed.');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
        } catch (RuntimeException $e) {
            flash_errors(['order' => $e->getMessage()]);
        }
        redirect('/admin/orders/' . $id);
    }
}
