<?php
require_once __DIR__ . '/config/functions.php';
start_session();

// Only allow known local destinations after authentication.
$next = $_POST['next'] ?? $_GET['next'] ?? 'shop.php';
if (!is_string($next) || !preg_match('/\A(?:shop\.php|cart\.php|checkout\.php(?:\?mode=buynow)?|product\.php\?id=[0-9]+|account\.php|wishlist\.php|my-orders\.php)\z/', $next)) {
    $next = 'shop.php';
}

if (is_logged_in()) {
    redirect(is_staff() ? staff_home() : $next);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
        redirect('login.php?next=' . rawurlencode($next));
    }
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!login_allowed($email)) {
        flash('error', 'Too many failed attempts. Wait 15 minutes before trying again.');
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = :e AND is_active = 1");
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            login_clear($email);
            login_user($user);
            flash('success', 'Welcome back, ' . $user['first_name'] . '!');
            redirect(is_staff() ? staff_home() : $next);
        } else {
            login_hit($email);
            flash('error', login_allowed($email) ? 'Invalid email or password.' : 'Too many failed attempts. Wait 15 minutes before trying again.');
        }
    }
}
?>
<?php $pageTitle = 'Log In | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container auth-wrap">
        <div class="auth-card">
            <span class="eyebrow">WELCOME BACK</span>
            <h2>Log in to your account</h2>

            <form method="post" action="login.php">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="next" value="<?php echo e($next); ?>">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>

                <label>Password</label>
                <div class="pass-wrap">
                    <input type="password" name="password" id="password" required>
                    <button type="button" class="pass-eye" data-showpass="password" aria-label="Show password">👁</button>
                </div>

                <p class="password-help"><a href="forgot-password.php">Forgot password?</a></p>
                <button type="submit" class="btn btn-primary">Log In</button>
            </form>

            <p class="auth-alt">New here? <a href="register.php">Create an account</a></p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
