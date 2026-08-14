<?php

declare(strict_types=1);

define('APP_BASE_PATH', dirname(__DIR__));

require APP_BASE_PATH . '/vendor/autoload.php';

if (is_file(APP_BASE_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(APP_BASE_PATH);
    $dotenv->safeLoad();
}

date_default_timezone_set((string) env('APP_TIMEZONE', 'UTC'));

$debug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);

ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (\Throwable $e) use ($debug): void {
    \App\Core\Logger::error($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string) $e . "\n");
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
    }

    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES) . '</pre>';
        return;
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Something went wrong</title></head>'
        . '<body style="background:#0a0a0f;color:#e8e8f0;font-family:system-ui,sans-serif;display:flex;'
        . 'align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;">'
        . '<div><h1 style="font-weight:600;">Something went wrong</h1>'
        . '<p style="color:#9a9ab0;">Please try again in a moment. Our team has been notified.</p></div></body></html>';
});
