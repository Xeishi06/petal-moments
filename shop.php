<?php
require_once __DIR__ . '/config/functions.php';
start_session();

$categorySlug = $_GET['category'] ?? '';
$search       = trim($_GET['q'] ?? '');

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
$sql .= " ORDER BY p.id DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$allCats = db()->query("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY id")->fetchAll();
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
            <input type="text" name="q" placeholder="Search arrangements..." value="<?php echo e($search); ?>" aria-label="Search products">
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
                    <a class="heart-btn" href="product.php?id=<?php echo (int)$p['id']; ?>" aria-label="View arrangement">→</a>
                    <img src="<?php echo display_image(); ?>" alt="Arrangement">
                </div>
                <div class="product-details">
                    <div>
                        <h3><?php echo display_name($p); ?></h3>
                        <p>For any occasion</p>
                    </div>
                    <strong>₱<?php echo display_price(); ?></strong>
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
