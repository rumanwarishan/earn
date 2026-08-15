<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Services\AdminAuthService;
use App\Support\ValidationException;

final class AdminAuthController
{
    public function showLogin(Request $request): void
    {
        echo view('admin.auth.login');
    }

    public function login(Request $request): void
    {
        try {
            AdminAuthService::login(
                (string) $request->input('email', ''),
                (string) $request->input('password', ''),
                $request->ip(),
                $request->userAgent()
            );
            redirect('/admin/dashboard');
        } catch (ValidationException $e) {
            flash_errors($e->errors());
            redirect('/admin/login');
        }
    }

    public function logout(Request $request): void
    {
        AdminAuthService::logout();
        redirect('/admin/login');
    }
}
