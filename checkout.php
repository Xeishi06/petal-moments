<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Shop GCash account — REPLACE with your real number before going live
$shopGcash = '0917 123 4567 (Petal Moments)';

// Buy Now mode: check out ONLY the buy-now item, cart stays saved
$isBuyNow = (($_GET['mode'] ?? '') === 'buynow') && !empty($_SESSION['buy_now']);
$source = $isBuyNow ? $_SESSION['buy_now'] : $_SESSION['cart'];
$storeKey = $isBuyNow ? 'buy_now' : 'cart';

$cartItems = [];
$cartTotal = 0.0;
if (!empty($source)) {
    $ids = array_keys($source);
    $in  = implode(',', array_map('intval', $ids));
    $found = [];
    foreach (db()->query("SELECT id, name, price, stock FROM products WHERE id IN ($in) AND is_active = 1")->fetchAll() as $r) {
        $found[(int)$r['id']] = true;
        $qty = max(1, (int)$source[$r['id']]);
        if ((int)$r['stock'] > 0) { $qty = min($qty, (int)$r['stock']); }
        $_SESSION[$storeKey][$r['id']] = $qty;
        $subtotal = (float)$r['price'] * $qty;
        $cartTotal += $subtotal;
        $cartItems[] = array_merge($r, ['quantity' => $qty, 'subtotal' => $subtotal]);
    }
    // Drop items that were deactivated or deleted since being added
    foreach (array_keys($source) as $sid) {
        if (!isset($found[(int)$sid])) { unset($_SESSION[$storeKey][$sid]); }
    }
    if (count($found) < count($source)) {
        flash('error', 'Some items are no longer available and were removed. Please review your order.');
        redirect($isBuyNow ? 'shop.php' : 'cart.php');
    }
}

