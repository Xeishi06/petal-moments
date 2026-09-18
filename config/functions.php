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

function display_name($product)
{
    return 'Arrangement';
}

function display_image()
{
    return 'placeholder.svg';
}

function display_price()
{
    return number_format(0, 2);
}
