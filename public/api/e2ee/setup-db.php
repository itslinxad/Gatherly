<?php
/**
 * Database setup script for E2EE tables
 * Run this once to create the required tables
 */

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

try {
    $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Create user_keys table
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_keys (
            key_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            public_key TEXT NOT NULL,
            encrypted_private_key TEXT NOT NULL,
            key_salt VARCHAR(64) NOT NULL,
            encrypted_recovery_key TEXT NOT NULL,
            key_version INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_key_version (key_version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create user_pins table
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_pins (
            pin_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            pin_hash VARCHAR(255) NOT NULL,
            pin_salt VARCHAR(64) NOT NULL,
            failed_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Add E2EE columns to chat table if they don't exist
    $result = $conn->query("SHOW COLUMNS FROM chat LIKE 'encryption_type'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE chat ADD COLUMN encryption_type VARCHAR(20) DEFAULT 'legacy'");
        $conn->query("ALTER TABLE chat ADD COLUMN encrypted_session_key TEXT");
        $conn->query("ALTER TABLE chat ADD COLUMN iv VARCHAR(32)");
        $conn->query("ALTER TABLE chat ADD COLUMN auth_tag VARCHAR(32)");
        $conn->query("ALTER TABLE chat ADD COLUMN key_version INT DEFAULT 1");
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'E2EE tables created/verified successfully'
    ]);
    
} catch (Exception $e) {
    error_log("E2EE DB Setup Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}