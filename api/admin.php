<?php
// Admin endpoint.
//   GET — all appointments
//   PUT — { appointment_id, appointment_date?, mechanic_id? } reschedule / reassign
require_once __DIR__ . '/bootstrap.php';

requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = db()->query('
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
    ');
    $appointments = $stmt->fetchAll();
    $mechanicCount = (int)db()->query('SELECT COUNT(*) FROM mechanics')->fetchColumn();

    jsonResponse([
        'success'        => true,
        'data'           => $appointments,
        'total'          => count($appointments),
        'mechanic_count' => $mechanicCount,
    ]);
}

if ($method !== 'PUT') {
    jsonError('Method not allowed. Use GET or PUT.', 405);
}

$input         = readJsonInput();
$appointmentId = isset($input['appointment_id']) ? (int)$input['appointment_id'] : 0;
if ($appointmentId <= 0) {
    jsonError('Valid appointment ID is required.');
}

$stmt = db()->prepare('SELECT * FROM appointments WHERE id = :id');
$stmt->execute([':id' => $appointmentId]);
$appointment = $stmt->fetch();
if (!$appointment) {
    jsonError('Appointment not found.', 404);
}

$date       = isset($input['appointment_date']) ? inputString($input, 'appointment_date') : $appointment['appointment_date'];
$mechanicId = isset($input['mechanic_id']) ? (int)$input['mechanic_id'] : (int)$appointment['mechanic_id'];

$dateChanged     = $date !== $appointment['appointment_date'];
$mechanicChanged = $mechanicId !== (int)$appointment['mechanic_id'];
if (!$dateChanged && !$mechanicChanged) {
    jsonResponse(['success' => true, 'message' => 'No changes to save.']);
}

if ($dateChanged) {
    $dateObj = parseDate($date);
    if (!$dateObj) {
        jsonError('Invalid date format. Use YYYY-MM-DD.');
    }
    if (isPastDate($dateObj)) {
        jsonError('An appointment cannot be moved to a past date.');
    }

    $stmt = db()->prepare('
        SELECT id FROM appointments
        WHERE user_id = :user_id AND appointment_date = :date AND id != :id
    ');
    $stmt->execute([
        ':user_id' => $appointment['user_id'],
        ':date'    => $date,
        ':id'      => $appointmentId,
    ]);
    if ($stmt->fetch()) {
        jsonError("This client already has another appointment on {$date}.", 409);
    }
}

$pdo = db();
$pdo->beginTransaction();

$mechanic = mechanicName($mechanicId, true);
if ($mechanic === null) {
    $pdo->rollBack();
    jsonError('Selected mechanic does not exist.', 404);
}
if (bookedCount($mechanicId, $date, $appointmentId) >= MAX_APPOINTMENTS_PER_DAY) {
    $pdo->rollBack();
    jsonError("Mechanic {$mechanic} is fully booked on {$date} (maximum " . MAX_APPOINTMENTS_PER_DAY . ' appointments).', 409);
}

try {
    // is_updated_by_admin shows a "rescheduled" notice on the client's dashboard.
    $stmt = $pdo->prepare('
        UPDATE appointments
        SET appointment_date = :date, mechanic_id = :mechanic_id, is_updated_by_admin = 1
        WHERE id = :id
    ');
    $stmt->execute([
        ':date'        => $date,
        ':mechanic_id' => $mechanicId,
        ':id'          => $appointmentId,
    ]);
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    if (isDuplicateKeyError($e)) {
        jsonError('This client already has an appointment on the selected date.', 409);
    }
    throw $e;
}

jsonResponse([
    'success' => true,
    'message' => "Appointment updated successfully. New date: {$date}, Mechanic: {$mechanic}.",
]);
