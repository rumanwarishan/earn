<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\ValidationException;
use PDO;

/**
 * Handles the wallet-funded "buy now, dropship it" purchase flow - distinct
 * from the affiliate click-through flow (ShopController::go / admin-recorded
 * orders), which never touches the wallet since payment happens externally.
 */
final class PurchaseService
{
    public static function buyWithWallet(int $userId, int $productId, array $shipping, string $ip): int
    {
        self::validateShipping($shipping);

        return Database::transaction(function (PDO $pdo) use ($userId, $productId, $shipping, $ip) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product || !$product['is_published']) {
                throw new ValidationException(['product' => 'This product is not available.']);
            }
            if ($product['fulfillment_type'] !== 'dropship') {
                throw new ValidationException(['product' => 'This product is not purchasable with wallet balance.']);
            }
            if ($product['stock_quantity'] !== null && (int) $product['stock_quantity'] <= 0) {
                throw new ValidationException(['product' => 'This product is currently out of stock.']);
            }

            $price = (string) $product['display_price'];
            $cashbackAmount = $product['cashback_enabled']
                ? ($product['cashback_type'] === 'percentage'
                    ? bcdiv(bcmul($price, (string) $product['cashback_value'], 4), '100', 2)
                    : (string) $product['cashback_value'])
                : '0.00';

            $uuid = uuid4();
            $pdo->prepare('INSERT INTO orders
                (order_uuid, user_id, product_id, marketplace_id, fulfillment_type, payment_source,
                 product_price, cashback_percentage, cashback_amount, status, cashback_status,
                 shipping_name, shipping_phone, shipping_address_line1, shipping_address_line2,
                 shipping_city, shipping_state, shipping_postal_code, shipping_country, ordered_at)
                VALUES (?,?,?,?,"dropship","wallet",?,?,?,"confirmed",?,?,?,?,?,?,?,?,?,NOW())')
                ->execute([
                    $uuid, $userId, $productId, $product['marketplace_id'], $price,
                    $product['cashback_value'], $cashbackAmount,
                    $product['cashback_enabled'] ? 'pending' : 'not_applicable',
                    $shipping['name'], $shipping['phone'], $shipping['address_line1'], $shipping['address_line2'] ?: null,
                    $shipping['city'], $shipping['state'], $shipping['postal_code'], $shipping['country'],
                ]);

            $orderId = (int) $pdo->lastInsertId();

            // Throws InsufficientBalanceException if the wallet can't cover it -
            // rolls back the order insert above too, since we're in one transaction.
            WalletService::debitForPurchase($userId, $price, 'purchase_debit', 'order', $orderId, 'Purchase: ' . $product['name'], $ip);

            if ($product['stock_quantity'] !== null) {
                $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - 1 WHERE id = ? AND stock_quantity > 0')
                    ->execute([$productId]);
            }

            // Payment has already happened (unlike affiliate orders, which wait for
            // admin confirmation) - the order starts "confirmed", so cashback can be
            // credited immediately via the same code path admin-confirmed orders use.
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            OrderService::creditCashbackLocked($pdo, $order, null, $ip);

            MembershipService::refresh($userId);

            NotificationService::notify($userId, 'order_created', 'Purchase confirmed',
                'Your order for ' . $product['name'] . ' has been placed and will ship soon.');
            AuditLogger::log('user', $userId, 'purchase.wallet', 'order', $orderId, null,
                ['product_id' => $productId, 'price' => $price], null, $ip);

            return $orderId;
        });
    }

    private static function validateShipping(array $shipping): void
    {
        $required = [
            'name' => 'Full name', 'phone' => 'Phone number', 'address_line1' => 'Address',
            'city' => 'City', 'state' => 'State/Province', 'postal_code' => 'Postal code', 'country' => 'Country',
        ];
        $errors = [];
        foreach ($required as $key => $label) {
            if (trim((string) ($shipping[$key] ?? '')) === '') {
                $errors[$key] = "{$label} is required.";
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
    }
}
