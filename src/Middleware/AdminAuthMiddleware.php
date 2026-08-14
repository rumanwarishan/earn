<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Session;

final class AdminAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        if (!Session::has('admin')) {
            redirect('/admin/login');
        }

        $next();
    }
}
