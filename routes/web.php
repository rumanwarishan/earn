<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\ChatbotController;
use App\Controllers\DashboardController;
use App\Controllers\DepositController;
use App\Controllers\GameController;
use App\Controllers\NotificationController;
use App\Controllers\PurchaseController;
use App\Controllers\TaskController;
use App\Controllers\ProfileController;
use App\Controllers\ReferralController;
use App\Controllers\ShopController;
use App\Controllers\WalletController;
use App\Controllers\WatchEarnController;
use App\Controllers\WithdrawalController;
use App\Controllers\Admin\AdminAdController;
use App\Controllers\Admin\AdminAuditController;
use App\Controllers\Admin\AdminAuthController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminDepositController;
use App\Controllers\Admin\AdminGameController;
use App\Controllers\Admin\AdminMarketplaceController;
use App\Controllers\Admin\AdminMembershipController;
use App\Controllers\Admin\AdminOrderController;
use App\Controllers\Admin\AdminProductController;
use App\Controllers\Admin\AdminTaskController;
use App\Controllers\Admin\AdminSettingsController;
use App\Controllers\Admin\AdminUserController;
use App\Controllers\Admin\AdminWalletController;
use App\Controllers\Admin\AdminWithdrawalController;
use App\Middleware\SecurityHeaders;
use App\Middleware\VerifyCsrfToken;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\AdminAuthMiddleware;
use App\Middleware\AdminGuestMiddleware;
use App\Middleware\MaintenanceMode;

$router = new Router();

