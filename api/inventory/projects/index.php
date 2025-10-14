<?php

declare(strict_types=1);

use HouzzHunt\Controllers\InventoryController;
use HouzzHunt\Support\JsonResponder;

try {
    [$container, $auth] = require __DIR__ . '/../../bootstrap.php';

    $controller = new InventoryController($container->inventoryService());

    $startDate = trim((string) ($_GET['start_date'] ?? ''));
    $endDate = trim((string) ($_GET['end_date'] ?? ''));

    $result = $controller->projects(
        (string) ($_GET['range'] ?? 'last_30_days'),
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
        'error' => 'Unable to load inventory summary.',
        'meta' => [
            'generated_at' => gmdate(DATE_ATOM),
        ],
    ], JSON_THROW_ON_ERROR);
}
