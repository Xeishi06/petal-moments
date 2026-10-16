<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_staff();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Session expired. Refresh the order and try again.');
}

$pdo = db();
$orderId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email AS customer_email, u.phone AS customer_phone
    FROM orders o JOIN users u ON u.id = o.user_id
    WHERE o.id = :id
");
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('orders.php');
}

// Warn if this GCash ref appears on other non-cancelled orders
$dupOrders = [];
if ($order['payment_method'] === 'gcash' && !empty($order['payment_ref'])) {
    $dq = $pdo->prepare("SELECT id FROM orders WHERE payment_method = 'gcash' AND payment_ref = :ref AND id != :id AND status != 'cancelled'");
    $dq->execute([':ref' => $order['payment_ref'], ':id' => $orderId]);
    $dupOrders = array_column($dq->fetchAll(), 'id');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'status') {
    $status = $_POST['status'] ?? '';
    $note   = trim($_POST['note'] ?? '');
    if (in_array($status, ['pending', 'processing', 'delivered', 'cancelled'], true)) {
        $pdo->beginTransaction();
        try {
            check_order_transition($pdo, $orderId, $status);
            $note = trim($note . ' [Updated by ' . current_role() . ' #' . (int)$_SESSION['user_id'] . ']');
            $pdo->prepare("UPDATE orders SET status = :s WHERE id = :id")->execute([':s' => $status, ':id' => $orderId]);
            $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (:oid, :s, :n)")
                ->execute([':oid' => $orderId, ':s' => $status, ':n' => $note !== '' ? $note : null]);
            $pdo->commit();
            flash('success', 'Order #' . $orderId . ' updated to ' . $status . '.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', 'Update failed: ' . $e->getMessage());
        }
    }
    redirect('order_detail.php?id=' . $orderId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'verify') {
    require_admin();
    $pdo->prepare("UPDATE orders SET pay_verified = 1 - pay_verified WHERE id = :id")->execute([':id' => $orderId]);
    $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (:oid, :s, 'Payment verification toggled by admin')")
        ->execute([':oid' => $orderId, ':s' => $order['status']]);
    flash('success', 'Payment verification updated.');
    redirect('order_detail.php?id=' . $orderId);
}

$items = $pdo->prepare("
    SELECT oi.*, p.name, p.image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = :id
");
$items->execute([':id' => $orderId]);
$items = $items->fetchAll();

$history = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id = :id ORDER BY changed_at DESC");
$history->execute([':id' => $orderId]);
$history = $history->fetchAll();

$active = 'orders';
$adminTitle = 'Order #' . $orderId . ' | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-card">
    <p><a class="btn btn-ghost btn-sm" href="orders.php">← All orders</a></p>
    <h1>Order #<?php echo (int)$order['id']; ?> <span class="badge badge-<?php echo e($order['status']); ?>"><?php echo ucfirst(e($order['status'])); ?></span></h1>
    <p>Placed <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?> • <?php echo e($order['first_name'] . ' ' . $order['last_name']); ?> (<?php echo e($order['customer_email']); ?><?php echo $order['customer_phone'] ? ' • ' . e($order['customer_phone']) : ''; ?>)</p>
</div>

<div class="dash-grid">
    <div class="admin-card">
        <h2>Items</h2>
        <div class="table-scroll">
        <table class="admin-table">
            <thead><tr><th></th><th>Product</th><th>Qty</th><th>Unit</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?php if (!empty($it['image'])): ?><img src="<?php echo e($it['image']); ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;"><?php endif; ?></td>
                    <td>
                        <strong><?php echo e($it['name'] ?? 'Deleted product'); ?></strong>
                        <?php if (!empty($it['variant_name']) && $it['variant_name'] !== 'Standard'): ?><br><small>Variant: <?php echo e($it['variant_name']); ?></small><?php endif; ?>
                        <?php if (!empty($it['dedication'])): ?><br><small>✎ <?php echo e($it['dedication']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo (int)$it['quantity']; ?></td>
                    <td>₱<?php echo number_format((float)$it['unit_price'], 2); ?></td>
                    <td>₱<?php echo number_format((float)$it['subtotal'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php if (!empty($order['delivery_fee']) && (float)$order['delivery_fee'] > 0): ?>
                <tr><td colspan="4" style="text-align:right;">Delivery fee</td><td>₱<?php echo number_format((float)$order['delivery_fee'], 2); ?></td></tr>
                <?php endif; ?>
                <tr><td colspan="4" style="text-align:right;"><strong>Total</strong></td><td><strong>₱<?php echo number_format((float)$order['total_amount'], 2); ?></strong></td></tr>
            </tfoot>
        </table>
        </div>
    </div>

    <div class="admin-card">
        <h2>Fulfillment & Payment</h2>
        <div class="inq-grid" style="grid-template-columns:1fr 1fr;">
            <div><span>Method</span><strong><?php echo ($order['fulfillment'] ?? 'delivery') === 'pickup' ? '🏃 Pickup' : '🚚 Delivery'; ?></strong></div>
            <div><span>Payment</span><strong><?php echo e(pay_label($order['payment_method'])); ?></strong></div>
            <div class="wide"><span><?php echo ($order['fulfillment'] ?? 'delivery') === 'pickup' ? 'Pickup point' : 'Deliver to'; ?></span><p><?php echo ($order['fulfillment'] ?? 'delivery') === 'pickup' ? e(setting('pickup_address', 'Shop pickup')) : e($order['delivery_address'] ?? '—'); ?></p></div>
            <div><span>Date</span><strong><?php echo $order['delivery_date'] ? e(date('M j, Y', strtotime($order['delivery_date']))) : '—'; ?></strong></div>
            <?php if (in_array($order['payment_method'], ['gcash', 'card']) && !empty($order['payment_ref'])): ?><div><span><?php echo $order['payment_method'] === 'card' ? 'Approval code' : 'GCash ref'; ?></span><strong><?php echo e($order['payment_ref']); ?></strong></div><?php elseif ($order['payment_method'] === 'gcash'): ?><div><span>GCash ref</span><strong>— missing —</strong></div><?php endif; ?>
            <?php if (!empty($dupOrders)): ?>
                <div class="wide"><span>⚠ Duplicate ref</span><p style="color:#b34f4f;font-weight:600;">Same reference used on order(s): <?php foreach ($dupOrders as $d): ?><a href="order_detail.php?id=<?php echo (int)$d; ?>">#<?php echo (int)$d; ?></a> <?php endforeach; ?> — verify carefully.</p></div>
            <?php endif; ?>
            <?php if (is_admin() && !empty($order['receipt'])): ?>
                <div class="wide"><span>Receipt</span><p><a href="../receipt.php?order=<?php echo (int)$order['id']; ?>" target="_blank" rel="noopener"><img src="../receipt.php?order=<?php echo (int)$order['id']; ?>" alt="GCash receipt" style="max-width:220px;border-radius:10px;border:1px solid var(--border);"></a><br><small>Click to enlarge</small></p></div>
            <?php endif; ?>
            <div class="wide">
                <span>Payment verification</span>
                <p>
                    <?php if (!empty($order['pay_verified'])): ?>
                        <span class="chip chip-yes">✔ Verified</span>
                    <?php else: ?>
                        <span class="chip chip-out">Unverified</span>
                    <?php endif; ?>
                </p>
                <?php if (is_admin()): ?>
                <form method="post" action="order_detail.php?id=<?php echo (int)$order['id']; ?>" style="margin-top:8px;">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <button type="submit" name="form" value="verify" class="btn <?php echo !empty($order['pay_verified']) ? 'btn-light' : 'btn-primary'; ?> btn-sm"><?php echo !empty($order['pay_verified']) ? 'Mark Unverified' : '✔ Mark Payment Verified'; ?></button>
                </form>
                <?php else: ?><small>Payment verification is handled by an admin.</small><?php endif; ?>
            </div>
            <?php if (!empty($order['sender_no'])): ?><div><span><?php echo $order['payment_method'] === 'card' ? 'Card used' : 'Paid from'; ?></span><strong><?php echo e($order['sender_no']); ?></strong></div><?php endif; ?>
            <div class="wide"><span>Notes</span><p><?php echo !empty($order['delivery_notes']) ? nl2br(e($order['delivery_notes'])) : '<span class="link-soft">—</span>'; ?></p></div>
        </div>

        <h2 style="margin-top:20px;">Update Status</h2>
        <form method="post" action="order_detail.php?id=<?php echo (int)$order['id']; ?>" class="admin-form">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="form" value="status">
            <div class="form-row">
                <div>
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (order_status_options($order) as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $order['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Note (optional)</label>
                    <input type="text" name="note" maxlength="255" placeholder="e.g. GCash verified ✔">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Status</button>
            </div>
        </form>

        <?php if (!empty($history)): ?>
        <h2 style="margin-top:20px;">History</h2>
        <?php foreach ($history as $h): ?>
            <div class="summary-line" style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:0.88rem;">
                <span><strong><?php echo ucfirst(e($h['status'])); ?></strong><?php echo !empty($h['note']) ? ' — ' . e($h['note']) : ''; ?></span>
                <span style="color:var(--muted);"><?php echo date('M j, g:i A', strtotime($h['changed_at'])); ?></span>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
