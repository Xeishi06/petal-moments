<?php
require_once __DIR__ . '/config/functions.php';
start_session();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$name       = trim($_POST['name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$eventType  = trim($_POST['event_type'] ?? '');
$eventDate  = $_POST['event_date'] ?? '';
$guestCount = (int)($_POST['guest_count'] ?? 0);
$budget     = (float)($_POST['budget'] ?? 0);
$message    = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $eventType === '') {
    echo json_encode(['ok' => false, 'error' => 'Name, email and event type are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

$stmt = db()->prepare("
    INSERT INTO event_inquiries (user_id, name, email, phone, event_type, event_date, guest_count, budget, message, status)
    VALUES (:uid, :name, :email, :phone, :et, :ed, :gc, :bd, :msg, 'pending')
");
$stmt->execute([
    ':uid'   => is_logged_in() ? $_SESSION['user_id'] : null,
    ':name'  => $name,
    ':email' => $email,
    ':phone' => $phone !== '' ? $phone : null,
    ':et'    => $eventType,
    ':ed'    => $eventDate !== '' ? $eventDate : null,
    ':gc'    => $guestCount > 0 ? $guestCount : null,
    ':bd'    => $budget > 0 ? $budget : null,
    ':msg'   => $message,
]);

echo json_encode(['ok' => true]);
exit;
