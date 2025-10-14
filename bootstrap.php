<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('hh_datamap')) {
    /**
     * Load the database to UI mapping as a shared immutable array.
     */
    function hh_datamap(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $mapPath = __DIR__ . '/config/datamap.php';

        if (!file_exists($mapPath)) {
            throw new RuntimeException('config/datamap.php is missing.');
        }

        $loaded = require $mapPath;

        if (!is_array($loaded)) {
            throw new RuntimeException('config/datamap.php must return an array.');
        }

        $map = $loaded;

        return $map;
    }
}

if (!function_exists('hh_request_input')) {
    /**
     * Fetch a GET/POST/JSON input value with optional sanitisation.
     */
    function hh_request_input(string $key, ?callable $filter = null, $default = null)
    {
        $value = $_GET[$key] ?? $_POST[$key] ?? $default;

        if (isset($_SERVER['CONTENT_TYPE']) && str_contains((string) $_SERVER['CONTENT_TYPE'], 'application/json')) {
            static $jsonBody = null;
            if ($jsonBody === null) {
                $raw = file_get_contents('php://input');
                $jsonBody = $raw ? json_decode($raw, true) : [];
            }
            if (is_array($jsonBody) && array_key_exists($key, $jsonBody)) {
                $value = $jsonBody[$key];
            }
        }

        if ($filter !== null) {
            return $filter($value);
        }

        return $value;
    }
}
