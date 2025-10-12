<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/chat-helpers.php';

chat_require_login();

$pdo = hh_db();
$userId = chat_current_user_id();

$conversationId = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
$lastMessageId = isset($_POST['last_message_id']) ? (int) $_POST['last_message_id'] : 0;

if ($conversationId <= 0 || $lastMessageId <= 0) {
    chat_json_response(['error' => 'conversation_id and last_message_id are required.'], 422);
}

try {
    chat_assert_participant($pdo, $conversationId, $userId);
    chat_record_presence($pdo, $userId);

    $pdo->beginTransaction();

    $updateParticipant = $pdo->prepare('UPDATE chat_participants SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), :last_message) WHERE conversation_id = :conversation AND user_id = :user');
    $updateParticipant->execute([
        'last_message' => $lastMessageId,
        'conversation' => $conversationId,
        'user'         => $userId,
    ]);

    $insertReads = $pdo->prepare(<<<SQL
        INSERT INTO chat_message_reads (message_id, user_id, read_at)
        SELECT m.id, :user, NOW()
        FROM chat_messages m
        LEFT JOIN chat_message_reads r ON r.message_id = m.id AND r.user_id = :user
        WHERE m.conversation_id = :conversation
          AND m.id <= :last_message
          AND r.message_id IS NULL
    SQL);
    $insertReads->execute([
        'user'         => $userId,
        'conversation' => $conversationId,
        'last_message' => $lastMessageId,
    ]);

    $pdo->commit();

    chat_json_response(['status' => 'ok']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    chat_json_response([
        'error' => 'Unable to update read receipts.',
        'details' => $e->getMessage(),
    ], 500);
}
