<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Session;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        if (Session::has('user')) {
            redirect('/dashboard');
        }

        $next();
    }
}
