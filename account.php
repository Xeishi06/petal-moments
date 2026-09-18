<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($firstName === '' || $lastName === '') {
        flash('error', 'Name fields are required.');
    } else {
        $sql  = "UPDATE users SET first_name=:fn, last_name=:ln, phone=:ph, address=:ad";
        $params = [':fn'=>$firstName, ':ln'=>$lastName, ':ph'=>$phone, ':ad'=>$address, ':id'=>$_SESSION['user_id']];
        if ($password !== '') {
            if (strlen($password) < 6) {
                flash('error', 'New password must be at least 6 characters.');
                redirect('account.php');
            }
            $sql .= ", password=:pw";
            $params[':pw'] = password_hash($password, PASSWORD_BCRYPT);
        }
        $sql .= " WHERE id=:id";
        db()->prepare($sql)->execute($params);
        flash('success', 'Profile updated.');
        redirect('account.php');
    }
}

$stmt = db()->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

$orders = db()->prepare("
    SELECT o.*, COUNT(oi.id) AS items
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = :id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders->execute([':id' => $_SESSION['user_id']]);
$myOrders = $orders->fetchAll();
?>
<?php $pageTitle = 'My Account | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">MY ACCOUNT</span>
            <h2>Hello, <em><?php echo e($user['first_name']); ?></em></h2>
        </div>

        <div class="account-grid">
            <div class="account-profile">
                <h3>Profile</h3>
                <form method="post" action="account.php">
                    <input type="hidden" name="form" value="profile">
                    <div class="form-row">
                        <div>
                            <label>First name</label>
                            <input type="text" name="first_name" value="<?php echo e($user['first_name']); ?>" required>
                        </div>
                        <div>
                            <label>Last name</label>
                            <input type="text" name="last_name" value="<?php echo e($user['last_name']); ?>" required>
                        </div>
                    </div>
                    <label>Email</label>
                    <input type="email" value="<?php echo e($user['email']); ?>" disabled>
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo e($user['phone']); ?>">
                    <label>Address</label>
                    <input type="text" name="address" value="<?php echo e($user['address']); ?>">
                    <label>New password (leave blank to keep current)</label>
                    <input type="password" name="password">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>

            <div class="account-orders">
                <h3>My Orders</h3>
                <?php if (empty($myOrders)): ?>
                    <p class="empty-state">You have no orders yet. <a href="shop.php">Start shopping →</a></p>
                <?php else: ?>
                    <table class="cart-table">
                        <thead>
                            <tr><th>Order #</th><th>Total</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($myOrders as $o): ?>
                            <tr>
                                <td>#<?php echo (int)$o['id']; ?></td>
                                <td>₱<?php echo number_format((float)$o['total_amount'], 2); ?></td>
                                <td><span class="badge badge-<?php echo e($o['status']); ?>"><?php echo ucfirst(e($o['status'])); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($o['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
