<?php
$active = 'products';
$adminTitle = 'Products | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = db();

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => (int)$_GET['delete']]);
    flash('success', 'Product deleted.');
    redirect('products.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'product') {
    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $category  = (int)($_POST['category_id'] ?? 0);
    $price     = (float)($_POST['price'] ?? 0);
    $stock     = (int)($_POST['stock'] ?? 0);
    $image     = trim($_POST['image'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $featured  = isset($_POST['is_featured']) ? 1 : 0;
    $slug      = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));

    if ($name === '' || $price <= 0) {
        flash('error', 'Product name and a valid price are required.');
    } else {
        if ($id > 0) {
            $pdo->prepare("UPDATE products SET category_id=:c, name=:n, slug=:s, description=:d, price=:p, image=:i, stock=:st, is_featured=:f WHERE id=:id")
                ->execute([':c'=>$category?:null, ':n'=>$name, ':s'=>$slug, ':d'=>$desc, ':p'=>$price, ':i'=>$image, ':st'=>$stock, ':f'=>$featured, ':id'=>$id]);
            flash('success', 'Product updated.');
        } else {
            $pdo->prepare("INSERT INTO products (category_id, name, slug, description, price, image, stock, is_featured) VALUES (:c,:n,:s,:d,:p,:i,:st,:f)")
                ->execute([':c'=>$category?:null, ':n'=>$name, ':s'=>$slug, ':d'=>$desc, ':p'=>$price, ':i'=>$image, ':st'=>$stock, ':f'=>$featured]);
            flash('success', 'Product added.');
        }
        redirect('products.php');
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$products = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    ORDER BY p.id DESC
")->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM products WHERE id=:id");
    $st->execute([':id'=>(int)$_GET['edit']]);
    $editing = $st->fetch();
}
?>

<div class="admin-card">
    <h1>Products</h1>
    <p>Manage your floral arrangements.</p>
</div>

<div class="admin-card">
    <h2><?php echo $editing ? 'Edit Product #' . (int)$editing['id'] : 'Add New Product'; ?></h2>
    <form method="post" action="products.php" class="admin-form">
        <input type="hidden" name="form" value="product">
        <input type="hidden" name="id" value="<?php echo $editing ? (int)$editing['id'] : 0; ?>">

        <div class="form-row">
            <div>
                <label>Name</label>
                <input type="text" name="name" value="<?php echo e($editing['name'] ?? ''); ?>" required>
            </div>
            <div>
                <label>Category</label>
                <select name="category_id">
                    <option value="0">-- None --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"
                            <?php echo ($editing && $editing['category_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo e($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label>Price (₱)</label>
        <input type="number" name="price" step="0.01" min="0" value="<?php echo e($editing['price'] ?? ''); ?>" required>

        <label>Stock</label>
        <input type="number" name="stock" min="0" value="<?php echo e($editing['stock'] ?? '0'); ?>">

        <label>Image URL</label>
        <input type="text" name="image" value="<?php echo e($editing['image'] ?? ''); ?>">

        <label>Description</label>
        <textarea name="description" rows="3"><?php echo e($editing['description'] ?? ''); ?></textarea>

        <label class="checkbox"><input type="checkbox" name="is_featured" <?php echo ($editing && $editing['is_featured']) ? 'checked' : ''; ?>> Featured</label>

        <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save Changes' : 'Add Product'; ?></button>
        <?php if ($editing): ?><a class="btn btn-light" href="products.php">Cancel</a><?php endif; ?>
    </form>
</div>

<div class="admin-card">
    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Featured</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?php echo (int)$p['id']; ?></td>
                <td><?php echo e($p['name']); ?></td>
                <td><?php echo e($p['category_name'] ?? '—'); ?></td>
                <td>₱<?php echo number_format((float)$p['price'], 2); ?></td>
                <td><?php echo (int)$p['stock']; ?></td>
                <td><?php echo $p['is_featured'] ? 'Yes' : 'No'; ?></td>
                <td>
                    <a href="products.php?edit=<?php echo (int)$p['id']; ?>">Edit</a> |
                    <a class="danger" href="products.php?delete=<?php echo (int)$p['id']; ?>"
                       onclick="return confirm('Delete this product?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
