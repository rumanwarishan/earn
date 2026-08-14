<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class MembershipService
{
    /** Re-evaluate and, if eligible, upgrade a user's membership level. Never downgrades automatically. */
    public static function refresh(int $userId): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT total_deposited FROM wallets WHERE user_id = ?');
        $stmt->execute([$userId]);
        $totalDeposited = (string) ($stmt->fetchColumn() ?: '0');

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(product_price),0) FROM orders WHERE user_id = ? AND status IN ("confirmed","completed")');
        $stmt->execute([$userId]);
        $totalPurchased = (string) ($stmt->fetchColumn() ?: '0');

        $stmt = $pdo->prepare('SELECT membership_level_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $currentLevelId = $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT * FROM membership_levels WHERE is_active = 1
            AND min_total_deposited <= ? AND min_total_purchased <= ?
            ORDER BY sort_order DESC LIMIT 1');
        $stmt->execute([$totalDeposited, $totalPurchased]);
        $eligible = $stmt->fetch();

        if (!$eligible) {
            return;
        }

        if ($currentLevelId) {
            $stmt = $pdo->prepare('SELECT sort_order FROM membership_levels WHERE id = ?');
            $stmt->execute([$currentLevelId]);
            $currentSort = (int) $stmt->fetchColumn();
            if ($currentSort >= (int) $eligible['sort_order']) {
                return;
            }
        }

        $pdo->prepare('UPDATE users SET membership_level_id = ? WHERE id = ?')->execute([$eligible['id'], $userId]);

        NotificationService::notify(
            $userId,
            'membership_upgrade',
            'Membership upgraded!',
            "Congratulations, you've been upgraded to {$eligible['name']} membership.",
            ['level_slug' => $eligible['slug']]
        );
    }
}
