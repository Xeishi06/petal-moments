<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

$orderId = (int)($_GET['order'] ?? 0);
$stmt = db()->prepare("
    SELECT * FROM orders WHERE id = :id AND user_id = :user
");
$stmt->execute([':id' => $orderId, ':user' => $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('shop.php');
}

$items = db()->prepare("
    SELECT oi.*, p.name, p.image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = :id
");
$items->execute([':id' => $orderId]);
$items = $items->fetchAll();

// Flowers in this order the customer hasn't reviewed yet → review prompt
$toReview = db()->prepare("
    SELECT DISTINCT oi.product_id, p.name, p.image
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    LEFT JOIN reviews r ON r.product_id = oi.product_id AND r.user_id = :u
    WHERE oi.order_id = :id AND r.id IS NULL
");
$toReview->execute([':id' => $orderId, ':u' => $_SESSION['user_id']]);
$toReview = array_map(function ($t) {
    return ['id' => (int)$t['product_id'], 'name' => $t['name'] ?? 'Arrangement', 'image' => $t['image'] ?? ''];
}, $toReview->fetchAll());
?>
<?php $pageTitle = 'Order Confirmed | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="thankyou-card">
            <span class="step-icon">✿</span>
            <h2>Thank you for your order!</h2>
            <p>Your order <strong>#<?php echo (int)$order['id']; ?></strong> has been placed and is now
            <strong>pending</strong>. We will prepare your flowers with care.</p>

            <table class="cart-table">
                <thead>
                    <tr><th></th><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><img class="order-thumb" src="<?php echo e($item['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=200&q=70'); ?>" alt=""></td>
                        <td><?php echo e($item['name'] ?? 'Arrangement'); ?><?php echo (!empty($item['variant_name']) && $item['variant_name'] !== 'Standard') ? ' (' . e($item['variant_name']) . ')' : ''; ?><?php echo !empty($item['dedication']) ? '<br><small>✎ ' . e($item['dedication']) . '</small>' : ''; ?></td>
                        <td><?php echo (int)$item['quantity']; ?></td>
                        <td>₱<?php echo number_format((float)$item['unit_price'], 2); ?></td>
                        <td>₱<?php echo number_format((float)$item['subtotal'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="right">Total</td><td>₱<?php echo number_format((float)$order['total_amount'], 2); ?></td></tr>
                </tfoot>
            </table>

            <?php if (($order['fulfillment'] ?? 'delivery') === 'pickup'): ?>
            <p><strong>🏃 Pick up at:</strong> 688 B. Manuel St., Montalban, Rizal — we'll message you when your flowers are ready.</p>
            <?php else: ?>
            <p><strong>Delivery to:</strong> <?php echo e($order['delivery_address'] ?? '—'); ?></p>
            <?php endif; ?>
            <p><strong>Payment:</strong> <?php echo e(strtoupper(str_replace('_', ' ', $order['payment_method']))); ?><?php echo ($order['payment_method'] === 'gcash' && !empty($order['payment_ref'])) ? ' • Ref: ' . e($order['payment_ref']) : ''; ?></p>

            <a class="btn btn-primary" href="shop.php">Continue Shopping</a>
            <a class="btn btn-dark" href="events.php">Plan an Event</a>
        </div>
    </div>
</section>

<?php if (!empty($toReview)): ?>
<div class="modal-overlay" id="reviewModal" aria-hidden="true">
    <div class="modal-card auth-modal-card" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
        <button type="button" class="modal-close" data-review-close aria-label="Close">✕</button>
        <div class="auth-modal-body">
            <span class="eyebrow">ENJOY YOUR FLOWERS?</span>
            <h2 id="reviewModalTitle">Rate <em id="reviewProductName"></em></h2>
            <div class="review-modal-photo"><img id="reviewProductImg" src="" alt=""></div>
            <div class="star-pick" id="reviewStars" role="radiogroup" aria-label="Star rating">
                <label><input type="radio" name="rr" value="1"><span>★</span></label>
                <label><input type="radio" name="rr" value="2"><span>★</span></label>
                <label><input type="radio" name="rr" value="3"><span>★</span></label>
                <label><input type="radio" name="rr" value="4"><span>★</span></label>
                <label><input type="radio" name="rr" value="5"><span>★</span></label>
            </div>
            <textarea id="reviewComment" rows="2" maxlength="1000" placeholder="Optional — how were the flowers?"></textarea>
            <div class="modal-message" id="reviewMsg" role="status"></div>
            <div class="auth-modal-actions">
                <button type="button" class="btn btn-light" id="reviewSkip">Skip</button>
                <button type="button" class="btn btn-primary" id="reviewSend">Submit ★</button>
            </div>
            <p class="modal-note">Totally optional — skip any time.</p>
        </div>
    </div>
</div>
<script>
(function () {
    var queue = <?php echo json_encode($toReview, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var csrf = <?php echo json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var modal = document.getElementById('reviewModal');
    if (!modal || !queue.length) return;
    var idx = 0, rating = 0;
    var nameEl = document.getElementById('reviewProductName');
    var imgEl = document.getElementById('reviewProductImg');
    var stars = document.getElementById('reviewStars');
    var commentEl = document.getElementById('reviewComment');
    var msgEl = document.getElementById('reviewMsg');
    var starSpans = Array.prototype.slice.call(stars.querySelectorAll('span'));
    function paint(n) {
        starSpans.forEach(function (sp, i) { sp.classList.toggle('lit', i < n); });
    }
    function open() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }
    function close() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }
    function show(i) {
        idx = i; rating = 0;
        nameEl.textContent = queue[i].name;
        imgEl.src = queue[i].image;
        imgEl.alt = queue[i].name;
        commentEl.value = '';
        msgEl.textContent = '';
        msgEl.className = 'modal-message';
        stars.querySelectorAll('input').forEach(function (r) { r.checked = false; });
        paint(0);
        open();
    }
    starSpans.forEach(function (sp, i) {
        sp.parentElement.addEventListener('mouseenter', function () { paint(i + 1); });
        sp.parentElement.addEventListener('click', function () {
            rating = i + 1;
            stars.querySelectorAll('input')[i].checked = true;
            paint(rating);
        });
    });
    stars.addEventListener('mouseleave', function () { paint(rating); });
    document.querySelectorAll('[data-review-close]').forEach(function (b) {
        b.addEventListener('click', close);
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    document.getElementById('reviewSkip').addEventListener('click', function () {
        if (idx + 1 < queue.length) { show(idx + 1); } else { close(); }
    });
    document.getElementById('reviewSend').addEventListener('click', async function () {
        if (!rating) {
            msgEl.textContent = 'Please tap a star rating first — or Skip.';
            msgEl.className = 'modal-message error';
            return;
        }
        var body = new FormData();
        body.append('product_id', queue[idx].id);
        body.append('rating', rating);
        body.append('comment', commentEl.value);
        body.append('csrf', csrf);
        try {
            var res = await fetch('review_submit.php', { method: 'POST', body: body });
            var json = await res.json();
            if (json.ok) {
                if (idx + 1 < queue.length) { show(idx + 1); }
                else {
                    msgEl.textContent = 'Thanks for your reviews! ♥';
                    msgEl.className = 'modal-message success';
                    setTimeout(close, 1200);
                }
            } else {
                msgEl.textContent = json.error || 'Something went wrong.';
                msgEl.className = 'modal-message error';
            }
        } catch (err) {
            msgEl.textContent = 'Network error. Please try again.';
            msgEl.className = 'modal-message error';
        }
    });
    // Gentle delay so the confetti moment lands first
    setTimeout(function () { show(0); }, 1200);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
