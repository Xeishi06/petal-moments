<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_admin();

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
    $isAddon   = isset($_POST['is_addon']) ? 1 : 0;
    $slug      = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));

    // File upload takes precedence over URL if a file was picked
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
            $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name !== '' ? $name : 'product'));
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
        flash('error', 'Product name and a valid price are required.');
    } elseif ($uploadError !== null) {
        flash('error', $uploadError);
    } else {
        if ($id > 0) {
            $pdo->prepare("UPDATE products SET category_id=:c, name=:n, slug=:s, description=:d, price=:p, image=:i, stock=:st, is_featured=:f, is_addon=:a WHERE id=:id")
                ->execute([':c'=>$category?:null, ':n'=>$name, ':s'=>$slug, ':d'=>$desc, ':p'=>$price, ':i'=>$image, ':st'=>$stock, ':f'=>$featured, ':a'=>$isAddon, ':id'=>$id]);
            flash('success', 'Product updated.');
        } else {
            $pdo->prepare("INSERT INTO products (category_id, name, slug, description, price, image, stock, is_featured, is_addon) VALUES (:c,:n,:s,:d,:p,:i,:st,:f,:a)")
                ->execute([':c'=>$category?:null, ':n'=>$name, ':s'=>$slug, ':d'=>$desc, ':p'=>$price, ':i'=>$image, ':st'=>$stock, ':f'=>$featured, ':a'=>$isAddon]);
            flash('success', 'Product added.');
        }
        redirect('products.php');
    }
}

