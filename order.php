<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

$orderId = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user");
$stmt->execute([':id' => $orderId, ':user' => $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('account.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'cancel') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
        redirect('order.php?id=' . $orderId);
    }
    if ($order['status'] !== 'pending') {
        flash('error', 'Only pending orders can be cancelled.');
        redirect('order.php?id=' . $orderId);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=:id")->execute([':id' => $orderId]);
        $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (:oid,'cancelled','Cancelled by customer')")
            ->execute([':oid' => $orderId]);
        // Return stock
        $pdo->prepare("UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.stock = p.stock + oi.quantity WHERE oi.order_id = :oid")
            ->execute([':oid' => $orderId]);
        $pdo->commit();
        flash('success', 'Order #' . $orderId . ' has been cancelled.');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Could not cancel your order. Please try again.');
    }
    redirect('order.php?id=' . $orderId);
}

$items = db()->prepare("
    SELECT oi.*, p.name, p.image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = :id
");
$items->execute([':id' => $orderId]);
$items = $items->fetchAll();

$history = db()->prepare("SELECT * FROM order_status_history WHERE order_id = :id ORDER BY changed_at ASC");
$history->execute([':id' => $orderId]);
$history = $history->fetchAll();
?>
<?php $pageTitle = 'Order #' . $orderId . ' | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <p class="back-link"><a href="account.php">← Back to my account</a></p>

        <div class="section-heading">
            <span class="eyebrow">ORDER DETAILS</span>
            <h2>Order <em>#<?php echo (int)$order['id']; ?></em></h2>
            <p>Placed <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?> •
               <span class="badge badge-<?php echo e($order['status']); ?>"><?php echo ucfirst(e($order['status'])); ?></span></p>
        </div>

        <div class="checkout-grid">
            <div>
                <table class="cart-table">
                    <thead>
                        <tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><a href="product.php?id=<?php echo (int)$item['product_id']; ?>"><?php echo e($item['name'] ?? 'Arrangement'); ?></a></td>
                            <td><?php echo (int)$item['quantity']; ?></td>
                            <td>₱<?php echo number_format((float)$item['unit_price'], 2); ?></td>
                            <td>₱<?php echo number_format((float)$item['subtotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" class="right">Total</td><td>₱<?php echo number_format((float)$order['total_amount'], 2); ?></td></tr>
                    </tfoot>
                </table>

                <?php if ($order['status'] === 'pending'): ?>
                <form method="post" action="order.php?id=<?php echo (int)$order['id']; ?>" onsubmit="return confirm('Cancel this order? Stock will be returned.');">
                    <input type="hidden" name="form" value="cancel">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <button type="submit" class="btn btn-light">Cancel Order</button>
                </form>
                <?php endif; ?>
            </div>

            <div class="checkout-summary">
                <h3>Summary</h3>
                <div class="summary-line">
                    <span>Fulfillment</span>
                    <span><?php echo ($order['fulfillment'] ?? 'delivery') === 'pickup' ? '🏃 Pickup' : '🚚 Delivery'; ?></span>
                </div>
                <?php if (($order['fulfillment'] ?? 'delivery') === 'pickup'): ?>
                    <div class="summary-line"><span>Pickup point</span><span>688 B. Manuel St., Montalban</span></div>
                <?php else: ?>
                    <div class="summary-line"><span>Deliver to</span><span><?php echo e($order['delivery_address'] ?? '—'); ?></span></div>
                <?php endif; ?>
                <div class="summary-line"><span>Date</span><span><?php echo $order['delivery_date'] ? e(date('M j, Y', strtotime($order['delivery_date']))) : '—'; ?></span></div>
                <div class="summary-line"><span>Payment</span><span><?php echo e(strtoupper(str_replace('_', ' ', $order['payment_method']))); ?></span></div>
                <?php if ($order['payment_method'] === 'gcash' && !empty($order['payment_ref'])): ?>
                    <div class="summary-line"><span>GCash ref</span><span><?php echo e($order['payment_ref']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($order['delivery_notes'])): ?>
                    <div class="summary-line"><span>Notes</span><span><?php echo e($order['delivery_notes']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($history)): ?>
                    <h3 style="margin-top:18px;">Status history</h3>
                    <?php foreach ($history as $h): ?>
                        <div class="summary-line"><span><?php echo ucfirst(e($h['status'])); ?></span><span><?php echo date('M j, Y', strtotime($h['changed_at'])); ?></span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
