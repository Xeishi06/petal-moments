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
$newMessages = 0;
try { $newMessages = (int)db()->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn(); } catch (Exception $e) {}
$recentActivity = [];
try {
    $recentActivity = db()->query("
        SELECT h.order_id, h.status, h.note, h.changed_at
        FROM order_status_history h
        ORDER BY h.changed_at DESC LIMIT 8
    ")->fetchAll();
} catch (Exception $e) {}

$recentOrders = db()->query("
    SELECT o.id, o.total_amount, o.status, o.created_at, u.id AS customer_id
    FROM orders o JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC LIMIT 6
")->fetchAll();

// Low stock alert (threshold: 5 and below)
$lowStock = db()->query("
    SELECT id, name, stock FROM products
    WHERE is_active = 1 AND stock <= 5
    ORDER BY stock ASC, name ASC LIMIT 8
")->fetchAll();
$lowStockCount = (int)db()->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= 5")->fetchColumn();

// Sales overview: last 30 days (excluding cancelled)
$revenue30  = db()->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$orders30   = db()->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$topProducts = db()->query("
    SELECT p.id, p.name, SUM(oi.quantity) AS sold, SUM(oi.subtotal) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
    JOIN products p ON p.id = oi.product_id
    GROUP BY p.id, p.name
    ORDER BY sold DESC LIMIT 5
")->fetchAll();
?>

<div class="admin-card">
    <h1>Dashboard</h1>
    <p>Welcome back, <strong>Admin</strong>. Here's an overview of Petal Moments.</p>
</div>

<div class="stat-grid">
    <a class="stat-card stat-sage" href="products.php"><span>Total Products</span><strong><?php echo (int)$totalProducts; ?></strong><small>Manage →</small><?php if ($lowStockCount > 0): ?><span class="stat-badge warn" title="<?php echo $lowStockCount; ?> low stock items"><?php echo $lowStockCount; ?></span><?php endif; ?></a>
    <a class="stat-card stat-blue" href="orders.php"><span>Total Orders</span><strong><?php echo (int)$totalOrders; ?></strong><small>View all →</small></a>
    <a class="stat-card stat-gold" href="orders.php"><span>Pending Orders</span><strong><?php echo (int)$pendingOrders; ?></strong><small>Review →</small><?php if ((int)$pendingOrders > 0): ?><span class="stat-badge" title="Needs attention"><?php echo (int)$pendingOrders; ?></span><?php endif; ?></a>
    <a class="stat-card stat-rose" href="inquiries.php"><span>Pending Inquiries</span><strong><?php echo (int)$pendingInquiries; ?></strong><small>Review →</small><?php if ((int)$pendingInquiries > 0): ?><span class="stat-badge" title="Needs attention"><?php echo (int)$pendingInquiries; ?></span><?php endif; ?></a>
    <a class="stat-card stat-rose" href="orders.php"><span>Total Revenue</span><strong>₱<?php echo number_format((float)$totalRevenue, 2); ?></strong><small>View orders →</small></a>
    <a class="stat-card stat-blue" href="users.php"><span>Customers</span><strong><?php echo (int)$totalUsers; ?></strong><small>Manage →</small></a>
    <a class="stat-card stat-gold" href="messages.php"><span>New Messages</span><strong><?php echo (int)$newMessages; ?></strong><small>Read →</small><?php if ((int)$newMessages > 0): ?><span class="stat-badge" title="Unread messages"><?php echo (int)$newMessages; ?></span><?php endif; ?></a>
</div>

<div class="admin-card">
    <h2>Recent Orders</h2>
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
            <tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php if (empty($recentOrders)): ?>
            <tr><td colspan="5" class="table-empty">No orders yet.</td></tr>
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
</div>

<div class="dash-grid">
    <div class="admin-card">
        <h2>⚠ Low Stock Alert</h2>
        <?php if (empty($lowStock)): ?>
            <p class="stock-ok">✔ All active products are well stocked.</p>
        <?php else: ?>
            <p><?php echo count($lowStock); ?> product(s) at 5 or below — restock soon.</p>
            <div class="table-scroll">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Left</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($lowStock as $ls): ?>
                    <tr>
                        <td><strong><?php echo e($ls['name']); ?></strong> <small>#<?php echo (int)$ls['id']; ?></small></td>
                        <td><span class="chip <?php echo (int)$ls['stock'] === 0 ? 'chip-out' : 'chip-yes'; ?>"><?php echo (int)$ls['stock']; ?> left</span></td>
                        <td><a class="btn btn-ghost btn-sm" href="products.php?edit=<?php echo (int)$ls['id']; ?>">Restock</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <h2>📈 Sales — Last 30 Days</h2>
        <div class="mini-stats">
            <div><span>Revenue</span><strong>₱<?php echo number_format((float)$revenue30, 2); ?></strong></div>
            <div><span>Orders</span><strong><?php echo (int)$orders30; ?></strong></div>
        </div>
        <h3 class="sub-h">Top products</h3>
        <?php if (empty($topProducts)): ?>
            <p class="empty-note">No sales yet — top sellers will appear here.</p>
        <?php else: ?>
            <div class="table-scroll">
            <table class="admin-table">
                <thead><tr><th>#</th><th>Sold</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php $rank = 1; foreach ($topProducts as $tp): ?>
                    <tr>
                        <td><?php echo $rank++; ?>. <strong><?php echo e($tp['name']); ?></strong></td>
                        <td><?php echo (int)$tp['sold']; ?></td>
                        <td>₱<?php echo number_format((float)$tp['revenue'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="admin-card">
    <h2>🕘 Recent Activity</h2>
    <?php if (empty($recentActivity)): ?>
        <p class="empty-note">No status changes yet.</p>
    <?php else: ?>
        <?php foreach ($recentActivity as $a): ?>
            <div class="summary-line" style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:0.88rem;">
                <span>Order <a href="order_detail.php?id=<?php echo (int)$a['order_id']; ?>">#<?php echo (int)$a['order_id']; ?></a> → <strong><?php echo ucfirst(e($a['status'])); ?></strong><?php echo !empty($a['note']) ? ' — ' . e($a['note']) : ''; ?></span>
                <span style="color:var(--muted);white-space:nowrap;"><?php echo date('M j, g:i A', strtotime($a['changed_at'])); ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
