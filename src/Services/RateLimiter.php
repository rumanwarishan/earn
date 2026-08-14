<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class RateLimiter {
    public static function tooManyLoginAttempts(string $identifier, string $ip): bool
    {
        $max = (int) env('RATE_LIMIT_LOGIN_MAX_ATTEMPTS', 5);
        $decayMinutes = (int) env('RATE_LIMIT_LOGIN_DECAY_MINUTES', 15);

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts
            WHERE (identifier = ? OR ip = ?) AND success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)');
        $stmt->execute([$identifier, $ip, $decayMinutes]);

        return (int) $stmt->fetchColumn() >= $max;
    }

    public static function recordAttempt(string $identifier, string $ip, bool $success): void
    {
        Database::connection()
            ->prepare('INSERT INTO login_attempts (identifier, ip, success) VALUES (?, ?, ?)')
            ->execute([$identifier, $ip, $success ? 1 : 0]);
    }
}
