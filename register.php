<?php
require_once __DIR__ . '/config/functions.php';
start_session();
    
if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'shop.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
        flash('error', 'Please fill in all required fields.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
    } elseif (strlen($password) < 6) {
        flash('error', 'Password must be at least 6 characters.');
    } else {
        $stmt = db()->prepare("SELECT id FROM users WHERE email = :e");
        $stmt->execute([':e' => $email]);
        if ($stmt->fetch()) {
            flash('error', 'An account with that email already exists.');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins = db()->prepare("
                INSERT INTO users (first_name, last_name, email, password, phone, address, role)
                VALUES (:fn, :ln, :e, :pw, :ph, :ad, 'customer')
            ");
            $ins->execute([
                ':fn' => $firstName,
                ':ln' => $lastName,
                ':e'  => $email,
                ':pw' => $hash,
                ':ph' => $phone,
                ':ad' => $address,
            ]);
            flash('success', 'Account created! You can now log in.');
            redirect('login.php');
        }
    }
}
?>
<?php $pageTitle = 'Create Account | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container auth-wrap">
        <div class="auth-card">
            <span class="eyebrow">JOIN US</span>
            <h2>Create your account</h2>

            <form method="post" action="register.php">
                <div class="form-row">
                    <div>
                        <label>First name *</label>
                        <input type="text" name="first_name" value="<?php echo e($_POST['first_name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label>Last name *</label>
                        <input type="text" name="last_name" value="<?php echo e($_POST['last_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <label>Email *</label>
                <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>

                <label>Phone</label>
                <input type="text" name="phone" value="<?php echo e($_POST['phone'] ?? ''); ?>">

                <label>Address</label>
                <input type="text" name="address" value="<?php echo e($_POST['address'] ?? ''); ?>">

                <label>Password *</label>
                <input type="password" name="password" id="password" required>

                <label class="show-password">
                    <input type="checkbox" onchange="document.getElementById('password').type = this.checked ? 'text' : 'password';"> Show password
                </label>

                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>

            <p class="auth-alt">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
