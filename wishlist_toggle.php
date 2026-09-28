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
if ($productId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid product.']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id FROM products WHERE id = :id AND is_active = 1");
$stmt->execute([':id' => $productId]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Product not found.']);
    exit;
}

$exists = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = :u AND product_id = :p");
$exists->execute([':u' => $_SESSION['user_id'], ':p' => $productId]);

if ($row = $exists->fetch()) {
    $pdo->prepare("DELETE FROM wishlist WHERE id = :id")->execute([':id' => $row['id']]);
    echo json_encode(['ok' => true, 'saved' => false]);
} else {
    try {
        $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (:u, :p)")
            ->execute([':u' => $_SESSION['user_id'], ':p' => $productId]);
    } catch (Exception $e) {
        // Already saved (race) — treat as saved
    }
    echo json_encode(['ok' => true, 'saved' => true]);
}
exit;
