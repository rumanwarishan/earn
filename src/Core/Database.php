<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $db = env('DB_DATABASE', '');
            $charset = env('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

            try {
                self::$instance = new PDO($dsn, env('DB_USERNAME', ''), env('DB_PASSWORD', ''), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
                ]);
            } catch (PDOException $e) {
                Logger::error('Database connection failed: ' . $e->getMessage());
                throw new PDOException('Database connection failed.');
            }
        }

        return self::$instance;
    }

    /**
     * Run a callback inside a transaction with automatic commit/rollback.
     * Nested calls reuse the outer transaction.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $alreadyInTransaction = $pdo->inTransaction();

        if (!$alreadyInTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);

            if (!$alreadyInTransaction) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if (!$alreadyInTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
