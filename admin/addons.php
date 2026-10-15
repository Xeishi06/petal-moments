<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_admin();

$pdo = db();

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM products WHERE id = :id AND is_addon = 1")->execute([':id' => (int)$_GET['delete']]);
    flash('success', 'Add-on deleted.');
    redirect('addons.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'addon') {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $sale  = trim($_POST['sale_price'] ?? '');
    $sale  = $sale === '' ? null : (float)$sale;
    $stock = (int)($_POST['stock'] ?? 0);
    $cat   = (int)($_POST['category_id'] ?? 0);
    $image = trim($_POST['image'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $slug  = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));

    $uploadError = null;
    if (!empty($_FILES['image_file']['name']) && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image_file']['tmp_name']);
        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
            $uploadError = 'Please pick a JPG, PNG, WEBP or GIF image.';
        } elseif ($_FILES['image_file']['size'] > 2 * 1024 * 1024) {
            $uploadError = 'Image must be under 2MB.';
        } else {
            $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name !== '' ? $name : 'addon'));
            $filename = trim($base, '-') . '-' . time() . '.' . $ext;
            $destDir = __DIR__ . '/../uploads/products';
            if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $destDir . '/' . $filename)) {
                $image = 'uploads/products/' . $filename;
            } else {
                $uploadError = 'Could not save uploaded file.';
            }
        }
    }

    if ($name === '' || $price <= 0) {
        flash('error', 'Add-on name and a valid price are required.');
    } elseif ($sale !== null && ($sale <= 0 || $sale >= $price)) {
        flash('error', 'Sale price must be below the regular price (or left blank).');
    } elseif ($uploadError !== null) {
        flash('error', $uploadError);
    } else {
        if ($id > 0) {
            $pdo->prepare("UPDATE products SET category_id=:c, name=:n, slug=:s, description=:d, price=:p, sale_price=:sp, image=:i, stock=:st WHERE id=:id AND is_addon = 1")
                ->execute([':c' => $cat ?: null, ':n' => $name, ':s' => $slug, ':d' => $desc, ':p' => $price, ':sp' => $sale, ':i' => $image, ':st' => $stock, ':id' => $id]);
            flash('success', 'Add-on updated. It shows in “Complete your gift” right away.');
        } else {
            $pdo->prepare("INSERT INTO products (category_id, name, slug, description, price, sale_price, image, stock, is_featured, is_addon) VALUES (:c,:n,:s,:d,:p,:sp,:i,:st,0,1)")
                ->execute([':c' => $cat ?: null, ':n' => $name, ':s' => $slug, ':d' => $desc, ':p' => $price, ':sp' => $sale, ':i' => $image, ':st' => $stock]);
            flash('success', 'Add-on added. It shows in “Complete your gift” right away.');
        }
        redirect('addons.php');
    }
}

$active = 'addons';
$adminTitle = 'Add-ons | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$allCats = $pdo->query("SELECT id, name FROM categories ORDER BY id")->fetchAll();
$addons = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_addon = 1 ORDER BY p.id DESC
")->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM products WHERE id = :id AND is_addon = 1");
    $st->execute([':id' => (int)$_GET['edit']]);
    $editing = $st->fetch();
}
?>

<div class="admin-card">
    <h1>Gift Add-ons</h1>
    <p>Chocolates, balloons, cakes & little extras shown in “Complete your gift” on every flower page. Separate from categories — deleting a category can never remove these.</p>
</div>

<div class="admin-card">
    <h2><?php echo $editing ? 'Edit Add-on' : 'Add Add-on'; ?></h2>
    <form method="post" action="addons.php" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="form" value="addon">
        <input type="hidden" name="id" value="<?php echo $editing ? (int)$editing['id'] : 0; ?>">
        <div class="form-row">
            <div>
                <label>Name</label>
                <input type="text" name="name" value="<?php echo e($editing['name'] ?? ''); ?>" placeholder="e.g. Chocolate Truffle Box" required>
            </div>
            <div>
                <label>Price (₱)</label>
                <input type="number" name="price" step="0.01" min="0" value="<?php echo e($editing['price'] ?? ''); ?>" required>
            </div>
        </div>
        <label>Sale price (₱) <small>(optional — must be below regular price)</small></label>
        <input type="number" name="sale_price" step="0.01" min="0" value="<?php echo e($editing['sale_price'] ?? ''); ?>" placeholder="Leave blank for no sale">
        <label>Stock</label>
        <input type="number" name="stock" min="0" value="<?php echo e($editing['stock'] ?? '50'); ?>">
        <label>Shows on <small class="opt">(which occasions)</small></label>
        <select name="category_id">
            <option value="0">All occasions</option>
            <?php foreach ($allCats as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>"
                    <?php echo ($editing && $editing['category_id'] == $c['id']) ? 'selected' : ''; ?>>
                    <?php echo e($c['name']); ?> only
                </option>
            <?php endforeach; ?>
        </select>
        <label>Image URL</label>
        <input type="text" name="image" value="<?php echo e($editing['image'] ?? ''); ?>" placeholder="https://... or leave blank">
        <div class="or-divider">OR</div>
        <label>Pick a picture from your computer</label>
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
        <?php if (!empty($editing['image'])): ?>
            <div class="img-preview"><img src="<?php echo e($editing['image']); ?>" alt="Add-on preview"><small>Current image</small></div>
        <?php endif; ?>
        <label>Description</label>
        <textarea name="description" rows="2" placeholder="Short description shown to customers..."><?php echo e($editing['description'] ?? ''); ?></textarea>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save Changes' : 'Add Add-on'; ?></button>
            <?php if ($editing): ?><a class="btn btn-light" href="addons.php">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
            <tr><th>Add-on</th><th>Shows on</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($addons)): ?>
            <tr><td colspan="5" class="table-empty">No add-ons yet. Add your first one above.</td></tr>
        <?php else: ?>
        <?php foreach ($addons as $a): ?>
            <tr>
                <td>
                    <div class="cell-with-thumb">
                        <?php if (!empty($a['image'])): ?>
                            <img src="<?php echo e($a['image']); ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                            <span class="thumb"><?php echo mb_strtoupper(mb_substr($a['name'], 0, 1)); ?></span>
                        <?php endif; ?>
                        <div>
                            <strong><?php echo e($a['name']); ?></strong>
                            <small>#<?php echo (int)$a['id']; ?></small>
                        </div>
                    </div>
                </td>
                <td><?php echo e($a['category_name'] ?? 'All occasions'); ?></td>
                <td>
                    <?php if (!empty($a['sale_price']) && (float)$a['sale_price'] > 0 && (float)$a['sale_price'] < (float)$a['price']): ?>
                        <s style="color:#999;">₱<?php echo number_format((float)$a['price'], 2); ?></s><br>
                        <strong style="color:#e04848;">₱<?php echo number_format((float)$a['sale_price'], 2); ?></strong>
                    <?php else: ?>
                        ₱<?php echo number_format((float)$a['price'], 2); ?>
                    <?php endif; ?>
                </td>
                <td><span class="chip <?php echo $a['stock'] > 0 ? 'chip-in' : 'chip-out'; ?>"><?php echo (int)$a['stock']; ?></span></td>
                <td>
                    <div class="cell-actions">
                        <a class="btn btn-ghost btn-sm" href="addons.php?edit=<?php echo (int)$a['id']; ?>">Edit</a>
                        <a class="btn btn-danger btn-sm" href="addons.php?delete=<?php echo (int)$a['id']; ?>"
                           onclick="return confirm('Delete this add-on? It will disappear from Complete your gift.');">Delete</a>
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
