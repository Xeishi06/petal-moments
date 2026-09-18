<?php
require_once __DIR__ . '/../../config/functions.php';
start_session();
require_admin();

$adminTitle = $adminTitle ?? 'Admin | Petal Moments';

$navLabels = [
    'dashboard'   => 'Dashboard',
    'products'    => 'Products',
    'categories'  => 'Categories',
    'orders'      => 'Orders',
    'users'       => 'Users',
    'inquiries'   => 'Event Inquiries',
    'subscribers' => 'Subscribers',
];
$crumbLabel = $navLabels[$active ?? ''] ?? 'Admin Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo e($adminTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css" />
</head>
<body class="admin-body">

<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <img src="logo.png" alt="Petal Moments logo" class="brand-logo" />
            <div>
                <strong>Petal Moments</strong>
                <small>Admin Panel</small>
            </div>
        </div>

        <div class="admin-nav-label">Menu</div>
        <nav class="admin-nav">
            <a href="index.php" class="<?php echo ($active ?? '') === 'dashboard' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg>
                Dashboard
            </a>
            <a href="products.php" class="<?php echo ($active ?? '') === 'products' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg>
                Products
            </a>
            <a href="categories.php" class="<?php echo ($active ?? '') === 'categories' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 12 22l-9-9V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r=".5"/></svg>
                Categories
            </a>
            <a href="orders.php" class="<?php echo ($active ?? '') === 'orders' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"/><path d="M9 7h6"/><path d="M9 11h6"/></svg>
                Orders
            </a>
            <a href="users.php" class="<?php echo ($active ?? '') === 'users' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-3.5 3.2-5.5 6.5-5.5s6 2 6.5 5.5"/><circle cx="17.5" cy="9.5" r="2.5"/><path d="M16 14.6c2.6.3 4.5 2 5 4.9"/></svg>
                Users
            </a>
            <a href="inquiries.php" class="<?php echo ($active ?? '') === 'inquiries' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/></svg>
                Event Inquiries
            </a>
            <a href="subscribers.php" class="<?php echo ($active ?? '') === 'subscribers' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v12H9l-5 4V4Z"/><path d="M8 9h8"/><path d="M8 12h5"/></svg>
                Subscribers
            </a>
        </nav>

        <div class="admin-sidebar-foot">
            <span>Petal Moments</span>
            <small>Flowers &amp; Events Admin</small>
        </div>
    </aside>

    <div class="admin-content">
        <header class="admin-topbar">
            <div class="crumb">Admin Panel <span class="crumb-span">/</span> <b><?php echo e($crumbLabel); ?></b></div>
            <div class="topbar-right">
                <div class="admin-user">
                    <span class="avatar avatar-sm">A</span>
                    <span>Admin</span>
                </div>
                <a class="btn btn-ghost btn-sm" href="../index.php">View site</a>
                <a class="btn btn-light btn-sm" href="../logout.php">Logout</a>
            </div>
        </header>

        <main class="admin-main">
        <?php foreach (get_flashes() as $flashMsg): ?>
            <div class="flash-alert flash-<?php echo $flashMsg['type'] === 'error' ? 'error' : 'success'; ?>">
                <?php echo e($flashMsg['message']); ?>
            </div>
        <?php endforeach; ?>