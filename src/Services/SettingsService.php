<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SettingsService
{
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            $stmt = Database::connection()->query('SELECT `key`, `value`, `type` FROM site_settings');
            self::$cache = [];
            foreach ($stmt->fetchAll() as $row) {
                self::$cache[$row['key']] = self::cast($row['value'], $row['type']);
            }
        }
        return self::$cache;
    }

    private static function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'bool' => (bool) (int) $value,
            'decimal' => $value !== null ? (string) $value : null,
            'int' => (int) $value,
            default => $value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::load();
        return $all[$key] ?? $default;
    }

    public static function set(string $key, string $value, string $type = 'string'): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO site_settings (`key`, `value`, `type`) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)');
        $stmt->execute([$key, $value, $type]);
        self::$cache = null;
    }

    public static function all(): array
    {
        return self::load();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
