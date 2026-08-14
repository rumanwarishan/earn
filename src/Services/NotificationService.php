<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class NotificationService
{
    public static function notify(int $userId, string $type, string $title, string $message, array $data = []): void
    {
        Database::connection()
            ->prepare('INSERT INTO notifications (user_id, type, title, message, data) VALUES (?,?,?,?,?)')
            ->execute([$userId, $type, $title, $message, $data ? json_encode($data) : null]);
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function recent(int $userId, int $limit = 15): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function markAllRead(int $userId): void
    {
        Database::connection()
            ->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
            ->execute([$userId]);
    }
}
