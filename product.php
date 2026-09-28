<?php
require_once __DIR__ . '/config/functions.php';
start_session();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = :id AND p.is_active = 1
");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    redirect('shop.php');
}

$isSaved = false;
if (is_logged_in()) {
    $ws = db()->prepare("SELECT id FROM wishlist WHERE user_id = :u AND product_id = :p");
    $ws->execute([':u' => $_SESSION['user_id'], ':p' => $product['id']]);
    $isSaved = (bool)$ws->fetch();
}
?>
<?php $pageTitle = e($product['name'] ?? 'Arrangement') . ' | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <p class="back-link"><a href="shop.php">← Back to shop</a></p>

        <div class="product-detail-grid">
            <div class="product-detail-image">
                <img src="<?php echo e($product['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=900&q=80'); ?>" alt="<?php echo e($product['name'] ?? 'Arrangement'); ?>">
            </div>

            <div class="product-detail-info">
                <?php if ($product['category_name']): ?>
                    <span class="eyebrow"><?php echo e($product['category_name']); ?></span>
                <?php endif; ?>

                <h1><?php echo e($product['name'] ?? 'Arrangement'); ?></h1>
                <div class="price">₱<?php echo number_format((float)($product['price'] ?? 0), 2); ?></div>
                <p class="product-desc"><?php echo e($product['description'] ?: 'Handcrafted with fresh, carefully selected blooms.'); ?></p>

                <p class="stock-status">
                    <?php if ((int)$product['stock'] > 0): ?>
                        <span class="in">In stock (<?php echo (int)$product['stock']; ?> available)</span>
                    <?php else: ?>
                        <span class="out">Out of stock — check back soon</span>
                    <?php endif; ?>
                </p>

                <?php if (is_logged_in()): ?>
                <?php if ((int)$product['stock'] > 0): ?>
                <form class="add-to-cart-form" method="post" action="cart.php?action=add">
                    <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                    <label for="qty">Quantity</label>
                    <input type="number" id="qty" name="quantity" value="1" min="1" max="<?php echo max(1, min(10, (int)$product['stock'])); ?>" required>
                    <div class="buy-row">
                        <button type="submit" class="btn btn-dark">
                            Add to Cart
                        </button>
                        <button type="submit" name="buy_now" value="1" class="btn btn-primary">
                            Buy Now →
                        </button>
                    </div>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn-light btn-fav<?php echo $isSaved ? ' active' : ''; ?>" data-wishlist="<?php echo (int)$product['id']; ?>" aria-pressed="<?php echo $isSaved ? 'true' : 'false'; ?>">
                    ♥ <?php echo $isSaved ? 'Saved to Favorites' : 'Save to Favorites'; ?>
                </button>
                <?php else: ?>
                <p class="stock-status">
                    <span class="out">Please log in to order.</span>
                </p>
                <a class="btn btn-primary" href="login.php">Log in to Order</a>
                <button type="button" class="btn btn-light btn-fav" data-wishlist="<?php echo (int)$product['id']; ?>" aria-pressed="false">
                    ♥ Save to Favorites
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
