<?php

declare(strict_types=1);

namespace HouzzHunt\Support;

final class AccessControl
{
    /**
     * Ensure a user is authenticated and return their session payload.
     *
     * @return array{user_id:int|null, role:string|null, name:string|null}
     */
    public static function requireAuth(): array
    {
        $loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

        if (!$loggedIn) {
            self::deny(401, 'Authentication required.');
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $role   = isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
        $name   = isset($_SESSION['username']) ? (string) $_SESSION['username'] : null;

        if ($userId === null || $role === null) {
            self::deny(401, 'Authentication required.');
        }

        return [
            'user_id' => $userId,
            'role'    => $role,
            'name'    => $name,
        ];
    }

    /**
     * Abort the request with a JSON response and exit.
     */
    public static function deny(int $statusCode, string $message): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
        }

        echo json_encode([
            'error' => $message,
            'meta'  => [
                'generated_at' => gmdate(DATE_ATOM),
            ],
        ], JSON_THROW_ON_ERROR);

        exit;
    }
}
