<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'user_action') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
        redirect('users.php');
    }
    $id = (int)($_POST['user_id'] ?? 0);
    $do = $_POST['do'] ?? '';
    $target = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $target->execute([':id' => $id]);
    $target = $target->fetch();
    if (!$target) {
        flash('error', 'User not found.');
    } elseif ($id === (int)$_SESSION['user_id'] && in_array($do, ['toggle', 'role', 'delete'], true)) {
        flash('error', 'You cannot change your own account from here. Ask another admin.');
    } elseif ($do === 'toggle') {
        $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = :id")->execute([':id' => $id]);
        flash('success', 'User status updated.');
    } elseif ($do === 'role') {
        $pdo->prepare("UPDATE users SET role = IF(role = 'admin', 'customer', 'admin') WHERE id = :id")->execute([':id' => $id]);
        flash('success', 'User role updated.');
    } elseif ($do === 'delete') {
        $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
        flash('success', 'User deleted.');
    }
    redirect('users.php');
}

$active = 'users';
$adminTitle = 'Users | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$users = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) AS order_count
    FROM users u ORDER BY u.created_at DESC
")->fetchAll();
?>

<div class="admin-card"><h1>Users</h1><p>Manage customer and admin accounts.</p></div>

<div class="admin-card">
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
            <tr><th>User</th><th>Role</th><th>Orders</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <?php $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']); ?>
            <tr>
                <td>
                    <div class="cell-with-thumb">
                        <span class="avatar avatar-sm <?php echo $u['role'] === 'admin' ? '' : 'avatar-sage'; ?>"><?php echo e(mb_strtoupper(mb_substr(trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['email'], 0, 1))); ?></span>
                        <div>
                            <strong><?php echo e(trim($u['first_name'] . ' ' . $u['last_name'])); ?></strong>
                            <small><?php echo e($u['email']); ?><?php echo $isSelf ? ' • you' : ''; ?></small>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-<?php echo e($u['role']); ?>"><?php echo e($u['role']); ?></span></td>
                <td><?php echo (int)$u['order_count']; ?></td>
                <td><span class="chip <?php echo $u['is_active'] ? 'chip-in' : 'chip-out'; ?>"><?php echo $u['is_active'] ? 'Active' : 'Disabled'; ?></span></td>
                <td>
                    <?php if ($isSelf): ?>
                        <span class="link-soft">Your account</span>
                    <?php else: ?>
                    <div class="cell-actions">
                        <form method="post" action="users.php" style="display:inline;">
                            <input type="hidden" name="form" value="user_action">
                            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                            <input type="hidden" name="do" value="toggle">
                            <button type="submit" class="btn btn-ghost btn-sm"><?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?></button>
                        </form>
                        <form method="post" action="users.php" style="display:inline;" onsubmit="return confirm('Change this user\u2019s role?');">
                            <input type="hidden" name="form" value="user_action">
                            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                            <input type="hidden" name="do" value="role">
                            <button type="submit" class="link-soft" style="border:0;background:none;cursor:pointer;"><?php echo $u['role'] === 'admin' ? 'Make customer' : 'Make admin'; ?></button>
                        </form>
                        <form method="post" action="users.php" style="display:inline;" onsubmit="return confirm('Delete this user and their orders?');">
                            <input type="hidden" name="form" value="user_action">
                            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                            <input type="hidden" name="do" value="delete">
                            <button type="submit" class="link-danger" style="border:0;background:none;cursor:pointer;">Delete</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
