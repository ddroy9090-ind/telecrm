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

// Ensure the required tables exist
$createUsersTable = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL DEFAULT '',
    role ENUM('admin', 'manager', 'agent') NOT NULL DEFAULT 'agent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createUsersTable)) {
    die('Failed to ensure users table exists: ' . $mysqli->error);
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

// Seed a default admin user if no users exist yet
$existingUsers = $mysqli->query('SELECT COUNT(*) AS user_count FROM users');
if ($existingUsers) {
    $row = $existingUsers->fetch_assoc();
    if ((int) ($row['user_count'] ?? 0) === 0) {
        $defaultPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        if ($stmt) {
            $defaultName = 'Administrator';
            $defaultEmail = 'admin@example.com';
            $defaultRole = 'admin';
            $stmt->bind_param('ssss', $defaultName, $defaultEmail, $defaultPasswordHash, $defaultRole);
            $stmt->execute();
            $stmt->close();
        }
    }
    $existingUsers->free();
}
