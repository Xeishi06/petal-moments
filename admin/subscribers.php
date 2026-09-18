<?php
$active = 'subscribers';
$adminTitle = 'Newsletter | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = db();

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id=:id")->execute([':id'=>(int)$_GET['delete']]);
    flash('success', 'Subscriber removed.');
    redirect('subscribers.php');
}

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE newsletter_subscribers SET is_subscribed = 1 - is_subscribed WHERE id=:id")->execute([':id'=>(int)$_GET['toggle']]);
    redirect('subscribers.php');
}

$subs = $pdo->query("SELECT * FROM newsletter_subscribers ORDER BY created_at DESC")->fetchAll();
$count = $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_subscribed=1")->fetchColumn();
?>

<div class="admin-card"><h1>Newsletter Subscribers</h1><p><?php echo (int)$count; ?> active subscriber(s).</p></div>

<div class="admin-card">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Email</th><th>Subscribed</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($subs as $s): ?>
            <tr>
                <td><?php echo (int)$s['id']; ?></td>
                <td><?php echo e($s['email']); ?></td>
                <td><?php echo $s['is_subscribed'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo date('M j, Y', strtotime($s['created_at'])); ?></td>
                <td>
                    <a href="subscribers.php?toggle=<?php echo (int)$s['id']; ?>"><?php echo $s['is_subscribed'] ? 'Unsubscribe' : 'Subscribe'; ?></a> |
                    <a class="danger" href="subscribers.php?delete=<?php echo (int)$s['id']; ?>" onclick="return confirm('Remove this subscriber?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
