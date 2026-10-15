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
    SELECT o.*, COUNT(oi.id) AS items,
           (SELECT p.image FROM order_items oi2 JOIN products p ON p.id = oi2.product_id WHERE oi2.order_id = o.id LIMIT 1) AS thumb
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = :id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders->execute([':id' => $_SESSION['user_id']]);
$myOrders = $orders->fetchAll();

$statSpent = 0.0;
foreach ($myOrders as $so) {
    if ($so['status'] !== 'cancelled') { $statSpent += (float)$so['total_amount']; }
}
$statFav = db()->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = :id");
$statFav->execute([':id' => $_SESSION['user_id']]);
$statFav = (int)$statFav->fetchColumn();
$statRev = db()->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = :id");
$statRev->execute([':id' => $_SESSION['user_id']]);
$statRev = (int)$statRev->fetchColumn();

$myReviews = db()->prepare("
    SELECT r.*, p.name AS product_name
    FROM reviews r JOIN products p ON p.id = r.product_id
    WHERE r.user_id = :id
    ORDER BY r.created_at DESC
");
$myReviews->execute([':id' => $_SESSION['user_id']]);
$myReviews = $myReviews->fetchAll();

$favPreview = db()->prepare("
    SELECT p.id, p.name, p.price, p.image
    FROM wishlist w JOIN products p ON p.id = w.product_id AND p.is_active = 1
    WHERE w.user_id = :id
    ORDER BY w.created_at DESC LIMIT 4
");
$favPreview->execute([':id' => $_SESSION['user_id']]);
$favPreview = $favPreview->fetchAll();
?>
<?php $pageTitle = 'My Account | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">MY ACCOUNT</span>
            <h2>Hello, <em><?php echo e($user['first_name']); ?></em></h2>
        </div>

        <div class="hub-stats">
            <a class="hub-stat" href="#orders"><span><?php echo count($myOrders); ?></span><small>Orders</small></a>
            <a class="hub-stat" href="#orders"><span>₱<?php echo number_format($statSpent, 2); ?></span><small>Total spent</small></a>
            <a class="hub-stat" href="wishlist.php"><span><?php echo $statFav; ?></span><small>Favorites</small></a>
            <a class="hub-stat" href="#reviews"><span><?php echo $statRev; ?></span><small>Reviews</small></a>
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

            <div class="account-orders" id="orders">
                <h3>My Orders</h3>
                <?php if (empty($myOrders)): ?>
                    <p class="empty-state">You have no orders yet. <a href="shop.php">Start shopping →</a></p>
                <?php else: ?>
                    <table class="cart-table">
                        <thead>
                            <tr><th></th><th>Order #</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($myOrders as $o): ?>
                            <tr>
                                <td><a href="order.php?id=<?php echo (int)$o['id']; ?>"><img class="order-thumb" src="<?php echo e($o['thumb'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=200&q=70'); ?>" alt=""></a></td>
                                <td><a href="order.php?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a></td>
                                <td>₱<?php echo number_format((float)$o['total_amount'], 2); ?></td>
                                <td><span class="badge badge-<?php echo e($o['status']); ?>"><?php echo ucfirst(e($o['status'])); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($o['created_at'])); ?></td>
                                <td>
                                    <form method="post" action="order.php?id=<?php echo (int)$o['id']; ?>" style="margin:0;">
                                        <input type="hidden" name="form" value="reorder">
                                        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                                        <button type="submit" class="remove-link" style="border:0;background:none;cursor:pointer;">↻ Reorder</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h3 id="reviews" style="margin-top:28px;">My Reviews</h3>
                <?php if (empty($myReviews)): ?>
                    <p class="empty-state">No reviews yet — they appear here after you rate your flowers.</p>
                <?php else: ?>
                    <?php foreach ($myReviews as $rv): ?>
                    <div class="review-item" style="margin-bottom:10px;">
                        <div class="review-head"><a href="product.php?id=<?php echo (int)$rv['product_id']; ?>"><strong><?php echo e($rv['product_name']); ?></strong></a> <?php echo stars_html($rv['rating']); ?></div>
                        <?php if (!empty($rv['comment'])): ?><p><?php echo nl2br(e($rv['comment'])); ?></p><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <h3 style="margin-top:28px;">My Favorites <a class="text-link" style="font-size:0.8rem;" href="wishlist.php">View all →</a></h3>
                <?php if (empty($favPreview)): ?>
                    <p class="empty-state">Nothing saved yet. <a href="shop.php">Find flowers you love →</a></p>
                <?php else: ?>
                    <div class="fav-strip">
                        <?php foreach ($favPreview as $fp): ?>
                        <a class="fav-thumb" href="product.php?id=<?php echo (int)$fp['id']; ?>" title="<?php echo e($fp['name']); ?>">
                            <img src="<?php echo e($fp['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=200&q=70'); ?>" alt="<?php echo e($fp['name']); ?>">
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
