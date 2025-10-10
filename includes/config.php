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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($createAllLeadsTable)) {
    die('Failed to ensure All leads table exists: ' . $mysqli->error);
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
