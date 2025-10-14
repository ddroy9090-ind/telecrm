<?php

declare(strict_types=1);

use HouzzHunt\Controllers\ActivitiesController;
use HouzzHunt\Support\JsonResponder;
use Throwable;

try {
    [$container, $auth] = require __DIR__ . '/../../bootstrap.php';

    $controller = new ActivitiesController($container->activityService());

    $result = $controller->recent(
        [
            'role' => $auth['role'],
            'user_id' => $auth['user_id'],
            'user_name' => $auth['name'],
        ],
        isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 100)) : 20
    );

    JsonResponder::send($result);
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'error' => 'Unable to load recent activities.',
        'meta' => [
            'generated_at' => gmdate(DATE_ATOM),
        ],
    ], JSON_THROW_ON_ERROR);
}
