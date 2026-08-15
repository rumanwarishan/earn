<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\FileUploadService;
use App\Services\ProductService;
use App\Support\ValidationException;
use PDO;

final class AdminProductController
{
    public function index(Request $request): void
    {
        require_admin_permission('products.view');
        $pdo = Database::connection();

        $q = trim((string) $request->query('q', ''));
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = 'p.name LIKE ?';
            $params[] = "%{$q}%";
        }
        $whereSql = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT p.*, mk.name AS marketplace_name FROM products p
            JOIN marketplaces mk ON mk.id = p.marketplace_id WHERE {$whereSql} ORDER BY p.created_at DESC LIMIT 200");
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        echo view('layouts.admin', [
            'pageTitle' => 'Products',
            'content' => view('admin.products.index', ['products' => $products, 'q' => $q]),
        ]);
    }

    public function create(Request $request): void
    {
        require_admin_permission('products.create');
        $pdo = Database::connection();
        echo view('layouts.admin', [
            'pageTitle' => 'New product',
            'content' => view('admin.products.form', [
                'product' => null,
                'marketplaces' => $pdo->query('SELECT * FROM marketplaces ORDER BY name')->fetchAll(),
                'categories' => $pdo->query('SELECT * FROM product_categories ORDER BY name')->fetchAll(),
            ]),
        ]);
    }

    public function store(Request $request): void
    {
        require_admin_permission('products.create');
        $this->save($request, null);
    }

    public function edit(Request $request): void
    {
        require_admin_permission('products.update');
        $id = (int) $request->param('id');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) {
            abort(404, 'Product not found');
        }

        echo view('layouts.admin', [
            'pageTitle' => 'Edit product',
            'content' => view('admin.products.form', [
                'product' => $product,
                'marketplaces' => $pdo->query('SELECT * FROM marketplaces ORDER BY name')->fetchAll(),
                'categories' => $pdo->query('SELECT * FROM product_categories ORDER BY name')->fetchAll(),
            ]),
        ]);
    }

    public function update(Request $request): void
    {
        require_admin_permission('products.update');
        $this->save($request, (int) $request->param('id'));
    }

    private function save(Request $request, ?int $id): void
    {
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $data = $request->only([
            'name', 'description', 'short_description', 'marketplace_id', 'category_id',
            'external_url', 'affiliate_url', 'original_price', 'display_price',
            'cashback_type', 'cashback_value', 'tags', 'is_featured', 'is_published',
            'fulfillment_type', 'stock_quantity',
        ]);

        try {
            if (trim((string) $data['name']) === '') {
                throw new ValidationException(['name' => 'Product name is required.']);
            }
            $fulfillmentType = in_array($data['fulfillment_type'], ['affiliate', 'dropship'], true) ? $data['fulfillment_type'] : 'affiliate';
            if ($fulfillmentType === 'affiliate' && !filter_var($data['external_url'], FILTER_VALIDATE_URL)) {
                throw new ValidationException(['external_url' => 'A valid external URL is required.']);
            }
            if (!preg_match('/^\d+(\.\d{1,2})?$/', (string) $data['display_price'])) {
                throw new ValidationException(['display_price' => 'Enter a valid price.']);
            }
            $stockQuantity = null;
            if ($data['stock_quantity'] !== null && trim((string) $data['stock_quantity']) !== '') {
                if (!preg_match('/^\d+$/', (string) $data['stock_quantity'])) {
                    throw new ValidationException(['stock_quantity' => 'Enter a valid whole number.']);
                }
                $stockQuantity = (int) $data['stock_quantity'];
            }

            $imagePath = null;
            $file = $request->file('image');
            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $imagePath = FileUploadService::storeProductImage($file);
            }

            $isFeatured = isset($data['is_featured']) ? 1 : 0;
            $isPublished = isset($data['is_published']) ? 1 : 0;
            $externalUrl = (string) ($data['external_url'] ?: '');

            if ($id === null) {
                $slug = ProductService::uniqueSlug($data['name'], $pdo);
                $stmt = $pdo->prepare('INSERT INTO products
                    (name, slug, description, short_description, image_path, marketplace_id, category_id,
                     external_url, affiliate_url, original_price, display_price, cashback_type, cashback_value,
                     tags, is_featured, is_published, fulfillment_type, stock_quantity)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([
                    $data['name'], $slug, $data['description'], $data['short_description'], $imagePath,
                    $data['marketplace_id'] ?: null, $data['category_id'] ?: null,
                    $externalUrl, $data['affiliate_url'] ?: ($externalUrl ?: null),
                    $data['original_price'] ?: null, $data['display_price'], $data['cashback_type'] ?: 'percentage',
                    $data['cashback_value'] ?: 0, $data['tags'], $isFeatured, $isPublished,
                    $fulfillmentType, $stockQuantity,
                ]);
                $newId = (int) $pdo->lastInsertId();
                AuditLogger::log('admin', $admin['id'], 'product.created', 'product', $newId, null, $data, null, $request->ip());
                flash_success('Product created.');
                redirect('/admin/products/' . $newId . '/edit');
            }

            $sql = 'UPDATE products SET name=?, description=?, short_description=?, marketplace_id=?, category_id=?,
                external_url=?, affiliate_url=?, original_price=?, display_price=?, cashback_type=?, cashback_value=?,
                tags=?, is_featured=?, is_published=?, fulfillment_type=?, stock_quantity=?';
            $params = [
                $data['name'], $data['description'], $data['short_description'],
                $data['marketplace_id'] ?: null, $data['category_id'] ?: null,
                $externalUrl, $data['affiliate_url'] ?: ($externalUrl ?: null),
                $data['original_price'] ?: null, $data['display_price'], $data['cashback_type'] ?: 'percentage',
                $data['cashback_value'] ?: 0, $data['tags'], $isFeatured, $isPublished,
                $fulfillmentType, $stockQuantity,
            ];
            if ($imagePath) {
                $sql .= ', image_path = ?';
                $params[] = $imagePath;
            }
            $sql .= ' WHERE id = ?';
            $params[] = $id;

            $pdo->prepare($sql)->execute($params);
            AuditLogger::log('admin', $admin['id'], 'product.updated', 'product', $id, null, $data, null, $request->ip());
            flash_success('Product updated.');
            redirect('/admin/products/' . $id . '/edit');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
            redirect($id ? '/admin/products/' . $id . '/edit' : '/admin/products/new');
        }
    }

    public function archive(Request $request): void
    {
        require_admin_permission('products.delete');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');

        Database::connection()->prepare('UPDATE products SET is_published = 0 WHERE id = ?')->execute([$id]);
        AuditLogger::log('admin', $admin['id'], 'product.archived', 'product', $id, null, null, null, $request->ip());
        flash_success('Product archived (unpublished).');
        redirect('/admin/products');
    }

    public function duplicate(Request $request): void
    {
        require_admin_permission('products.create');
        $id = (int) $request->param('id');
        $admin = Session::get('admin');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) {
            abort(404);
        }

        $newSlug = ProductService::uniqueSlug($product['name'] . ' copy', $pdo);
        $stmt = $pdo->prepare('INSERT INTO products
            (name, slug, description, short_description, image_path, marketplace_id, category_id, external_url,
             affiliate_url, original_price, display_price, cashback_type, cashback_value, tags, is_featured, is_published)
            SELECT CONCAT(name, " (copy)"), ?, description, short_description, image_path, marketplace_id, category_id,
             external_url, affiliate_url, original_price, display_price, cashback_type, cashback_value, tags, 0, 0
            FROM products WHERE id = ?');
        $stmt->execute([$newSlug, $id]);
        $newId = (int) $pdo->lastInsertId();

        AuditLogger::log('admin', $admin['id'], 'product.duplicated', 'product', $newId, null, ['source_id' => $id], null, $request->ip());
        flash_success('Product duplicated as a draft.');
        redirect('/admin/products/' . $newId . '/edit');
    }

    public function exportCsv(Request $request): void
    {
        require_admin_permission('products.view');
        $pdo = Database::connection();

        $stmt = $pdo->query('SELECT p.*, mk.slug AS marketplace_slug, c.slug AS category_slug FROM products p
            JOIN marketplaces mk ON mk.id = p.marketplace_id LEFT JOIN product_categories c ON c.id = p.category_id
            ORDER BY p.id');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products-export-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['name', 'marketplace', 'category', 'external_url', 'affiliate_url', 'original_price',
            'display_price', 'cashback_type', 'cashback_value', 'short_description', 'is_featured', 'is_published'], ',', '"', '\\');

        foreach ($stmt as $p) {
            fputcsv($out, array_map([ProductService::class, 'csvSafe'], [
                $p['name'], $p['marketplace_slug'], $p['category_slug'] ?? '', $p['external_url'], $p['affiliate_url'],
                $p['original_price'], $p['display_price'], $p['cashback_type'], $p['cashback_value'],
                $p['short_description'], $p['is_featured'] ? '1' : '0', $p['is_published'] ? '1' : '0',
            ]), ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    public function importPreview(Request $request): void
    {
        require_admin_permission('products.create');
        $file = $request->file('csv_file');

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash_errors(['csv_file' => 'Please choose a valid CSV file.']);
            redirect('/admin/products/import');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            flash_errors(['csv_file' => 'CSV file must be smaller than 2MB.']);
            redirect('/admin/products/import');
        }

        $pdo = Database::connection();
        $marketplaceBySlug = [];
        foreach ($pdo->query('SELECT id, slug FROM marketplaces') as $m) {
            $marketplaceBySlug[$m['slug']] = (int) $m['id'];
        }
        $categoryBySlug = [];
        foreach ($pdo->query('SELECT id, slug FROM product_categories') as $c) {
            $categoryBySlug[$c['slug']] = (int) $c['id'];
        }

        $handle = fopen($file['tmp_name'], 'r');
        $headers = fgetcsv($handle, escape: '\\');
        if (!$headers || !in_array('name', $headers, true)) {
            flash_errors(['csv_file' => 'CSV must include a header row with at least a "name" column.']);
            redirect('/admin/products/import');
        }

        $results = [];
        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = array_combine($headers, array_pad($row, count($headers), ''));
            $results[] = ProductService::validateRow($assoc, $pdo, $marketplaceBySlug, $categoryBySlug);
        }
        fclose($handle);

        $token = bin2hex(random_bytes(16));
        $validRows = array_values(array_filter(array_map(fn ($r) => $r['valid'] ? $r['data'] : null, $results)));

        $dir = base_path('storage/framework/imports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/' . $token . '.json', json_encode($validRows));

        echo view('layouts.admin', [
            'pageTitle' => 'Import preview',
            'content' => view('admin.products.import-preview', [
                'results' => $results,
                'validCount' => count($validRows),
                'invalidCount' => count($results) - count($validRows),
                'token' => $token,
            ]),
        ]);
    }

    public function importShow(Request $request): void
    {
        require_admin_permission('products.create');
        echo view('layouts.admin', ['pageTitle' => 'Import products', 'content' => view('admin.products.import')]);
    }

    public function importConfirm(Request $request): void
    {
        require_admin_permission('products.create');
        $admin = Session::get('admin');
        $token = (string) $request->input('token', '');
        $path = base_path('storage/framework/imports/' . basename($token) . '.json');

        if (!preg_match('/^[a-f0-9]{32}$/', $token) || !is_file($path)) {
            flash_errors(['import' => 'This import session has expired. Please upload the CSV again.']);
            redirect('/admin/products/import');
        }

        $rows = json_decode(file_get_contents($path), true) ?: [];
        unlink($path);

        $count = ProductService::importValidatedRows($rows, $admin['id'], $request->ip());
        flash_success("Imported {$count} product(s) successfully.");
        redirect('/admin/products');
    }
}
