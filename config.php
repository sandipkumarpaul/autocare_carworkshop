<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'car_workshop');
define('DB_USER', 'root');
define('DB_PASS', '');
define('MAX_APPOINTMENTS_PER_DAY', 4);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        checkDatabaseMigrations($pdo);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit();
    }
}
function checkDatabaseMigrations($pdo) {
    try {
        $rs = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'user_id'");
        if (!$rs->fetch()) {
            $pdo->exec("ALTER TABLE appointments ADD COLUMN user_id INT NULL, ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
        }
        $rs = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'is_updated_by_admin'");
        if (!$rs->fetch()) {
            $pdo->exec("ALTER TABLE appointments ADD COLUMN is_updated_by_admin TINYINT(1) DEFAULT 0");
        }
    } catch (Exception $e) {
    }
}
