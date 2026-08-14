<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Session;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        if (!Session::has('user')) {
            Session::flash('_errors', ['auth' => 'Please log in to continue.']);
            redirect('/login');
        }

        $next();
    }
}
