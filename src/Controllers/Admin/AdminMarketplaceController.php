<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Services\AuditLogger;
use App\Services\ProductService;
use App\Core\Session;

final class AdminMarketplaceController
{
    public function index(Request $request): void
    {
        require_admin_permission('marketplaces.view');
        $pdo = Database::connection();
        $marketplaces = $pdo->query('SELECT * FROM marketplaces ORDER BY sort_order')->fetchAll();
        $categories = $pdo->query('SELECT * FROM product_categories ORDER BY sort_order')->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Marketplaces',
            'content' => view('admin.marketplaces.index', ['marketplaces' => $marketplaces, 'categories' => $categories]),
        ]);
    }

    public function store(Request $request): void
    {
        require_admin_permission('marketplaces.create');
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            flash_errors(['name' => 'Marketplace name is required.']);
            redirect('/admin/marketplaces');
        }

        $slug = ProductService::slugify($name);
        $pdo->prepare('INSERT INTO marketplaces (name, slug, website_url, is_active) VALUES (?,?,?,1)')
            ->execute([$name, $slug, (string) $request->input('website_url', '')]);

        AuditLogger::log('admin', $admin['id'], 'marketplace.created', 'marketplace', (int) $pdo->lastInsertId(), null, ['name' => $name], null, $request->ip());
        flash_success('Marketplace added.');
        redirect('/admin/marketplaces');
    }

    public function storeCategory(Request $request): void
    {
        require_admin_permission('marketplaces.create');
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            flash_errors(['name' => 'Category name is required.']);
            redirect('/admin/marketplaces');
        }

        $slug = ProductService::slugify($name);
        $pdo->prepare('INSERT INTO product_categories (name, slug) VALUES (?,?)')->execute([$name, $slug]);

        AuditLogger::log('admin', $admin['id'], 'category.created', 'product_category', (int) $pdo->lastInsertId(), null, ['name' => $name], null, $request->ip());
        flash_success('Category added.');
        redirect('/admin/marketplaces');
    }

    public function toggleActive(Request $request): void
    {
        require_admin_permission('marketplaces.update');
        $id = (int) $request->param('id');
        $pdo = Database::connection();
        $pdo->prepare('UPDATE marketplaces SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash_success('Marketplace updated.');
        redirect('/admin/marketplaces');
    }
}
