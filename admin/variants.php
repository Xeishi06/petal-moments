<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_admin();

$pdo = db();

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM variants WHERE id = :id")->execute([':id' => (int)$_GET['delete']]);
    flash('success', 'Variant deleted. Existing orders keep their saved variant name.');
    redirect('variants.php');
}

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE variants SET is_active = 1 - is_active WHERE id = :id")->execute([':id' => (int)$_GET['toggle']]);
    redirect('variants.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'variant') {
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $adjust = (float)str_replace(',', '', $_POST['price_adjust'] ?? 0);
    $needs  = isset($_POST['needs_message']) ? 1 : 0;

    if ($name === '') {
        flash('error', 'Variant name is required.');
    } elseif ($adjust < 0) {
        flash('error', 'Price adjustment cannot be negative.');
    } else {
        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE variants SET name=:n, price_adjust=:p, needs_message=:m WHERE id=:id")
                    ->execute([':n' => $name, ':p' => $adjust, ':m' => $needs, ':id' => $id]);
                flash('success', 'Variant updated. New orders will use the new price.');
            } else {
                $pdo->prepare("INSERT INTO variants (name, price_adjust, needs_message) VALUES (:n,:p,:m)")
                    ->execute([':n' => $name, ':p' => $adjust, ':m' => $needs]);
                flash('success', 'Variant added.');
            }
            redirect('variants.php');
        } catch (Exception $e) {
            flash('error', 'That variant name already exists.');
        }
    }
}

$active = 'variants';
$adminTitle = 'Variants | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$variants = $pdo->query("SELECT * FROM variants ORDER BY price_adjust ASC, id ASC")->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM variants WHERE id = :id");
    $st->execute([':id' => (int)$_GET['edit']]);
    $editing = $st->fetch();
}
?>

<div class="admin-card">
    <h1>Variants</h1>
    <p>Customization presets shown on every flower page (e.g. Standard, Deluxe). Price adds on top of the flower price. Past orders keep whatever was charged.</p>
</div>

<div class="admin-card">
    <h2><?php echo $editing ? 'Edit Variant' : 'Add Variant'; ?></h2>
    <form method="post" action="variants.php" class="admin-form">
        <input type="hidden" name="form" value="variant">
        <input type="hidden" name="id" value="<?php echo $editing ? (int)$editing['id'] : 0; ?>">
        <div class="form-row">
            <div>
                <label>Name</label>
                <input type="text" name="name" value="<?php echo e($editing['name'] ?? ''); ?>" placeholder="e.g. Happy Birthday!" required>
            </div>
            <div>
                <label>Price add-on (₱)</label>
                <input type="number" name="price_adjust" step="0.01" min="0" value="<?php echo e($editing['price_adjust'] ?? '0'); ?>" required>
            </div>
        </div>
        <label class="checkbox"><input type="checkbox" name="needs_message" <?php echo ($editing && $editing['needs_message']) ? 'checked' : ''; ?>> Requires a card message from the customer</label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save' : 'Add'; ?></button>
            <?php if ($editing): ?><a class="btn btn-light" href="variants.php">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-scroll">
    <table class="admin-table">
        <thead><tr><th>Name</th><th>Add-on</th><th>Card msg</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($variants as $v): ?>
            <tr>
                <td><strong><?php echo e($v['name']); ?></strong></td>
                <td><?php echo $v['price_adjust'] > 0 ? '+₱' . number_format((float)$v['price_adjust'], 2) : '—'; ?></td>
                <td><?php echo $v['needs_message'] ? 'Required' : 'Optional'; ?></td>
                <td><span class="chip <?php echo $v['is_active'] ? 'chip-yes' : 'chip-no'; ?>"><?php echo $v['is_active'] ? 'Active' : 'Hidden'; ?></span></td>
                <td>
                    <div class="cell-actions">
                        <a class="btn btn-ghost btn-sm" href="variants.php?edit=<?php echo (int)$v['id']; ?>">Edit</a>
                        <a class="btn btn-ghost btn-sm" href="variants.php?toggle=<?php echo (int)$v['id']; ?>"><?php echo $v['is_active'] ? 'Hide' : 'Show'; ?></a>
                        <a class="btn btn-danger btn-sm" href="variants.php?delete=<?php echo (int)$v['id']; ?>" onclick="return confirm('Delete this variant? Past orders are unaffected.');">Delete</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
