<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Services\NotificationService;

final class NotificationController
{
    public function index(Request $request): void
    {
        $user = Session::get('user');
        $notifications = NotificationService::recent((int) $user['id'], 50);
        NotificationService::markAllRead((int) $user['id']);

        echo view('layouts.app', [
            'pageTitle' => 'Notifications',
            'unreadCount' => 0,
            'content' => view('notifications.index', ['notifications' => $notifications]),
        ]);
    }
}
