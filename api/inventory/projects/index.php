<?php

declare(strict_types=1);

use HouzzHunt\Controllers\InventoryController;
use HouzzHunt\Support\JsonResponder;
use Throwable;

try {
    [$container, $auth] = require __DIR__ . '/../../bootstrap.php';

    $controller = new InventoryController($container->inventoryService());

    $result = $controller->projects((string) ($_GET['range'] ?? 'last_30_days'));

    JsonResponder::send($result);
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'error' => 'Unable to load inventory summary.',
        'meta' => [
            'generated_at' => gmdate(DATE_ATOM),
        ],
    ], JSON_THROW_ON_ERROR);
}
