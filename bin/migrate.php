<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Database;

$pdo = Database::connection();

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(150) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$dir = dirname(__DIR__) . '/database/migrations';
$files = glob($dir . '/*.sql');
sort($files);

$ran = 0;

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }

    echo "Applying {$name}...\n";
    $sql = file_get_contents($file);

    // Strip full-line SQL comments first so they never get glued onto the
    // start of the following real statement by the naive splitter below.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);

    // Split on semicolons at end of statements (simple, safe for our DDL-only files).
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));

    // Note: MySQL/MariaDB DDL statements auto-commit implicitly, so these
    // migrations are not wrapped in a transaction (that would be a no-op
    // for CREATE TABLE anyway). Migrations must be additive/idempotent.
    try {
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }
            $pdo->exec($statement);
        }

        $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
        $stmt->execute([$name]);
        $ran++;
        echo "  OK\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "Migration {$name} failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo $ran > 0 ? "Applied {$ran} migration(s).\n" : "Nothing to migrate.\n";
