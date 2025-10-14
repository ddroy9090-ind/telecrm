<?php

declare(strict_types=1);

// Prefer Composer's autoloader when dependencies have been installed. When the
// vendor directory is absent (for example in limited environments where
// `composer install` cannot be executed), fall back to a lightweight PSR-4
// autoloader so the dashboard continues to operate.
$composerAutoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'HouzzHunt\\';
        $baseDir = __DIR__ . '/src/';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', '/', $relativeClass) . '.php';
        $file = $baseDir . $relativePath;

        if (file_exists($file)) {
            require_once $file;
        }
    });
}
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
