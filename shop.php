<?php
require_once __DIR__ . '/config/functions.php';
start_session();

$categorySlug = $_GET['category'] ?? '';
$search       = trim($_GET['q'] ?? '');
$sort         = $_GET['sort'] ?? 'newest';
if (!in_array($sort, ['newest', 'price_asc', 'price_desc'], true)) { $sort = 'newest'; }

$sql   = "SELECT p.*, c.name AS category_name
          FROM products p
          LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.is_active = 1";
$params = [];

if ($categorySlug !== '') {
    $sql .= " AND c.slug = :slug";
    $params[':slug'] = $categorySlug;
}
if ($search !== '') {
    $sql .= " AND (p.name LIKE :q OR p.description LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
$sql .= $sort === 'price_asc' ? " ORDER BY p.price ASC" : ($sort === 'price_desc' ? " ORDER BY p.price DESC" : " ORDER BY p.id DESC");

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$allCats = db()->query("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY id")->fetchAll();
$savedIds = [];
if (is_logged_in()) {
    $savedIds = db()->prepare("SELECT product_id FROM wishlist WHERE user_id = :u");
    $savedIds->execute([':u' => $_SESSION['user_id']]);
    $savedIds = array_map('intval', array_column($savedIds->fetchAll(), 'product_id'));
}
$activeCatName = '';
foreach ($allCats as $c) { if ($c['slug'] === $categorySlug) { $activeCatName = $c['name']; break; } }
?>
<?php $pageTitle = 'Shop | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">

        <div class="section-heading centered">
            <span class="eyebrow">OUR COLLECTION</span>
            <h2><?php echo $activeCatName !== '' ? e($activeCatName) : 'All <em>arrangements</em>'; ?></h2>
            <p>Browse our flowers and find the one that feels just right.</p>
        </div>

        <form method="get" action="shop.php" class="shop-filter">
            <?php if ($categorySlug !== ''): ?><input type="hidden" name="category" value="<?php echo e($categorySlug); ?>"><?php endif; ?>
            <input type="text" name="q" placeholder="Search arrangements..." value="<?php echo e($search); ?>" aria-label="Search products">
            <select name="sort" aria-label="Sort products" onchange="this.form.submit()">
                <option value="newest"<?php echo $sort === 'newest' ? ' selected' : ''; ?>>Newest</option>
                <option value="price_asc"<?php echo $sort === 'price_asc' ? ' selected' : ''; ?>>Price: Low to High</option>
                <option value="price_desc"<?php echo $sort === 'price_desc' ? ' selected' : ''; ?>>Price: High to Low</option>
            </select>
            <button class="btn btn-primary btn-small" type="submit">Search</button>
        </form>

        <div class="category-tabs">
            <a href="shop.php" class="<?php echo $categorySlug === '' ? 'active' : ''; ?>">All</a>
            <?php foreach ($allCats as $c): ?>
                <a href="shop.php?category=<?php echo e($c['slug']); ?>"
                   class="<?php echo $categorySlug === $c['slug'] ? 'active' : ''; ?>"><?php echo e($c['name']); ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (count($products) > 0): ?>
        <div class="product-grid">
            <?php foreach ($products as $p): ?>
            <article class="product-card">
                <div class="product-image">
                    <a class="view-btn" href="product.php?id=<?php echo (int)$p['id']; ?>" aria-label="View arrangement">View</a>
                    <button type="button" class="fav-btn<?php echo in_array((int)$p['id'], $savedIds, true) ? ' active' : ''; ?>" data-wishlist="<?php echo (int)$p['id']; ?>" aria-label="Save to favorites" aria-pressed="<?php echo in_array((int)$p['id'], $savedIds, true) ? 'true' : 'false'; ?>">♥</button>
                    <img src="<?php echo e($p['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=700&q=80'); ?>" alt="<?php echo e($p['name'] ?? 'Arrangement'); ?>">
                </div>
                <div class="product-details">
                    <div>
                        <h3><?php echo e($p['name'] ?? 'Arrangement'); ?></h3>
                        <p><?php echo e($p['category_name'] ?? 'For any occasion'); ?></p>
                    </div>
                    <strong>₱<?php echo number_format((float)($p['price'] ?? 0), 2); ?></strong>
                </div>
                <div class="product-card-actions">
                    <a class="btn btn-dark btn-small" href="product.php?id=<?php echo (int)$p['id']; ?>">View Details</a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="empty-state">No arrangements found. Try a different search.</p>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
