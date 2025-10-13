<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/chat-helpers.php';

chat_require_login();

$pdo = hh_db();
$userId = chat_current_user_id();

$conversationId = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
$isTyping = isset($_POST['is_typing']) ? filter_var($_POST['is_typing'], FILTER_VALIDATE_BOOLEAN) : false;

if ($conversationId <= 0) {
    chat_json_response(['error' => 'conversation_id is required.'], 422);
}

try {
    chat_assert_participant($pdo, $conversationId, $userId);
    chat_record_presence($pdo, $userId);

    if ($isTyping) {
        $stmt = $pdo->prepare('UPDATE chat_participants SET typing_at = NOW() WHERE conversation_id = :conversation AND user_id = :user');
    } else {
        $stmt = $pdo->prepare('UPDATE chat_participants SET typing_at = NULL WHERE conversation_id = :conversation AND user_id = :user');
    }

    $stmt->execute([
        'conversation' => $conversationId,
        'user'         => $userId,
    ]);

    try {
        $participants = chat_conversation_participant_ids($pdo, $conversationId);
        chat_queue_event($pdo, 'typing', [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'name'            => chat_current_user_name(),
            'is_typing'       => $isTyping,
        ], $participants, $conversationId);
    } catch (Throwable $eventError) {
        // Silently ignore event queue failures.
    }

    chat_json_response(['status' => 'ok']);
} catch (Throwable $e) {
    chat_json_response([
        'error' => 'Unable to update typing state.',
        'details' => $e->getMessage(),
    ], 500);
}
