<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

$stmt = db()->prepare('SELECT user_id, receipt FROM orders WHERE id = :id');
$stmt->execute([':id' => (int)($_GET['order'] ?? 0)]);
$order = $stmt->fetch();
if (!$order || ((int)$order['user_id'] !== (int)$_SESSION['user_id'] && !is_admin())) {
    http_response_code(404);
    exit('Receipt not found.');
}
$root = realpath(__DIR__ . '/uploads/receipts');
$file = !empty($order['receipt']) ? realpath(__DIR__ . '/' . $order['receipt']) : false;
if (!$root || !$file || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    http_response_code(404);
    exit('Receipt not found.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit('Unsupported receipt format.');
}
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($file));
readfile($file);
