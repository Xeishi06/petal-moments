<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/functions.php';
function check_access($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
function staff_request($cookie, $path, $data = null) {
    $ch = curl_init('http://localhost/petal-moments/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_FOLLOWLOCATION => false]);
    if ($data !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $html = curl_exec($ch);
    if ($html === false) { throw new RuntimeException(curl_error($ch)); }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return [$code, $html, $redirect];
}
function token_from($html) {
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m);
    if (empty($m[1])) { throw new RuntimeException('Missing CSRF form token'); }
    return $m[1];
}
$pdo = db();
$fixtures = [];
$cookies = [];
$orderId = null;
try {
    foreach (['admin', 'employee', 'customer'] as $role) {
        $email = 'staff-test-' . $role . '-' . bin2hex(random_bytes(5)) . '@example.invalid';
        $pdo->prepare('INSERT INTO users (first_name,last_name,email,password,role) VALUES (?,?,?,?,?)')
            ->execute(['Temporary', 'Access test', $email, password_hash('TestAccess123', PASSWORD_BCRYPT), $role]);
        $fixtures[$role] = ['id' => (int)$pdo->lastInsertId(), 'email' => $email];
        $cookies[$role] = tempnam(sys_get_temp_dir(), 'petal-staff-');
        [, $html] = staff_request($cookies[$role], 'login.php');
        [$code, , $location] = staff_request($cookies[$role], 'login.php', ['csrf' => token_from($html), 'email' => $email, 'password' => 'TestAccess123']);
        check_access($code === 302, "$role can log in");
        if ($role === 'employee') { check_access(strpos($location, 'admin/employee.php') !== false, 'Employee login opens employee dashboard'); }
    }
    $employee = $cookies['employee'];
    foreach (['employee.php', 'orders.php', 'inquiries.php', 'messages.php'] as $page) {
        [$code, $html] = staff_request($employee, 'admin/' . $page);
        check_access($code === 200 && strpos($html, 'Fatal error') === false, "Employee can open $page");
        check_access(strpos($html, 'href="users.php"') === false && strpos($html, 'href="settings.php"') === false, "$page hides admin-only navigation");
    }
    foreach (['index.php','users.php','products.php','categories.php','addons.php','variants.php','reviews.php','settings.php','subscribers.php','orders.php?export=csv'] as $page) {
        [$code] = staff_request($employee, 'admin/' . $page);
        check_access($code === 403, "Employee direct access denied: $page");
    }
    [$code] = staff_request($cookies['customer'], 'admin/employee.php');
    check_access($code === 403, 'Customer cannot enter staff dashboard');
    $pdo->prepare("INSERT INTO orders (user_id,total_amount,delivery_address,payment_method,status) VALUES (?,100,'Temporary test address','cash_on_delivery','pending')")->execute([$fixtures['customer']['id']]);
    $orderId = (int)$pdo->lastInsertId();
    [, $html] = staff_request($employee, 'admin/order_detail.php?id=' . $orderId);
    $csrf = token_from($html);
    $status = function () use ($pdo, $orderId) { $q = $pdo->prepare('SELECT status FROM orders WHERE id = ?'); $q->execute([$orderId]); return $q->fetchColumn(); };
    staff_request($employee, 'admin/orders.php', ['form'=>'status','order_id'=>$orderId,'status'=>'cancelled','csrf'=>$csrf]);
    check_access($status() === 'pending', 'Forged employee cancellation is rejected');
    staff_request($employee, 'admin/order_detail.php?id=' . $orderId, ['form'=>'status','status'=>'processing','csrf'=>$csrf]);
    check_access($status() === 'processing', 'Employee can start fulfillment');
    staff_request($employee, 'admin/orders.php', ['form'=>'status','order_id'=>$orderId,'status'=>'delivered','csrf'=>$csrf]);
    check_access($status() === 'delivered', 'Employee can complete fulfillment');
    staff_request($employee, 'admin/order_detail.php?id=' . $orderId, ['form'=>'status','status'=>'pending','csrf'=>$csrf]);
    check_access($status() === 'delivered', 'Employee cannot reopen a completed order');
    [$code] = staff_request($employee, 'admin/order_detail.php?id=' . $orderId, ['form'=>'verify','csrf'=>$csrf]);
    check_access($code === 403, 'Employee cannot verify payments');
    $pdo->prepare("UPDATE orders SET status='pending',payment_method='gcash',pay_verified=0 WHERE id=?")->execute([$orderId]);
    staff_request($employee, 'admin/orders.php', ['form'=>'status','order_id'=>$orderId,'status'=>'processing','csrf'=>$csrf]);
    check_access($status() === 'pending', 'Unverified GCash order cannot advance');
    [$code] = staff_request($employee, 'admin/messages.php', ['delete'=>0,'csrf'=>$csrf]);
    check_access($code === 403, 'Employee cannot delete messages');
    [$code] = staff_request($employee, 'admin/inquiries.php', ['id'=>0,'status'=>'completed']);
    check_access($code === 403, 'Inquiry updates require CSRF token');
    [, $html] = staff_request($cookies['admin'], 'admin/users.php');
    $adminCsrf = token_from($html);
    staff_request($cookies['admin'], 'admin/users.php', ['form'=>'user_action','do'=>'role','role'=>'employee','user_id'=>$fixtures['customer']['id'],'csrf'=>$adminCsrf]);
    [$code] = staff_request($cookies['customer'], 'admin/employee.php');
    check_access($code === 200, 'Admin promotion grants employee access without relogin');
    staff_request($cookies['admin'], 'admin/users.php', ['form'=>'user_action','do'=>'role','role'=>'customer','user_id'=>$fixtures['employee']['id'],'csrf'=>$adminCsrf]);
    [$code] = staff_request($employee, 'admin/orders.php');
    check_access($code === 403, 'Demotion revokes access in an existing session');
    $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$fixtures['customer']['id']]);
    [$code] = staff_request($cookies['customer'], 'admin/employee.php');
    check_access($code === 403, 'Disabled employee immediately loses staff access');
} finally {
    if ($orderId) { $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]); }
    foreach ($fixtures as $user) { $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]); login_clear($user['email']); }
    foreach ($cookies as $cookie) { if (is_file($cookie)) { unlink($cookie); } }
}
