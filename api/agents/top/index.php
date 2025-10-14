<?php

declare(strict_types=1);

use HouzzHunt\Controllers\AgentController;
use HouzzHunt\Support\JsonResponder;
use Throwable;

try {
    [$container, $auth] = require __DIR__ . '/../../bootstrap.php';

    $controller = new AgentController($container->agentPerformanceService());

    $result = $controller->topAgents(
        (string) ($_GET['range'] ?? 'last_30_days'),
        [
            'role' => $auth['role'],
            'user_id' => $auth['user_id'],
            'user_name' => $auth['name'],
        ],
        isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 20)) : 5
    );

    JsonResponder::send($result);
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'error' => 'Unable to load agent leaderboard.',
        'meta' => [
            'generated_at' => gmdate(DATE_ATOM),
        ],
    ], JSON_THROW_ON_ERROR);
}
