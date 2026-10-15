<?php
require_once __DIR__ . '/database.php';

function start_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function e($value)
{
    return htmlspecialchars((string)$value);
}

function redirect($location)
{
    header('Location: ' . $location);
    exit;
}

function flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes()
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrf_token()
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf($token)
{
    start_session();
    return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

// Returns '' when the password is strong enough, otherwise a human reason.
function password_strength($pw)
{
    if (strlen($pw) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
        return 'Password needs both letters and numbers.';
    }
    return '';
}

// Simple brute-force brake: max 5 password-change tries per 15 minutes.
function pw_change_allowed()
{
    start_session();
    $now = time();
    $_SESSION['pw_attempts'] = array_values(array_filter(
        $_SESSION['pw_attempts'] ?? [],
        function ($t) use ($now) { return ($now - $t) < 900; }
    ));
    return count($_SESSION['pw_attempts']) < 5;
}

function pw_change_hit()
{
    start_session();
    $_SESSION['pw_attempts'][] = time();
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function is_admin()
{
    return is_logged_in() && (($_SESSION['role'] ?? '') === 'admin');
}

function require_login()
{
    start_session();
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_admin()
{
    start_session();
    if (!is_admin()) {
        http_response_code(403);
        die('Access denied.');
    }
}

function login_user($user)
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name']    = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['theme']   = $user['theme'] ?? 'light';
}

function logout_user()
{
    $_SESSION = [];
    session_destroy();
}

function cart_variants()
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = [];
    try {
        foreach (db()->query("SELECT id, name, price_adjust, needs_message FROM variants WHERE is_active = 1 ORDER BY price_adjust ASC, id ASC")->fetchAll() as $v) {
            $cache[(int)$v['id']] = ['name' => $v['name'], 'adjust' => (float)$v['price_adjust'], 'needs_message' => (bool)$v['needs_message']];
        }
    } catch (Exception $e) { /* variants table missing — no customization */ }
    return $cache;
}

function cart_default_variant()
{
    foreach (cart_variants() as $id => $v) {
        if ($v['adjust'] == 0) { return $id; }
    }
    $ids = array_keys(cart_variants());
    return $ids ? $ids[0] : 0;
}

// Normalize a cart store into rows: [rowid => [pid, qty, vid, ded]]
// Also upgrades the legacy {product_id: qty} format automatically.
function cart_lines($store = 'cart')
{
    start_session();
    if (!isset($_SESSION[$store]) || !is_array($_SESSION[$store])) {
        $_SESSION[$store] = [];
    }
    $lines = [];
    foreach ($_SESSION[$store] as $key => $val) {
        if (is_array($val) && isset($val['pid'])) {
            $lines[$key] = [
                'pid' => (int)$val['pid'],
                'qty' => max(1, (int)($val['qty'] ?? 1)),
                'vid' => (int)($val['vid'] ?? 0),
                'ded' => mb_substr(trim((string)($val['ded'] ?? '')), 0, 255),
            ];
        } elseif (is_numeric($val)) {
            $lines['r' . substr(md5($key . microtime(true) . rand()), 0, 10)] = [
                'pid' => (int)$key,
                'qty' => max(1, (int)$val),
                'vid' => 0,
                'ded' => '',
            ];
        }
    }
    $_SESSION[$store] = $lines;
    return $lines;
}

function cart_save_lines($store, $lines)
{
    $_SESSION[$store] = $lines;
}

// Shop settings (admin-editable, with fallback defaults)
function setting($key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query("SELECT skey, svalue FROM settings")->fetchAll() as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (Exception $e) { /* settings table missing — use defaults */ }
    }
    return isset($cache[$key]) && $cache[$key] !== '' ? $cache[$key] : $default;
}

// Sale-aware pricing: sale_price wins when set and below regular price
function eff_price($p)
{
    $price = (float)($p['price'] ?? 0);
    $sale = isset($p['sale_price']) && $p['sale_price'] !== null && $p['sale_price'] !== '' ? (float)$p['sale_price'] : null;
    if ($sale !== null && $sale > 0 && $sale < $price) { return $sale; }
    return $price;
}

function on_sale($p)
{
    return eff_price($p) < (float)($p['price'] ?? 0);
}

function pay_label($method)
{
    $labels = [
        'cash_on_delivery' => 'Cash on Delivery',
        'pay_on_pickup'    => 'Pay on Pickup',
        'card'             => 'Debit/Credit Card',
        'gcash'            => 'GCash',
    ];
    return $labels[$method] ?? ucwords(str_replace('_', ' ', (string)$method));
}

// Units sold per product (paid pipeline only — cancelled excluded)
function product_sold_counts($ids)
{
    $ids = array_map('intval', (array)$ids);
    $ids = array_values(array_unique(array_filter($ids)));
    if (empty($ids)) { return []; }
    $in = implode(',', $ids);
    $out = array_fill_keys($ids, 0);
    try {
        foreach (db()->query("
            SELECT oi.product_id, COALESCE(SUM(oi.quantity), 0) AS sold
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
            WHERE oi.product_id IN ($in)
            GROUP BY oi.product_id
        ")->fetchAll() as $r) {
            $out[(int)$r['product_id']] = (int)$r['sold'];
        }
    } catch (Exception $e) { /* stats are best-effort */ }
    return $out;
}

function stars_html($avg)
{
    $full = (int)floor($avg);
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $full ? '★' : '☆';
    }
    return '<span class="stars" title="' . number_format((float)$avg, 1) . ' out of 5">' . $html . '</span>';
}

// Average rating + review count per product (approved only)
function product_ratings($ids)
{
    $ids = array_map('intval', (array)$ids);
    $ids = array_values(array_unique(array_filter($ids)));
    if (empty($ids)) { return []; }
    $in = implode(',', $ids);
    $out = [];
    foreach ($ids as $pid) { $out[$pid] = ['avg' => 0, 'n' => 0]; }
    try {
        foreach (db()->query("
            SELECT product_id, COUNT(*) AS n, AVG(rating) AS avg
            FROM reviews
            WHERE status = 'approved' AND product_id IN ($in)
            GROUP BY product_id
        ")->fetchAll() as $r) {
            $out[(int)$r['product_id']] = ['avg' => (float)$r['avg'], 'n' => (int)$r['n']];
        }
    } catch (Exception $e) { /* stats are best-effort */ }
    return $out;
}
