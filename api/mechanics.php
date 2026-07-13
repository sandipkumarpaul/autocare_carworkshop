<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = isset($_GET['date']) ? trim($_GET['date']) : '';
    if (empty($date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Date parameter is required.'
        ]);
        exit();
    }
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid date format. Use YYYY-MM-DD.'
        ]);
        exit();
    }
    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.name,
                m.specialization,
                COUNT(a.id) AS booked_count,
                (:max_appt - COUNT(a.id)) AS available_slots
            FROM mechanics m
            LEFT JOIN appointments a ON m.id = a.mechanic_id AND a.appointment_date = :date
            GROUP BY m.id, m.name, m.specialization
            ORDER BY m.name ASC
        ");
        $stmt->execute([
            ':date' => $date,
            ':max_appt' => MAX_APPOINTMENTS_PER_DAY
        ]);
        $mechanics = $stmt->fetchAll();
        echo json_encode([
            'success' => true,
            'data' => $mechanics,
            'max_per_day' => MAX_APPOINTMENTS_PER_DAY
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch mechanics: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);
}
