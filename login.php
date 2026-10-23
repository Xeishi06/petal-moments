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
    $lockSeconds = 0;

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
    } elseif ($email !== '' && !login_allowed($email)) {
        $lockSeconds = login_wait_seconds($email);
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
            if (!login_allowed($email)) {
                $lockSeconds = login_wait_seconds($email);
            } else {
                flash('error', 'Invalid email or password.');
            }
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

            <?php if (!empty($lockSeconds) && $lockSeconds > 0): ?>
            <div class="lockout-box" id="lockoutBox" data-seconds="<?php echo (int)$lockSeconds; ?>">
                <strong>Too many failed attempts.</strong>
                <p>For your security, this email is locked. You can try again in
                    <span id="lockoutTimer">--:--</span>.</p>
            </div>
            <script>
                window.addEventListener('DOMContentLoaded', function () {
                    var box = document.getElementById('lockoutBox');
                    var out = document.getElementById('lockoutTimer');
                    var btn = document.querySelector('.auth-card button[type="submit"]');
                    var left = parseInt(box.getAttribute('data-seconds'), 10) || 0;
                    function fmt(s) {
                        var m = Math.floor(s / 60), r = s % 60;
                        return m + ':' + (r < 10 ? '0' : '') + r;
                    }
                    function tick() {
                        if (left <= 0) {
                            if (btn) { btn.disabled = false; }
                            out.textContent = 'now \u2014 you can try again.';
                            return;
                        }
                        out.textContent = fmt(left);
                        left -= 1;
                        setTimeout(tick, 1000);
                    }
                    if (btn) { btn.disabled = true; }
                    tick();
                });
            </script>
            <?php endif; ?>

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
