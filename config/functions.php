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