if (empty($cartItems)) {
    flash('error', $isBuyNow ? 'This item is no longer available.' : 'Your cart is empty.');
    redirect($isBuyNow ? 'shop.php' : 'cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try placing your order again.');
        redirect('checkout.php' . ($isBuyNow ? '?mode=buynow' : ''));
    }
    $fulfillment     = ($_POST['fulfillment'] ?? 'delivery') === 'pickup' ? 'pickup' : 'delivery';
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $deliveryDate    = trim($_POST['delivery_date'] ?? '');
    $deliveryNotes   = trim($_POST['delivery_notes'] ?? '');
    $paymentMethod   = $_POST['payment_method'] ?? 'cash_on_delivery';
    $paymentRef      = trim($_POST['payment_ref'] ?? '');

    $payOptions = $fulfillment === 'pickup'
        ? ['pay_on_pickup' => 'Pay on pickup', 'gcash' => 'GCash']
        : ['cash_on_delivery' => 'Cash on Delivery', 'gcash' => 'GCash'];

    $errors = [];
    if (!isset($payOptions[$paymentMethod])) {
        $errors[] = $fulfillment === 'pickup'
            ? 'Please choose Pay on pickup or GCash.'
            : 'Please choose Cash on Delivery or GCash.';
    }
    if ($paymentMethod === 'gcash') {
        if ($paymentRef === '') {
            $errors[] = 'Please enter your GCash reference number so we can verify your payment.';
        } elseif (!preg_match('/^[0-9]{10,13}$/', preg_replace('/\s+/', '', $paymentRef))) {
            $errors[] = 'GCash reference number should be the 10–13 digit number from your receipt.';
        } elseif (mb_strlen($paymentRef) > 100) {
            $errors[] = 'GCash reference number is too long.';
        }
    }
    if ($fulfillment === 'delivery') {
        if ($deliveryAddress === '') {
            $errors[] = 'Delivery address is required.';
        } elseif (mb_strlen($deliveryAddress) < 5) {
            $errors[] = 'Delivery address looks too short. Please enter the full address.';
        } elseif (mb_strlen($deliveryAddress) > 255) {
            $errors[] = 'Delivery address must be under 255 characters.';
        }
    }
    if ($deliveryDate === '') {
        $errors[] = 'Please choose a delivery date.';
    } else {
        $minDate = date('Y-m-d', strtotime('+1 day'));
        $d = DateTime::createFromFormat('Y-m-d', $deliveryDate);
        if (!$d || $d->format('Y-m-d') !== $deliveryDate) {
            $errors[] = 'Please choose a valid delivery date.';
        } elseif ($deliveryDate < $minDate) {
            $errors[] = 'Same-day orders are not available. Please choose tomorrow or later.';
        }
    }
    if (mb_strlen($deliveryNotes) > 2000) {
        $errors[] = 'Delivery notes must be under 2000 characters.';
    }
    if ($cartTotal <= 0) {
        $errors[] = 'Your order total is invalid. Please review your cart.';
    }

    if (!empty($errors)) {
        foreach ($errors as $msg) { flash('error', $msg); }
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Re-check stock inside the transaction to prevent overselling
            $check = $pdo->prepare("SELECT id, price, stock FROM products WHERE id = :id FOR UPDATE");
            $lines = [];
            $total = 0.0;
            foreach ($cartItems as $item) {
                $check->execute([':id' => $item['id']]);
                $live = $check->fetch();
                if (!$live || (int)$live['stock'] < (int)$item['quantity']) {
                    throw new Exception('Sorry, "' . $item['name'] . '" only has ' . (int)($live['stock'] ?? 0) . ' left in stock.');
                }
                $lineTotal = (float)$live['price'] * (int)$item['quantity'];
                $total += $lineTotal;
                $lines[] = ['id' => $item['id'], 'qty' => (int)$item['quantity'], 'price' => (float)$live['price'], 'subtotal' => $lineTotal];
            }
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, total_amount, delivery_address, delivery_date, delivery_notes, payment_method, payment_ref, fulfillment, status)
                VALUES (:user, :total, :address, :date, :notes, :method, :ref, :fulfill, 'pending')
            ");
            $stmt->execute([
                ':user'    => $_SESSION['user_id'],
                ':total'   => $total,
                ':address' => $fulfillment === 'delivery' ? $deliveryAddress : null,
                ':date'    => $deliveryDate !== '' ? $deliveryDate : null,
                ':notes'   => $deliveryNotes,
                ':method'  => $paymentMethod,
                ':ref'     => $paymentMethod === 'gcash' ? $paymentRef : null,
                ':fulfill' => $fulfillment,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                VALUES (:oid, :pid, :qty, :price, :sub)
            ");
            $decStmt = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :pid");
            foreach ($lines as $ln) {
                $itemStmt->execute([
                    ':oid' => $orderId,
                    ':pid' => $ln['id'],
                    ':qty' => $ln['qty'],
                    ':price' => $ln['price'],
                    ':sub' => $ln['subtotal'],
                ]);
                $decStmt->execute([':qty' => $ln['qty'], ':pid' => $ln['id']]);
            }

            $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (:oid, 'pending', 'Order placed by customer')")
                ->execute([':oid' => $orderId]);

            $pdo->commit();

            $_SESSION[$storeKey] = [];
            flash('success', 'Thank you! Your order has been placed.');
            redirect('thanks.php?order=' . $orderId);
        } catch (Exception $ex) {
            $pdo->rollBack();
            $msg = $ex->getMessage();
            flash('error', (strpos($msg, 'only has') !== false) ? $msg : 'There was a problem placing your order.');
        }
    }
}
?>
<?php $pageTitle = 'Checkout | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow"><?php echo $isBuyNow ? 'BUY NOW' : 'ALMOST THERE'; ?></span>
            <h2><?php echo $isBuyNow ? 'Buy <em>now</em>' : 'Checkout'; ?></h2>
            <?php if ($isBuyNow): ?><p>Express checkout — your saved cart is untouched.</p><?php endif; ?>
        </div>

        <div class="checkout-grid">
            <div class="checkout-form">
                <form method="post" action="checkout.php<?php echo $isBuyNow ? '?mode=buynow' : ''; ?>" id="checkoutForm" novalidate>
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <div class="form-errors" id="checkoutErrors" hidden></div>
                    <label>How do you want to get your flowers?</label>
                    <div class="fulfill-row" role="radiogroup" aria-label="Fulfillment method">
                        <label class="fulfill-pill">
                            <input type="radio" name="fulfillment" value="delivery" checked>
                            <span>🚚 Delivery</span>
                        </label>
                        <label class="fulfill-pill">
                            <input type="radio" name="fulfillment" value="pickup">
                            <span>🏃 Pick up</span>
                        </label>
                    </div>

                    <div id="addressWrap">
                        <label>Delivery Address *</label>
                        <input type="text" name="delivery_address" id="deliveryAddress" maxlength="255"
                               value="<?php echo e($_SESSION['address'] ?? ''); ?>" required>
                    </div>
                    <p class="pickup-note" id="pickupNote" hidden>Pick up at <strong>688 B. Manuel St., Montalban, Rizal</strong> — we'll message you when your flowers are ready.</p>

                    <label>Delivery / Pickup Date *</label>
                    <input type="date" name="delivery_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                    <small class="field-hint">Order at least a day ahead — same-day orders aren't available.</small>

                    <label>Delivery Notes</label>
                    <textarea name="delivery_notes" rows="3" maxlength="2000" placeholder="Recipient details, message on the card, etc."></textarea>

                    <label>Payment Method</label>
                    <select name="payment_method" id="paymentMethod">
                        <option value="cash_on_delivery" data-for="delivery">Cash on Delivery</option>
                        <option value="pay_on_pickup" data-for="pickup">Pay on pickup</option>
                        <option value="gcash" data-for="both">GCash</option>
                    </select>

                    <div class="gcash-box" id="gcashBox" hidden>
                        <strong>Pay with GCash</strong>
                        <p>Send exactly <strong>₱<?php echo number_format((float)$cartTotal, 2); ?></strong> to<br><span class="gcash-num"><?php echo e($shopGcash); ?></span></p>
                        <label>GCash reference number *</label>
                        <input type="text" name="payment_ref" id="paymentRef" inputmode="numeric" maxlength="20" placeholder="13-digit number from your receipt">
                    </div>

                    <button type="submit" class="btn btn-primary">Place Order</button>
                </form>
                <script>
                (function () {
                    var form = document.getElementById('checkoutForm');
                    if (!form) return;
                    var addrWrap = document.getElementById('addressWrap');
                    var addrInput = document.getElementById('deliveryAddress');
                    var note = document.getElementById('pickupNote');
                    var errBox = document.getElementById('checkoutErrors');
                    var paySel = document.getElementById('paymentMethod');
                    var gcashBox = document.getElementById('gcashBox');
                    var refInput = document.getElementById('paymentRef');
                    function isPickup() {
                        var f = form.querySelector('input[name="fulfillment"]:checked');
                        return f && f.value === 'pickup';
                    }
                    function syncPayments() {
                        var pickup = isPickup();
                        var cur = paySel.value;
                        Array.prototype.forEach.call(paySel.options, function (opt) {
                            var scope = opt.getAttribute('data-for');
                            opt.hidden = (scope === 'delivery' && pickup) || (scope === 'pickup' && !pickup);
                        });
                        var visible = Array.prototype.filter.call(paySel.options, function (o) { return !o.hidden; });
                        var stillValid = visible.some(function (o) { return o.value === cur; });
                        paySel.value = stillValid ? cur : visible[0].value;
                        syncGcash();
                    }
                    function syncGcash() {
                        var isGcash = paySel.value === 'gcash';
                        gcashBox.hidden = !isGcash;
                        refInput.required = isGcash;
                    }
                    form.querySelectorAll('input[name="fulfillment"]').forEach(function (r) {
                        r.addEventListener('change', function () {
                            var pickup = isPickup();
                            addrWrap.style.display = pickup ? 'none' : '';
                            note.hidden = !pickup;
                            addrInput.required = !pickup;
                            syncPayments();
                        });
                    });
                    paySel.addEventListener('change', syncGcash);
                    syncPayments();
                    function showErrors(msgs) {
                        errBox.innerHTML = msgs.map(function (m) { return '<div>' + m + '</div>'; }).join('');
                        errBox.hidden = false;
                        errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    form.addEventListener('submit', function (ev) {
                        var msgs = [];
                        var fulfillment = form.querySelector('input[name="fulfillment"]:checked');
                        var isPickup = fulfillment && fulfillment.value === 'pickup';
                        var addr = addrInput.value.trim();
                        if (!isPickup) {
                            if (addr === '') { msgs.push('Delivery address is required.'); }
                            else if (addr.length < 5) { msgs.push('Delivery address looks too short. Please enter the full address.'); }
                        }
                        var dateInput = form.querySelector('input[name="delivery_date"]');
                        if (!dateInput || dateInput.value === '') {
                            msgs.push('Please choose a delivery date.');
                        } else {
                            var ok = /^\d{4}-\d{2}-\d{2}$/.test(dateInput.value);
                            var minDay = new Date(); minDay.setHours(0, 0, 0, 0); minDay.setDate(minDay.getDate() + 1);
                            var picked = ok ? new Date(dateInput.value + 'T00:00:00') : null;
                            if (!ok || isNaN(picked.getTime())) { msgs.push('Please choose a valid delivery date.'); }
                            else if (picked < minDay) { msgs.push("Same-day orders are not available. Please choose tomorrow or later."); }
                        }
                        var pay = paySel.value;
                        if (pay === 'gcash') {
                            var ref = refInput.value.replace(/\s+/g, '');
                            if (ref === '') { msgs.push('Please enter your GCash reference number so we can verify your payment.'); }
                            else if (!/^[0-9]{10,13}$/.test(ref)) { msgs.push('GCash reference number should be the 10–13 digit number from your receipt.'); }
                        }
                        if (msgs.length) {
                            ev.preventDefault();
                            showErrors(msgs);
                        } else {
                            errBox.hidden = true;
                        }
                    });
                })();
                </script>
            </div>

            <div class="checkout-summary">
                <h3>Order Summary</h3>
                <?php foreach ($cartItems as $item): ?>
                    <div class="summary-line">
                        <span><?php echo e($item['name']); ?> × <?php echo (int)$item['quantity']; ?></span>
                        <span>₱<?php echo number_format((float)$item['subtotal'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="summary-total">
                    <strong>Total</strong>
                    <strong>₱<?php echo number_format((float)$cartTotal, 2); ?></strong>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
