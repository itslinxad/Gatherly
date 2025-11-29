<?php
// Use absolute path to ensure it works from anywhere
$config_path = __DIR__ . '/../../config/database.php';
if (!file_exists($config_path)) {
    error_log("Config file not found at: " . $config_path);
    throw new Exception("Database configuration file not found");
}
require_once $config_path;

// Create MySQLi connection for compatibility with existing code
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection - throw exception instead of die() for better error handling
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    throw new Exception("Database connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for full Unicode support
$conn->set_charset("utf8mb4");

// Optional: Keep the PDO class for backward compatibility if other files use it
class Database1
{
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $conn;
    private $error;

    public function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->dbname,
                $this->user,
                $this->pass,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );

            // Set MySQL timezone to Philippine time
            $this->conn->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            echo 'Connection Error: ' . $this->error;
        }
    }

    public function getConnection()
    {
        return $this->conn;
    }
}

// Create a global PDO instance for AI services
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
} catch (PDOException $e) {
    error_log('PDO Connection Error: ' . $e->getMessage());
    throw $e;
}
