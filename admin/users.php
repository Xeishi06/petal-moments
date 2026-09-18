<?php
$active = 'users';
$adminTitle = 'Users | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = db();

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=:id")->execute([':id'=>$id]);
    flash('success', 'User status updated.');
    redirect('users.php');
}

if (isset($_GET['role'])) {
    $id = (int)$_GET['role'];
    $pdo->prepare("UPDATE users SET role = IF(role='admin','customer','admin') WHERE id=:id")->execute([':id'=>$id]);
    flash('success', 'User role updated.');
    redirect('users.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id == $_SESSION['user_id']) {
        flash('error', 'You cannot delete your own account.');
    } else {
        $pdo->prepare("DELETE FROM users WHERE id=:id")->execute([':id'=>$id]);
        flash('success', 'User deleted.');
    }
    redirect('users.php');
}

$users = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) AS order_count
    FROM users u ORDER BY u.created_at DESC
")->fetchAll();
?>

<div class="admin-card"><h1>Users</h1><p>Manage customer and admin accounts.</p></div>

<div class="admin-card">
    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Orders</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo (int)$u['id']; ?></td>
                <td><?php echo $u['role'] === 'admin' ? 'Admin' : ('Customer ' . (int)$u['id']); ?></td>
                <td><?php echo e($u['email']); ?></td>
                <td><span class="badge badge-<?php echo e($u['role']); ?>"><?php echo e($u['role']); ?></span></td>
                <td><?php echo (int)$u['order_count']; ?></td>
                <td><?php echo $u['is_active'] ? 'Active' : 'Disabled'; ?></td>
                <td>
                    <a href="users.php?toggle=<?php echo (int)$u['id']; ?>"><?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?></a> |
                    <?php if ($u['role'] === 'admin'): ?><a href="users.php?role=<?php echo (int)$u['id']; ?>">Make customer</a><?php else: ?><a href="users.php?role=<?php echo (int)$u['id']; ?>">Make admin</a><?php endif; ?>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?> |
                    <a class="danger" href="users.php?delete=<?php echo (int)$u['id']; ?>" onclick="return confirm('Delete this user and their orders?');">Delete</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
