<?php
$active = 'inquiries';
$adminTitle = 'Event Inquiries | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = db();

if (isset($_GET['status']) && isset($_GET['id'])) {
    $pdo->prepare("UPDATE event_inquiries SET status=:s WHERE id=:id")
        ->execute([':s'=>$_GET['status'], ':id'=>(int)$_GET['id']]);
    flash('success', 'Inquiry status updated.');
    redirect('inquiries.php');
}

$inquiries = $pdo->query("
    SELECT i.*, u.first_name, u.last_name, u.email, u.phone
    FROM event_inquiries i
    LEFT JOIN users u ON u.id = i.user_id
    ORDER BY i.created_at DESC
")->fetchAll();
?>

<div class="admin-card"><h1>Event Inquiries</h1><p>Review and manage event styling requests.</p></div>

<div class="admin-card">
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
            <tr><th>Customer</th><th>Event</th><th>Guests</th><th>Budget</th><th>Message</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($inquiries)): ?>
            <tr><td colspan="7" class="table-empty">No event inquiries yet.</td></tr>
        <?php else: ?>
        <?php foreach ($inquiries as $i): ?>
            <tr>
                <td>
                    <div class="cell-with-thumb">
                        <span class="avatar avatar-sm avatar-sage">E</span>
                        <div>
                            <strong><?php echo e($i['name'] ?: ($i['user_id'] ? ('Customer ' . (int)$i['user_id']) : 'Guest')); ?></strong>
                            <small><?php echo e($i['email']); ?><?php echo $i['phone'] ? (' • ' . e($i['phone'])) : ''; ?></small>
                        </div>
                    </div>
                </td>
                <td>
                    <strong><?php echo e($i['event_type']); ?></strong>
                    <small><?php echo $i['event_date'] ? e($i['event_date']) : '—'; ?> • #<?php echo (int)$i['id']; ?></small>
                </td>
                <td><?php echo $i['guest_count'] ? (int)$i['guest_count'] : '—'; ?></td>
                <td><?php echo $i['budget'] ? '₱'.number_format((float)$i['budget'],2) : '—'; ?></td>
                <td>
                    <?php if ($i['message']): ?>
                        <span title="<?php echo e($i['message']); ?>"><?php echo e(mb_strimwidth($i['message'], 0, 48, '…')); ?></span>
                    <?php else: ?>
                        <span class="link-soft">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?php echo e($i['status']); ?>"><?php echo ucfirst(e($i['status'])); ?></span></td>
                <td>
                    <div class="cell-actions">
                        <?php if ($i['status'] !== 'contacted'): ?><a class="btn btn-ghost btn-sm" href="inquiries.php?status=contacted&id=<?php echo (int)$i['id']; ?>">Contacted</a><?php endif; ?>
                        <?php if ($i['status'] !== 'completed'): ?><a class="btn btn-ghost btn-sm" href="inquiries.php?status=completed&id=<?php echo (int)$i['id']; ?>">Completed</a><?php endif; ?>
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
