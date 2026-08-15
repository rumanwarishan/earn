<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Session;

final class MaintenanceMode implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        $isAdmin = str_starts_with($request->path(), '/admin');
        $enabled = (bool) setting('maintenance_mode', false);

        if ($enabled && !$isAdmin && !Session::has('admin')) {
            http_response_code(503);
            header('Retry-After: 3600');
            echo view('errors.maintenance');
            exit;
        }

        $next();
    }
}
