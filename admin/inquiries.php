<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_staff();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) { http_response_code(403); exit('Session expired. Refresh and try again.'); }
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['pending', 'contacted', 'completed'], true)) {
        $pdo->prepare("UPDATE event_inquiries SET status=:s WHERE id=:id")
            ->execute([':s'=>$status, ':id'=>(int)($_POST['id'] ?? 0)]);
        flash('success', 'Inquiry status updated.');
    } else { flash('error', 'Invalid inquiry status.'); }
    redirect('inquiries.php');
}

$active = 'inquiries';
$adminTitle = 'Event Inquiries | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$inquiries = $pdo->query("
    SELECT i.*, u.first_name, u.last_name, COALESCE(NULLIF(i.email, ''), u.email) AS email, COALESCE(NULLIF(i.phone, ''), u.phone) AS phone
    FROM event_inquiries i
    LEFT JOIN users u ON u.id = i.user_id
    ORDER BY i.created_at DESC
")->fetchAll();
?>

<div class="admin-card"><h1>Event Inquiries</h1><p>Review and manage event styling requests.</p></div>

<div class="admin-card">
    <div class="table-scroll">
    <table class="admin-table inq-table">
        <thead>
            <tr><th>Customer</th><th>Event</th><th>When</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($inquiries)): ?>
            <tr><td colspan="5" class="table-empty">No event inquiries yet.</td></tr>
        <?php else: ?>
        <?php foreach ($inquiries as $i): ?>
            <?php
            $when = trim(($i['event_date'] ? date('M j, Y', strtotime($i['event_date'])) : '') . ($i['event_time'] ? ' ' . date('g:i A', strtotime($i['event_time'])) : ''));
            $svc = !empty($i['service_type']) ? ucwords(str_replace('_', ' ', $i['service_type'])) : '';
            ?>
            <tr class="inq-row" data-inq="<?php echo (int)$i['id']; ?>">
                <td>
                    <div class="cell-with-thumb">
                        <span class="avatar avatar-sm avatar-sage">E</span>
                        <div>
                            <strong><?php echo e($i['name'] ?: ($i['user_id'] ? ('Customer ' . (int)$i['user_id']) : 'Guest')); ?></strong>
                            <small><?php echo e($i['email']); ?></small>
                        </div>
                    </div>
                </td>
                <td>
                    <strong><?php echo e($i['event_type']); ?></strong>
                    <?php if (!empty($i['venue'])): ?><small>📍 <?php echo e($i['venue']); ?></small><?php endif; ?>
                    <?php if ($svc !== ''): ?><small><?php echo e($svc); ?></small><?php endif; ?>
                </td>
                <td><?php echo $when !== '' ? e($when) : '<span class="link-soft">—</span>'; ?></td>
                <td><span class="badge badge-<?php echo e($i['status']); ?>"><?php echo ucfirst(e($i['status'])); ?></span></td>
                <td>
                    <div class="cell-actions">
                        <button type="button" class="btn btn-ghost btn-sm inq-toggle" data-inq="<?php echo (int)$i['id']; ?>" aria-expanded="false">View</button>
                        <form method="post" action="inquiries.php" class="inline-form">
                            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>">
                            <select name="status" aria-label="Inquiry status">
                                <?php foreach (['pending', 'contacted', 'completed'] as $status): ?><option value="<?php echo $status; ?>"<?php echo $i['status'] === $status ? ' selected' : ''; ?>><?php echo ucfirst($status); ?></option><?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-ghost btn-sm">Update</button>
                        </form>
                    </div>
                </td>
            </tr>
            <tr class="inq-detail" id="inq-<?php echo (int)$i['id']; ?>" hidden>
                <td colspan="5">
                    <div class="inq-grid">
                        <div><span>Contact</span><strong><?php echo e($i['name'] ?: '—'); ?></strong><small><?php echo e($i['email'] ?: '—'); ?><?php echo $i['phone'] ? (' • ' . e($i['phone'])) : ''; ?></small></div>
                        <div><span>Schedule</span><strong><?php echo $when !== '' ? e($when) : '—'; ?></strong><small>Inquiry #<?php echo (int)$i['id']; ?> • Sent <?php echo date('M j, Y', strtotime($i['created_at'])); ?></small></div>
                        <div><span>Venue</span><strong><?php echo e($i['venue'] ?: '—'); ?></strong></div>
                        <div><span>Service</span><strong><?php echo $svc !== '' ? e($svc) : '—'; ?></strong></div>
                        <div><span>Guests</span><strong><?php echo $i['guest_count'] ? (int)$i['guest_count'] : '—'; ?></strong></div>
                        <div><span>Budget</span><strong><?php echo $i['budget'] ? '₱' . number_format((float)$i['budget'], 2) : '—'; ?></strong></div>
                        <div class="wide"><span>💐 Theme, colors & flowers</span><p><?php echo !empty($i['preferences']) ? nl2br(e($i['preferences'])) : '<span class="link-soft">—</span>'; ?></p></div>
                        <div class="wide"><span>Notes</span><p><?php echo $i['message'] ? nl2br(e($i['message'])) : '<span class="link-soft">—</span>'; ?></p></div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<script>
document.querySelectorAll('.inq-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
        const row = document.getElementById('inq-' + btn.dataset.inq);
        if (!row) return;
        const open = row.hidden;
        row.hidden = !open;
        btn.textContent = open ? 'Hide' : 'View';
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
