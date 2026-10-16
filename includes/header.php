<?php
require_once __DIR__ . '/../config/functions.php';
start_session();

$pageTitle = $pageTitle ?? 'Petal Moments | Flowers & Events';
$active    = $active ?? '';
$theme = is_logged_in() && (($_SESSION['theme'] ?? 'light') === 'dark') ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="color-scheme" content="<?php echo $theme; ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Petal Moments Flowers & Events - Flowers made for life's sweetest moments." />
    <title><?php echo e($pageTitle); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime(__DIR__ . '/../styles.css'); ?>" />
</head>
<body id="top" class="<?php echo $theme === 'dark' ? 'dark' : ''; ?>" data-auth="<?php echo is_logged_in() ? '1' : '0'; ?>" data-csrf="<?php echo e(csrf_token()); ?>">

<div class="announcement">
    <p><?php echo e(str_replace('Same-day orders subject to availability.', 'Order at least a day ahead for delivery or pickup.', setting('announcement', 'Fresh flowers, thoughtfully arranged. Order at least a day ahead.'))); ?></p>
</div>

<header class="site-header">
    <div class="container nav-wrap">

        <a class="brand" href="index.php#top" aria-label="Petal Moments home">
            <img class="brand-logo" src="Petal Moments Logo.png" alt="Petal Moments Logo">
            <span>
                <strong>Petal Moments</strong>
                <small>Flowers & Events</small>
            </span>
        </a>

        <button class="menu-btn" id="menuBtn" aria-label="Open navigation" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="main-nav" id="mainNav">
            <a href="index.php#top">Home</a>
            <a href="index.php#shop">Shop</a>
            <a href="index.php#bestsellers">Best Sellers</a>
            <a href="index.php#events">Events</a>
            <a href="index.php#about">About</a>
            <a href="index.php#how">How It Works</a>
            <a href="contact.php">Contact</a>
            <?php if (is_logged_in()): ?>
                <a href="my-orders.php">Your Orders</a>
                <a href="account.php">My Account</a>
            <?php endif; ?>
            <span class="nav-divider nav-mobile-only" aria-hidden="true"></span>
            <?php if (is_logged_in()): ?>
                <a class="nav-mobile-only" href="cart.php">🛒 Cart</a>
                <a class="nav-mobile-only" href="wishlist.php">♡ Favorites</a>
                <?php if (is_staff()): ?>
                    <a class="nav-mobile-only nav-cta" href="<?php echo e(staff_home()); ?>"><?php echo is_admin() ? 'Admin Panel' : 'Employee Panel'; ?></a>
                <?php endif; ?>
                <a class="nav-mobile-only" href="logout.php">Logout</a>
            <?php else: ?>
                <a class="nav-mobile-only" href="cart.php">🛒 Cart</a>
                <button type="button" class="nav-mobile-only nav-link-btn" data-auth-required>♡ Favorites</button>
                <a class="nav-mobile-only" href="login.php">Login</a>
                <a class="nav-mobile-only nav-cta" href="register.php">Sign Up</a>
            <?php endif; ?>
        </nav>

        <div class="nav-actions">
            <?php if (is_logged_in()): ?>
                <a class="icon-btn" href="wishlist.php" aria-label="My favorites">♡</a>
                <a class="icon-btn" href="cart.php" aria-label="Cart">🛒</a>
                <?php if (is_staff()): ?>
                    <a class="btn btn-primary btn-small" href="<?php echo e(staff_home()); ?>"><?php echo is_admin() ? 'Admin Panel' : 'Employee Panel'; ?></a>
                <?php endif; ?>
                <a class="btn btn-dark btn-small" href="logout.php">Logout</a>
            <?php else: ?>
                <button type="button" class="icon-btn" data-auth-required aria-label="My favorites">♡</button>
                <a class="icon-btn" href="cart.php" aria-label="Cart">🛒</a>
                <a class="btn btn-dark btn-small" href="login.php">Login</a>
                <a class="btn btn-primary btn-small" href="register.php">Sign Up</a>
            <?php endif; ?>
        </div>

    </div>
</header>

<main>
<?php
foreach (get_flashes() as $flashMsg):
    $tone = $flashMsg['type'] === 'error' ? 'error' : 'success';
?>
    <div class="flash-alert flash-<?php echo $tone; ?>">
        <?php echo e($flashMsg['message']); ?>
    </div>
<?php endforeach; ?>
