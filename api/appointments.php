<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login first.'
    ]);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.client_name,
                a.phone,
                a.car_license,
                a.car_engine,
                a.appointment_date,
                a.is_updated_by_admin,
                m.name AS mechanic_name,
                m.specialization AS mechanic_specialization
            FROM appointments a
            JOIN mechanics m ON a.mechanic_id = m.id
            WHERE a.user_id = :user_id
            ORDER BY a.appointment_date DESC, a.created_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        $appointments = $stmt->fetchAll();
        echo json_encode([
            'success' => true,
            'data' => $appointments
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch appointments: ' . $e->getMessage()
        ]);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input.'
        ]);
        exit();
    }
    if (isset($input['action']) && $input['action'] === 'dismiss_notification') {
        $apptId = isset($input['appointment_id']) ? (int)$input['appointment_id'] : 0;
        if ($apptId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Valid appointment ID is required.'
            ]);
            exit();
        }
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET is_updated_by_admin = 0 WHERE id = :id AND user_id = :user_id");
            $stmt->execute([
                ':id' => $apptId,
                ':user_id' => $userId
            ]);
            echo json_encode([
                'success' => true,
                'message' => 'Notification dismissed.'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to dismiss notification: ' . $e->getMessage()
            ]);
        }
        exit();
    }
    $clientName     = isset($input['client_name']) ? trim($input['client_name']) : '';
    $address        = isset($input['address']) ? trim($input['address']) : '';
    $phone          = isset($input['phone']) ? trim($input['phone']) : '';
    $carLicense     = isset($input['car_license']) ? trim($input['car_license']) : '';
    $carEngine      = isset($input['car_engine']) ? trim($input['car_engine']) : '';
    $appointmentDate = isset($input['appointment_date']) ? trim($input['appointment_date']) : '';
    $mechanicId     = isset($input['mechanic_id']) ? (int)$input['mechanic_id'] : 0;
    $errors = [];
    if (empty($clientName)) {
        $errors[] = 'Client name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s.\'-]+$/', $clientName)) {
        $errors[] = 'Client name should contain only letters, spaces, dots, apostrophes, and hyphens.';
    }
    if (empty($address)) {
        $errors[] = 'Address is required.';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9]{7,15}$/', $phone)) {
        $errors[] = 'Phone number must contain only digits (7-15 digits).';
    }
    if (empty($carLicense)) {
        $errors[] = 'Car license number is required.';
    }
    if (empty($carEngine)) {
        $errors[] = 'Car engine number is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9\-]+$/', $carEngine)) {
        $errors[] = 'Car engine number must be alphanumeric (letters, digits, hyphens only).';
    }
    if (empty($appointmentDate)) {
        $errors[] = 'Appointment date is required.';
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $appointmentDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $appointmentDate) {
            $errors[] = 'Invalid date format. Use YYYY-MM-DD.';
        } else {
            $today = new DateTime('today');
            if ($dateObj < $today) {
                $errors[] = 'Appointment date cannot be in the past.';
            }
        }
    }
    if ($mechanicId <= 0) {
        $errors[] = 'Please select a mechanic.';
    }
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => implode(' ', $errors),
            'errors' => $errors
        ]);
        exit();
    }
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM mechanics WHERE id = :id");
        $stmt->execute([':id' => $mechanicId]);
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
    try {
        $stmt = $pdo->prepare("
            SELECT a.id, m.name AS mechanic_name
            FROM appointments a
            JOIN mechanics m ON a.mechanic_id = m.id
            WHERE a.user_id = :user_id AND a.appointment_date = :date
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':date'    => $appointmentDate
        ]);
        $existing = $stmt->fetch();
        if ($existing) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "You already have an appointment on {$appointmentDate} with mechanic {$existing['mechanic_name']}. Only one appointment per day is allowed."
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
            SELECT COUNT(*) AS booked_count
            FROM appointments
            WHERE mechanic_id = :mechanic_id AND appointment_date = :date
        ");
        $stmt->execute([
            ':mechanic_id' => $mechanicId,
            ':date'        => $appointmentDate
        ]);
        $result = $stmt->fetch();
        if ((int)$result['booked_count'] >= MAX_APPOINTMENTS_PER_DAY) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Mechanic {$mechanic['name']} is fully booked on {$appointmentDate} (maximum " . MAX_APPOINTMENTS_PER_DAY . " appointments). Please select another mechanic or date."
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
            INSERT INTO appointments (user_id, client_name, address, phone, car_license, car_engine, appointment_date, mechanic_id)
            VALUES (:user_id, :client_name, :address, :phone, :car_license, :car_engine, :appointment_date, :mechanic_id)
        ");
        $stmt->execute([
            ':user_id'          => $userId,
            ':client_name'      => $clientName,
            ':address'          => $address,
            ':phone'            => $phone,
            ':car_license'      => $carLicense,
            ':car_engine'       => $carEngine,
            ':appointment_date' => $appointmentDate,
            ':mechanic_id'      => $mechanicId
        ]);
        $appointmentId = $pdo->lastInsertId();
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => "Appointment booked successfully with {$mechanic['name']} on {$appointmentDate}.",
            'data' => [
                'appointment_id' => $appointmentId,
                'mechanic_name'  => $mechanic['name']
            ]
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'You already have an appointment on this date.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create appointment: ' . $e->getMessage()
            ]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET or POST.'
    ]);
}
