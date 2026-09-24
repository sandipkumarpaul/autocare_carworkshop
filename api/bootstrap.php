<?php
// Shared setup for every API endpoint: config, session, JSON helpers, DB access.
require_once __DIR__ . '/../config.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Anything unexpected is logged server-side; the client only gets a generic message.
set_exception_handler(function (Throwable $e) {
    error_log('[AutoCare] ' . $e);
    jsonError('Something went wrong on the server. Please try again later.', 500);
});

function jsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

function jsonError(string $message, int $status = 400, array $errors = []): void {
    $payload = ['success' => false, 'message' => $message];
    if ($errors) {
        $payload['errors'] = $errors;
    }
    jsonResponse($payload, $status);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('[AutoCare] DB connection failed: ' . $e->getMessage());
            jsonError('Database connection failed. Please try again later.', 500);
        }
    }
    return $pdo;
}

function readJsonInput(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonError('Invalid JSON input.');
    }
    return $input;
}

function inputString(array $input, string $key): string {
    return isset($input[$key]) && is_scalar($input[$key]) ? trim((string)$input[$key]) : '';
}

// Returns a DateTime for a strict YYYY-MM-DD string, or null if it is malformed.
function parseDate(string $value): ?DateTime {
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $date : null;
}

function isPastDate(DateTime $date): bool {
    return $date < new DateTime('today');
}

function isDuplicateKeyError(PDOException $e): bool {
    return $e->getCode() == 23000;
}

function currentUserId(): int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function requireLogin(): int {
    $userId = currentUserId();
    if ($userId <= 0) {
        jsonError('Unauthorized. Please login first.', 401);
    }
    return $userId;
}

function requireAdmin(): int {
    $userId = currentUserId();
    if ($userId <= 0 || ($_SESSION['user_role'] ?? '') !== 'admin') {
        jsonError('Forbidden. Admin access required.', 403);
    }
    return $userId;
}

function startUserSession(array $user): void {
    // New session ID on login prevents session fixation.
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
}

function sessionUser(): array {
    return [
        'id'    => (int)$_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

function mechanicName(int $mechanicId, bool $lockForUpdate = false): ?string {
    $sql = 'SELECT name FROM mechanics WHERE id = :id' . ($lockForUpdate ? ' FOR UPDATE' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $mechanicId]);
    $name = $stmt->fetchColumn();
    return $name === false ? null : $name;
}

// Number of appointments a mechanic already has on a date, optionally ignoring one appointment.
function bookedCount(int $mechanicId, string $date, int $excludeAppointmentId = 0): int {
    $stmt = db()->prepare('
        SELECT COUNT(*) FROM appointments
        WHERE mechanic_id = :mechanic_id AND appointment_date = :date AND id != :exclude_id
    ');
    $stmt->execute([
        ':mechanic_id' => $mechanicId,
        ':date'        => $date,
        ':exclude_id'  => $excludeAppointmentId,
    ]);
    return (int)$stmt->fetchColumn();
}
