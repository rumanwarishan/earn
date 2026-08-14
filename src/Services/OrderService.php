<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\ValidationException;
use PDO;
use RuntimeException;

final class OrderService
{
    private const STATUSES = ['pending', 'tracking', 'confirmed', 'completed', 'cancelled', 'refunded'];

    public static function create(int $userId, int $productId, ?int $productClickId, ?string $externalRef, ?int $adminId, string $ip, ?string $notes = null): int
    {
        return Database::transaction(function (PDO $pdo) use ($userId, $productId, $productClickId, $externalRef, $adminId, $ip, $notes) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new ValidationException(['product_id' => 'Product not found.']);
            }

            $cashbackAmount = $product['cashback_enabled']
                ? ($product['cashback_type'] === 'percentage'
                    ? bcdiv(bcmul((string) $product['display_price'], (string) $product['cashback_value'], 4), '100', 2)
                    : (string) $product['cashback_value'])
                : '0.00';

            $uuid = uuid4();
            $pdo->prepare('INSERT INTO orders
                (order_uuid, user_id, product_id, product_click_id, marketplace_id, external_order_reference,
                 product_price, cashback_percentage, cashback_amount, status, cashback_status, admin_id, admin_notes, ordered_at)
                VALUES (?,?,?,?,?,?,?,?,?,"pending",?,?,?,NOW())')
                ->execute([
                    $uuid, $userId, $productId, $productClickId, $product['marketplace_id'], $externalRef,
                    $product['display_price'], $product['cashback_value'], $cashbackAmount,
                    $product['cashback_enabled'] ? 'pending' : 'not_applicable', $adminId, $notes,
                ]);

            $orderId = (int) $pdo->lastInsertId();

            NotificationService::notify($userId, 'order_created', 'Order recorded',
                'An order for ' . $product['name'] . ' has been recorded on your account.');
            AuditLogger::log('admin', $adminId, 'order.created', 'order', $orderId, null,
                ['user_id' => $userId, 'product_id' => $productId], $notes, $ip);

            return $orderId;
        });
    }

    public static function updateStatus(int $orderId, string $newStatus, int $adminId, string $ip, ?string $notes = null): void
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new ValidationException(['status' => 'Invalid status.']);
        }

        Database::transaction(function (PDO $pdo) use ($orderId, $newStatus, $adminId, $ip, $notes) {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) {
                throw new RuntimeException('Order not found.');
            }

            $oldStatus = $order['status'];
            $confirmedAt = in_array($newStatus, ['confirmed', 'completed'], true) && !$order['confirmed_at'] ? date('Y-m-d H:i:s') : $order['confirmed_at'];
            $completedAt = $newStatus === 'completed' && !$order['completed_at'] ? date('Y-m-d H:i:s') : $order['completed_at'];

            $pdo->prepare('UPDATE orders SET status = ?, admin_id = ?, admin_notes = COALESCE(?, admin_notes), confirmed_at = ?, completed_at = ? WHERE id = ?')
                ->execute([$newStatus, $adminId, $notes, $confirmedAt, $completedAt, $orderId]);

            AuditLogger::log('admin', $adminId, 'order.status_updated', 'order', $orderId, ['status' => $oldStatus], ['status' => $newStatus], $notes, $ip);
            NotificationService::notify((int) $order['user_id'], 'order_status', 'Order updated', 'Your order status changed to: ' . $newStatus);

            // Cashback becomes creditable once an order is confirmed or completed -
            // never on cancellation/refund, and never twice (guarded by cashback_status).
            if (in_array($newStatus, ['confirmed', 'completed'], true) && $order['cashback_status'] === 'pending') {
                self::creditCashbackLocked($pdo, $order, $adminId, $ip);
            }

            if (in_array($newStatus, ['cancelled', 'refunded'], true) && $order['cashback_status'] === 'credited') {
                self::reverseCashbackLocked($pdo, $order, $adminId, 'Order ' . $newStatus, $ip);
            }
        });
    }

    private static function creditCashbackLocked(PDO $pdo, array $order, int $adminId, string $ip): void
    {
        if (bccomp($order['cashback_amount'], '0', 2) <= 0) {
            return;
        }

        $pdo->prepare('INSERT INTO cashback_transactions (order_id, user_id, amount, status, admin_id) VALUES (?,?,?,"released",?)')
            ->execute([$order['id'], $order['user_id'], $order['cashback_amount'], $adminId]);

        $pdo->prepare('UPDATE orders SET cashback_status = "credited" WHERE id = ?')->execute([$order['id']]);

        WalletService::applyLedgerEntry(
            (int) $order['user_id'], 'cashback', $order['cashback_amount'], 'cashback_release',
            'order', (int) $order['id'], 'Cashback credited for confirmed order',
            'admin', $adminId, null, $ip
        );

        MembershipService::refresh((int) $order['user_id']);

        NotificationService::notify((int) $order['user_id'], 'cashback_credited', 'Cashback credited',
            'You earned ' . money($order['cashback_amount']) . ' cashback on your order.');
    }

    private static function reverseCashbackLocked(PDO $pdo, array $order, int $adminId, string $reason, string $ip): void
    {
        $pdo->prepare('UPDATE cashback_transactions SET status = "reversed", admin_id = ?, reason = ? WHERE order_id = ? AND status = "released"')
            ->execute([$adminId, $reason, $order['id']]);
        $pdo->prepare('UPDATE orders SET cashback_status = "reversed" WHERE id = ?')->execute([$order['id']]);

        WalletService::applyLedgerEntry(
            (int) $order['user_id'], 'cashback', bcmul($order['cashback_amount'], '-1', 2), 'cashback_reversal',
            'order', (int) $order['id'], 'Cashback reversed: ' . $reason,
            'admin', $adminId, $reason, $ip,
            allowNegative: true
        );
    }

    public static function reverseCashback(int $orderId, int $adminId, string $reason, string $ip): void
    {
        if (trim($reason) === '') {
            throw new ValidationException(['reason' => 'A reason is required to reverse cashback.']);
        }

        Database::transaction(function (PDO $pdo) use ($orderId, $adminId, $reason, $ip) {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order || $order['cashback_status'] !== 'credited') {
                throw new RuntimeException('This order has no active cashback to reverse.');
            }
            self::reverseCashbackLocked($pdo, $order, $adminId, $reason, $ip);
            AuditLogger::log('admin', $adminId, 'order.cashback_reversed', 'order', $orderId, ['cashback_status' => 'credited'], ['cashback_status' => 'reversed'], $reason, $ip);
        });
    }
}
