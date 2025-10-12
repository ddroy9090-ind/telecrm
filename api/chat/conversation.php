<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/chat-helpers.php';

chat_require_login();

$pdo = hh_db();
$userId = chat_current_user_id();

$conversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;
if ($conversationId <= 0) {
    chat_json_response(['error' => 'conversation_id is required.'], 422);
}

try {
    $conversationStmt = $pdo->prepare('SELECT id, name, is_group, direct_key, created_at FROM chat_conversations WHERE id = :id LIMIT 1');
    $conversationStmt->execute(['id' => $conversationId]);
    $conversation = $conversationStmt->fetch();

    if (!$conversation) {
        chat_json_response(['error' => 'Conversation not found.'], 404);
    }

    chat_assert_participant($pdo, $conversationId, $userId);
    chat_record_presence($pdo, $userId);

    $participantStmt = $pdo->prepare('SELECT cp.user_id, cp.last_read_message_id, cp.typing_at, u.full_name, u.email FROM chat_participants cp JOIN users u ON u.id = cp.user_id WHERE cp.conversation_id = :conversation ORDER BY u.full_name');
    $participantStmt->execute(['conversation' => $conversationId]);
    $participants = $participantStmt->fetchAll();

    $participantPayload = [];
    $displayName = $conversation['name'] ?? 'Conversation';

    if ((int) $conversation['is_group'] === 0) {
        foreach ($participants as $participant) {
            if ((int) $participant['user_id'] !== $userId) {
                $displayName = $participant['full_name'] !== '' ? $participant['full_name'] : $participant['email'];
                break;
            }
        }
    } elseif ($displayName === null || trim($displayName) === '') {
        $displayName = 'Group Chat';
    }

    foreach ($participants as $participant) {
        $participantPayload[] = [
            'id'                 => (int) $participant['user_id'],
            'name'               => $participant['full_name'] !== '' ? $participant['full_name'] : $participant['email'],
            'email'              => $participant['email'],
            'last_read_message'  => $participant['last_read_message_id'] !== null ? (int) $participant['last_read_message_id'] : null,
            'typing_at'          => $participant['typing_at'],
        ];
    }

    $messageLimit = isset($_GET['limit']) ? max(10, min(500, (int) $_GET['limit'])) : 200;

    $messageStmt = $pdo->prepare('SELECT m.id, m.sender_id, m.body, m.created_at, u.full_name, u.email FROM chat_messages m JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = :conversation ORDER BY m.id DESC LIMIT :limit');
    $messageStmt->bindValue(':conversation', $conversationId, PDO::PARAM_INT);
    $messageStmt->bindValue(':limit', $messageLimit, PDO::PARAM_INT);
    $messageStmt->execute();
    $messages = array_reverse($messageStmt->fetchAll());

    $messageIds = array_map(static fn ($m) => (int) $m['id'], $messages);
    $readMap = [];

    if (count($messageIds) > 0) {
        $inQuery = implode(',', array_fill(0, count($messageIds), '?'));
        $readsStmt = $pdo->prepare('SELECT r.message_id, r.user_id, r.read_at, u.full_name, u.email FROM chat_message_reads r JOIN users u ON u.id = r.user_id WHERE r.message_id IN (' . $inQuery . ') ORDER BY r.read_at ASC');
        $readsStmt->execute($messageIds);
        while ($row = $readsStmt->fetch()) {
            $messageId = (int) $row['message_id'];
            $readMap[$messageId][] = [
                'user_id' => (int) $row['user_id'],
                'name'    => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
                'read_at' => $row['read_at'],
            ];
        }
    }

    $messagePayload = [];
    $lastMessageId = 0;

    foreach ($messages as $message) {
        $messageId = (int) $message['id'];
        $lastMessageId = max($lastMessageId, $messageId);
        $messagePayload[] = [
            'id'         => $messageId,
            'sender_id'  => (int) $message['sender_id'],
            'sender'     => $message['full_name'] !== '' ? $message['full_name'] : $message['email'],
            'body'       => $message['body'],
            'created_at' => $message['created_at'],
            'is_mine'    => (int) $message['sender_id'] === $userId,
            'read_by'    => $readMap[$messageId] ?? [],
        ];
    }

    $typingParticipants = [];
    $now = new DateTimeImmutable('now');
    foreach ($participants as $participant) {
        if ((int) $participant['user_id'] === $userId) {
            continue;
        }
        if ($participant['typing_at'] === null) {
            continue;
        }
        try {
            $typingAt = new DateTimeImmutable($participant['typing_at']);
        } catch (Throwable $e) {
            continue;
        }
        if ($typingAt >= $now->sub(new DateInterval('PT5S'))) {
            $typingParticipants[] = [
                'id'   => (int) $participant['user_id'],
                'name' => $participant['full_name'] !== '' ? $participant['full_name'] : $participant['email'],
            ];
        }
    }

    chat_json_response([
        'conversation' => [
            'id'           => (int) $conversation['id'],
            'name'         => $displayName,
            'is_group'     => (bool) $conversation['is_group'],
            'created_at'   => $conversation['created_at'],
            'participants' => $participantPayload,
        ],
        'messages'        => $messagePayload,
        'last_message_id' => $lastMessageId,
        'typing'          => $typingParticipants,
    ]);
} catch (Throwable $e) {
    chat_json_response([
        'error' => 'Unable to load conversation.',
        'details' => $e->getMessage(),
    ], 500);
}
