<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ChatbotService;

final class ChatbotController
{
    public function message(Request $request): void
    {
        $sessionId = substr((string) $request->input('session_id', ''), 0, 64) ?: bin2hex(random_bytes(8));
        $message = trim((string) $request->input('message', ''));

        if ($message === '') {
            Response::json(['reply' => 'Please type a message.', 'quick_replies' => []]);
        }
        if (mb_strlen($message) > 500) {
            $message = mb_substr($message, 0, 500);
        }

        $user = Session::get('user');
        $userId = $user['id'] ?? null;

        ChatbotService::logMessage($userId, $sessionId, 'user', $message);
        $response = ChatbotService::respond($message, $userId);
        ChatbotService::logMessage($userId, $sessionId, 'bot', $response['reply_html'] ?? $response['reply'] ?? '');

        Response::json($response);
    }
}
