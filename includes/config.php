<?php
// Database configuration and connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'telecrm';

// $host = 'localhost';
// $username = 'u431421769_root1';
// $password = 'TeleCRM@123';
// $database = 'u431421769_telecrm';

$mysqli = @new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

if (!function_exists('hh_db')) {
    /**
     * Provide a shared PDO connection that reuses the TeleCRM credentials.
     */
    function hh_db(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        global $host, $username, $password, $database;

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $database);

        $pdo = new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        return $pdo;
    }
}

if (!function_exists('hh_base_path')) {
    /**
     * Determine the URL path (without host) where the current script is served.
     */
    function hh_base_path(): string
    {
        static $basePath = null;

        if ($basePath !== null) {
            return $basePath;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName === '') {
            $basePath = '';
            return $basePath;
        }

        $directory = str_replace('\\', '/', dirname($scriptName));

        if ($directory === '/' || $directory === '.') {
            $directory = '';
        }

        $basePath = rtrim($directory, '/');

        return $basePath;
    }
}

if (!function_exists('hh_base_href')) {
    /**
     * Return the base href (with trailing slash) used for resolving relative URLs.
     */
    function hh_base_href(): string
    {
        $basePath = hh_base_path();

        return $basePath === '' ? '/' : $basePath . '/';
    }
}

if (!function_exists('hh_asset')) {
    /**
     * Build an absolute path (relative to the current installation) for assets.
     */
    function hh_asset(string $path): string
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
        $basePath = hh_base_path();
        $prefix = $basePath !== '' ? $basePath . '/' : '/';

        return $prefix . $normalizedPath;
    }
}

if (!function_exists('hh_url')) {
    /**
     * Build an absolute URL path for internal links.
     */
    function hh_url(string $path): string
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
        $baseHref = rtrim(hh_base_href(), '/');

        return $baseHref === '' ? '/' . $normalizedPath : $baseHref . '/' . $normalizedPath;
    }
}

if (!function_exists('hh_ensure_view')) {
    /**
     * Create a lightweight view when a legacy alias is required for new features.
     */
    function hh_ensure_view(mysqli $mysqli, string $database, string $viewName, string $selectSql): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $viewName)) {
            die('Invalid view name: ' . $viewName);
        }

        $stmt = $mysqli->prepare(
            'SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
        );

        if ($stmt === false) {
            die('Unable to inspect existing views: ' . $mysqli->error);
        }

        $stmt->bind_param('ss', $database, $viewName);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            die('Unable to inspect existing views: ' . $error);
        }

        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            if ($row) {
                $result->free();
                $stmt->close();
                return; // Table or view already exists.
            }
            $result->free();
        }

        $stmt->close();

        $sql = sprintf('CREATE VIEW `%s` AS %s', $viewName, $selectSql);

        if (!$mysqli->query($sql)) {
            die(sprintf('Unable to create %s view: %s', $viewName, $mysqli->error));
        }
    }
}

// Ensure the required tables exist
$createUsersTable = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL DEFAULT '',
    contact_number VARCHAR(30) DEFAULT NULL,
    role ENUM('admin', 'manager', 'agent') NOT NULL DEFAULT 'agent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createUsersTable)) {
    die('Failed to ensure users table exists: ' . $mysqli->error);
}

$createAllLeadsTable = <<<SQL
CREATE TABLE IF NOT EXISTS `all_leads` (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stage VARCHAR(50) DEFAULT NULL,
    rating VARCHAR(50) DEFAULT NULL,
    assigned_to VARCHAR(255) DEFAULT NULL,
    source VARCHAR(100) DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    alternate_phone VARCHAR(50) DEFAULT NULL,
    nationality VARCHAR(100) DEFAULT NULL,
    interested_in VARCHAR(100) DEFAULT NULL,
    property_type VARCHAR(100) DEFAULT NULL,
    location_preferences VARCHAR(255) DEFAULT NULL,
    budget_range VARCHAR(100) DEFAULT NULL,
    size_required VARCHAR(100) DEFAULT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    urgency VARCHAR(100) DEFAULT NULL,
    alternate_email VARCHAR(255) DEFAULT NULL,
    payout_received TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED DEFAULT NULL,
    created_by_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createAllLeadsTable)) {
    die('Failed to ensure all_leads table exists: ' . $mysqli->error);
}

// Ensure created_by column exists so every lead can be traced back to a registered user
$createdByColumnCheck = $mysqli->query("SHOW COLUMNS FROM all_leads LIKE 'created_by'");
if ($createdByColumnCheck) {
    if ($createdByColumnCheck->num_rows === 0) {
        if (!$mysqli->query("ALTER TABLE all_leads ADD COLUMN created_by INT UNSIGNED DEFAULT NULL AFTER payout_received")) {
            die('Failed to add created_by column: ' . $mysqli->error);
        }
    }
    $createdByColumnCheck->free();
} else {
    die('Failed to inspect all_leads table for created_by column: ' . $mysqli->error);
}

// Ensure supporting tables exist for partner analytics
$createChannelPartnersTable = <<<SQL
CREATE TABLE IF NOT EXISTS channel_partners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_name VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) DEFAULT NULL,
    contact_email VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX channel_partners_status_idx (status),
    INDEX channel_partners_created_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createChannelPartnersTable)) {
    die('Failed to ensure channel_partners table exists: ' . $mysqli->error);
}

// Provide convenient views required by the dashboard services
hh_ensure_view($mysqli, $database, 'leads', 'SELECT * FROM all_leads');
hh_ensure_view($mysqli, $database, 'lead_sources', 'SELECT id, source, assigned_to, created_by, created_by_name, created_at FROM all_leads');
hh_ensure_view($mysqli, $database, 'activity_log', 'SELECT * FROM lead_activity_log');
hh_ensure_view($mysqli, $database, 'properties', 'SELECT * FROM properties_list');

