<?php
// GET ?date=YYYY-MM-DD — every mechanic with their free slots on that date.
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed. Use GET.', 405);
}

$date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
if ($date === '') {
    jsonError('Date parameter is required.');
}
if (!parseDate($date)) {
    jsonError('Invalid date format. Use YYYY-MM-DD.');
}

$stmt = db()->prepare('
    SELECT m.id, m.name, m.specialization, COUNT(a.id) AS booked_count
    FROM mechanics m
    LEFT JOIN appointments a ON a.mechanic_id = m.id AND a.appointment_date = :date
    GROUP BY m.id, m.name, m.specialization
    ORDER BY m.name ASC
');
$stmt->execute([':date' => $date]);

$mechanics = array_map(function (array $m) {
    $booked = (int)$m['booked_count'];
    return [
        'id'              => (int)$m['id'],
        'name'            => $m['name'],
        'specialization'  => $m['specialization'],
        'booked_count'    => $booked,
        'available_slots' => max(0, MAX_APPOINTMENTS_PER_DAY - $booked),
    ];
}, $stmt->fetchAll());

jsonResponse([
    'success'     => true,
    'data'        => $mechanics,
    'max_per_day' => MAX_APPOINTMENTS_PER_DAY,
]);
