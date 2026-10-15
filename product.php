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

$allVariants = cart_variants();
$showVariants = ($product['category_slug'] ?? '') !== 'gifts' && count($allVariants) > 1;
$defaultVid = cart_default_variant();

// "Complete your gift" cross-sell: gift add-ons, excluding this product
$giftStmt = db()->prepare("
    SELECT p.id, p.name, p.price, p.image
    FROM products p
    JOIN categories c ON c.id = p.category_id AND c.slug = 'gifts'
    WHERE p.is_active = 1 AND p.id != :id
    ORDER BY p.id LIMIT 4
");
$giftStmt->execute([':id' => $product['id']]);
$gifts = $giftStmt->fetchAll();
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
                <div class="price" id="detailPrice">₱<?php echo number_format((float)($product['price'] ?? 0), 2); ?></div>
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
                <form class="add-to-cart-form" method="post" action="cart.php?action=add" id="detailForm">
                    <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                    <?php if ($showVariants): ?>
                    <label>Make it yours</label>
                    <div class="variant-row" role="radiogroup" aria-label="Choose a variant">
                        <?php foreach ($allVariants as $vid => $v): ?>
                        <label class="variant-pill<?php echo $vid === $defaultVid ? ' picked' : ''; ?>">
                            <input type="radio" name="variant_id" value="<?php echo (int)$vid; ?>"<?php echo $vid === $defaultVid ? ' checked' : ''; ?> data-adjust="<?php echo number_format((float)$v['adjust'], 2, '.', ''); ?>" data-needsmsg="<?php echo $v['needs_message'] ? '1' : '0'; ?>">
                            <span><?php echo e($v['name']); ?><?php echo $v['adjust'] > 0 ? ' +₱' . number_format((float)$v['adjust'], 0) : ''; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <label for="dedication">Card message <small class="opt">(optional)</small></label>
                    <input type="text" id="dedication" name="dedication" maxlength="255" placeholder="e.g. Happy Birthday, Mom! ❤" class="dedication-input">
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
                <script>
                (function () {
                    var form = document.getElementById('detailForm');
                    if (!form) return;
                    var base = <?php echo json_encode((float)($product['price'] ?? 0)); ?>;
                    var priceEl = document.getElementById('detailPrice');
                    var dedInput = document.getElementById('dedication');
                    function peso(n) {
                        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                    form.querySelectorAll('input[name="variant_id"]').forEach(function (r) {
                        r.addEventListener('change', function () {
                            form.querySelectorAll('.variant-pill').forEach(function (p) { p.classList.remove('picked'); });
                            r.closest('.variant-pill').classList.add('picked');
                            priceEl.textContent = peso(base + parseFloat(r.dataset.adjust || 0));
                            if (dedInput && r.dataset.needsmsg === '1') {
                                dedInput.setAttribute('required', 'required');
                                dedInput.setAttribute('placeholder', 'Write the message for your card *');
                                dedInput.focus();
                            } else if (dedInput) {
                                dedInput.removeAttribute('required');
                            }
                        });
                    });
                })();
                </script>
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

        <?php if (!empty($gifts)): ?>
        <div class="cross-sell">
            <div class="section-heading">
                <span class="eyebrow">COMPLETE YOUR GIFT</span>
                <h2>Add a little <em>extra</em></h2>
            </div>
            <div class="product-grid cross-grid">
                <?php foreach ($gifts as $g): ?>
                <article class="product-card">
                    <div class="product-image">
                        <img src="<?php echo e($g['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=700&q=80'); ?>" alt="<?php echo e($g['name']); ?>">
                    </div>
                    <div class="product-details">
                        <div>
                            <h3><?php echo e($g['name']); ?></h3>
                        </div>
                        <strong>₱<?php echo number_format((float)$g['price'], 2); ?></strong>
                    </div>
                    <div class="product-card-actions">
                        <?php if (is_logged_in()): ?>
                        <form method="post" action="cart.php?action=add">
                            <input type="hidden" name="product_id" value="<?php echo (int)$g['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="btn btn-dark btn-small">Add +</button>
                        </form>
                        <?php else: ?>
                        <a class="btn btn-dark btn-small" href="product.php?id=<?php echo (int)$g['id']; ?>">View</a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
