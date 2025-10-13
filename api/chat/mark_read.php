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

    $readsStmt = $pdo->prepare(<<<SQL
        SELECT
            r.message_id,
            r.user_id,
            r.read_at,
            u.full_name,
            u.email
        FROM chat_message_reads r
        JOIN chat_messages m ON m.id = r.message_id
        JOIN users u ON u.id = r.user_id
        WHERE m.conversation_id = :conversation
          AND r.user_id = :user
          AND r.message_id <= :last_message
        ORDER BY r.message_id ASC
    SQL);
    $readsStmt->execute([
        'conversation' => $conversationId,
        'user'         => $userId,
        'last_message' => $lastMessageId,
    ]);

    $reads = [];
    while ($row = $readsStmt->fetch()) {
        $reads[] = [
            'message_id' => (int) $row['message_id'],
            'user_id'    => (int) $row['user_id'],
            'name'       => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
            'read_at'    => $row['read_at'],
        ];
    }

    try {
        $participants = chat_conversation_participant_ids($pdo, $conversationId);
        chat_queue_event($pdo, 'read', [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'reads'           => $reads,
        ], $participants, $conversationId);
    } catch (Throwable $eventError) {
        // Ignore queue failures.
    }

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
