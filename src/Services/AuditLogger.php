<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AuditLogger
{
    public static function log(
        string $actorType,
        ?int $actorId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        Database::connection()->prepare(
            'INSERT INTO audit_logs
            (actor_type, actor_id, action, target_type, target_id, old_values, new_values, reason, ip_address, user_agent)
            VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $actorType, $actorId, $action, $targetType, $targetId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $reason, $ip, $userAgent,
        ]);
    }
}
