<?php

declare(strict_types=1);

use HouzzHunt\Controllers\SearchController;
use HouzzHunt\Support\JsonResponder;

try {
    [$container, $auth] = require __DIR__ . '/../bootstrap.php';

    $term = trim((string) ($_GET['q'] ?? ''));
    if ($term === '') {
        throw new RuntimeException('Search term is required.');
    }

    $controller = new SearchController($container->searchService());
    $result = $controller->search($term, [
        'role' => $auth['role'],
        'user_id' => $auth['user_id'],
        'user_name' => $auth['name'],
    ]);

    JsonResponder::send($result);
} catch (RuntimeException $e) {
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
        'error' => 'Unable to complete search.',
        'meta' => [
            'generated_at' => gmdate(DATE_ATOM),
        ],
    ], JSON_THROW_ON_ERROR);
}
