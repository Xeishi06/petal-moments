<?php
require_once __DIR__ . '/config/password_reset.php';
start_session();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$error = '';
$valid = false;
try {
    $pdo = db();
    $valid = (bool)find_password_reset($pdo, $token);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
        if (!verify_csrf($_POST['csrf'] ?? '')) {
            $error = 'Session expired. Refresh this page and try again.';
        } elseif (password_strength($password) !== '') {
            $error = password_strength($password);
        } elseif (strlen($password) > 72) {
            $error = 'Please use a password of 72 bytes or fewer.';
        } elseif ($password !== $confirmation) {
            $error = 'The passwords do not match.';
        } elseif (complete_password_reset($pdo, $token, $password)) {
            $_SESSION = [];
            session_regenerate_id(true);
            flash('success', 'Your password has been updated. Log in with your new password.');
            redirect('login.php');
        } else {
            $valid = false;
        }
    }
} catch (Throwable $e) {
    error_log('Petal Moments: password reset failed. Check database configuration.');
    $error = 'We could not process this reset. Please try again later.';
}
$pageTitle = 'Reset Password | Petal Moments';
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container auth-wrap">
        <div class="auth-card">
            <span class="eyebrow">A FRESH START</span>
            <h2>Choose a new password</h2>
            <?php if ($error !== ''): ?><p class="form-errors" role="alert"><?php echo e($error); ?></p><?php endif; ?>
            <?php if ($valid): ?>
                <p class="acct-note">Use at least 8 characters, including letters and numbers.</p>
                <form method="post" action="reset-password.php">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="token" value="<?php echo e($token); ?>">
                    <label for="newPassword">New password</label>
                    <div class="pass-wrap">
                        <input type="password" id="newPassword" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>
                        <button type="button" class="pass-eye" data-showpass="newPassword" aria-label="Show password">👁</button>
                    </div>
                    <label for="confirmPassword">Confirm new password</label>
                    <input type="password" id="confirmPassword" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="72" required>
                    <button type="submit" class="btn btn-primary">Save New Password</button>
                </form>
            <?php else: ?>
                <p class="acct-note">This reset link is invalid, expired, or has already been used.</p>
                <a class="btn btn-primary" href="forgot-password.php">Request a New Link</a>
            <?php endif; ?>
            <p class="auth-alt"><a href="login.php">← Back to login</a></p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
