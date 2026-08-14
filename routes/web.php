<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\AuthController;
use App\Middleware\SecurityHeaders;
use App\Middleware\VerifyCsrfToken;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;

$router = new Router();

$router->group('', [SecurityHeaders::class], function (Router $router) {

    // ---- Guest auth routes ----
    $router->group('', [GuestMiddleware::class], function (Router $router) {
        $router->get('/register', [AuthController::class, 'showRegister']);
        $router->post('/register', [AuthController::class, 'register'], [VerifyCsrfToken::class]);
        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'login'], [VerifyCsrfToken::class]);
        $router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
        $router->post('/forgot-password', [AuthController::class, 'forgotPassword'], [VerifyCsrfToken::class]);
        $router->get('/reset-password', [AuthController::class, 'showResetPassword']);
        $router->post('/reset-password', [AuthController::class, 'resetPassword'], [VerifyCsrfToken::class]);
    });

    $router->get('/verify-email/pending', [AuthController::class, 'showVerifyPending']);
    $router->post('/verify-email/resend', [AuthController::class, 'resendVerification'], [VerifyCsrfToken::class]);
    $router->get('/verify-email', [AuthController::class, 'verifyEmail']);
    $router->post('/logout', [AuthController::class, 'logout'], [VerifyCsrfToken::class]);

    $router->get('/', function () {
        redirect(\App\Core\Session::has('user') ? '/dashboard' : '/login');
    });
});

return $router;
