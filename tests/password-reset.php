<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/password_reset.php';
function check_reset($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
$pdo = db();
$email = 'reset-test-' . bin2hex(random_bytes(6)) . '@example.invalid';
$oldPassword = password_hash('OriginalPassword123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password, role) VALUES (:first, :last, :email, :password, :role)');
$stmt->execute([':first' => 'Temporary', ':last' => 'Reset test', ':email' => $email, ':password' => $oldPassword, ':role' => 'customer']);
$id = (int)$pdo->lastInsertId();
$user = ['id' => $id, 'password' => $oldPassword];
try {
    $token = create_password_reset($pdo, $user);
    $otherToken = create_password_reset($pdo, $user);
    check_reset((bool)find_password_reset($pdo, $token), 'Fresh reset token is accepted');
    $stored = $pdo->prepare('SELECT token_hash FROM password_resets WHERE token_hash = ?');
    $stored->execute([hash('sha256', $token)]);
    check_reset($stored->fetchColumn() !== $token, 'Raw reset token is not stored');
    check_reset(!find_password_reset($pdo, 'invalid'), 'Malformed token is rejected');
    check_reset(!find_password_reset($pdo, bin2hex(random_bytes(32))), 'Unknown token is rejected');
    check_reset(!complete_password_reset($pdo, $token, 'weak'), 'Weak password is rejected');
    check_reset((bool)find_password_reset($pdo, $token), 'Validation failure does not consume token');
    check_reset(complete_password_reset($pdo, $token, 'ReplacementPassword456'), 'Valid reset succeeds');
    $current = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $current->execute([$id]);
    $newHash = $current->fetchColumn();
    check_reset(password_verify('ReplacementPassword456', $newHash) && !password_verify('OriginalPassword123', $newHash), 'Only the new password authenticates');
    check_reset(!complete_password_reset($pdo, $token, 'ReplayPassword789'), 'Used token cannot be replayed');
    check_reset(!find_password_reset($pdo, $otherToken), 'Successful reset invalidates other links');
    $user['password'] = $newHash;
    $expired = create_password_reset($pdo, $user);
    $pdo->prepare('UPDATE password_resets SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE token_hash = ?')->execute([hash('sha256', $expired)]);
    check_reset(!complete_password_reset($pdo, $expired, 'ExpiredPassword789'), 'Expired token cannot change password');
    $inactive = create_password_reset($pdo, $user);
    $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$id]);
    check_reset(!complete_password_reset($pdo, $inactive, 'InactivePassword789'), 'Disabled account cannot be reset');
    $pdo->prepare('UPDATE users SET is_active = 1, password = ? WHERE id = ?')->execute([$oldPassword, $id]);
    check_reset(!find_password_reset($pdo, $inactive), 'Changing password invalidates previously issued links');
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    login_clear($email);
}
