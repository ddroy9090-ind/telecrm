<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/chat-helpers.php';

chat_require_login();

$pdo = hh_db();
$userId = chat_current_user_id();

$conversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;
$afterId = isset($_GET['after_id']) ? max(0, (int) $_GET['after_id']) : 0;

if ($conversationId <= 0) {
    chat_json_response(['error' => 'conversation_id is required.'], 422);
}

try {
    chat_assert_participant($pdo, $conversationId, $userId);
    chat_record_presence($pdo, $userId);

    $messagesStmt = $pdo->prepare('SELECT m.id, m.sender_id, m.body, m.created_at, u.full_name, u.email FROM chat_messages m JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = :conversation AND m.id > :after ORDER BY m.id ASC');
    $messagesStmt->execute([
        'conversation' => $conversationId,
        'after'        => $afterId,
    ]);

    $messages = [];
    $messageIds = [];
    $lastMessageId = $afterId;

    while ($row = $messagesStmt->fetch()) {
        $messageId = (int) $row['id'];
        $messageIds[] = $messageId;
        $lastMessageId = max($lastMessageId, $messageId);
        $messages[] = [
            'id'         => $messageId,
            'sender_id'  => (int) $row['sender_id'],
            'sender'     => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
            'body'       => $row['body'],
            'created_at' => $row['created_at'],
            'is_mine'    => (int) $row['sender_id'] === $userId,
        ];
    }

    $receiptBaseline = max(0, $afterId - 50);
    $receiptIds = $messageIds;

    if ($receiptBaseline > 0) {
        $baselineStmt = $pdo->prepare('SELECT id FROM chat_messages WHERE conversation_id = :conversation AND id >= :baseline ORDER BY id ASC');
        $baselineStmt->execute([
            'conversation' => $conversationId,
            'baseline'     => $receiptBaseline,
        ]);
        while ($row = $baselineStmt->fetchColumn()) {
            $receiptIds[] = (int) $row;
        }
    }

    $receiptIds = array_values(array_unique($receiptIds));
    sort($receiptIds);

    $reads = [];
    if (count($receiptIds) > 0) {
        $inQuery = implode(',', array_fill(0, count($receiptIds), '?'));
        $readsStmt = $pdo->prepare('SELECT r.message_id, r.user_id, r.read_at, u.full_name, u.email FROM chat_message_reads r JOIN users u ON u.id = r.user_id WHERE r.message_id IN (' . $inQuery . ') ORDER BY r.read_at ASC');
        $readsStmt->execute($receiptIds);
        while ($row = $readsStmt->fetch()) {
            $reads[] = [
                'message_id' => (int) $row['message_id'],
                'user_id'    => (int) $row['user_id'],
                'name'       => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
                'read_at'    => $row['read_at'],
            ];
        }
    }

    $typingStmt = $pdo->prepare('SELECT cp.user_id, u.full_name, u.email FROM chat_participants cp JOIN users u ON u.id = cp.user_id WHERE cp.conversation_id = :conversation AND cp.user_id <> :user AND cp.typing_at IS NOT NULL AND cp.typing_at >= (NOW() - INTERVAL 5 SECOND) ORDER BY u.full_name');
    $typingStmt->execute([
        'conversation' => $conversationId,
        'user'         => $userId,
    ]);

    $typing = [];
    while ($row = $typingStmt->fetch()) {
        $typing[] = [
            'id'   => (int) $row['user_id'],
            'name' => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
        ];
    }

    $lastMessageStmt = $pdo->prepare('SELECT MAX(id) FROM chat_messages WHERE conversation_id = :conversation');
    $lastMessageStmt->execute(['conversation' => $conversationId]);
    $latestId = (int) $lastMessageStmt->fetchColumn();
    $lastMessageId = max($lastMessageId, $latestId);

    chat_json_response([
        'messages'         => $messages,
        'reads'            => $reads,
        'typing'           => $typing,
        'last_message_id'  => $lastMessageId,
    ]);
} catch (Throwable $e) {
    chat_json_response([
        'error' => 'Unable to poll conversation.',
        'details' => $e->getMessage(),
    ], 500);
}
