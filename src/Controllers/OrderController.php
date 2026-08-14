<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\NotificationService;

final class OrderController
{
    public function index(Request $request): void
    {
        $user = Session::get('user');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT o.*, p.name AS product_name, p.image_path, mk.name AS marketplace_name
            FROM orders o
            JOIN products p ON p.id = o.product_id
            JOIN marketplaces mk ON mk.id = o.marketplace_id
            WHERE o.user_id = ? ORDER BY o.created_at DESC');
        $stmt->execute([$user['id']]);
        $orders = $stmt->fetchAll();

        echo view('layouts.app', [
            'pageTitle' => 'Orders',
            'unreadCount' => NotificationService::unreadCount((int) $user['id']),
            'content' => view('orders.index', ['orders' => $orders]),
        ]);
    }
}
