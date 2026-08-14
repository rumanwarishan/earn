<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DepositController;
use App\Controllers\NotificationController;
use App\Controllers\OrderController;
use App\Controllers\ProfileController;
use App\Controllers\ReferralController;
use App\Controllers\ShopController;
use App\Controllers\WalletController;
use App\Controllers\WithdrawalController;
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

    // ---- Public shop routes (browsable without login) ----
    $router->get('/shop', [ShopController::class, 'index']);
    $router->get('/shop/go/{id}', [ShopController::class, 'go']);
    $router->get('/shop/{slug}', [ShopController::class, 'show']);

    // ---- Authenticated user routes ----
    $router->group('', [AuthMiddleware::class], function (Router $router) {
        $router->get('/dashboard', [DashboardController::class, 'index']);

        $router->get('/wallet', [WalletController::class, 'index']);
        $router->get('/wallet/deposit', [DepositController::class, 'show']);
        $router->post('/wallet/deposit', [DepositController::class, 'submit'], [VerifyCsrfToken::class]);
        $router->get('/wallet/withdraw', [WithdrawalController::class, 'show']);
        $router->post('/wallet/withdraw', [WithdrawalController::class, 'submit'], [VerifyCsrfToken::class]);
        $router->post('/wallet/withdraw/{id}/cancel', [WithdrawalController::class, 'cancel'], [VerifyCsrfToken::class]);

        $router->get('/referral', [ReferralController::class, 'index']);
        $router->get('/profile', [ProfileController::class, 'index']);
        $router->post('/profile/password', [ProfileController::class, 'updatePassword'], [VerifyCsrfToken::class]);
        $router->get('/notifications', [NotificationController::class, 'index']);
        $router->get('/orders', [OrderController::class, 'index']);
    });
});

return $router;
