<?php
$active = 'orders';
$adminTitle = 'Orders | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'status') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $note    = trim($_POST['note'] ?? '');
    $allowed = ['pending','processing','delivered','cancelled'];
    if (in_array($status, $allowed, true)) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE orders SET status=:s WHERE id=:id")->execute([':s'=>$status,':id'=>$orderId]);
            $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (:oid,:s,:n)")
                ->execute([':oid'=>$orderId, ':s'=>$status, ':n'=>$note]);
            $pdo->commit();
            flash('success', 'Order #'.$orderId.' status updated to '.$status.'.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', 'Update failed: ' . $e->getMessage());
        }
    }
    redirect('orders.php');
}

$orders = $pdo->query("
    SELECT o.id, o.user_id, o.total_amount, o.status, o.created_at, o.payment_method, o.delivery_address,
           u.id AS customer_id, u.email,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS items
    FROM orders o JOIN users u ON u.id=o.user_id
    ORDER BY o.created_at DESC
")->fetchAll();
?>

<div class="admin-card"><h1>Orders</h1><p>Manage and update the status of customer orders.</p></div>

<div class="admin-card">
    <table class="admin-table">
        <thead>
            <tr><th>#</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Update</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td>#<?php echo (int)$o['id']; ?></td>
                <td>
                    Customer <?php echo (int)$o['customer_id']; ?><br>
                    <small><?php echo e($o['email']); ?></small><br>
                    <small><?php echo e($o['delivery_address']); ?></small>
                </td>
                <td><?php echo (int)$o['items']; ?></td>
                <td>₱<?php echo number_format((float)$o['total_amount'], 2); ?></td>
                <td><?php echo e(strtoupper(str_replace('_',' ',$o['payment_method']))); ?></td>
                <td><span class="badge badge-<?php echo e($o['status']); ?>"><?php echo ucfirst(e($o['status'])); ?></span></td>
                <td><?php echo date('M j, Y', strtotime($o['created_at'])); ?></td>
                <td>
                    <form method="post" action="orders.php" class="inline-form">
                        <input type="hidden" name="form" value="status">
                        <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach (['pending','processing','delivered','cancelled'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $o['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
