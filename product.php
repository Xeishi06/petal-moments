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
?>
<?php $pageTitle = 'Arrangement | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <p class="back-link"><a href="shop.php">← Back to shop</a></p>

        <div class="product-detail-grid">
            <div class="product-detail-image">
                <img src="<?php echo display_image(); ?>" alt="Arrangement">
            </div>

            <div class="product-detail-info">
                <?php if ($product['category_name']): ?>
                    <span class="eyebrow"><?php echo e($product['category_name']); ?></span>
                <?php endif; ?>

                <h1><?php echo display_name($product); ?></h1>
                <div class="price">₱<?php echo display_price(); ?></div>
                <p class="product-desc">For any occasion.</p>

                <p class="stock-status">
                    <span>Availability checked at checkout</span>
                </p>

                <?php if (is_logged_in()): ?>
                <form class="add-to-cart-form" method="post" action="cart.php?action=add">
                    <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                    <label for="qty">Quantity</label>
                    <input type="number" id="qty" name="quantity" value="1" min="1" max="10" required>
                    <button type="submit" class="btn btn-primary">
                        Add to Cart
                    </button>
                </form>
                <?php else: ?>
                <p class="stock-status">
                    <span class="out">Please log in to order.</span>
                </p>
                <a class="btn btn-primary" href="login.php">Log in to Order</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
