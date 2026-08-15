<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\InsufficientBalanceException;
use App\Services\PurchaseService;
use App\Services\WalletService;
use App\Support\ValidationException;
use PDO;
use Tests\TestCase;
use Tests\TestSeed;

final class PurchaseServiceTest extends TestCase
{
    private const SHIPPING = [
        'name' => 'Jane Doe',
        'phone' => '555-0100',
        'address_line1' => '123 Main St',
        'address_line2' => '',
        'city' => 'Springfield',
        'state' => 'IL',
        'postal_code' => '62701',
        'country' => 'US',
    ];

    private function makeProduct(PDO $pdo, array $overrides = []): int
    {
        $marketplaceId = (int) $pdo->query('SELECT id FROM marketplaces LIMIT 1')->fetchColumn();
        if (!$marketplaceId) {
            $pdo->exec('INSERT INTO marketplaces (name, slug, is_active) VALUES ("Test Market", "test-market-' . bin2hex(random_bytes(3)) . '", 1)');
            $marketplaceId = (int) $pdo->lastInsertId();
        }

        $defaults = [
            'name' => 'Test Widget',
            'slug' => 'test-widget-' . bin2hex(random_bytes(6)),
            'marketplace_id' => $marketplaceId,
            'external_url' => '',
            'display_price' => '50.00',
            'cashback_type' => 'percentage',
            'cashback_value' => '10',
            'cashback_enabled' => 1,
            'is_published' => 1,
            'fulfillment_type' => 'dropship',
            'stock_quantity' => null,
        ];
        $data = array_merge($defaults, $overrides);

        $stmt = $pdo->prepare('INSERT INTO products
            (name, slug, marketplace_id, external_url, display_price, cashback_type, cashback_value,
             cashback_enabled, is_published, fulfillment_type, stock_quantity)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $data['name'], $data['slug'], $data['marketplace_id'], $data['external_url'], $data['display_price'],
            $data['cashback_type'], $data['cashback_value'], $data['cashback_enabled'], $data['is_published'],
            $data['fulfillment_type'], $data['stock_quantity'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function test_successful_purchase_debits_wallet_and_credits_cashback(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('buy-ok');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '100.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $productId = $this->makeProduct($pdo, ['display_price' => '50.00', 'cashback_value' => '10']);

        $orderId = PurchaseService::buyWithWallet((int) $user['id'], $productId, self::SHIPPING, '127.0.0.1');
        $this->assertGreaterThan(0, $orderId);

        $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);
        $this->assertSame('50.00', $wallet['deposited_balance']);

        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        $this->assertSame('wallet', $order['payment_source']);
        $this->assertSame('dropship', $order['fulfillment_type']);
        $this->assertSame('confirmed', $order['status']);
        $this->assertSame('Jane Doe', $order['shipping_name']);
    }

    public function test_insufficient_balance_rolls_back_order_and_wallet(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('buy-insufficient');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '10.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $productId = $this->makeProduct($pdo, ['display_price' => '50.00']);

        $this->expectException(InsufficientBalanceException::class);
        try {
            PurchaseService::buyWithWallet((int) $user['id'], $productId, self::SHIPPING, '127.0.0.1');
        } finally {
            $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);
            $this->assertSame('10.00', $wallet['deposited_balance'], 'Balance must be untouched on a failed purchase');

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$user['id'], $productId]);
            $this->assertSame(0, (int) $stmt->fetchColumn(), 'No order row should survive a rolled-back purchase');
        }
    }

    public function test_out_of_stock_product_is_rejected(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('buy-oos');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '100.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $productId = $this->makeProduct($pdo, ['display_price' => '50.00', 'stock_quantity' => 0]);

        $this->expectException(ValidationException::class);
        PurchaseService::buyWithWallet((int) $user['id'], $productId, self::SHIPPING, '127.0.0.1');
    }

    public function test_affiliate_product_cannot_be_bought_with_wallet(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('buy-affiliate');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '100.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $productId = $this->makeProduct($pdo, ['fulfillment_type' => 'affiliate', 'external_url' => 'https://example.test/item']);

        $this->expectException(ValidationException::class);
        PurchaseService::buyWithWallet((int) $user['id'], $productId, self::SHIPPING, '127.0.0.1');
    }

    public function test_stock_quantity_decrements_on_purchase(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('buy-stock');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '100.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $productId = $this->makeProduct($pdo, ['display_price' => '50.00', 'stock_quantity' => 3]);

        PurchaseService::buyWithWallet((int) $user['id'], $productId, self::SHIPPING, '127.0.0.1');

        $stmt = $pdo->prepare('SELECT stock_quantity FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $this->assertSame(2, (int) $stmt->fetchColumn());
    }

    public function test_missing_shipping_fields_are_rejected(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('buy-noship');
        WalletService::applyLedgerEntry($user['id'], 'deposited', '100.00', 'admin_credit', null, null, 'seed', 'admin', 1);
        $productId = $this->makeProduct($pdo, ['display_price' => '50.00']);

        $this->expectException(ValidationException::class);
        PurchaseService::buyWithWallet((int) $user['id'], $productId, ['name' => 'Jane Doe'], '127.0.0.1');
    }
}
