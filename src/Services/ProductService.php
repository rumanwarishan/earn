<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\ValidationException;
use PDO;

final class ProductService
{
    public static function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
        return $slug !== '' ? $slug : 'product-' . substr(md5(uniqid('', true)), 0, 8);
    }

    public static function uniqueSlug(string $base, PDO $pdo, ?int $excludeId = null): string
    {
        $slug = self::slugify($base);
        $candidate = $slug;
        $i = 1;
        while (true) {
            $sql = 'SELECT COUNT(*) FROM products WHERE slug = ?' . ($excludeId ? ' AND id != ?' : '');
            $params = $excludeId ? [$candidate, $excludeId] : [$candidate];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() === 0) {
                return $candidate;
            }
            $candidate = $slug . '-' . (++$i);
        }
    }

    public static function validateRow(array $row, PDO $pdo, array $marketplaceBySlug, array $categoryBySlug): array
    {
        $errors = [];
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Product name is required.';
        }

        $marketplaceSlug = strtolower(trim((string) ($row['marketplace'] ?? '')));
        if (!isset($marketplaceBySlug[$marketplaceSlug])) {
            $errors[] = "Unknown marketplace '{$marketplaceSlug}'.";
        }

        $categorySlug = strtolower(trim((string) ($row['category'] ?? '')));
        if ($categorySlug !== '' && !isset($categoryBySlug[$categorySlug])) {
            $errors[] = "Unknown category '{$categorySlug}'.";
        }

        $externalUrl = trim((string) ($row['external_url'] ?? ''));
        if (!filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'A valid external_url is required.';
        }

        $displayPrice = (string) ($row['display_price'] ?? '');
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $displayPrice)) {
            $errors[] = 'display_price must be a valid number.';
        }

        $cashbackType = strtolower(trim((string) ($row['cashback_type'] ?? 'percentage')));
        if (!in_array($cashbackType, ['percentage', 'fixed'], true)) {
            $errors[] = 'cashback_type must be percentage or fixed.';
        }

        $cashbackValue = (string) ($row['cashback_value'] ?? '0');
        if (!preg_match('/^\d+(\.\d{1,3})?$/', $cashbackValue)) {
            $errors[] = 'cashback_value must be a valid number.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'marketplace_id' => $marketplaceBySlug[$marketplaceSlug] ?? null,
                'category_id' => $categoryBySlug[$categorySlug] ?? null,
                'external_url' => $externalUrl,
                'affiliate_url' => trim((string) ($row['affiliate_url'] ?? '')) ?: $externalUrl,
                'original_price' => trim((string) ($row['original_price'] ?? '')) ?: null,
                'display_price' => $displayPrice,
                'cashback_type' => $cashbackType,
                'cashback_value' => $cashbackValue,
                'short_description' => trim((string) ($row['short_description'] ?? '')),
                'is_featured' => filter_var($row['is_featured'] ?? '0', FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_published' => isset($row['is_published']) ? (filter_var($row['is_published'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
            ],
        ];
    }

    public static function importValidatedRows(array $rows, int $adminId, string $ip): int
    {
        return Database::transaction(function (PDO $pdo) use ($rows, $adminId, $ip) {
            $count = 0;
            foreach ($rows as $row) {
                $slug = self::uniqueSlug($row['name'], $pdo);
                $pdo->prepare('INSERT INTO products
                    (name, slug, marketplace_id, category_id, external_url, affiliate_url, original_price, display_price,
                     cashback_type, cashback_value, short_description, is_featured, is_published)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([
                        $row['name'], $slug, $row['marketplace_id'], $row['category_id'], $row['external_url'],
                        $row['affiliate_url'], $row['original_price'], $row['display_price'],
                        $row['cashback_type'], $row['cashback_value'], $row['short_description'],
                        $row['is_featured'], $row['is_published'],
                    ]);
                $count++;
            }

            AuditLogger::log('admin', $adminId, 'product.csv_import', 'product', null, null, ['imported_count' => $count], null, $ip);

            return $count;
        });
    }

    /** Escapes a value for safe CSV export, neutralizing formula-injection payloads. */
    public static function csvSafe(?string $value): string
    {
        $value = (string) $value;
        if ($value !== '' && str_contains('=+-@', $value[0])) {
            return "'" . $value;
        }
        return $value;
    }
}
