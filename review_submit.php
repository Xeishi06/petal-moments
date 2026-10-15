<?php
require_once __DIR__ . '/config/functions.php';
start_session();
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

if (!verify_csrf($_POST['csrf'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'Session expired. Please refresh and try again.']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = mb_substr(trim($_POST['comment'] ?? ''), 0, 1000);

if ($productId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid product.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['ok' => false, 'error' => 'Please tap a star rating from 1 to 5.']);
    exit;
}

$bought = db()->prepare("
    SELECT 1 FROM order_items oi
    JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
    WHERE o.user_id = :u AND oi.product_id = :p LIMIT 1
");
$bought->execute([':u' => $_SESSION['user_id'], ':p' => $productId]);
if (!$bought->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Only customers who bought this flower can review it.']);
    exit;
}

db()->prepare("
    INSERT INTO reviews (product_id, user_id, rating, comment)
    VALUES (:p, :u, :r, :c)
    ON DUPLICATE KEY UPDATE rating = :r, comment = :c, status = 'approved', created_at = CURRENT_TIMESTAMP
")->execute([':p' => $productId, ':u' => $_SESSION['user_id'], ':r' => $rating, ':c' => $comment !== '' ? $comment : null]);

echo json_encode(['ok' => true]);
exit;
