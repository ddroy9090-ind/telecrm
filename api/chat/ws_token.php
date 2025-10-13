<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/chat-helpers.php';

chat_require_login();

$pdo = hh_db();
$userId = chat_current_user_id();

try {
    $token = chat_issue_websocket_token($pdo, $userId);
    chat_json_response([
        'token'       => $token,
        'expires_in'  => 1800,
        'websocket'   => [
            'url'  => chat_websocket_url(),
            'port' => chat_websocket_port(),
        ],
    ]);
} catch (Throwable $e) {
    chat_json_response([
        'error' => 'Unable to create a WebSocket token.',
        'details' => $e->getMessage(),
    ], 500);
}
