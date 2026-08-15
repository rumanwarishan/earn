<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\NotificationService;
use App\Services\WalletService;

final class ShopController
{
    public function index(Request $request): void
    {
        $pdo = Database::connection();

        $q = trim((string) $request->query('q', ''));
        $marketplace = (string) $request->query('marketplace', '');
        $category = (string) $request->query('category', '');
        $sort = (string) $request->query('sort', 'newest');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        $where = ['p.is_published = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.tags LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        if ($marketplace !== '') {
            $where[] = 'mk.slug = ?';
            $params[] = $marketplace;
        }
        if ($category !== '') {
            $where[] = 'c.slug = ?';
            $params[] = $category;
        }

        $orderBy = match ($sort) {
            'price_low' => 'p.display_price ASC',
            'price_high' => 'p.display_price DESC',
            'cashback' => 'p.cashback_value DESC',
            default => 'p.created_at DESC',
        };

        $whereSql = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products p
            JOIN marketplaces mk ON mk.id = p.marketplace_id
            LEFT JOIN product_categories c ON c.id = p.category_id
            WHERE {$whereSql}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT p.*, mk.name AS marketplace_name, mk.slug AS marketplace_slug FROM products p
            JOIN marketplaces mk ON mk.id = p.marketplace_id
            LEFT JOIN product_categories c ON c.id = p.category_id
            WHERE {$whereSql} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        $marketplaces = $pdo->query('SELECT * FROM marketplaces WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
        $categories = $pdo->query('SELECT * FROM product_categories WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

        $user = Session::get('user');

        echo view('layouts.app', [
            'pageTitle' => 'Shop',
            'unreadCount' => $user ? NotificationService::unreadCount((int) $user['id']) : 0,
            'content' => view('shop.index', [
                'products' => $products,
                'marketplaces' => $marketplaces,
                'categories' => $categories,
                'q' => $q,
                'marketplace' => $marketplace,
                'category' => $category,
                'sort' => $sort,
                'page' => $page,
                'totalPages' => (int) ceil($total / $perPage),
            ]),
        ]);
    }

    public function show(Request $request): void
    {
        $slug = (string) $request->param('slug');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT p.*, mk.name AS marketplace_name, mk.slug AS marketplace_slug FROM products p
            JOIN marketplaces mk ON mk.id = p.marketplace_id WHERE p.slug = ? AND p.is_published = 1');
        $stmt->execute([$slug]);
        $product = $stmt->fetch();

        if (!$product) {
            abort(404, 'Product not found');
        }

        $user = Session::get('user');
        $spendable = null;
        if ($user) {
            $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);
            $spendable = WalletService::spendableBalance($wallet);
        }

        echo view('layouts.app', [
            'pageTitle' => $product['name'],
            'robots' => 'index,follow',
            'unreadCount' => $user ? NotificationService::unreadCount((int) $user['id']) : 0,
            'content' => view('shop.show', [
                'product' => $product,
                'user' => $user,
                'spendable' => $spendable,
            ]),
        ]);
    }

    public function go(Request $request): void
    {
        $productId = (int) $request->param('id');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_published = 1');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            abort(404, 'Product not found');
        }

        $user = Session::get('user');
        $trackingUrl = $product['affiliate_url'] ?: $product['external_url'];

        $pdo->prepare('INSERT INTO product_clicks (click_uuid, user_id, product_id, marketplace_id, tracking_url, ip_address, user_agent)
            VALUES (?,?,?,?,?,?,?)')
            ->execute([
                uuid4(), $user['id'] ?? null, $productId, $product['marketplace_id'], $trackingUrl,
                $request->ip(), $request->userAgent(),
            ]);

        redirect($trackingUrl);
    }
}
