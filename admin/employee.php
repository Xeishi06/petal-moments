<?php
require_once __DIR__ . '/../config/functions.php';
require_staff();
$active = 'dashboard';
$adminTitle = 'Employee Dashboard | Petal Moments';
include __DIR__ . '/includes/admin_header.php';
$pdo = db();
$pending = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$processing = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
$inquiries = (int)$pdo->query("SELECT COUNT(*) FROM event_inquiries WHERE status = 'pending'")->fetchColumn();
$messages = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
$orders = $pdo->query("SELECT id, delivery_date, fulfillment, status FROM orders WHERE status IN ('pending', 'processing') ORDER BY delivery_date IS NULL, delivery_date, id LIMIT 12")->fetchAll();
?>
<div class="admin-card">
    <h1>Employee Dashboard</h1>
    <p>Welcome, <?php echo e($_SESSION['name'] ?? 'team member'); ?>. Keep orders moving and help customers with their celebrations.</p>
</div>
<div class="stat-grid">
    <a class="stat-card stat-gold" href="orders.php?status=pending"><span>Pending Orders</span><strong><?php echo $pending; ?></strong><small>Review →</small></a>
    <a class="stat-card stat-blue" href="orders.php?status=processing"><span>Preparing Orders</span><strong><?php echo $processing; ?></strong><small>Fulfill →</small></a>
    <a class="stat-card stat-rose" href="inquiries.php"><span>Event Inquiries</span><strong><?php echo $inquiries; ?></strong><small>Follow up →</small></a>
    <a class="stat-card stat-sage" href="messages.php"><span>Unread Messages</span><strong><?php echo $messages; ?></strong><small>Read →</small></a>
</div>
<div class="admin-card">
    <h2>Upcoming Orders</h2>
    <div class="table-scroll"><table class="admin-table">
        <thead><tr><th>Order</th><th>Delivery / Pickup Date</th><th>Fulfillment</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$orders): ?><tr><td colspan="4" class="table-empty">No orders awaiting fulfillment.</td></tr><?php endif; ?>
        <?php foreach ($orders as $order): ?>
            <tr><td><a href="order_detail.php?id=<?php echo (int)$order['id']; ?>">#<?php echo (int)$order['id']; ?></a></td><td><?php echo e($order['delivery_date'] ?: 'Not scheduled'); ?></td><td><?php echo e(ucfirst($order['fulfillment'])); ?></td><td><span class="badge badge-<?php echo e($order['status']); ?>"><?php echo e(ucfirst($order['status'])); ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<div class="admin-card"><h2>Your Access</h2><p>You can view orders, move them from pending to processing to delivered, update event inquiry progress, and mark customer messages as read or replied. Online payments must be verified by an admin before fulfillment.</p><p>For cancellations, payment verification, catalog changes, or account changes, contact an admin.</p></div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