$router->group('', [SecurityHeaders::class, MaintenanceMode::class], function (Router $router) {

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

    $router->post('/api/chatbot/message', [ChatbotController::class, 'message']);

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
        $router->get('/tasks', [TaskController::class, 'index']);
        $router->post('/tasks/{id}/claim', [TaskController::class, 'claim'], [VerifyCsrfToken::class]);
        $router->get('/orders', function () { redirect('/tasks'); });

        $router->get('/watch-and-earn', [WatchEarnController::class, 'index']);
        $router->post('/watch-and-earn/{id}/start', [WatchEarnController::class, 'start'], [VerifyCsrfToken::class]);
        $router->post('/watch-and-earn/session/{uuid}/complete', [WatchEarnController::class, 'complete'], [VerifyCsrfToken::class]);

        $router->post('/shop/{id}/buy', [PurchaseController::class, 'buy'], [VerifyCsrfToken::class]);

        // ---- Billions Flight (virtual Game Points only - see GameRoundService) ----
        $router->get('/game', [GameController::class, 'index']);
        $router->get('/game/state', [GameController::class, 'state']);
        $router->post('/game/round/join', [GameController::class, 'join'], [VerifyCsrfToken::class]);
        $router->post('/game/round/{uuid}/cashout', [GameController::class, 'cashout'], [VerifyCsrfToken::class]);
        $router->post('/game/daily-bonus', [GameController::class, 'dailyBonus'], [VerifyCsrfToken::class]);
        $router->get('/game/history', [GameController::class, 'history']);
        $router->get('/game/round-history', [GameController::class, 'roundHistory']);
    });

    // ---- Admin panel ----
    $router->group('/admin', [], function (Router $router) {
        $router->group('', [AdminGuestMiddleware::class], function (Router $router) {
            $router->get('/login', [AdminAuthController::class, 'showLogin']);
            $router->post('/login', [AdminAuthController::class, 'login'], [VerifyCsrfToken::class]);
        });

        $router->group('', [AdminAuthMiddleware::class], function (Router $router) {
            $router->post('/logout', [AdminAuthController::class, 'logout'], [VerifyCsrfToken::class]);
            $router->get('/dashboard', [AdminDashboardController::class, 'index']);

            $router->get('/users', [AdminUserController::class, 'index']);
            $router->get('/users/{id}', [AdminUserController::class, 'show']);
            $router->post('/users/{id}/status', [AdminUserController::class, 'toggleStatus'], [VerifyCsrfToken::class]);

            $router->get('/deposits', [AdminDepositController::class, 'index']);
            $router->get('/deposits/{id}', [AdminDepositController::class, 'show']);
            $router->post('/deposits/{id}/approve', [AdminDepositController::class, 'approve'], [VerifyCsrfToken::class]);
            $router->post('/deposits/{id}/reject', [AdminDepositController::class, 'reject'], [VerifyCsrfToken::class]);

            $router->get('/withdrawals', [AdminWithdrawalController::class, 'index']);
            $router->get('/withdrawals/{id}', [AdminWithdrawalController::class, 'show']);
            $router->post('/withdrawals/{id}/approve', [AdminWithdrawalController::class, 'approve'], [VerifyCsrfToken::class]);
            $router->post('/withdrawals/{id}/reject', [AdminWithdrawalController::class, 'reject'], [VerifyCsrfToken::class]);
            $router->post('/withdrawals/{id}/processing', [AdminWithdrawalController::class, 'processing'], [VerifyCsrfToken::class]);
            $router->post('/withdrawals/{id}/paid', [AdminWithdrawalController::class, 'paid'], [VerifyCsrfToken::class]);
            $router->post('/withdrawals/{id}/completed', [AdminWithdrawalController::class, 'completed'], [VerifyCsrfToken::class]);

            $router->get('/wallets', [AdminWalletController::class, 'index']);
            $router->get('/wallets/{id}', [AdminWalletController::class, 'userWallet']);
            $router->post('/wallets/{id}/adjust', [AdminWalletController::class, 'adjust'], [VerifyCsrfToken::class]);
            $router->post('/wallets/{id}/toggle-freeze', [AdminWalletController::class, 'toggleFreeze'], [VerifyCsrfToken::class]);

            $router->get('/products', [AdminProductController::class, 'index']);
            $router->get('/products/new', [AdminProductController::class, 'create']);
            $router->post('/products', [AdminProductController::class, 'store'], [VerifyCsrfToken::class]);
            $router->get('/products/import', [AdminProductController::class, 'importShow']);
            $router->post('/products/import/preview', [AdminProductController::class, 'importPreview'], [VerifyCsrfToken::class]);
            $router->post('/products/import/confirm', [AdminProductController::class, 'importConfirm'], [VerifyCsrfToken::class]);
            $router->get('/products/export', [AdminProductController::class, 'exportCsv']);
            $router->get('/products/{id}/edit', [AdminProductController::class, 'edit']);
            $router->post('/products/{id}', [AdminProductController::class, 'update'], [VerifyCsrfToken::class]);
            $router->post('/products/{id}/archive', [AdminProductController::class, 'archive'], [VerifyCsrfToken::class]);
            $router->post('/products/{id}/duplicate', [AdminProductController::class, 'duplicate'], [VerifyCsrfToken::class]);

            $router->get('/orders', [AdminOrderController::class, 'index']);
            $router->get('/orders/new', [AdminOrderController::class, 'create']);
            $router->post('/orders', [AdminOrderController::class, 'store'], [VerifyCsrfToken::class]);
            $router->get('/orders/{id}', [AdminOrderController::class, 'show']);
            $router->post('/orders/{id}/status', [AdminOrderController::class, 'updateStatus'], [VerifyCsrfToken::class]);
            $router->post('/orders/{id}/reverse-cashback', [AdminOrderController::class, 'reverseCashback'], [VerifyCsrfToken::class]);

            $router->get('/membership-levels', [AdminMembershipController::class, 'index']);
            $router->post('/membership-levels/{id}', [AdminMembershipController::class, 'update'], [VerifyCsrfToken::class]);

            $router->get('/tasks', [AdminTaskController::class, 'index']);
            $router->get('/tasks/new', [AdminTaskController::class, 'create']);
            $router->post('/tasks', [AdminTaskController::class, 'store'], [VerifyCsrfToken::class]);
            $router->get('/tasks/{id}/edit', [AdminTaskController::class, 'edit']);
            $router->post('/tasks/{id}', [AdminTaskController::class, 'update'], [VerifyCsrfToken::class]);
            $router->post('/tasks/{id}/toggle', [AdminTaskController::class, 'toggleActive'], [VerifyCsrfToken::class]);
            $router->post('/tasks/{id}/delete', [AdminTaskController::class, 'destroy'], [VerifyCsrfToken::class]);

            $router->get('/ads', [AdminAdController::class, 'index']);
            $router->get('/ads/new', [AdminAdController::class, 'create']);
            $router->post('/ads', [AdminAdController::class, 'store'], [VerifyCsrfToken::class]);
            $router->get('/ads/{id}/edit', [AdminAdController::class, 'edit']);
            $router->post('/ads/{id}', [AdminAdController::class, 'update'], [VerifyCsrfToken::class]);
            $router->post('/ads/{id}/toggle', [AdminAdController::class, 'toggleActive'], [VerifyCsrfToken::class]);
            $router->post('/ads/{id}/delete', [AdminAdController::class, 'destroy'], [VerifyCsrfToken::class]);

            $router->get('/marketplaces', [AdminMarketplaceController::class, 'index']);
            $router->post('/marketplaces', [AdminMarketplaceController::class, 'store'], [VerifyCsrfToken::class]);
            $router->post('/marketplaces/categories', [AdminMarketplaceController::class, 'storeCategory'], [VerifyCsrfToken::class]);
            $router->post('/marketplaces/{id}/toggle', [AdminMarketplaceController::class, 'toggleActive'], [VerifyCsrfToken::class]);

            $router->get('/settings', [AdminSettingsController::class, 'index']);
            $router->post('/settings', [AdminSettingsController::class, 'update'], [VerifyCsrfToken::class]);
            $router->post('/settings/referral-levels', [AdminSettingsController::class, 'addReferralLevel'], [VerifyCsrfToken::class]);
            $router->post('/settings/referral-levels/{id}', [AdminSettingsController::class, 'updateReferralLevel'], [VerifyCsrfToken::class]);

            $router->get('/audit-logs', [AdminAuditController::class, 'index']);

            $router->get('/game', [AdminGameController::class, 'index']);
            $router->get('/game/rounds', [AdminGameController::class, 'rounds']);
            $router->get('/game/settings', [AdminGameController::class, 'settingsShow']);
            $router->post('/game/settings', [AdminGameController::class, 'settingsUpdate'], [VerifyCsrfToken::class]);
            $router->post('/game-points/adjust', [AdminGameController::class, 'adjustGamePoints'], [VerifyCsrfToken::class]);
        });
    });
});

return $router;
