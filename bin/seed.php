<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;

$pdo = Database::connection();

function upsertSetting(PDO $pdo, string $key, string $value, string $type = 'string'): void
{
    $stmt = $pdo->prepare('INSERT INTO site_settings (`key`, `value`, `type`) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE `value` = IF(VALUES(`value`) IS NOT NULL, `value`, `value`)');
    // Only insert if not already present, never overwrite an admin's saved config on re-seed.
    $exists = $pdo->prepare('SELECT COUNT(*) FROM site_settings WHERE `key` = ?');
    $exists->execute([$key]);
    if ((int) $exists->fetchColumn() > 0) {
        return;
    }
    $stmt->execute([$key, $value, $type]);
}

$defaults = [
    ['site_name', 'Billions Earn', 'string'],
    ['support_email', 'support@billionsstore.com', 'string'],
    ['currency', 'USD', 'string'],
    ['timezone', 'UTC', 'string'],
    ['btc_deposit_address', '', 'string'],
    ['btc_deposit_network_note', 'Bitcoin (BTC) network only. Sending any other asset or network will result in permanent loss of funds.', 'string'],
    ['min_deposit_usd', '50', 'decimal'],
    ['min_withdrawal_usd', '1000', 'decimal'],
    ['max_withdrawal_usd', '50000', 'decimal'],
    ['welcome_bonus_amount', '20', 'decimal'],
    ['welcome_bonus_enabled', '1', 'bool'],
    ['maintenance_mode', '0', 'bool'],
    ['registration_enabled', '1', 'bool'],
    ['chatbot_enabled', '1', 'bool'],
];

foreach ($defaults as [$key, $value, $type]) {
    upsertSetting($pdo, $key, $value, $type);
}

// Referral levels: level 1 = $20 fixed bonus on qualifying first deposit >= $50.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM referral_settings');
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $pdo->prepare('INSERT INTO referral_settings (level, bonus_type, bonus_amount, min_qualifying_deposit, is_active) VALUES (1, "fixed", 20.00, 50.00, 1)')->execute();
    $pdo->prepare('INSERT INTO referral_settings (level, bonus_type, bonus_amount, min_qualifying_deposit, is_active) VALUES (2, "fixed", 5.00, 50.00, 0)')->execute();
}

// Membership levels.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM membership_levels');
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $levels = [
        ['Bronze', 'bronze', 'bronze', '#cd7f32', 'Welcome tier for every new member.', 0, 0, 1.000, 1.000, 1],
        ['Silver', 'silver', 'silver', '#c0c0c0', 'Unlocked after your first deposits and purchases.', 500, 250, 1.100, 1.100, 2],
        ['Gold', 'gold', 'gold', '#e8b923', 'For consistently active members.', 2500, 1000, 1.250, 1.250, 3],
        ['Platinum', 'platinum', 'platinum', '#7ee8e0', 'Our highest tier with maximum benefits.', 10000, 5000, 1.500, 1.500, 4],
    ];
    $ins = $pdo->prepare('INSERT INTO membership_levels (name, slug, icon, color, description, min_total_deposited, min_total_purchased, cashback_multiplier, referral_bonus_multiplier, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($levels as $l) {
        $ins->execute($l);
    }
}

// RBAC roles.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM roles');
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $roles = [
        ['Super Admin', 'super_admin', json_encode(['*'])],
        ['Finance Admin', 'finance_admin', json_encode(['deposits.*', 'withdrawals.*', 'wallets.*', 'ledger.view'])],
        ['Product Admin', 'product_admin', json_encode(['products.*', 'categories.*', 'marketplaces.*', 'orders.*'])],
        ['Support Admin', 'support_admin', json_encode(['users.view', 'users.update', 'chatbot.*', 'faq.*'])],
        ['Content Admin', 'content_admin', json_encode(['pages.*', 'faq.*', 'notifications.templates'])],
    ];
    $ins = $pdo->prepare('INSERT INTO roles (name, slug, permissions) VALUES (?,?,?)');
    foreach ($roles as $r) {
        $ins->execute($r);
    }
}

// Super admin account (change password immediately after first login).
$stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users');
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $roleId = $pdo->query("SELECT id FROM roles WHERE slug = 'super_admin'")->fetchColumn();
    $email = getenv('SEED_ADMIN_EMAIL') ?: 'admin@billionsstore.com';
    $password = getenv('SEED_ADMIN_PASSWORD') ?: bin2hex(random_bytes(8));
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO admin_users (uuid, name, email, password_hash, role_id) VALUES (?,?,?,?,?)')
        ->execute([uuid4(), 'Super Admin', $email, $hash, $roleId]);
    echo "Created super admin: {$email} / {$password}  (SAVE THIS PASSWORD NOW, it will not be shown again)\n";
}

