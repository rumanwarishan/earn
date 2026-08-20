<?php

declare(strict_types=1);

// Test env defaults - only applied if not already set by the environment,
// so CI can override via real env vars without editing this file.
$defaults = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'true',
    'APP_URL' => 'http://127.0.0.1:8080',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'billions_earn_test',
    'DB_USERNAME' => 'earn_app',
    'DB_PASSWORD' => 'earn_app_pw',
    'DB_CHARSET' => 'utf8mb4',
    'SESSION_SECURE_COOKIE' => 'false',
    'MAIL_MAILER' => 'log',
    'RATE_LIMIT_LOGIN_MAX_ATTEMPTS' => '5',
    'RATE_LIMIT_LOGIN_DECAY_MINUTES' => '15',
];

foreach ($defaults as $key => $value) {
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('UTC');

// Re-run migrations against the test DB (idempotent - CREATE TABLE IF NOT EXISTS).
$pdo = App\Core\Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(150) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
    $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$name]);
}

// Full clean slate at the start of every test run (not per-test - see
// TestCase's docblock for why per-test transactions are intentionally avoided).
$tables = [
    'wallet_ledger', 'wallets', 'deposit_reviews', 'deposits', 'withdrawal_reviews', 'withdrawals',
    'referral_rewards', 'referral_relationships', 'email_verifications', 'password_resets', 'login_attempts',
    'notifications', 'audit_logs', 'cashback_transactions', 'orders', 'product_clicks', 'chat_messages',
    'invitation_codes', 'referral_settings', 'membership_levels', 'site_settings',
    // TRUNCATE with FK checks off does not cascade - without these, rows left
    // over from a previous local `composer test` run stay in these tables
    // forever (users is reset and its AUTO_INCREMENT restarts, so a later run
    // can mint a brand-new user that happens to reuse an old numeric id and
    // silently "inherit" that stale user's leftover ad/task completions).
    'ad_completions', 'ad_watch_sessions', 'advertisements', 'task_completions', 'tasks',
    'users',
];
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tables as $table) {
    $pdo->exec("TRUNCATE TABLE {$table}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

require_once dirname(__DIR__) . '/tests/TestSeed.php';
Tests\TestSeed::ensureBaseline();
