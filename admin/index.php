<?php
$active = 'dashboard';
$adminTitle = 'Dashboard | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$totalProducts = db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders   = db()->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue  = db()->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$totalUsers    = db()->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$pendingOrders = db()->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$pendingInquiries = db()->query("SELECT COUNT(*) FROM event_inquiries WHERE status='pending'")->fetchColumn();

$recentOrders = db()->query("
    SELECT o.id, o.total_amount, o.status, o.created_at, u.id AS customer_id
    FROM orders o JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC LIMIT 6
")->fetchAll();
?>

<div class="admin-card">
    <h1>Dashboard</h1>
    <p>Welcome back, <strong>Admin</strong>. Here's an overview of Petal Moments.</p>
</div>

<div class="stat-grid">
    <div class="stat-card"><span>Total Products</span><strong><?php echo (int)$totalProducts; ?></strong></div>
    <div class="stat-card"><span>Total Orders</span><strong><?php echo (int)$totalOrders; ?></strong></div>
    <div class="stat-card"><span>Pending Orders</span><strong><?php echo (int)$pendingOrders; ?></strong></div>
    <div class="stat-card"><span>Pending Inquiries</span><strong><?php echo (int)$pendingInquiries; ?></strong></div>
    <div class="stat-card"><span>Total Revenue</span><strong>₱<?php echo number_format((float)$totalRevenue, 2); ?></strong></div>
    <div class="stat-card"><span>Customers</span><strong><?php echo (int)$totalUsers; ?></strong></div>
</div>

<div class="admin-card">
    <h2>Recent Orders</h2>
    <table class="admin-table">
        <thead>
            <tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php if (empty($recentOrders)): ?>
            <tr><td colspan="5">No orders yet.</td></tr>
        <?php else: ?>
            <?php foreach ($recentOrders as $o): ?>
            <tr>
                <td>#<?php echo (int)$o['id']; ?></td>
                <td>Customer <?php echo (int)$o['customer_id']; ?></td>
                <td>₱<?php echo number_format((float)$o['total_amount'], 2); ?></td>
                <td><span class="badge badge-<?php echo e($o['status']); ?>"><?php echo ucfirst(e($o['status'])); ?></span></td>
                <td><?php echo date('M j, Y', strtotime($o['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
