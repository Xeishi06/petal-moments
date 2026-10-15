<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

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
?>
<?php $pageTitle = 'Your Orders | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">ORDER HISTORY</span>
            <h2>Your <em>orders</em></h2>
            <p>Everything you've ordered — click one for details, receipts, and reordering.</p>
        </div>

        <?php if (empty($myOrders)): ?>
            <p class="empty-state">You haven't ordered yet. <a href="shop.php">Start shopping →</a></p>
        <?php else: ?>
            <div class="account-orders">
                <table class="cart-table">
                    <thead>
                        <tr><th></th><th>Order #</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myOrders as $o): ?>
                        <tr>
                            <td><a href="order.php?id=<?php echo (int)$o['id']; ?>"><img class="order-thumb" src="<?php echo e($o['thumb'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=200&q=70'); ?>" alt=""></a></td>
                            <td><a href="order.php?id=<?php echo (int)$o['id']; ?>"><strong>#<?php echo (int)$o['id']; ?></strong></a></td>
                            <td><?php echo (int)$o['items']; ?></td>
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
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
