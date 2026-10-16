<?php
require_once __DIR__ . '/config/password_reset.php';
start_session();
header('Cache-Control: no-store');
$errors = [];
$sent = false;
$email = '';
$mailReady = reset_mail_ready();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Session expired. Refresh this page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!$mailReady) {
        $errors[] = 'Password reset emails are not available yet. Please contact the shop for help.';
    } else {
        try {
            $emailKey = 'reset-email:' . $email;
            $ipKey = 'reset-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            // Same response for unknown accounts and rate-limited requests.
            if (login_allowed($emailKey) && login_allowed($ipKey)) {
                login_hit($emailKey);
                login_hit($ipKey);
                $pdo = db();
                $pdo->exec('DELETE FROM password_resets WHERE expires_at <= UTC_TIMESTAMP()');
                $stmt = $pdo->prepare('SELECT id, email, password FROM users WHERE email = :email AND is_active = 1');
                $stmt->execute([':email' => $email]);
                if ($user = $stmt->fetch()) {
                    $token = create_password_reset($pdo, $user);
                    if (!send_password_reset($user['email'], $token)) {
                        $pdo->prepare('DELETE FROM password_resets WHERE token_hash = :token')->execute([':token' => hash('sha256', $token)]);
                        error_log('Petal Moments: password reset email delivery failed. Check PHP mail transport.');
                    }
                }
            }
            $sent = true;
        } catch (Throwable $e) {
            error_log('Petal Moments: password reset request failed. Check database and mail configuration.');
            $errors[] = 'We could not process your request. Please try again later.';
        }
    }
}
$pageTitle = 'Forgot Password | Petal Moments';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container auth-wrap">
        <div class="auth-card">
            <span class="eyebrow">LET'S GET YOU BACK IN</span>
            <h2>Forgot your password?</h2>
            <?php if ($sent): ?>
                <div class="recovery-message" role="status">If an active account matches that email, a reset link will be sent. Check your inbox and spam folder. The link expires in 30 minutes.</div>
                <p class="auth-alt">No email? Wait a few minutes, then <a href="forgot-password.php">try again</a>.</p>
            <?php else: ?>
                <p class="acct-note">Enter your account email and we'll send you a link to choose a new password.</p>
                <?php if (!$mailReady): ?><p class="recovery-message" role="status">Email recovery is being set up. Please <a href="contact.php">contact the shop</a> if you need help signing in.</p><?php endif; ?>
                <?php foreach ($errors as $error): ?><p class="form-errors" role="alert"><?php echo e($error); ?></p><?php endforeach; ?>
                <form method="post" action="forgot-password.php">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <label for="recoveryEmail">Email address</label>
                    <input type="email" id="recoveryEmail" name="email" autocomplete="email" maxlength="120" value="<?php echo e($email); ?>" placeholder="you@example.com" required>
                    <button type="submit" class="btn btn-primary"<?php echo !$mailReady ? ' disabled' : ''; ?>>Send Reset Link</button>
                </form>
            <?php endif; ?>
            <p class="auth-alt"><a href="login.php">← Back to login</a></p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
