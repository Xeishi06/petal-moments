<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_admin();

$pdo = db();

if (isset($_GET['status']) && isset($_GET['id'])) {
    $allowed = ['new', 'read', 'replied'];
    if (in_array($_GET['status'], $allowed, true)) {
        $pdo->prepare("UPDATE contact_messages SET status=:s WHERE id=:id")
            ->execute([':s' => $_GET['status'], ':id' => (int)$_GET['id']]);
        flash('success', 'Message marked as ' . $_GET['status'] . '.');
    }
    redirect('messages.php');
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM contact_messages WHERE id=:id")->execute([':id' => (int)$_GET['delete']]);
    flash('success', 'Message deleted.');
    redirect('messages.php');
}

$active = 'messages';
$adminTitle = 'Messages | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$msgs = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
$newCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn();
?>

<div class="admin-card"><h1>Messages</h1><p><?php echo (int)$newCount; ?> unread message(s) from the contact form.</p></div>

<div class="admin-card">
    <div class="table-scroll">
    <table class="admin-table">
        <thead><tr><th>From</th><th>Message</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($msgs)): ?>
            <tr><td colspan="5" class="table-empty">No messages yet.</td></tr>
        <?php else: ?>
        <?php foreach ($msgs as $m): ?>
            <tr>
                <td>
                    <div class="cell-with-thumb">
                        <span class="avatar avatar-sm">M</span>
                        <div>
                            <strong><?php echo e($m['name']); ?></strong>
                            <small><?php echo e($m['email']); ?></small>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if (!empty($m['subject'])): ?><strong><?php echo e($m['subject']); ?></strong><?php endif; ?>
                    <span title="<?php echo e($m['message']); ?>"><?php echo e(mb_strimwidth($m['message'], 0, 80, '…')); ?></span>
                </td>
                <td><span class="badge badge-<?php echo $m['status'] === 'new' ? 'pending' : ($m['status'] === 'replied' ? 'delivered' : 'processing'); ?>"><?php echo ucfirst(e($m['status'])); ?></span></td>
                <td><?php echo date('M j, Y', strtotime($m['created_at'])); ?></td>
                <td>
                    <div class="cell-actions">
                        <?php if ($m['status'] === 'new'): ?><a class="btn btn-ghost btn-sm" href="messages.php?status=read&id=<?php echo (int)$m['id']; ?>">Mark read</a><?php endif; ?>
                        <?php if ($m['status'] !== 'replied'): ?><a class="btn btn-ghost btn-sm" href="messages.php?status=replied&id=<?php echo (int)$m['id']; ?>">Replied</a><?php endif; ?>
                        <a class="btn btn-danger btn-sm" href="messages.php?delete=<?php echo (int)$m['id']; ?>" onclick="return confirm('Delete this message?');">Delete</a>
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
