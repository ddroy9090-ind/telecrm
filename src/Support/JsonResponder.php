<?php

declare(strict_types=1);

namespace HouzzHunt\Support;

final class JsonResponder
{
    public static function send(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
        }

        echo json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