// Ensure created_by_name column exists for legacy installations
$createdByNameColumnCheck = $mysqli->query("SHOW COLUMNS FROM all_leads LIKE 'created_by_name'");
if ($createdByNameColumnCheck) {
    if ($createdByNameColumnCheck->num_rows === 0) {
        if (!$mysqli->query("ALTER TABLE all_leads ADD COLUMN created_by_name VARCHAR(255) DEFAULT NULL AFTER created_by")) {
            die('Failed to add created_by_name column: ' . $mysqli->error);
        }
    }
    $createdByNameColumnCheck->free();
} else {
    die('Failed to inspect all_leads table for created_by_name column: ' . $mysqli->error);
}

// Ensure password_hash column exists for legacy installations
$passwordColumnCheck = $mysqli->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
if ($passwordColumnCheck) {
    if ($passwordColumnCheck->num_rows === 0) {
        if (!$mysqli->query("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER email")) {
            die('Failed to add password column: ' . $mysqli->error);
        }
    }
    $passwordColumnCheck->free();
} else {
    die('Failed to inspect users table: ' . $mysqli->error);
}

// Ensure contact_number column exists for legacy installations
$contactColumnCheck = $mysqli->query("SHOW COLUMNS FROM users LIKE 'contact_number'");
if ($contactColumnCheck) {
    if ($contactColumnCheck->num_rows === 0) {
        if (!$mysqli->query("ALTER TABLE users ADD COLUMN contact_number VARCHAR(30) DEFAULT NULL AFTER password_hash")) {
            die('Failed to add contact number column: ' . $mysqli->error);
        }
    }
    $contactColumnCheck->free();
} else {
    die('Failed to inspect users table for contact column: ' . $mysqli->error);
}

// Seed a default admin user if no users exist yet
$existingUsers = $mysqli->query('SELECT COUNT(*) AS user_count FROM users');
if ($existingUsers) {
    $row = $existingUsers->fetch_assoc();
    if ((int) ($row['user_count'] ?? 0) === 0) {
        $defaultPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('INSERT INTO users (full_name, email, password_hash, contact_number, role) VALUES (?, ?, ?, ?, ?)');
        if ($stmt) {
            $defaultName = 'Administrator';
            $defaultEmail = 'admin@example.com';
            $defaultRole = 'admin';
            $defaultContact = '+1 555 0100';
            $stmt->bind_param('sssss', $defaultName, $defaultEmail, $defaultPasswordHash, $defaultContact, $defaultRole);
            $stmt->execute();
            $stmt->close();
        }
    }
    $existingUsers->free();
}

// --- Realtime chat tables -------------------------------------------------

$createChatConversationsTable = <<<SQL
CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    is_group TINYINT(1) NOT NULL DEFAULT 0,
    direct_key CHAR(64) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chat_conversations_direct_key UNIQUE KEY (direct_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createChatConversationsTable)) {
    die('Failed to ensure chat_conversations table exists: ' . $mysqli->error);
}

$createChatParticipantsTable = <<<SQL
CREATE TABLE IF NOT EXISTS chat_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_message_id INT UNSIGNED DEFAULT NULL,
    typing_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY chat_participants_unique (conversation_id, user_id),
    CONSTRAINT chat_participants_conversation_fk FOREIGN KEY (conversation_id) REFERENCES chat_conversations (id) ON DELETE CASCADE,
    CONSTRAINT chat_participants_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createChatParticipantsTable)) {
    die('Failed to ensure chat_participants table exists: ' . $mysqli->error);
}

$createChatMessagesTable = <<<SQL
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chat_messages_conversation_fk FOREIGN KEY (conversation_id) REFERENCES chat_conversations (id) ON DELETE CASCADE,
    CONSTRAINT chat_messages_sender_fk FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createChatMessagesTable)) {
    die('Failed to ensure chat_messages table exists: ' . $mysqli->error);
}

$createChatMessageReadsTable = <<<SQL
CREATE TABLE IF NOT EXISTS chat_message_reads (
    message_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id, user_id),
    CONSTRAINT chat_reads_message_fk FOREIGN KEY (message_id) REFERENCES chat_messages (id) ON DELETE CASCADE,
    CONSTRAINT chat_reads_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createChatMessageReadsTable)) {
    die('Failed to ensure chat_message_reads table exists: ' . $mysqli->error);
}

$createUserPresenceTable = <<<SQL
CREATE TABLE IF NOT EXISTS user_presence (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT user_presence_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createUserPresenceTable)) {
    die('Failed to ensure user_presence table exists: ' . $mysqli->error);
}

$createChatTokensTable = <<<SQL
CREATE TABLE IF NOT EXISTS chat_ws_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX chat_ws_tokens_user_idx (user_id),
    INDEX chat_ws_tokens_expiry_idx (expires_at),
    CONSTRAINT chat_ws_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createChatTokensTable)) {
    die('Failed to ensure chat_ws_tokens table exists: ' . $mysqli->error);
}

$createChatEventsTable = <<<SQL
CREATE TABLE IF NOT EXISTS chat_ws_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    conversation_id INT UNSIGNED DEFAULT NULL,
    recipients TEXT NOT NULL,
    payload MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX chat_ws_events_type_idx (event_type),
    INDEX chat_ws_events_created_idx (created_at),
    INDEX chat_ws_events_conversation_idx (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createChatEventsTable)) {
    die('Failed to ensure chat_ws_events table exists: ' . $mysqli->error);
}
