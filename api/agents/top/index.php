<?php

declare(strict_types=1);

use HouzzHunt\Controllers\AgentController;
use HouzzHunt\Support\JsonResponder;

try {
    [$container, $auth] = require __DIR__ . '/../../bootstrap.php';

    $controller = new AgentController($container->agentPerformanceService());

    $context = [
        'role' => $auth['role'],
        'user_id' => $auth['user_id'],
        'user_name' => $auth['name'],
    ];

    $sourceFilter = trim((string) ($_GET['source'] ?? ''));
    if ($sourceFilter !== '') {
        $context['source_filter'] = $sourceFilter;
    }

    $startDate = trim((string) ($_GET['start_date'] ?? ''));
    $endDate = trim((string) ($_GET['end_date'] ?? ''));

    $result = $controller->topAgents(
        (string) ($_GET['range'] ?? 'last_30_days'),
        $context,
        isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 20)) : 5,
        $startDate !== '' ? $startDate : null,
        $endDate !== '' ? $endDate : null
    );

    JsonResponder::send($result);
} catch (InvalidArgumentException $e) {
    if (!headers_sent()) {
        http_response_code(400);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'error' => $e->getMessage(),
        'meta' => [
            'generated_at' => gmdate(DATE_ATOM),
        ],
    ], JSON_THROW_ON_ERROR);
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
