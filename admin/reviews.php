<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_admin();

$pdo = db();

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE reviews SET status = CASE WHEN status = 'approved' THEN 'hidden' ELSE 'approved' END WHERE id = :id")
        ->execute([':id' => (int)$_GET['toggle']]);
    flash('success', 'Review visibility updated.');
    redirect('reviews.php');
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM reviews WHERE id = :id")->execute([':id' => (int)$_GET['delete']]);
    flash('success', 'Review deleted.');
    redirect('reviews.php');
}

$active = 'reviews';
$adminTitle = 'Reviews | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$reviews = $pdo->query("
    SELECT r.*, p.name AS product_name, u.first_name, u.last_name
    FROM reviews r
    JOIN products p ON p.id = r.product_id
    JOIN users u ON u.id = r.user_id
    ORDER BY r.created_at DESC
")->fetchAll();
?>

<div class="admin-card"><h1>Reviews</h1><p>Buyer ratings from product pages. Hide anything inappropriate — hidden reviews disappear from the site.</p></div>

<div class="admin-card">
    <div class="table-scroll">
    <table class="admin-table">
        <thead><tr><th>Product</th><th>Buyer</th><th>Rating</th><th>Comment</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($reviews)): ?>
            <tr><td colspan="6" class="table-empty">No reviews yet.</td></tr>
        <?php else: ?>
        <?php foreach ($reviews as $r): ?>
            <tr>
                <td><strong><?php echo e($r['product_name']); ?></strong></td>
                <td><?php echo e($r['first_name'] . ' ' . $r['last_name']); ?><br><small><?php echo date('M j, Y', strtotime($r['created_at'])); ?></small></td>
                <td><?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?></td>
                <td><span title="<?php echo e($r['comment'] ?? ''); ?>"><?php echo $r['comment'] ? e(mb_strimwidth($r['comment'], 0, 80, '…')) : '<span class="link-soft">—</span>'; ?></span></td>
                <td><span class="chip <?php echo $r['status'] === 'approved' ? 'chip-yes' : 'chip-no'; ?>"><?php echo ucfirst(e($r['status'])); ?></span></td>
                <td>
                    <div class="cell-actions">
                        <a class="btn btn-ghost btn-sm" href="reviews.php?toggle=<?php echo (int)$r['id']; ?>"><?php echo $r['status'] === 'approved' ? 'Hide' : 'Approve'; ?></a>
                        <a class="btn btn-danger btn-sm" href="reviews.php?delete=<?php echo (int)$r['id']; ?>" onclick="return confirm('Delete this review?');">Delete</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
