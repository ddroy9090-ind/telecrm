<?php

declare(strict_types=1);

use HouzzHunt\Controllers\StatsController;
use HouzzHunt\Support\JsonResponder;
use Throwable;

try {
    [$container, $auth] = require __DIR__ . '/../../bootstrap.php';

    $controller = new StatsController(
        $container->leadStatsService(),
        $container->performanceService()
    );

    $result = $controller->leadCounters(
        (string) ($_GET['range'] ?? 'last_30_days'),
        [
            'role' => $auth['role'],
            'user_id' => $auth['user_id'],
            'user_name' => $auth['name'],
        ]
    );

    JsonResponder::send($result);
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'error' => 'Unable to load lead counters.',
        'meta' => [
            'generated_at' => gmdate(DATE_ATOM),
        ],
    ], JSON_THROW_ON_ERROR);
}
