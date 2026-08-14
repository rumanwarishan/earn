<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\MiddlewareInterface;
use App\Core\Request;

final class VerifyCsrfToken implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        if ($request->isPost() && !Csrf::verify($request->csrfToken())) {
            if ($request->wantsJson()) {
                \App\Core\Response::json(['error' => 'Invalid or expired security token. Please refresh and try again.'], 419);
            }
            flash_errors(['csrf' => 'Your session expired. Please try again.']);
            redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }

        $next();
    }
}
