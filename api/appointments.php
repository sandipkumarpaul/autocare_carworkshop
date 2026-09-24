<?php
// Client endpoint.
//   GET  — the logged-in user's appointments
//   POST — book a new appointment, or { action: dismiss_notification, appointment_id }
require_once __DIR__ . '/bootstrap.php';

$userId = requireLogin();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = db()->prepare('
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
    ');
    $stmt->execute([':user_id' => $userId]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method !== 'POST') {
    jsonError('Method not allowed. Use GET or POST.', 405);
}

$input = readJsonInput();

if (inputString($input, 'action') === 'dismiss_notification') {
    $appointmentId = isset($input['appointment_id']) ? (int)$input['appointment_id'] : 0;
    if ($appointmentId <= 0) {
        jsonError('Valid appointment ID is required.');
    }
    $stmt = db()->prepare('UPDATE appointments SET is_updated_by_admin = 0 WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => $appointmentId, ':user_id' => $userId]);
    jsonResponse(['success' => true, 'message' => 'Notification dismissed.']);
}

// ---- Book a new appointment ----
$clientName = inputString($input, 'client_name');
$address    = inputString($input, 'address');
$phone      = inputString($input, 'phone');
$carLicense = inputString($input, 'car_license');
$carEngine  = inputString($input, 'car_engine');
$date       = inputString($input, 'appointment_date');
$mechanicId = isset($input['mechanic_id']) ? (int)$input['mechanic_id'] : 0;

$errors = [];
if ($clientName === '') {
    $errors[] = 'Client name is required.';
} elseif (!preg_match("/^[a-zA-Z\s.'\-]+$/", $clientName)) {
    $errors[] = 'Client name should contain only letters, spaces, dots, apostrophes, and hyphens.';
}
if ($address === '') {
    $errors[] = 'Address is required.';
}
if ($phone === '') {
    $errors[] = 'Phone number is required.';
} elseif (!preg_match('/^[0-9]{7,15}$/', $phone)) {
    $errors[] = 'Phone number must contain only digits (7-15 digits).';
}
if ($carLicense === '') {
    $errors[] = 'Car license number is required.';
}
if ($carEngine === '') {
    $errors[] = 'Car engine number is required.';
} elseif (!preg_match('/^[a-zA-Z0-9\-]+$/', $carEngine)) {
    $errors[] = 'Car engine number must be alphanumeric (letters, digits, hyphens only).';
}
if ($date === '') {
    $errors[] = 'Appointment date is required.';
} elseif (!($dateObj = parseDate($date))) {
    $errors[] = 'Invalid date format. Use YYYY-MM-DD.';
} elseif (isPastDate($dateObj)) {
    $errors[] = 'Appointment date cannot be in the past.';
}
if ($mechanicId <= 0) {
    $errors[] = 'Please select a mechanic.';
}
if ($errors) {
    jsonError(implode(' ', $errors), 400, $errors);
}

// One appointment per client per day (also enforced by a unique key).
$stmt = db()->prepare('
    SELECT m.name
    FROM appointments a
    JOIN mechanics m ON a.mechanic_id = m.id
    WHERE a.user_id = :user_id AND a.appointment_date = :date
');
$stmt->execute([':user_id' => $userId, ':date' => $date]);
$existingMechanic = $stmt->fetchColumn();
if ($existingMechanic !== false) {
    jsonError("You already have an appointment on {$date} with mechanic {$existingMechanic}. Only one appointment per day is allowed.", 409);
}

$pdo = db();
$pdo->beginTransaction();

// Locking the mechanic row serialises concurrent bookings, so two clients
// can't both grab the last free slot.
$mechanic = mechanicName($mechanicId, true);
if ($mechanic === null) {
    $pdo->rollBack();
    jsonError('Selected mechanic does not exist.', 404);
}
if (bookedCount($mechanicId, $date) >= MAX_APPOINTMENTS_PER_DAY) {
    $pdo->rollBack();
    jsonError("Mechanic {$mechanic} is fully booked on {$date} (maximum " . MAX_APPOINTMENTS_PER_DAY . ' appointments). Please select another mechanic or date.', 409);
}

try {
    $stmt = $pdo->prepare('
        INSERT INTO appointments (user_id, client_name, address, phone, car_license, car_engine, appointment_date, mechanic_id)
        VALUES (:user_id, :client_name, :address, :phone, :car_license, :car_engine, :appointment_date, :mechanic_id)
    ');
    $stmt->execute([
        ':user_id'          => $userId,
        ':client_name'      => $clientName,
        ':address'          => $address,
        ':phone'            => $phone,
        ':car_license'      => $carLicense,
        ':car_engine'       => $carEngine,
        ':appointment_date' => $date,
        ':mechanic_id'      => $mechanicId,
    ]);
    $appointmentId = (int)$pdo->lastInsertId();
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    if (isDuplicateKeyError($e)) {
        jsonError('You already have an appointment on this date.', 409);
    }
    throw $e;
}

jsonResponse([
    'success' => true,
    'message' => "Appointment booked successfully with {$mechanic} on {$date}.",
    'data'    => [
        'appointment_id' => $appointmentId,
        'mechanic_name'  => $mechanic,
    ],
], 201);
