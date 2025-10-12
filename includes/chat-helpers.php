<?php

if (!function_exists('chat_require_login')) {
    function chat_require_login(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Authentication required.'
            ]);
            exit;
        }
    }
}

if (!function_exists('chat_current_user_id')) {
    function chat_current_user_id(): int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    }
}

if (!function_exists('chat_current_user_name')) {
    function chat_current_user_name(): string
    {
        return isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';
    }
}

if (!function_exists('chat_json_response')) {
    function chat_json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('chat_compute_direct_key')) {
    function chat_compute_direct_key(int $userA, int $userB): string
    {
        if ($userA > $userB) {
            [$userA, $userB] = [$userB, $userA];
        }

        return hash('sha256', $userA . ':' . $userB);
    }
}

if (!function_exists('chat_ensure_conversation')) {
    function chat_ensure_conversation(PDO $pdo, array $participantIds, int $creatorId, ?string $name = null): array
    {
        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));
        if (count($participantIds) < 2) {
            throw new InvalidArgumentException('A conversation requires at least two participants.');
        }

        $isGroup = count($participantIds) > 2;
        sort($participantIds);

        if (!$isGroup) {
            [$userA, $userB] = $participantIds;
            $directKey = chat_compute_direct_key($userA, $userB);

            $stmt = $pdo->prepare('SELECT * FROM chat_conversations WHERE direct_key = :direct LIMIT 1');
            $stmt->execute(['direct' => $directKey]);
            $conversation = $stmt->fetch();

            if ($conversation) {
                return $conversation;
            }

            $pdo->beginTransaction();
            try {
                $insertConversation = $pdo->prepare('INSERT INTO chat_conversations (name, is_group, direct_key, created_by) VALUES (:name, 0, :direct_key, :created_by)');
                $insertConversation->execute([
                    'name'        => null,
                    'direct_key'  => $directKey,
                    'created_by'  => $creatorId,
                ]);

                $conversationId = (int) $pdo->lastInsertId();

                $participantStmt = $pdo->prepare('INSERT IGNORE INTO chat_participants (conversation_id, user_id) VALUES (:conversation_id, :user_id)');
                foreach ($participantIds as $userId) {
                    $participantStmt->execute([
                        'conversation_id' => $conversationId,
                        'user_id'         => $userId,
                    ]);
                }

                $pdo->commit();

                $stmt = $pdo->prepare('SELECT * FROM chat_conversations WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $conversationId]);
                $conversation = $stmt->fetch();

                if ($conversation) {
                    return $conversation;
                }
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            $pdo->beginTransaction();
            try {
                $insertConversation = $pdo->prepare('INSERT INTO chat_conversations (name, is_group, direct_key, created_by) VALUES (:name, 1, NULL, :created_by)');
                $insertConversation->execute([
                    'name'       => $name !== null ? trim($name) : null,
                    'created_by' => $creatorId,
                ]);

                $conversationId = (int) $pdo->lastInsertId();

                $participantStmt = $pdo->prepare('INSERT IGNORE INTO chat_participants (conversation_id, user_id) VALUES (:conversation_id, :user_id)');
                foreach ($participantIds as $userId) {
                    $participantStmt->execute([
                        'conversation_id' => $conversationId,
                        'user_id'         => $userId,
                    ]);
                }

                $pdo->commit();

                $stmt = $pdo->prepare('SELECT * FROM chat_conversations WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $conversationId]);
                $conversation = $stmt->fetch();

                if ($conversation) {
                    return $conversation;
                }
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        throw new RuntimeException('Failed to create or retrieve the conversation.');
    }
}

if (!function_exists('chat_assert_participant')) {
    function chat_assert_participant(PDO $pdo, int $conversationId, int $userId): void
    {
        $stmt = $pdo->prepare('SELECT 1 FROM chat_participants WHERE conversation_id = :conversation AND user_id = :user LIMIT 1');
        $stmt->execute([
            'conversation' => $conversationId,
            'user'         => $userId,
        ]);

        if (!$stmt->fetchColumn()) {
            chat_json_response(['error' => 'Access denied.'], 403);
        }
    }
}

if (!function_exists('chat_record_presence')) {
    function chat_record_presence(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare('INSERT INTO user_presence (user_id, last_seen) VALUES (:user_id, NOW()) ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)');
        $stmt->execute(['user_id' => $userId]);
    }
}

