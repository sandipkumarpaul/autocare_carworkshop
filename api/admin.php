<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
if ($userId <= 0 || $userRole !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden. Admin access required.'
    ]);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.client_name,
                a.address,
                a.phone,
                a.car_license,
                a.car_engine,
                a.appointment_date,
                a.mechanic_id,
                m.name AS mechanic_name,
                m.specialization AS mechanic_specialization,
                a.created_at
            FROM appointments a
            JOIN mechanics m ON a.mechanic_id = m.id
            ORDER BY a.appointment_date DESC, a.created_at DESC
        ");
        $stmt->execute();
        $appointments = $stmt->fetchAll();
        echo json_encode([
            'success' => true,
            'data' => $appointments,
            'total' => count($appointments)
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch appointments: ' . $e->getMessage()
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input.'
        ]);
        exit();
    }
    $appointmentId  = isset($input['appointment_id']) ? (int)$input['appointment_id'] : 0;
    $newDate        = isset($input['appointment_date']) ? trim($input['appointment_date']) : null;
    $newMechanicId  = isset($input['mechanic_id']) ? (int)$input['mechanic_id'] : null;
    if ($appointmentId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Valid appointment ID is required.'
        ]);
        exit();
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = :id");
        $stmt->execute([':id' => $appointmentId]);
        $appointment = $stmt->fetch();
        if (!$appointment) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Appointment not found.'
            ]);
            exit();
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit();
    }
    $finalDate       = $newDate !== null ? $newDate : $appointment['appointment_date'];
    $finalMechanicId = $newMechanicId !== null ? $newMechanicId : (int)$appointment['mechanic_id'];
    if ($newDate !== null) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $newDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $newDate) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid date format. Use YYYY-MM-DD.'
            ]);
            exit();
        }
    }
    if ($newMechanicId !== null) {
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM mechanics WHERE id = :id");
            $stmt->execute([':id' => $finalMechanicId]);
            $mechanic = $stmt->fetch();
            if (!$mechanic) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Selected mechanic does not exist.'
                ]);
                exit();
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
            exit();
        }
    }
    if ($newDate !== null && $newDate !== $appointment['appointment_date']) {
        try {
            $stmt = $pdo->prepare("
                SELECT id FROM appointments
                WHERE user_id = :user_id AND appointment_date = :date AND id != :id
            ");
            $stmt->execute([
                ':user_id' => $appointment['user_id'],
                ':date'    => $finalDate,
                ':id'      => $appointmentId
            ]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'This client already has another appointment on ' . $finalDate . '.'
                ]);
                exit();
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
            exit();
        }
    }
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS booked_count
            FROM appointments
            WHERE mechanic_id = :mechanic_id 
              AND appointment_date = :date
              AND id != :id
        ");
        $stmt->execute([
            ':mechanic_id' => $finalMechanicId,
            ':date'        => $finalDate,
            ':id'          => $appointmentId
        ]);
        $result = $stmt->fetch();
        if ((int)$result['booked_count'] >= MAX_APPOINTMENTS_PER_DAY) {
            $stmtM = $pdo->prepare("SELECT name FROM mechanics WHERE id = :id");
            $stmtM->execute([':id' => $finalMechanicId]);
            $mName = $stmtM->fetchColumn();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Mechanic {$mName} is fully booked on {$finalDate} (maximum " . MAX_APPOINTMENTS_PER_DAY . " appointments)."
            ]);
            exit();
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit();
    }
    try {
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET appointment_date = :date, mechanic_id = :mechanic_id, is_updated_by_admin = 1
            WHERE id = :id
        ");
        $stmt->execute([
            ':date'        => $finalDate,
            ':mechanic_id' => $finalMechanicId,
            ':id'          => $appointmentId
        ]);
        $stmtM = $pdo->prepare("SELECT name FROM mechanics WHERE id = :id");
        $stmtM->execute([':id' => $finalMechanicId]);
        $mechanicName = $stmtM->fetchColumn();
        echo json_encode([
            'success' => true,
            'message' => "Appointment updated successfully. New date: {$finalDate}, Mechanic: {$mechanicName}."
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'This client already has an appointment on the selected date.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update appointment: ' . $e->getMessage()
            ]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET or PUT.'
    ]);
}
