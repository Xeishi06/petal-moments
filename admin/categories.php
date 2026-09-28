<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_admin();

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
    $image = trim($_POST['image'] ?? '');
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
            $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name !== '' ? $name : 'category'));
            $filename = trim($base, '-') . '-' . time() . '.' . $ext;
            $destDir = __DIR__ . '/../uploads/categories';
            if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $destDir . '/' . $filename)) {
                $image = 'uploads/categories/' . $filename;
            } else {
                $uploadError = 'Could not save uploaded file.';
            }
        }
    }
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    if ($name === '') {
        flash('error', 'Category name is required.');
    } elseif ($uploadError !== null) {
        flash('error', $uploadError);
    } else {
        try {
            // Pre-check for duplicates to give a friendly message
            $dup = $pdo->prepare("SELECT id FROM categories WHERE (name = :n OR slug = :s) AND id != :id");
            $dup->execute([':n' => $name, ':s' => $slug, ':id' => $id]);
            if ($dup->fetch()) {
                throw new Exception('A category with that name already exists.');
            }
            if ($id > 0) {
                $pdo->prepare("UPDATE categories SET name=:n, slug=:s, description=:d, image=:img WHERE id=:id")->execute([':n'=>$name,':s'=>$slug,':d'=>$desc,':img'=>$image ?: null,':id'=>$id]);
                flash('success', 'Category updated.');
            } else {
                $pdo->prepare("INSERT INTO categories (name, slug, description, image) VALUES (:n,:s,:d,:img)")->execute([':n'=>$name,':s'=>$slug,':d'=>$desc,':img'=>$image ?: null]);
                flash('success', 'Category added.');
            }
            redirect('categories.php');
        } catch (Exception $e) {
            // 23000 = duplicate key or FK violation — show friendly message, stay on form
            if (strpos($e->getMessage(), 'Duplicate') !== false || ($e->getCode() == 23000)) {
                flash('error', 'That category name already exists. Use a different name.');
            } else {
                flash('error', 'Could not save category: ' . $e->getMessage());
            }
        }
    }
}

$active = 'categories';
$adminTitle = 'Categories | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

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
    <form method="post" action="categories.php" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="form" value="category">
        <input type="hidden" name="id" value="<?php echo $editing ? (int)$editing['id'] : 0; ?>">
        <label>Name</label>
        <input type="text" name="name" value="<?php echo e($editing['name'] ?? ''); ?>" required>
        <label>Image URL</label>
        <input type="text" name="image" value="<?php echo e($editing['image'] ?? ''); ?>" placeholder="https://... or leave blank">
        <div class="or-divider">OR</div>
        <label>Pick a picture from your computer</label>
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
        <?php if (!empty($editing['image'])): ?>
            <div class="img-preview"><img src="<?php echo e($editing['image']); ?>" alt="Category preview"><small>Current image</small></div>
        <?php endif; ?>
        <label>Description</label>
        <textarea name="description" rows="2"><?php echo e($editing['description'] ?? ''); ?></textarea>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save' : 'Add'; ?></button>
            <?php if ($editing): ?><a class="btn btn-light" href="categories.php">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-scroll">
    <table class="admin-table">
        <thead><tr><th>Name</th><th>Slug</th><th>Products</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td>
                    <div class="cell-with-thumb">
                        <?php if (!empty($c['image'])): ?>
                            <img src="<?php echo e($c['image']); ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                            <span class="thumb"><?php echo mb_strtoupper(mb_substr($c['name'], 0, 1)); ?></span>
                        <?php endif; ?>
                        <div>
                            <strong><?php echo e($c['name']); ?></strong>
                            <small>#<?php echo (int)$c['id']; ?></small>
                        </div>
                    </div>
                </td>
                <td><?php echo e($c['slug']); ?></td>
                <td><span class="chip chip-yes"><?php echo (int)$c['pcount']; ?></span></td>
                <td>
                    <div class="cell-actions">
                        <a class="btn btn-ghost btn-sm" href="categories.php?edit=<?php echo (int)$c['id']; ?>">Edit</a>
                        <a class="btn btn-danger btn-sm" href="categories.php?delete=<?php echo (int)$c['id']; ?>" onclick="return confirm('Delete this category?');">Delete</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
