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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'reorder') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
        redirect('order.php?id=' . $orderId);
    }
    $variants = cart_variants();
    $byName = [];
    foreach ($variants as $vid => $v) { $byName[$v['name']] = $vid; }
    $prodStmt = db()->prepare("SELECT id, stock FROM products WHERE id = :id AND is_active = 1");
    $lines = cart_lines('cart');
    $added = 0;
    $skipped = 0;
    foreach ($items as $it) {
        $prodStmt->execute([':id' => $it['product_id']]);
        if (!$prodStmt->fetch()) { $skipped++; continue; }
        $vid = isset($byName[$it['variant_name'] ?? '']) ? $byName[$it['variant_name']] : 0;
        $ded = mb_substr(trim((string)($it['dedication'] ?? '')), 0, 255);
        $qty = max(1, min(10, (int)$it['quantity']));
        $merged = false;
        foreach ($lines as $rk => $ln) {
            if ($ln['pid'] === (int)$it['product_id'] && $ln['vid'] === $vid && $ln['ded'] === $ded) {
                $lines[$rk]['qty'] = min(10, $ln['qty'] + $qty);
                $merged = true;
                break;
            }
        }
        if (!$merged) {
            $lines['r' . substr(md5($it['product_id'] . $vid . $ded . microtime(true)), 0, 10)] = [
                'pid' => (int)$it['product_id'], 'qty' => $qty, 'vid' => $vid, 'ded' => $ded,
            ];
        }
        $added++;
    }
    cart_save_lines('cart', $lines);
    if ($added > 0) {
        flash('success', 'Added ' . $added . ' item(s) back to your cart.' . ($skipped > 0 ? ' ' . $skipped . ' unavailable item(s) skipped.' : ''));
        redirect('cart.php');
    }
    flash('error', 'Nothing to reorder — those items are no longer available.');
    redirect('order.php?id=' . $orderId);
}

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
                        <tr><th></th><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><img class="order-thumb" src="<?php echo e($item['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=200&q=70'); ?>" alt=""></td>
                            <td><a href="product.php?id=<?php echo (int)$item['product_id']; ?>"><?php echo e($item['name'] ?? 'Arrangement'); ?></a><?php echo (!empty($item['variant_name']) && $item['variant_name'] !== 'Standard') ? ' (' . e($item['variant_name']) . ')' : ''; ?><?php echo !empty($item['dedication']) ? '<br><small>✎ ' . e($item['dedication']) . '</small>' : ''; ?></td>
                            <td><?php echo (int)$item['quantity']; ?></td>
                            <td>₱<?php echo number_format((float)$item['unit_price'], 2); ?></td>
                            <td>₱<?php echo number_format((float)$item['subtotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php if (!empty($order['delivery_fee']) && (float)$order['delivery_fee'] > 0): ?>
                        <tr><td colspan="4" class="right">Delivery fee</td><td>₱<?php echo number_format((float)$order['delivery_fee'], 2); ?></td></tr>
                        <?php endif; ?>
                        <tr><td colspan="4" class="right">Total</td><td>₱<?php echo number_format((float)$order['total_amount'], 2); ?></td></tr>
                    </tfoot>
                </table>

                <?php if ($order['status'] === 'pending'): ?>
                <form method="post" action="order.php?id=<?php echo (int)$order['id']; ?>" onsubmit="return confirm('Cancel this order? Stock will be returned.');">
                    <input type="hidden" name="form" value="cancel">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <button type="submit" class="btn btn-light">Cancel Order</button>
                </form>
                <?php endif; ?>
                <form method="post" action="order.php?id=<?php echo (int)$order['id']; ?>" style="margin-top:10px;">
                    <input type="hidden" name="form" value="reorder">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <button type="submit" class="btn btn-dark">↻ Buy These Again</button>
                </form>
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
                <div class="summary-line"><span>Payment</span><span><?php echo e(pay_label($order['payment_method'])); ?></span></div>
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
