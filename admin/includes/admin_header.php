<?php
require_once __DIR__ . '/../../config/functions.php';
start_session();
require_admin();

$adminTitle = $adminTitle ?? 'Admin | Petal Moments';
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
            <span class="brand-logo">✿</span>
            <div>
                <strong>Petal Moments</strong>
                <small>Admin Panel</small>
            </div>
        </div>

        <nav class="admin-nav">
            <a href="index.php" class="<?php echo ($active ?? '') === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
            <a href="products.php" class="<?php echo ($active ?? '') === 'products' ? 'active' : ''; ?>">Products</a>
            <a href="categories.php" class="<?php echo ($active ?? '') === 'categories' ? 'active' : ''; ?>">Categories</a>
            <a href="orders.php" class="<?php echo ($active ?? '') === 'orders' ? 'active' : ''; ?>">Orders</a>
            <a href="users.php" class="<?php echo ($active ?? '') === 'users' ? 'active' : ''; ?>">Users</a>
            <a href="inquiries.php" class="<?php echo ($active ?? '') === 'inquiries' ? 'active' : ''; ?>">Event Inquiries</a>
        </nav>

        <div class="admin-sidebar-foot">
            <span>Hi, Admin</span>
            <a href="../index.php">View site →</a>
            <a href="../logout.php">Logout</a>
        </div>
    </aside>

    <main class="admin-main">
        <?php foreach (get_flashes() as $flashMsg): ?>
            <div class="flash-alert flash-<?php echo $flashMsg['type'] === 'error' ? 'error' : 'success'; ?>">
                <?php echo e($flashMsg['message']); ?>
            </div>
        <?php endforeach; ?>
