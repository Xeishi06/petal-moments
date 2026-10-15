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
