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
    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>Customer</th><th>Type</th><th>Date</th><th>Guests</th><th>Budget</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($inquiries as $i): ?>
            <tr>
                <td><?php echo (int)$i['id']; ?></td>
                <td>
                    <?php echo e($i['name'] ?: ($i['user_id'] ? ('Customer ' . (int)$i['user_id']) : 'Guest')); ?><br>
                    <small><?php echo e($i['email']); ?><?php echo $i['phone'] ? (' • ' . e($i['phone'])) : ''; ?></small>
                </td>
                <td><?php echo e($i['event_type']); ?></td>
                <td><?php echo $i['event_date'] ? e($i['event_date']) : '—'; ?></td>
                <td><?php echo $i['guest_count'] ? (int)$i['guest_count'] : '—'; ?></td>
                <td><?php echo $i['budget'] ? '₱'.number_format((float)$i['budget'],2) : '—'; ?></td>
                <td><span class="badge badge-<?php echo e($i['status']); ?>"><?php echo ucfirst(e($i['status'])); ?></span></td>
                <td>
                    <?php if ($i['message']): ?><button class="btn-link" onclick="alert('<?php echo e($i['message']); ?>')">View</button> |<?php endif; ?>
                    <a href="inquiries.php?status=contacted&id=<?php echo (int)$i['id']; ?>">Contacted</a> |
                    <a href="inquiries.php?status=completed&id=<?php echo (int)$i['id']; ?>">Completed</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
