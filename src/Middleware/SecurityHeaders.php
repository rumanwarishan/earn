<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;

final class SecurityHeaders implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'");

        if (filter_var(env('SESSION_SECURE_COOKIE', 'true'), FILTER_VALIDATE_BOOLEAN)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        $next();
    }
}
