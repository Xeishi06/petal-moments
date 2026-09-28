<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

$pdo = db();

if (isset($_GET['remove'])) {
    $pdo->prepare("DELETE FROM wishlist WHERE user_id = :u AND product_id = :p")
        ->execute([':u' => $_SESSION['user_id'], ':p' => (int)$_GET['remove']]);
    flash('success', 'Removed from favorites.');
    redirect('wishlist.php');
}

$items = $pdo->prepare("
    SELECT p.id, p.name, p.slug, p.price, p.image, c.name AS category_name
    FROM wishlist w
    JOIN products p ON p.id = w.product_id AND p.is_active = 1
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE w.user_id = :u
    ORDER BY w.created_at DESC
");
$items->execute([':u' => $_SESSION['user_id']]);
$favs = $items->fetchAll();
?>
<?php $pageTitle = 'My Favorites | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="section-heading centered">
            <span class="eyebrow">SAVED FOR LATER</span>
            <h2>My <em>favorites</em></h2>
            <p>Tap the heart again any time to remove one.</p>
        </div>

        <?php if (empty($favs)): ?>
            <p class="empty-state">No favorites yet. <a href="shop.php">Find flowers you love →</a></p>
        <?php else: ?>
        <div class="product-grid">
            <?php foreach ($favs as $p): ?>
            <article class="product-card">
                <div class="product-image">
                    <button type="button" class="fav-btn active" data-wishlist="<?php echo (int)$p['id']; ?>" aria-label="Remove from favorites" aria-pressed="true">♥</button>
                    <img src="<?php echo e($p['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=700&q=80'); ?>" alt="<?php echo e($p['name']); ?>">
                </div>
                <div class="product-details">
                    <div>
                        <h3><?php echo e($p['name']); ?></h3>
                        <p><?php echo e($p['category_name'] ?? 'For any occasion'); ?></p>
                    </div>
                    <strong>₱<?php echo number_format((float)$p['price'], 2); ?></strong>
                </div>
                <div class="product-card-actions">
                    <a class="btn btn-dark btn-small" href="product.php?id=<?php echo (int)$p['id']; ?>">View Details</a>
                    <a class="remove-link" href="wishlist.php?remove=<?php echo (int)$p['id']; ?>">Remove</a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
