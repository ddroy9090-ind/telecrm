<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/chat-helpers.php';

chat_require_login();

$pdo = hh_db();
$userId = chat_current_user_id();

$conversationId = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
$messageBody = isset($_POST['message']) ? trim((string) $_POST['message']) : '';

if ($conversationId <= 0 || $messageBody === '') {
    chat_json_response(['error' => 'conversation_id and message are required.'], 422);
}

try {
    chat_assert_participant($pdo, $conversationId, $userId);
    chat_record_presence($pdo, $userId);

    $pdo->beginTransaction();

    $insertMessage = $pdo->prepare('INSERT INTO chat_messages (conversation_id, sender_id, body) VALUES (:conversation, :sender, :body)');
    $insertMessage->execute([
        'conversation' => $conversationId,
        'sender'       => $userId,
        'body'         => $messageBody,
    ]);

    $messageId = (int) $pdo->lastInsertId();

    $markSenderRead = $pdo->prepare('INSERT INTO chat_message_reads (message_id, user_id, read_at) VALUES (:message, :user, NOW()) ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)');
    $markSenderRead->execute([
        'message' => $messageId,
        'user'    => $userId,
    ]);

    $updateParticipant = $pdo->prepare('UPDATE chat_participants SET last_read_message_id = :message, typing_at = NULL WHERE conversation_id = :conversation AND user_id = :user');
    $updateParticipant->execute([
        'message'      => $messageId,
        'conversation' => $conversationId,
        'user'         => $userId,
    ]);

    $pdo->commit();

    $messageStmt = $pdo->prepare('SELECT m.id, m.sender_id, m.body, m.created_at, u.full_name, u.email FROM chat_messages m JOIN users u ON u.id = m.sender_id WHERE m.id = :id LIMIT 1');
    $messageStmt->execute(['id' => $messageId]);
    $message = $messageStmt->fetch();

    if (!$message) {
        chat_json_response(['error' => 'Message not found after creation.'], 404);
    }

    chat_json_response([
        'message' => [
            'id'         => (int) $message['id'],
            'sender_id'  => (int) $message['sender_id'],
            'sender'     => $message['full_name'] !== '' ? $message['full_name'] : $message['email'],
            'body'       => $message['body'],
            'created_at' => $message['created_at'],
            'is_mine'    => true,
            'read_by'    => [
                [
                    'user_id' => $userId,
                    'name'    => chat_current_user_name(),
                    'read_at' => $message['created_at'],
                ],
            ],
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    chat_json_response([
        'error' => 'Unable to send the message.',
        'details' => $e->getMessage(),
    ], 500);
}