// Bootstrap invitation code so the first real users can register before any referral codes exist.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM invitation_codes');
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $code = getenv('SEED_INVITE_CODE') ?: random_code(8);
    $pdo->prepare('INSERT INTO invitation_codes (code, max_uses, uses_count, is_active, note) VALUES (?, 999999, 0, 1, ?)')
        ->execute([$code, 'Bootstrap invitation code, seeded automatically']);
    echo "Bootstrap invitation code: {$code}\n";
}

// Marketplaces.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM marketplaces');
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $marketplaces = [
        ['Amazon', 'amazon', null, 'https://www.amazon.com', 1],
        ['eBay', 'ebay', null, 'https://www.ebay.com', 2],
        ['Walmart', 'walmart', null, 'https://www.walmart.com', 3],
        ['AliExpress', 'aliexpress', null, 'https://www.aliexpress.com', 4],
    ];
    $ins = $pdo->prepare('INSERT INTO marketplaces (name, slug, logo_path, website_url, sort_order) VALUES (?,?,?,?,?)');
    foreach ($marketplaces as $m) {
        $ins->execute($m);
    }
}

// Default product category.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM product_categories');
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $cats = ['Electronics', 'Home & Kitchen', 'Fashion', 'Beauty', 'Sports & Outdoors'];
    $ins = $pdo->prepare('INSERT INTO product_categories (name, slug, sort_order) VALUES (?,?,?)');
    foreach ($cats as $i => $c) {
        $ins->execute([$c, strtolower(str_replace([' ', '&'], ['-', 'and'], $c)), $i]);
    }
}

// FAQ seed content used by the rule-based chatbot.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM faq');
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $faqs = [
        ['How do I deposit funds?', 'Go to Wallet > Deposit, send BTC to the displayed address, then submit your transaction hash (TXID). An admin manually verifies and approves your deposit, after which the USD equivalent is credited to your wallet.', 'deposits'],
        ['How do withdrawals work?', 'The minimum withdrawal is $1,000. Submit a request with your BTC address from Wallet > Withdraw. An admin reviews and manually processes the BTC payment, then marks it complete.', 'withdrawals'],
        ['How does cashback work?', 'Every eligible product has a cashback percentage. When your order is confirmed, cashback is added to your wallet as pending, then released to your spendable/withdrawable cashback balance.', 'cashback'],
        ['How does the referral program work?', 'Share your referral code or link. New users who register with it get a welcome bonus, and you earn a referral bonus once they make a qualifying deposit.', 'referral'],
    ];
    $ins = $pdo->prepare('INSERT INTO faq (question, answer, category, sort_order) VALUES (?,?,?,?)');
    foreach ($faqs as $i => $f) {
        $ins->execute([$f[0], $f[1], $f[2], $i]);
    }
}

// Demo products, clearly marked - safe to delete from the admin panel at any time.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM products');
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $amazonId = $pdo->query("SELECT id FROM marketplaces WHERE slug = 'amazon'")->fetchColumn();
    $ebayId = $pdo->query("SELECT id FROM marketplaces WHERE slug = 'ebay'")->fetchColumn();
    $electronicsId = $pdo->query("SELECT id FROM product_categories WHERE slug = 'electronics'")->fetchColumn();

    $demoProducts = [
        ['Wireless Noise-Cancelling Headphones (Demo)', 'wireless-noise-cancelling-headphones-demo', $amazonId, $electronicsId, 'https://www.amazon.com', 159.99, 129.99, 10, 1],
        ['Smart Fitness Watch (Demo)', 'smart-fitness-watch-demo', $ebayId, $electronicsId, 'https://www.ebay.com', 89.99, 69.99, 8, 1],
    ];
    $ins = $pdo->prepare('INSERT INTO products
        (name, slug, marketplace_id, category_id, external_url, affiliate_url, original_price, display_price,
         cashback_type, cashback_value, is_featured, is_published, short_description)
        VALUES (?,?,?,?,?,?,?,?,"percentage",?,1,1,?)');
    foreach ($demoProducts as $p) {
        [$name, $slug, $mkId, $catId, $url, $original, $display, $cashback, $featured] = $p;
        $ins->execute([$name, $slug, $mkId, $catId, $url, $url, $original, $display, $cashback, 'Demo product seeded for local testing/preview - replace with real catalog data in the admin panel.']);
    }
}

echo "Seeding complete.\n";
