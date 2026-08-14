<?php

declare(strict_types=1);

use App\Core\Session;
use App\Core\View;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $base = rtrim((string) env('APP_URL', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Core\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . $path, true, 302);
        exit;
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        $data = Session::get('_old', []);
        return e($data[$key] ?? $default);
    }
}

if (!function_exists('flash_old')) {
    function flash_old(array $input): void
    {
        unset($input['password'], $input['confirm_password'], $input['_csrf']);
        Session::put('_old', $input);
    }
}

if (!function_exists('errors')) {
    function errors(): array
    {
        return Session::getFlash('_errors', []);
    }
}

if (!function_exists('flash_errors')) {
    function flash_errors(array $errors): void
    {
        Session::flash('_errors', $errors);
    }
}

if (!function_exists('flash_success')) {
    function flash_success(string $message): void
    {
        Session::flash('_success', $message);
    }
}

if (!function_exists('success_message')) {
    function success_message(): ?string
    {
        return Session::getFlash('_success');
    }
}

if (!function_exists('view')) {
    function view(string $name, array $data = []): string
    {
        return View::render($name, $data);
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return Session::get('user');
    }
}

if (!function_exists('current_admin')) {
    function current_admin(): ?array
    {
        return Session::get('admin');
    }
}

if (!function_exists('uuid4')) {
    function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('random_code')) {
    function random_code(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $code;
    }
}

if (!function_exists('money')) {
    function money(string|float|int $amount, string $currency = 'USD'): string
    {
        $amount = number_format((float) $amount, 2, '.', ',');
        return $currency === 'USD' ? '$' . $amount : $amount . ' ' . $currency;
    }
}

if (!function_exists('btc_amount')) {
    function btc_amount(string|float $amount): string
    {
        return bcadd((string) $amount, '0', 8) . ' BTC';
    }
}

if (!function_exists('bcmoney')) {
    // Adds two decimal money strings safely (no float rounding errors).
    function bcmoney(string $a, string $b, int $scale = 2): string
    {
        return bcadd($a, $b, $scale);
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        $view = match ($code) {
            403 => 'errors/403',
            404 => 'errors/404',
            default => 'errors/500',
        };
        echo view($view, ['message' => $message]);
        exit;
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Services\SettingsService::get($key, $default);
    }
}
