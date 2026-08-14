<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private static function path(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs/app-' . date('Y-m-d') . '.log';
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );

        $dir = dirname(self::path());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        error_log($line, 3, self::path());
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }
}
