<?php

declare(strict_types=1);

use HouzzHunt\Controllers\StatsController;
use HouzzHunt\Support\JsonResponder;

try {
    [$container, $auth] = require __DIR__ . '/../../bootstrap.php';

    $controller = new StatsController(
        $container->leadStatsService(),
        $container->performanceService()
    );

    $agentId = isset($_GET['agent_id']) ? (int) $_GET['agent_id'] : null;
    $sourceFilter = trim((string) ($_GET['source'] ?? ''));
    $context = [
        'role' => $auth['role'],
        'user_id' => $auth['user_id'],
        'user_name' => $auth['name'],
    ];

    if ($agentId !== null && $agentId > 0) {
        if ($auth['role'] === 'agent' && $agentId !== $auth['user_id']) {
            throw new RuntimeException('Agents can only view their own metrics.');
        }

        $agent = $container->userRepository()->find($agentId);
        if ($agent === null) {
            throw new RuntimeException('Agent not found.');
        }

        $context['agent_filter_id'] = $agentId;
        $context['agent_filter_name'] = $agent['full_name'] ?? $agent['name'] ?? null;
    }

    if ($sourceFilter !== '') {
        $context['source_filter'] = $sourceFilter;
    }

    $startDate = trim((string) ($_GET['start_date'] ?? ''));
    $endDate = trim((string) ($_GET['end_date'] ?? ''));

    $result = $controller->leadCounters(
        (string) ($_GET['range'] ?? 'last_30_days'),
        $context,
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
        'error' => 'Unable to load lead counters.',
        'meta' => [
            'generated_at' => gmdate(DATE_ATOM),
        ],
    ], JSON_THROW_ON_ERROR);
}
