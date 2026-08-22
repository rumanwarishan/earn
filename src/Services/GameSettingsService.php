<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class GameSettingsService
{
    public static function current(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? Database::connection();
        $stmt = $pdo->query('SELECT * FROM game_settings WHERE id = 1');
        $settings = $stmt->fetch();
        if (!$settings) {
            $pdo->exec('INSERT INTO game_settings (id) VALUES (1)');
            $stmt = $pdo->query('SELECT * FROM game_settings WHERE id = 1');
            $settings = $stmt->fetch();
        }
        return $settings;
    }

    public static function update(array $data): void
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE game_settings SET
                enabled = ?, maintenance_mode = ?, minimum_entry = ?, maximum_entry = ?,
                countdown_seconds = ?, round_grace_seconds = ?, growth_rate = ?, starting_balance = ?,
                daily_bonus_enabled = ?, daily_bonus_amount = ?, daily_bonus_max_per_day = ?,
                exchange_enabled = ?, exchange_rate = ?, min_exchange_amount = ?, max_exchange_per_day = ?
            WHERE id = 1')
            ->execute([
                $data['enabled'], $data['maintenance_mode'], $data['minimum_entry'], $data['maximum_entry'],
                $data['countdown_seconds'], $data['round_grace_seconds'], $data['growth_rate'], $data['starting_balance'],
                $data['daily_bonus_enabled'], $data['daily_bonus_amount'], $data['daily_bonus_max_per_day'],
                $data['exchange_enabled'], $data['exchange_rate'], $data['min_exchange_amount'], $data['max_exchange_per_day'],
            ]);
    }
}
