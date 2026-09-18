<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

$orderId = (int)($_GET['order'] ?? 0);
$stmt = db()->prepare("
    SELECT * FROM orders WHERE id = :id AND user_id = :user
");
$stmt->execute([':id' => $orderId, ':user' => $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('shop.php');
}

$items = db()->prepare("
    SELECT oi.*, p.name, p.image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = :id
");
$items->execute([':id' => $orderId]);
$items = $items->fetchAll();
?>
<?php $pageTitle = 'Order Confirmed | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="thankyou-card">
            <span class="step-icon">✿</span>
            <h2>Thank you for your order!</h2>
            <p>Your order <strong>#<?php echo (int)$order['id']; ?></strong> has been placed and is now
            <strong>pending</strong>. We will prepare your flowers with care.</p>

            <table class="cart-table">
                <thead>
                    <tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>Arrangement</td>
                        <td><?php echo (int)$item['quantity']; ?></td>
                        <td>₱0.00</td>
                        <td>₱0.00</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="right">Total</td><td>₱0.00</td></tr>
                </tfoot>
            </table>

            <p><strong>Delivery to:</strong> <?php echo e($order['delivery_address']); ?></p>
            <p><strong>Payment:</strong> <?php echo e(strtoupper(str_replace('_', ' ', $order['payment_method']))); ?></p>

            <a class="btn btn-primary" href="shop.php">Continue Shopping</a>
            <a class="btn btn-dark" href="events.php">Plan an Event</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
