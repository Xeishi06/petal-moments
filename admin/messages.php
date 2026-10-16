<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_staff();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Session expired. Refresh and try again.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'], $_POST['id'])) {
    $allowed = ['new', 'read', 'replied'];
    if (in_array($_POST['status'], $allowed, true)) {
        $pdo->prepare("UPDATE contact_messages SET status=:s WHERE id=:id")
            ->execute([':s' => $_POST['status'], ':id' => (int)$_POST['id']]);
        flash('success', 'Message marked as ' . $_POST['status'] . '.');
    }
    redirect('messages.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    require_admin();
    $pdo->prepare("DELETE FROM contact_messages WHERE id=:id")->execute([':id' => (int)$_POST['delete']]);
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
                    <details><summary><?php echo e(mb_strimwidth($m['message'], 0, 80, '…')); ?></summary><p><?php echo nl2br(e($m['message'])); ?></p></details>
                </td>
                <td><span class="badge badge-<?php echo $m['status'] === 'new' ? 'pending' : ($m['status'] === 'replied' ? 'delivered' : 'processing'); ?>"><?php echo ucfirst(e($m['status'])); ?></span></td>
                <td><?php echo date('M j, Y', strtotime($m['created_at'])); ?></td>
                <td>
                    <div class="cell-actions">
                        <form method="post" action="messages.php" class="cell-actions">
                            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                            <?php if ($m['status'] === 'new'): ?><button type="submit" name="status" value="read" class="btn btn-ghost btn-sm">Mark read</button><?php endif; ?>
                            <?php if ($m['status'] !== 'replied'): ?><button type="submit" name="status" value="replied" class="btn btn-ghost btn-sm">Mark replied</button><?php endif; ?>
                            <?php if (is_admin()): ?><button type="submit" name="delete" value="<?php echo (int)$m['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this message?');">Delete</button><?php endif; ?>
                        </form>
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
