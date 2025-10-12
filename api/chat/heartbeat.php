<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/chat-helpers.php';

chat_require_login();

$pdo = hh_db();
$userId = chat_current_user_id();

try {
    chat_record_presence($pdo, $userId);
    chat_json_response(['status' => 'ok']);
} catch (Throwable $e) {
    chat_json_response([
        'error' => 'Unable to update presence.',
        'details' => $e->getMessage(),
    ], 500);
}
