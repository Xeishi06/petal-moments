<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_admin();

$pdo = db();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $pdo->query("
        SELECT o.id, o.created_at, o.status, o.total_amount, o.delivery_fee, o.fulfillment,
               o.delivery_address, o.delivery_date, o.payment_method, o.payment_ref,
               u.first_name, u.last_name, u.email,
               (SELECT GROUP_CONCAT(CONCAT(p.name, ' x', oi.quantity) SEPARATOR '; ')
                FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = o.id) AS items
        FROM orders o JOIN users u ON u.id=o.user_id
        ORDER BY o.created_at DESC
    ")->fetchAll();
    $payLabels = ['cash_on_delivery' => 'Cash on Delivery', 'gcash' => 'GCash', 'pay_on_pickup' => 'Pay on Pickup'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="petal-moments-orders-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so ₱ renders in Excel
    fputcsv($out, ['Order #', 'Date', 'Time', 'Status', 'Items total', 'Delivery fee', 'Total', 'Fulfillment', 'Address', 'Delivery date', 'Payment', 'GCash ref', 'Customer', 'Email', 'Items']);
    foreach ($rows as $r) {
        $dt = strtotime($r['created_at']);
        // ="..." keeps long GCash refs as text instead of 1.23E+12
        $ref = ($r['payment_method'] === 'gcash' && !empty($r['payment_ref'])) ? '="' . $r['payment_ref'] . '"' : '';
        fputcsv($out, [
            $r['id'],
            date('Y-m-d', $dt),
            date('H:i', $dt),
            ucfirst($r['status']),
            $r['total_amount'] - $r['delivery_fee'],
            $r['delivery_fee'],
            $r['total_amount'],
            ucfirst($r['fulfillment']),
            $r['delivery_address'],
            $r['delivery_date'],
            $payLabels[$r['payment_method']] ?? $r['payment_method'],
            $ref,
            trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            $r['email'],
            $r['items'],
        ]);
    }
    fclose($out);
    exit;
}

$fStatus = $_GET['status'] ?? $_POST['fstatus'] ?? 'all';
if (!in_array($fStatus, ['all', 'pending', 'processing', 'delivered', 'cancelled'], true)) { $fStatus = 'all'; }
$fSearch = trim($_GET['q'] ?? $_POST['fq'] ?? '');

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
    $back = 'orders.php?status=' . urlencode($fStatus);
    if ($fSearch !== '') { $back .= '&q=' . urlencode($fSearch); }
    redirect($back);
}

$active = 'orders';
$adminTitle = 'Orders | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$where = [];
$params = [];
if ($fStatus !== 'all') { $where[] = 'o.status = :st'; $params[':st'] = $fStatus; }
if ($fSearch !== '') {
    $where[] = '(CAST(o.id AS CHAR) LIKE :q OR u.email LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q)';
    $params[':q'] = '%' . $fSearch . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$oq = $pdo->prepare("
    SELECT o.id, o.user_id, o.total_amount, o.status, o.created_at, o.payment_method, o.payment_ref, o.delivery_address, o.fulfillment,
           u.id AS customer_id, u.first_name, u.last_name, u.email,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS items,
           (SELECT GROUP_CONCAT(CONCAT(p.name, ' ×', oi.quantity) SEPARATOR ', ')
            FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = o.id) AS item_names
    FROM orders o JOIN users u ON u.id=o.user_id
    $whereSql
    ORDER BY o.created_at DESC
");
$oq->execute($params);
$orders = $oq->fetchAll();
?>

<div class="admin-card"><h1>Orders</h1><p>Manage and update the status of customer orders. <a class="btn btn-dark btn-sm" href="orders.php?export=csv">⬇ Export CSV</a></p></div>

<div class="admin-card">
    <form method="get" action="orders.php" class="filter-row">
        <select name="status" onchange="this.form.submit()">
            <option value="all"<?php echo $fStatus === 'all' ? ' selected' : ''; ?>>All statuses</option>
            <?php foreach (['pending', 'processing', 'delivered', 'cancelled'] as $s): ?>
                <option value="<?php echo $s; ?>"<?php echo $fStatus === $s ? ' selected' : ''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?php echo e($fSearch); ?>" placeholder="Search order #, name, email...">
        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
        <?php if ($fStatus !== 'all' || $fSearch !== ''): ?><a class="btn btn-light btn-sm" href="orders.php">Clear</a><?php endif; ?>
    </form>
    <p class="result-count" style="text-align:left;margin:10px 0 0;"><?php echo count($orders); ?> order(s) found.</p>
    <div class="table-scroll" style="margin-top:12px;">
    <table class="admin-table">
        <thead>
            <tr><th>#</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Update</th></tr>
        </thead>
        <tbody>
        <?php if (empty($orders)): ?>
            <tr><td colspan="8" class="table-empty">No orders yet.</td></tr>
        <?php else: ?>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><a href="order_detail.php?id=<?php echo (int)$o['id']; ?>"><strong>#<?php echo (int)$o['id']; ?></strong></a></td>
                <td>
                    <div class="cell-with-thumb">
                        <span class="avatar avatar-sm avatar-sage"><?php echo e(mb_strtoupper(mb_substr($o['first_name'] ?? 'C', 0, 1))); ?></span>
                        <div>
                            <strong><?php echo e(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: 'Customer ' . (int)$o['customer_id']); ?></strong>
                            <small><?php echo e($o['email']); ?></small>
                            <?php if (($o['fulfillment'] ?? 'delivery') === 'pickup'): ?>
                                <small>🏃 Pickup</small>
                            <?php else: ?>
                                <small>🚚 <?php echo e($o['delivery_address'] ?? '—'); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><?php echo (int)$o['items']; ?><?php if (!empty($o['item_names'])): ?><br><small title="<?php echo e($o['item_names']); ?>"><?php echo e(mb_strimwidth($o['item_names'], 0, 42, '…')); ?></small><?php endif; ?></td>
                <td>₱<?php echo number_format((float)$o['total_amount'], 2); ?></td>
                <td><?php echo e(strtoupper(str_replace('_',' ',$o['payment_method']))); ?><?php echo ($o['payment_method'] === 'gcash' && !empty($o['payment_ref'])) ? ' • Ref: ' . e($o['payment_ref']) : ''; ?></td>
                <td><span class="badge badge-<?php echo e($o['status']); ?>"><?php echo ucfirst(e($o['status'])); ?></span></td>
                <td><?php echo date('M j, Y', strtotime($o['created_at'])); ?></td>
                <td>
                    <form method="post" action="orders.php" class="inline-form">
                        <input type="hidden" name="form" value="status">
                        <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                        <input type="hidden" name="fstatus" value="<?php echo e($fStatus); ?>">
                        <input type="hidden" name="fq" value="<?php echo e($fSearch); ?>">
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach (['pending','processing','delivered','cancelled'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $o['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
