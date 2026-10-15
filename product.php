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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'review') {
    if (!is_logged_in()) {
        flash('error', 'Please log in to write a review.');
        redirect('login.php');
    }
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
        redirect('product.php?id=' . $product['id']);
    }
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = mb_substr(trim($_POST['comment'] ?? ''), 0, 1000);
    $bought = db()->prepare("
        SELECT 1 FROM order_items oi
        JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
        WHERE o.user_id = :u AND oi.product_id = :p LIMIT 1
    ");
    $bought->execute([':u' => $_SESSION['user_id'], ':p' => $product['id']]);
    if ($rating < 1 || $rating > 5) {
        flash('error', 'Please choose a star rating from 1 to 5.');
    } elseif (!$bought->fetch()) {
        flash('error', 'Only customers who bought this flower can review it.');
    } else {
        db()->prepare("
            INSERT INTO reviews (product_id, user_id, rating, comment)
            VALUES (:p, :u, :r, :c)
            ON DUPLICATE KEY UPDATE rating = :r, comment = :c, status = 'approved', created_at = CURRENT_TIMESTAMP
        ")->execute([':p' => $product['id'], ':u' => $_SESSION['user_id'], ':r' => $rating, ':c' => $comment !== '' ? $comment : null]);
        flash('success', 'Thanks for your review!');
    }
    redirect('product.php?id=' . $product['id']);
}

$revStats = db()->prepare("SELECT COUNT(*) AS n, COALESCE(AVG(rating), 0) AS avg FROM reviews WHERE product_id = :p AND status = 'approved'");
$revStats->execute([':p' => $product['id']]);
$revStats = $revStats->fetch();
$revList = db()->prepare("
    SELECT r.rating, r.comment, r.created_at, u.first_name
    FROM reviews r JOIN users u ON u.id = r.user_id
    WHERE r.product_id = :p AND r.status = 'approved'
    ORDER BY r.created_at DESC LIMIT 10
");
$revList->execute([':p' => $product['id']]);
$revList = $revList->fetchAll();
$canReview = false;
$myReview = null;
if (is_logged_in()) {
    $bought2 = db()->prepare("
        SELECT 1 FROM order_items oi
        JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
        WHERE o.user_id = :u AND oi.product_id = :p LIMIT 1
    ");
    $bought2->execute([':u' => $_SESSION['user_id'], ':p' => $product['id']]);
    $canReview = (bool)$bought2->fetch();
    if ($canReview) {
        $mr = db()->prepare("SELECT rating, comment FROM reviews WHERE product_id = :p AND user_id = :u");
        $mr->execute([':p' => $product['id'], ':u' => $_SESSION['user_id']]);
        $myReview = $mr->fetch();
    }
}

$allVariants = cart_variants();
$showVariants = empty($product['is_addon']) && ($product['category_slug'] ?? '') !== 'gifts' && count($allVariants) > 1;
$defaultVid = cart_default_variant();

// "Complete your gift" cross-sell: add-ons for this occasion (matching
// category, or occasion-free), excluding this product
$giftStmt = db()->prepare("
    SELECT p.id, p.name, p.price, p.sale_price, p.image
    FROM products p
    WHERE p.is_addon = 1 AND p.is_active = 1 AND p.id != :id
      AND (p.category_id IS NULL OR p.category_id = :cid)
    ORDER BY p.id LIMIT 4
");
$giftStmt->execute([':id' => $product['id'], ':cid' => $product['category_id']]);

// Related flowers: same occasion, excluding this product and add-ons
$relSql = "
    SELECT p.id, p.name, p.price, p.image, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1 AND p.is_addon = 0 AND p.id != :id AND ";
$relSql .= $product['category_id'] ? "p.category_id = :cid " : "p.category_id IS NULL ";
$relSql .= "ORDER BY p.id LIMIT 4";
$relStmt = db()->prepare($relSql);
$relStmt->bindValue(':id', $product['id'], PDO::PARAM_INT);
if ($product['category_id']) { $relStmt->bindValue(':cid', $product['category_id'], PDO::PARAM_INT); }
$relStmt->execute();
$related = $relStmt->fetchAll();
$relSold = product_sold_counts(array_column($related, 'id'));
$relRatings = product_ratings(array_column($related, 'id'));

// Saved set for the heart buttons below
$relSaved = [];
if (is_logged_in()) {
    $rs = db()->prepare("SELECT product_id FROM wishlist WHERE user_id = :u");
    $rs->execute([':u' => $_SESSION['user_id']]);
    $relSaved = array_map('intval', array_column($rs->fetchAll(), 'product_id'));
}
$gifts = $giftStmt->fetchAll();

// Category hopper above the product
$hopCats = db()->query("SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY id")->fetchAll();

// Gallery: main photo + extra pictures
$galStmt = db()->prepare("SELECT id, image FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC");
$galStmt->execute([':id' => $product['id']]);
$gallery = array_column($galStmt->fetchAll(), 'image');
$mainPhoto = $product['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=900&q=80';
$detailSold = product_sold_counts([$product['id']]);
$detailSold = $detailSold[(int)$product['id']] ?? 0;
?>
<?php $pageTitle = e($product['name'] ?? 'Arrangement') . ' | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <nav class="cat-hopper" aria-label="Browse categories">
            <a href="shop.php">All</a>
            <?php foreach ($hopCats as $hc): ?>
            <a href="shop.php?category=<?php echo e($hc['slug']); ?>"<?php echo ($product['category_slug'] ?? '') === $hc['slug'] ? ' class="current" aria-current="page"' : ''; ?>><?php echo e($hc['name']); ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="product-detail-grid<?php echo !empty($gifts) ? ' has-addons' : ''; ?>">
            <div class="product-detail-image">
                <img id="galleryMain" src="<?php echo e($mainPhoto); ?>" alt="<?php echo e($product['name'] ?? 'Arrangement'); ?>">
                <?php if (!empty($gallery)): ?>
                <div class="gallery-thumbs" role="listbox" aria-label="More pictures">
                    <button type="button" class="gallery-thumb picked" data-src="<?php echo e($mainPhoto); ?>" aria-label="Main photo">
                        <img src="<?php echo e($mainPhoto); ?>" alt="">
                    </button>
                    <?php foreach ($gallery as $gimg): ?>
                    <button type="button" class="gallery-thumb" data-src="<?php echo e($gimg); ?>" aria-label="View picture">
                        <img src="<?php echo e($gimg); ?>" alt="" loading="lazy">
                    </button>
                    <?php endforeach; ?>
                </div>
                <script>
                (function () {
                    var main = document.getElementById('galleryMain');
                    var thumbs = document.querySelectorAll('.gallery-thumb');
                    if (!main || !thumbs.length) return;
                    thumbs.forEach(function (t) {
                        t.addEventListener('click', function () {
                            if (main.getAttribute('src') === t.dataset.src) return;
                            main.style.opacity = '0';
                            setTimeout(function () {
                                main.src = t.dataset.src;
                                main.onload = function () { main.style.opacity = '1'; };
                                setTimeout(function () { main.style.opacity = '1'; }, 300);
                            }, 180);
                            thumbs.forEach(function (x) { x.classList.remove('picked'); });
                            t.classList.add('picked');
                        });
                    });
                })();
                </script>
                <?php endif; ?>
            </div>

            <div class="product-detail-info">
                <?php if ($product['category_name']): ?>
                    <span class="eyebrow"><?php echo e($product['category_name']); ?></span>
                <?php endif; ?>

                <h1><?php echo e($product['name'] ?? 'Arrangement'); ?></h1>
                <div class="price" id="detailPrice"><?php if (on_sale($product)): ?><s class="was">₱<?php echo number_format((float)$product['price'], 2); ?></s> <?php endif; ?>₱<?php echo number_format(eff_price($product), 2); ?><?php if (on_sale($product)): ?> <span class="sale-tag big">SALE</span><?php endif; ?></div>
                <p class="product-desc"><?php echo e($product['description'] ?: 'Handcrafted with fresh, carefully selected blooms.'); ?></p>

                <p class="stock-status">
                    <?php if ((int)$product['stock'] > 0): ?>
                        <span class="in">In stock (<?php echo (int)$product['stock']; ?> available)</span>
                    <?php else: ?>
                        <span class="out">Out of stock — check back soon</span>
                    <?php endif; ?>
                    <?php if ($detailSold > 0): ?><span class="sold-inline"> • 🔥 <?php echo $detailSold; ?> sold</span><?php endif; ?>
                    <?php if ((int)$revStats['n'] > 0): ?><span class="sold-inline"> • <?php echo stars_html($revStats['avg']); ?> <?php echo number_format((float)$revStats['avg'], 1); ?> (<?php echo (int)$revStats['n']; ?>)</span><?php endif; ?>
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
                    var base = <?php echo json_encode(eff_price($product)); ?>;
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

            <?php if (!empty($gifts)): ?>
            <aside class="addon-box addon-side">
                <div class="addon-head">
                    <strong>Complete your gift</strong>
                    <small>Small extras, big smiles</small>
                </div>
                <?php foreach ($gifts as $g): ?>
                <div class="addon-row">
                    <img src="<?php echo e($g['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=200&q=70'); ?>" alt="<?php echo e($g['name']); ?>">
                    <div class="addon-info">
                        <strong><?php echo e($g['name']); ?></strong>
                        <span><?php if (on_sale($g)): ?><s class="was">₱<?php echo number_format((float)$g['price'], 2); ?></s> <?php endif; ?>₱<?php echo number_format(eff_price($g), 2); ?></span>
                    </div>
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
                <?php endforeach; ?>
            </aside>
            <?php endif; ?>
        </div>

        <?php if (!empty($related)): ?>
        <div class="related-row">
            <div class="section-heading">
                <span class="eyebrow">KEEP EXPLORING</span>
                <h2>More <?php echo e($product['category_name'] ?? ''); ?> <em>arrangements</em></h2>
            </div>
            <div class="product-grid">
                <?php foreach ($related as $rp): ?>
                <article class="product-card">
                    <div class="product-image">
                        <a class="view-btn" href="product.php?id=<?php echo (int)$rp['id']; ?>" aria-label="View arrangement">View</a>
                        <button type="button" class="fav-btn<?php echo in_array((int)$rp['id'], $relSaved, true) ? ' active' : ''; ?>" data-wishlist="<?php echo (int)$rp['id']; ?>" aria-label="Save to favorites" aria-pressed="<?php echo in_array((int)$rp['id'], $relSaved, true) ? 'true' : 'false'; ?>">♥</button>
                        <img src="<?php echo e($rp['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=700&q=80'); ?>" alt="<?php echo e($rp['name']); ?>" loading="lazy">
                    </div>
                    <div class="product-details">
                        <div>
                            <h3><?php echo e($rp['name']); ?></h3>
                            <p><?php echo e($rp['category_name'] ?? 'For any occasion'); ?><?php echo ($relSold[(int)$rp['id']] ?? 0) > 0 ? ' • 🔥 ' . (int)$relSold[(int)$rp['id']] . ' sold' : ''; ?><?php $rri = $relRatings[(int)$rp['id']] ?? ['avg' => 0, 'n' => 0]; echo $rri['n'] > 0 ? ' • ★ ' . number_format($rri['avg'], 1) : ''; ?></p>
                        </div>
                        <strong><?php if (on_sale($rp)): ?><s class="was">₱<?php echo number_format((float)$rp['price'], 2); ?></s> <?php endif; ?>₱<?php echo number_format(eff_price($rp), 2); ?><?php if (on_sale($rp)): ?> <span class="sale-tag">SALE</span><?php endif; ?></strong>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="reviews-block">
            <div class="section-heading">
                <span class="eyebrow">CUSTOMER REVIEWS</span>
                <h2>What buyers <em>say</em></h2>
            </div>
            <?php if ((int)$revStats['n'] > 0): ?>
                <p class="review-summary"><?php echo stars_html($revStats['avg']); ?> <strong><?php echo number_format((float)$revStats['avg'], 1); ?> / 5</strong> from <?php echo (int)$revStats['n']; ?> verified <?php echo (int)$revStats['n'] === 1 ? 'buyer' : 'buyers'; ?></p>
                <div class="review-list">
                    <?php foreach ($revList as $rv): ?>
                    <div class="review-item">
                        <div class="review-head"><?php echo stars_html($rv['rating']); ?> <strong><?php echo e($rv['first_name']); ?></strong> <small>• verified buyer • <?php echo date('M j, Y', strtotime($rv['created_at'])); ?></small></div>
                        <?php if (!empty($rv['comment'])): ?><p><?php echo nl2br(e($rv['comment'])); ?></p><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">No reviews yet — be the first to review this flower after your order arrives.</p>
            <?php endif; ?>

            <?php if ($canReview): ?>
            <form method="post" action="product.php?id=<?php echo (int)$product['id']; ?>" class="review-form">
                <input type="hidden" name="form" value="review">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <h3><?php echo $myReview ? 'Update your review' : 'Write a review'; ?></h3>
                <label>Your rating *</label>
                <div class="star-pick" role="radiogroup" aria-label="Star rating" id="starPick">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                    <label><input type="radio" name="rating" value="<?php echo $s; ?>"<?php echo ($myReview && (int)$myReview['rating'] === $s) ? ' checked' : ''; ?> required><span>★</span></label>
                    <?php endfor; ?>
                </div>
                <script>
                (function () {
                    var wrap = document.getElementById('starPick');
                    if (!wrap) return;
                    var stars = Array.prototype.slice.call(wrap.querySelectorAll('span'));
                    function paint(n) {
                        stars.forEach(function (sp, idx) { sp.classList.toggle('lit', idx < n); });
                    }
                    paint(wrap.querySelector('input:checked') ? parseInt(wrap.querySelector('input:checked').value, 10) : 0);
                    wrap.querySelectorAll('label').forEach(function (lb, idx) {
                        lb.addEventListener('mouseenter', function () { paint(idx + 1); });
                        lb.addEventListener('click', function () { paint(idx + 1); });
                    });
                    wrap.addEventListener('mouseleave', function () {
                        var c = wrap.querySelector('input:checked');
                        paint(c ? parseInt(c.value, 10) : 0);
                    });
                })();
                </script>
                <label>Your review <small class="opt">(optional)</small></label>
                <textarea name="comment" rows="3" maxlength="1000" placeholder="How were the flowers?"><?php echo e($myReview['comment'] ?? ''); ?></textarea>
                <button type="submit" class="btn btn-dark"><?php echo $myReview ? 'Update Review' : 'Submit Review'; ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