if (isset($_GET['delimg'])) {
    $pdo->prepare("DELETE FROM product_images WHERE id = :id")->execute([':id' => (int)$_GET['delimg']]);
    flash('success', 'Gallery picture removed.');
    redirect('products.php' . (isset($_GET['edit']) ? '?edit=' . (int)$_GET['edit'] : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'gallery') {
    $gid = (int)($_POST['id'] ?? 0);
    $gurl = trim($_POST['gallery_url'] ?? '');
    if ($gid > 0) {
        if (!empty($_FILES['gallery_file']['name']) && ($_FILES['gallery_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
            $ext = strtolower(pathinfo($_FILES['gallery_file']['name'], PATHINFO_EXTENSION));
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['gallery_file']['tmp_name']);
            if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
                flash('error', 'Please pick a JPG, PNG, WEBP or GIF image.');
                redirect('products.php?edit=' . $gid);
            } elseif ($_FILES['gallery_file']['size'] > 2 * 1024 * 1024) {
                flash('error', 'Image must be under 2MB.');
                redirect('products.php?edit=' . $gid);
            } else {
                $filename = 'gallery-' . $gid . '-' . time() . '.' . $ext;
                $destDir = __DIR__ . '/../uploads/products';
                if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
                if (move_uploaded_file($_FILES['gallery_file']['tmp_name'], $destDir . '/' . $filename)) {
                    $gurl = 'uploads/products/' . $filename;
                } else {
                    flash('error', 'Could not save uploaded file.');
                    redirect('products.php?edit=' . $gid);
                }
            }
        }
        if ($gurl === '') {
            flash('error', 'Paste an image URL or pick a file.');
        } else {
            $ord = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_images WHERE product_id = :id");
            $ord->execute([':id' => $gid]);
            $pdo->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (:pid, :img, :ord)")
                ->execute([':pid' => $gid, ':img' => $gurl, ':ord' => (int)$ord->fetchColumn()]);
            flash('success', 'Gallery picture added.');
        }
    }
    redirect('products.php' . ($gid > 0 ? '?edit=' . $gid : ''));
}

$active = 'products';
$adminTitle = 'Products | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$typeFilter = $_GET['type'] ?? 'all';
if (!in_array($typeFilter, ['all', 'flowers', 'addons'], true)) { $typeFilter = 'all'; }
$typeWhere = $typeFilter === 'flowers' ? 'WHERE p.is_addon = 0' : ($typeFilter === 'addons' ? 'WHERE p.is_addon = 1' : '');
$products = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    $typeWhere
    ORDER BY p.id DESC
")->fetchAll();

$editing = null;
$galleryEdit = [];
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM products WHERE id=:id");
    $st->execute([':id'=>(int)$_GET['edit']]);
    $editing = $st->fetch();
    if ($editing) {
        $gs = $pdo->prepare("SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC");
        $gs->execute([':id' => (int)$editing['id']]);
        $galleryEdit = $gs->fetchAll();
    }
}

// All featured (active) products rotate in the homepage hero slideshow
$heroIds = array_map('intval', array_column(
    $pdo->query("SELECT id FROM products WHERE is_featured = 1 AND is_active = 1 ORDER BY id")->fetchAll(),
    'id'
));
?>

<div class="admin-card">
    <h1>Products</h1>
    <p>Manage your floral arrangements. Tip: tick <strong>Featured</strong> to put a product in homepage <strong>Best Sellers + hero slideshow</strong>.</p>
</div>

<div class="admin-card">
    <h2><?php echo $editing ? 'Edit Product #' . (int)$editing['id'] : 'Add New Product'; ?></h2>
    <form method="post" action="products.php" class="admin-form" enctype="multipart/form-data">
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
        <input type="text" name="image" value="<?php echo e($editing['image'] ?? ''); ?>" placeholder="https://... or leave blank">
        <div class="or-divider">OR</div>
        <label>Pick a picture from your computer</label>
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
        <?php if (!empty($editing['image'])): ?>
            <div class="img-preview"><img src="<?php echo e($editing['image']); ?>" alt="Product preview"><small>Current image</small></div>
        <?php endif; ?>

        <?php if ($editing): ?>
        <label>More pictures (shown as clickable thumbnails)</label>
        <?php if (!empty($galleryEdit)): ?>
            <div class="gallery-manage">
                <?php foreach ($galleryEdit as $gi): ?>
                <div class="gallery-manage-item">
                    <img src="<?php echo e($gi['image']); ?>" alt="">
                    <a href="products.php?edit=<?php echo (int)$editing['id']; ?>&delimg=<?php echo (int)$gi['id']; ?>"
                       onclick="return confirm('Remove this picture?');" aria-label="Remove picture">✕</a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-note">No extra pictures yet.</p>
        <?php endif; ?>
        <?php endif; ?>

        <label>Description</label>
        <textarea name="description" rows="3"><?php echo e($editing['description'] ?? ''); ?></textarea>

        <label class="checkbox"><input type="checkbox" name="is_featured" <?php echo ($editing && $editing['is_featured']) ? 'checked' : ''; ?>> Featured — shows in Best Sellers & hero slideshow</label>
        <label class="checkbox"><input type="checkbox" name="is_addon" <?php echo ($editing && !empty($editing['is_addon'])) ? 'checked' : ''; ?>> Gift add-on — shows in “Complete your gift” (independent of category)</label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save Changes' : 'Add Product'; ?></button>
            <?php if ($editing): ?><a class="btn btn-light" href="products.php">Cancel</a><?php endif; ?>
        </div>
    </form>
    <?php if ($editing): ?>
    <form method="post" action="products.php?edit=<?php echo (int)$editing['id']; ?>" class="admin-form gallery-add" enctype="multipart/form-data">
        <input type="hidden" name="form" value="gallery">
        <input type="hidden" name="id" value="<?php echo (int)$editing['id']; ?>">
        <label>Add another picture</label>
        <input type="text" name="gallery_url" placeholder="https://...">
        <div class="or-divider">OR</div>
        <input type="file" name="gallery_file" accept="image/jpeg,image/png,image/webp,image/gif">
        <div class="form-actions">
            <button type="submit" class="btn btn-dark">Add Picture</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="admin-card">
    <div class="category-tabs" style="margin-bottom:0;">
        <a href="products.php" class="<?php echo $typeFilter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="products.php?type=flowers" class="<?php echo $typeFilter === 'flowers' ? 'active' : ''; ?>">Flowers</a>
        <a href="products.php?type=addons" class="<?php echo $typeFilter === 'addons' ? 'active' : ''; ?>">Gift Add-ons</a>
    </div>
    <div class="table-scroll" style="margin-top:16px;">
    <table class="admin-table">
        <thead>
            <tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Type</th><th>Featured</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td>
                    <div class="cell-with-thumb">
                        <?php if (!empty($p['image'])): ?>
                            <img src="<?php echo e($p['image']); ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                            <span class="thumb"><?php echo mb_strtoupper(mb_substr($p['name'], 0, 1)); ?></span>
                        <?php endif; ?>
                        <div>
                            <strong><?php echo e($p['name']); ?></strong>
                            <small>#<?php echo (int)$p['id']; ?></small>
                        </div>
                    </div>
                </td>
                <td><?php echo e($p['category_name'] ?? '—'); ?></td>
                <td>₱<?php echo number_format((float)$p['price'], 2); ?></td>
                <td><span class="chip <?php echo $p['stock'] > 0 ? 'chip-in' : 'chip-out'; ?>"><?php echo (int)$p['stock']; ?> <?php echo $p['stock'] == 1 ? 'item' : 'items'; ?></span></td>
                <td><?php echo !empty($p['is_addon']) ? '<span class="chip chip-yes">Add-on</span>' : '<span class="chip chip-no">Flower</span>'; ?></td>
                <td>
                    <?php if (in_array((int)$p['id'], $heroIds, true)): ?>
                        <span class="chip chip-yes">★ Slideshow</span>
                    <?php elseif ($p['is_featured']): ?>
                        <span class="chip chip-yes">Bestseller</span>
                    <?php else: ?>
                        <span class="chip chip-no">No</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="cell-actions">
                        <a class="btn btn-ghost btn-sm" href="products.php?edit=<?php echo (int)$p['id']; ?>">Edit</a>
                        <a class="btn btn-danger btn-sm" href="products.php?delete=<?php echo (int)$p['id']; ?>"
                           onclick="return confirm('Delete this product?');">Delete</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
