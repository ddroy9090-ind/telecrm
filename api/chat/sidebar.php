<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/chat-helpers.php';

chat_require_login();

$pdo = hh_db();
$userId = chat_current_user_id();

try {
    chat_record_presence($pdo, $userId);

    $currentUserStmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = :id LIMIT 1');
    $currentUserStmt->execute(['id' => $userId]);
    $currentUser = $currentUserStmt->fetch();

    if (!$currentUser) {
        chat_json_response(['error' => 'User not found.'], 404);
    }

    $usersStmt = $pdo->prepare(<<<SQL
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.role,
            up.last_seen,
            CASE
                WHEN up.last_seen IS NOT NULL AND up.last_seen >= (NOW() - INTERVAL 90 SECOND) THEN 1
                ELSE 0
            END AS is_online,
            dc.id AS conversation_id,
            COALESCE(unread.unread_count, 0) AS unread_count
        FROM users u
        LEFT JOIN user_presence up ON up.user_id = u.id
        LEFT JOIN chat_conversations dc ON dc.direct_key = SHA2(CONCAT(LEAST(u.id, :me), ':', GREATEST(u.id, :me)), 256)
        LEFT JOIN (
            SELECT
                m.conversation_id,
                COUNT(*) AS unread_count
            FROM chat_messages m
            INNER JOIN chat_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = :me
            WHERE m.sender_id <> :me
              AND (cp.last_read_message_id IS NULL OR m.id > cp.last_read_message_id)
            GROUP BY m.conversation_id
        ) unread ON unread.conversation_id = dc.id
        WHERE u.id <> :me
        ORDER BY u.full_name ASC
    SQL);

    $usersStmt->execute(['me' => $userId]);
    $users = [];
    while ($row = $usersStmt->fetch()) {
        $users[] = [
            'id'              => (int) $row['id'],
            'name'            => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
            'email'           => $row['email'],
            'role'            => $row['role'],
            'is_online'       => (bool) $row['is_online'],
            'last_seen'       => $row['last_seen'],
            'conversation_id' => $row['conversation_id'] !== null ? (int) $row['conversation_id'] : null,
            'unread_count'    => (int) ($row['unread_count'] ?? 0),
        ];
    }

    $groupsStmt = $pdo->prepare(<<<SQL
        SELECT
            c.id,
            c.name,
            c.created_at,
            COALESCE(unread.unread_count, 0) AS unread_count,
            lm.id AS last_message_id,
            lm.body AS last_message_body,
            lm.created_at AS last_message_at,
            sender.full_name AS last_message_sender
        FROM chat_conversations c
        INNER JOIN chat_participants me_participant ON me_participant.conversation_id = c.id AND me_participant.user_id = :me
        LEFT JOIN (
            SELECT
                inner_m.conversation_id,
                inner_m.id,
                inner_m.body,
                inner_m.created_at,
                inner_m.sender_id
            FROM chat_messages inner_m
            WHERE inner_m.id IN (
                SELECT MAX(inner_m2.id)
                FROM chat_messages inner_m2
                WHERE inner_m2.conversation_id = inner_m.conversation_id
            )
        ) lm ON lm.conversation_id = c.id
        LEFT JOIN users sender ON sender.id = lm.sender_id
        LEFT JOIN (
            SELECT
                m.conversation_id,
                COUNT(*) AS unread_count
            FROM chat_messages m
            INNER JOIN chat_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = :me
            WHERE m.sender_id <> :me
              AND (cp.last_read_message_id IS NULL OR m.id > cp.last_read_message_id)
            GROUP BY m.conversation_id
        ) unread ON unread.conversation_id = c.id
        WHERE c.is_group = 1
        ORDER BY COALESCE(lm.created_at, c.created_at) DESC, c.id DESC
    SQL);

    $groupsStmt->execute(['me' => $userId]);
    $groups = $groupsStmt->fetchAll();

    $groupIds = array_map(static fn ($g) => (int) $g['id'], $groups);
    $groupParticipants = [];

    if (count($groupIds) > 0) {
        $inQuery = implode(',', array_fill(0, count($groupIds), '?'));
        $participantStmt = $pdo->prepare(
            'SELECT cp.conversation_id, u.id, u.full_name, u.email FROM chat_participants cp JOIN users u ON u.id = cp.user_id WHERE cp.conversation_id IN (' . $inQuery . ') ORDER BY u.full_name'
        );
        $participantStmt->execute($groupIds);
        while ($row = $participantStmt->fetch()) {
            $conversationId = (int) $row['conversation_id'];
            $groupParticipants[$conversationId][] = [
                'id'    => (int) $row['id'],
                'name'  => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
                'email' => $row['email'],
            ];
        }
    }

    $groupsPayload = array_map(static function ($group) use ($groupParticipants) {
        $conversationId = (int) $group['id'];
        return [
            'id'                => $conversationId,
            'name'              => $group['name'] !== null && $group['name'] !== '' ? $group['name'] : 'Group Chat',
            'unread_count'      => (int) ($group['unread_count'] ?? 0),
            'last_message'      => $group['last_message_body'],
            'last_message_at'   => $group['last_message_at'],
            'last_message_from' => $group['last_message_sender'],
            'participants'      => $groupParticipants[$conversationId] ?? [],
        ];
    }, $groups);

    chat_json_response([
        'current_user' => [
            'id'    => (int) $currentUser['id'],
            'name'  => $currentUser['full_name'] !== '' ? $currentUser['full_name'] : $currentUser['email'],
            'email' => $currentUser['email'],
            'role'  => $currentUser['role'],
        ],
        'users'        => $users,
        'groups'       => $groupsPayload,
    ]);
} catch (Throwable $e) {
    chat_json_response([
        'error' => 'Unable to build sidebar.',
        'details' => $e->getMessage(),
    ], 500);
}
