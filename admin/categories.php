<?php
$active = 'categories';
$adminTitle = 'Categories | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = db();

if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute([':id'=>(int)$_GET['delete']]);
        flash('success', 'Category deleted.');
    } catch (Exception $e) {
        flash('error', 'Could not delete category: ' . $e->getMessage());
    }
    redirect('categories.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'category') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    if ($name === '') {
        flash('error', 'Category name is required.');
    } else {
        if ($id > 0) {
            $pdo->prepare("UPDATE categories SET name=:n, slug=:s, description=:d WHERE id=:id")->execute([':n'=>$name,':s'=>$slug,':d'=>$desc,':id'=>$id]);
            flash('success', 'Category updated.');
        } else {
            $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (:n,:s,:d)")->execute([':n'=>$name,':s'=>$slug,':d'=>$desc]);
            flash('success', 'Category added.');
        }
        redirect('categories.php');
    }
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS pcount FROM categories c ORDER BY c.id")->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM categories WHERE id=:id"); $st->execute([':id'=>(int)$_GET['edit']]);
    $editing = $st->fetch();
}
?>

<div class="admin-card"><h1>Categories</h1><p>Organize your products by occasion.</p></div>

<div class="admin-card">
    <h2><?php echo $editing ? 'Edit Category' : 'Add Category'; ?></h2>
    <form method="post" action="categories.php" class="admin-form">
        <input type="hidden" name="form" value="category">
        <input type="hidden" name="id" value="<?php echo $editing ? (int)$editing['id'] : 0; ?>">
        <label>Name</label>
        <input type="text" name="name" value="<?php echo e($editing['name'] ?? ''); ?>" required>
        <label>Description</label>
        <textarea name="description" rows="2"><?php echo e($editing['description'] ?? ''); ?></textarea>
        <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save' : 'Add'; ?></button>
        <?php if ($editing): ?><a class="btn btn-light" href="categories.php">Cancel</a><?php endif; ?>
    </form>
</div>

<div class="admin-card">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Products</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><?php echo (int)$c['id']; ?></td>
                <td><?php echo e($c['name']); ?></td>
                <td><?php echo e($c['slug']); ?></td>
                <td><?php echo (int)$c['pcount']; ?></td>
                <td>
                    <a href="categories.php?edit=<?php echo (int)$c['id']; ?>">Edit</a> |
                    <a class="danger" href="categories.php?delete=<?php echo (int)$c['id']; ?>" onclick="return confirm('Delete this category?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
