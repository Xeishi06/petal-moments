<?php
require_once __DIR__ . '/config/functions.php';
start_session();

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'shop.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare("SELECT * FROM users WHERE email = :e AND is_active = 1");
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        login_user($user);
        flash('success', 'Welcome back, ' . $user['first_name'] . '!');
        redirect($user['role'] === 'admin' ? 'admin/index.php' : 'shop.php');
    } else {
        flash('error', 'Invalid email or password.');
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
                <label>Email</label>
                <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>

                <label>Password</label>
                <input type="password" name="password" id="password" required>

                <label class="show-password">
                    <input type="checkbox" onchange="document.getElementById('password').type = this.checked ? 'text' : 'password';"> Show password
                </label>

                <button type="submit" class="btn btn-primary">Log In</button>
            </form>

            <p class="auth-alt">New here? <a href="register.php">Create an account</a></p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
