<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/chat-helpers.php';

chat_require_login();

$pdo = hh_db();
$userId = chat_current_user_id();

$rawInput = file_get_contents('php://input');
$payload = [];

if ($rawInput !== false && $rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = array_merge($payload, $_POST);
}

$participantIds = $payload['participants'] ?? [];
if (is_string($participantIds)) {
    $participantIds = array_filter(array_map('intval', explode(',', $participantIds)));
} elseif (!is_array($participantIds)) {
    $participantIds = [];
}

$participantIds[] = $userId;
$participantIds = array_values(array_unique(array_map('intval', $participantIds)));

try {
    if (count($participantIds) < 2) {
        chat_json_response(['error' => 'At least two participants are required.'], 422);
    }

    $name = isset($payload['name']) ? trim((string) $payload['name']) : null;
    $conversation = chat_ensure_conversation($pdo, $participantIds, $userId, $name);

    $participantStmt = $pdo->prepare('SELECT cp.user_id, u.full_name, u.email FROM chat_participants cp JOIN users u ON u.id = cp.user_id WHERE cp.conversation_id = :conversation ORDER BY u.full_name');
    $participantStmt->execute(['conversation' => $conversation['id']]);
    $participants = [];
    while ($row = $participantStmt->fetch()) {
        $participants[] = [
            'id'    => (int) $row['user_id'],
            'name'  => $row['full_name'] !== '' ? $row['full_name'] : $row['email'],
            'email' => $row['email'],
        ];
    }

    chat_json_response([
        'conversation' => [
            'id'        => (int) $conversation['id'],
            'name'      => $conversation['is_group'] ? ($conversation['name'] ?? 'Group Chat') : null,
            'is_group'  => (bool) $conversation['is_group'],
            'created_at'=> $conversation['created_at'],
        ],
        'participants' => $participants,
    ]);
} catch (Throwable $e) {
    chat_json_response([
        'error' => 'Unable to start the conversation.',
        'details' => $e->getMessage(),
    ], 500);
}
