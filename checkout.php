<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Shop details come from admin Settings (with fallbacks)
$shopGcash = trim(setting('gcash_number', '0917 123 4567') . ' (' . setting('gcash_name', 'Petal Moments') . ')', ' ()');
$pickupAddr = setting('pickup_address', '688 B. Manuel St., Montalban, Rizal');

// Saved addresses: profile address + distinct past delivery addresses
$addrOptions = [];
try {
    $me = db()->prepare("SELECT address FROM users WHERE id = :id");
    $me->execute([':id' => $_SESSION['user_id']]);
    if ($row = $me->fetch()) {
        if (trim($row['address'] ?? '') !== '') { $addrOptions[] = trim($row['address']); }
    }
    $past = db()->prepare("SELECT DISTINCT delivery_address FROM orders WHERE user_id = :id AND delivery_address IS NOT NULL AND delivery_address != '' ORDER BY created_at DESC LIMIT 5");
    $past->execute([':id' => $_SESSION['user_id']]);
    foreach ($past->fetchAll() as $pr) {
        if (!in_array($pr['delivery_address'], $addrOptions, true)) { $addrOptions[] = $pr['delivery_address']; }
    }
} catch (Exception $e) { /* suggestions are best-effort */ }
$defaultAddr = $addrOptions[0] ?? '';

// Saved GCash numbers for one-tap refill at checkout
$savedGcash = [];
$defaultPay = null;
try {
    $sg = db()->prepare("SELECT label, detail, method, is_default FROM saved_payments WHERE user_id = :id ORDER BY is_default DESC, created_at DESC");
    $sg->execute([':id' => $_SESSION['user_id']]);
    foreach ($sg->fetchAll() as $row) {
        if ($row['method'] === 'gcash') { $savedGcash[] = $row; }
        if (!empty($row['is_default'])) { $defaultPay = $row; }
    }
} catch (Exception $e) { /* best-effort */ }

// Delivery fee rules (admin Settings; pickup is always free)
$feeFlat = max(0, (float)setting('delivery_fee', 0));
$feeFreeMin = max(0, (float)setting('free_delivery_min', 0));
function quote_fee($subtotal, $isPickup, $flat, $freeMin)
{
    if ($isPickup || $flat <= 0) { return 0.0; }
    if ($freeMin > 0 && $subtotal >= $freeMin) { return 0.0; }
    return $flat;
}

// One order token per session — rotated after every successful order.
// A double-click or back-button resubmit reuses the first order instead of cloning it.
if (empty($_SESSION['order_token'])) {
    $_SESSION['order_token'] = bin2hex(random_bytes(16));
    $_SESSION['order_tokens'] = [];
}

// Buy Now mode: check out ONLY the buy-now item, cart stays saved
$isBuyNow = (($_GET['mode'] ?? '') === 'buynow') && !empty($_SESSION['buy_now']);
$storeKey = $isBuyNow ? 'buy_now' : 'cart';
$variants = cart_variants();

$cartItems = [];
$cartTotal = 0.0;
$source = cart_lines($storeKey);
if (!empty($source)) {
    $ids = array_unique(array_map(function ($ln) { return $ln['pid']; }, $source));
    $in  = implode(',', array_map('intval', $ids));
    $rows = [];
    foreach (db()->query("SELECT id, name, price, sale_price, stock FROM products WHERE id IN ($in) AND is_active = 1")->fetchAll() as $r) {
        $rows[(int)$r['id']] = $r;
    }
    $kept = [];
    foreach ($source as $rk => $ln) {
        if (!isset($rows[$ln['pid']])) { continue; }
        $r = $rows[$ln['pid']];
        $qty = max(1, $ln['qty']);
        if ((int)$r['stock'] > 0) { $qty = min($qty, (int)$r['stock']); }
        $vid = isset($variants[$ln['vid']]) ? $ln['vid'] : 0;
        $adj = $vid ? $variants[$vid]['adjust'] : 0;
        $unit = eff_price($r) + $adj;
        $subtotal = $unit * $qty;
        $cartTotal += $subtotal;
        $cartItems[] = array_merge($r, [
            'quantity' => $qty,
            'variant' => $vid ? $variants[$vid]['name'] : '',
            'dedication' => $ln['ded'],
            'unit' => $unit,
            'subtotal' => $subtotal,
        ]);
        $ln['qty'] = $qty;
        $ln['vid'] = $vid;
        $kept[$rk] = $ln;
    }
    cart_save_lines($storeKey, $kept);
    // Drop items that were deactivated or deleted since being added
    if (count($kept) < count($source)) {
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
    // Idempotency: a double-click / back-button resubmit reuses the first order
    $orderToken = $_POST['order_token'] ?? '';
    if ($orderToken !== '' && isset($_SESSION['order_tokens'][$orderToken])) {
        redirect('thanks.php?order=' . (int)$_SESSION['order_tokens'][$orderToken]);
    }
    $fulfillment     = ($_POST['fulfillment'] ?? 'delivery') === 'pickup' ? 'pickup' : 'delivery';
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $deliveryDate    = trim($_POST['delivery_date'] ?? '');
    $deliveryNotes   = trim($_POST['delivery_notes'] ?? '');
    $paymentMethod   = $_POST['payment_method'] ?? 'cash_on_delivery';
    $paymentRef      = trim($_POST['payment_ref'] ?? '');
    $senderNo        = mb_substr(trim($_POST['sender_no'] ?? ''), 0, 100);

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
        } else {
            $dupRef = db()->prepare("SELECT id FROM orders WHERE payment_method = 'gcash' AND payment_ref = :ref AND status != 'cancelled' LIMIT 1");
            $dupRef->execute([':ref' => $paymentRef]);
            if ($dupRef->fetch()) {
                $errors[] = 'This GCash reference number was already used on another order. Please check your receipt and try again.';
            }
        }
        $senderDigits = preg_replace('/\s+/', '', $senderNo);
        if ($senderNo === '') {
            $errors[] = 'Please enter the GCash number you paid from so we can match your payment.';
        } elseif (!preg_match('/^[0-9]{10,13}$/', $senderDigits)) {
            $errors[] = 'Your GCash number should be 10–13 digits.';
        } else {
            $senderNo = $senderDigits;
        }
        // Optional receipt screenshot (validated now, moved after order is created)
        $receiptErr = null;
        if (!empty($_FILES['receipt']['name']) && ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
            $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['receipt']['tmp_name']);
            if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
                $receiptErr = 'Receipt must be a JPG, PNG or WEBP image.';
            } elseif ($_FILES['receipt']['size'] > 2 * 1024 * 1024) {
                $receiptErr = 'Receipt image must be under 2MB.';
            }
        }
        if ($receiptErr !== null) { $errors[] = $receiptErr; }
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

    // Price-change guard: the page may have sat open while prices/fees moved.
    // Recompute live and refuse to charge a different number than shown.
    $liveFee = quote_fee($cartTotal, $fulfillment === 'pickup', (float)setting('delivery_fee', 0), (float)setting('free_delivery_min', 0));
    $liveGrand = $cartTotal + $liveFee;
    $expectedGrand = isset($_POST['expected_grand']) && $_POST['expected_grand'] !== '' ? (float)$_POST['expected_grand'] : null;
    if ($expectedGrand !== null && abs($liveGrand - $expectedGrand) > 0.01) {
        $errors[] = 'The total moved from ₱' . number_format($expectedGrand, 2) . ' to ₱' . number_format($liveGrand, 2)
            . ' (a price, sale, stock amount, or fee changed). Please review the updated total below, then place your order again.';
    }

    if (!empty($errors)) {
        foreach ($errors as $msg) { flash('error', $msg); }
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Re-check stock inside the transaction to prevent overselling
            $check = $pdo->prepare("SELECT id, price, sale_price, stock FROM products WHERE id = :id FOR UPDATE");
            $lines = [];
            $total = 0.0;
            foreach ($cartItems as $item) {
                $check->execute([':id' => $item['id']]);
                $live = $check->fetch();
                if (!$live || (int)$live['stock'] < (int)$item['quantity']) {
                    throw new Exception('Sorry, "' . $item['name'] . '" only has ' . (int)($live['stock'] ?? 0) . ' left in stock.');
                }
                // Re-apply the live variant adjustment (admin may have changed it)
                $vAdj = 0;
                $vName = $item['variant'];
                if ($vName !== '') {
                    $vRow = $pdo->prepare("SELECT price_adjust FROM variants WHERE name = :n AND is_active = 1");
                    $vRow->execute([':n' => $vName]);
                    if ($vr = $vRow->fetch()) { $vAdj = (float)$vr['price_adjust']; }
                    else { $vName = ''; }
                }
                $unitPrice = eff_price($live) + $vAdj;
                $lineTotal = $unitPrice * (int)$item['quantity'];
                $total += $lineTotal;
                $lines[] = ['id' => $item['id'], 'qty' => (int)$item['quantity'], 'price' => $unitPrice, 'variant' => $vName, 'ded' => $item['dedication'], 'subtotal' => $lineTotal];
            }
            $orderFee = quote_fee($total, $fulfillment === 'pickup', (float)setting('delivery_fee', 0), (float)setting('free_delivery_min', 0));
            if (abs(($total + $orderFee) - $liveGrand) > 0.01) {
                throw new Exception('Totals shifted during checkout. Please review and try again.');
            }
            $grandTotal = $total + $orderFee;
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, total_amount, delivery_fee, delivery_address, delivery_date, delivery_notes, payment_method, payment_ref, sender_no, fulfillment, status)
                VALUES (:user, :total, :fee, :address, :date, :notes, :method, :ref, :sender, :fulfill, 'pending')
            ");
            $stmt->execute([
                ':user'    => $_SESSION['user_id'],
                ':total'   => $grandTotal,
                ':fee'     => $orderFee,
                ':address' => $fulfillment === 'delivery' ? $deliveryAddress : null,
                ':date'    => $deliveryDate !== '' ? $deliveryDate : null,
                ':notes'   => $deliveryNotes,
                ':method'  => $paymentMethod,
                ':ref'     => $paymentMethod === 'gcash' ? $paymentRef : null,
                ':sender'  => $paymentMethod === 'gcash' && $senderNo !== '' ? $senderNo : null,
                ':fulfill' => $fulfillment,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // Move the receipt now that the order exists (order id in filename)
            $receiptPath = null;
            if ($paymentMethod === 'gcash' && !empty($_FILES['receipt']['name']) && ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
                $destDir = __DIR__ . '/uploads/receipts';
                if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
                $receiptPath = 'uploads/receipts/receipt-' . $orderId . '-' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['receipt']['tmp_name'], __DIR__ . '/' . $receiptPath)) {
                    $pdo->prepare("UPDATE orders SET receipt = :r WHERE id = :id")->execute([':r' => $receiptPath, ':id' => $orderId]);
                } else {
                    $receiptPath = null;
                }
            }

            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, variant_name, dedication, subtotal)
                VALUES (:oid, :pid, :qty, :price, :variant, :ded, :sub)
            ");
            $decStmt = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :pid");
            foreach ($lines as $ln) {
                $itemStmt->execute([
                    ':oid' => $orderId,
                    ':pid' => $ln['id'],
                    ':qty' => $ln['qty'],
                    ':price' => $ln['price'],
                    ':variant' => $ln['variant'] !== '' ? $ln['variant'] : null,
                    ':ded' => $ln['ded'] !== '' ? $ln['ded'] : null,
                    ':sub' => $ln['subtotal'],
                ]);
                $decStmt->execute([':qty' => $ln['qty'], ':pid' => $ln['id']]);
            }

            $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (:oid, 'pending', 'Order placed by customer')")
                ->execute([':oid' => $orderId]);

            $pdo->commit();

            $_SESSION[$storeKey] = [];
            $_SESSION['order_tokens'][$orderToken] = $orderId;
            $_SESSION['order_tokens'] = array_slice($_SESSION['order_tokens'], -20, 20, true);
            $_SESSION['order_token'] = bin2hex(random_bytes(16));
            flash('success', 'Thank you! Your order has been placed.');
            redirect('thanks.php?order=' . $orderId);
        } catch (Exception $ex) {
            $pdo->rollBack();
            $msg = $ex->getMessage();
            if (strpos($msg, 'only has') !== false || strpos($msg, 'Totals shifted') !== false) {
                flash('error', $msg);
            } else {
                flash('error', 'There was a problem placing your order.');
            }
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
                <form method="post" action="checkout.php<?php echo $isBuyNow ? '?mode=buynow' : ''; ?>" id="checkoutForm" novalidate enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="order_token" value="<?php echo e($_SESSION['order_token']); ?>">
                    <input type="hidden" name="expected_grand" id="expectedGrand" value="<?php echo number_format($cartTotal + quote_fee($cartTotal, false, $feeFlat, $feeFreeMin), 2, '.', ''); ?>">
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
                        <input type="text" name="delivery_address" id="deliveryAddress" maxlength="255" list="addrList"
                               value="<?php echo e($defaultAddr); ?>" required>
                        <?php if (!empty($addrOptions)): ?>
                        <datalist id="addrList">
                            <?php foreach ($addrOptions as $ao): ?><option value="<?php echo e($ao); ?>"><?php endforeach; ?>
                        </datalist>
                        <?php endif; ?>
                    </div>
                    <p class="pickup-note" id="pickupNote" hidden>Pick up at <strong><?php echo e($pickupAddr); ?></strong> — we'll message you when your flowers are ready.</p>

                    <label>Delivery / Pickup Date *</label>
                    <input type="date" name="delivery_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                    <small class="field-hint">Order at least a day ahead — same-day orders aren't available.</small>

                    <label>Delivery Notes</label>
                    <textarea name="delivery_notes" rows="3" maxlength="2000" placeholder="Recipient details, message on the card, etc."></textarea>

                    <label>Payment Method</label>
                    <select name="payment_method" id="paymentMethod">
                        <option value="cash_on_delivery" data-for="delivery">Cash on Delivery</option>
                        <option value="pay_on_pickup" data-for="pickup">Pay on pickup</option>
                        <option value="gcash" data-for="both"<?php echo ($defaultPay && $defaultPay['method'] === 'gcash') ? ' selected' : ''; ?>>GCash</option>
                    </select>

                    <div class="gcash-box" id="gcashBox" hidden>
                        <strong>Pay with GCash</strong>
                        <p>Send exactly <strong id="gcashAmt">₱<?php echo number_format($cartTotal + quote_fee($cartTotal, false, $feeFlat, $feeFreeMin), 2); ?></strong> to<br><span class="gcash-num"><?php echo e($shopGcash); ?></span></p>
                        <label>GCash reference number *</label>
                        <input type="text" name="payment_ref" id="paymentRef" inputmode="numeric" maxlength="20" placeholder="13-digit number from your receipt">
                        <label>Your GCash number (paying from) *</label>
                        <input type="text" name="sender_no" id="senderNo" inputmode="numeric" maxlength="20" list="senderList" placeholder="e.g. 09171234567" value="<?php echo ($defaultPay && $defaultPay['method'] === 'gcash') ? e(preg_replace('/\s+/', '', $defaultPay['detail'])) : ''; ?>">
                        <?php if (!empty($savedGcash)): ?>
                        <datalist id="senderList">
                            <?php foreach ($savedGcash as $sgc): ?><option value="<?php echo e(preg_replace('/\s+/', '', $sgc['detail'])); ?>"><?php echo e($sgc['label']); ?></option><?php endforeach; ?>
                        </datalist>
                        <div class="saved-chips">
                            <small>Saved:</small>
                            <?php foreach ($savedGcash as $sgc): ?>
                            <button type="button" class="saved-chip" data-sender="<?php echo e(preg_replace('/\s+/', '', $sgc['detail'])); ?>"><?php echo e($sgc['label']); ?></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <label>Receipt screenshot <small class="opt">(optional, speeds up verification)</small></label>
                        <input type="file" name="receipt" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <button type="submit" class="btn btn-primary" id="placeOrderBtn">Place Order</button>
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
                    var senderInput = document.getElementById('senderNo');
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
                        if (senderInput) { senderInput.required = isGcash; }
                        var sp = document.getElementById('sumPay');
                        if (sp && paySel.selectedOptions.length) { sp.textContent = paySel.selectedOptions[0].textContent; }
                    }
                    document.querySelectorAll('.saved-chip').forEach(function (chip) {
                        chip.addEventListener('click', function () {
                            if (senderInput) {
                                senderInput.value = chip.getAttribute('data-sender') || '';
                                senderInput.focus();
                            }
                        });
                    });
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
                            var snd = senderInput ? senderInput.value.replace(/\s+/g, '') : '';
                            if (snd === '') { msgs.push('Please enter the GCash number you paid from.'); }
                            else if (!/^[0-9]{10,13}$/.test(snd)) { msgs.push('Your GCash number should be 10–13 digits.'); }
                        }
                        if (msgs.length) {
                            ev.preventDefault();
                            showErrors(msgs);
                        } else {
                            errBox.hidden = true;
                            // Lock the button: no double-click double orders
                            var btn = document.getElementById('placeOrderBtn');
                            if (btn) {
                                btn.disabled = true;
                                btn.textContent = 'Placing your order…';
                            }
                        }
                    });
                })();
                </script>
            </div>

            <div class="checkout-summary">
                <h3>Order Summary</h3>
                <?php foreach ($cartItems as $item): ?>
                    <div class="summary-line">
                        <span><?php echo e($item['name']); ?><?php echo ($item['variant'] !== '' && $item['variant'] !== 'Standard') ? ' (' . e($item['variant']) . ')' : ''; ?> × <?php echo (int)$item['quantity']; ?><?php echo $item['dedication'] !== '' ? ' ✎' : ''; ?></span>
                        <span>₱<?php echo number_format((float)$item['subtotal'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                <?php $summaryFee = quote_fee($cartTotal, false, $feeFlat, $feeFreeMin); ?>
                <div class="summary-line">
                    <span>Getting it by</span>
                    <span id="sumFulfill">🚚 Delivery</span>
                </div>
                <div class="summary-line">
                    <span>Paying with</span>
                    <span id="sumPay">Cash on Delivery</span>
                </div>
                <div class="summary-line" id="feeLine"<?php echo $summaryFee <= 0 ? ' hidden' : ''; ?>>
                    <span>Delivery fee<?php echo ($feeFreeMin > 0) ? ' <small>(free over ₱' . number_format($feeFreeMin, 2) . ')</small>' : ''; ?></span>
                    <span id="feeAmt">₱<?php echo number_format($summaryFee, 2); ?></span>
                </div>
                <div class="summary-total">
                    <strong>Total</strong>
                    <strong id="grandTotal">₱<?php echo number_format($cartTotal + $summaryFee, 2); ?></strong>
                </div>
            </div>
            <script>
            (function () {
                var form = document.getElementById('checkoutForm');
                var feeLine = document.getElementById('feeLine');
                var feeAmt = document.getElementById('feeAmt');
                var grandEl = document.getElementById('grandTotal');
                if (!form || !feeLine) return;
                var subtotal = <?php echo json_encode((float)$cartTotal); ?>;
                var flat = <?php echo json_encode((float)$feeFlat); ?>;
                var freeMin = <?php echo json_encode((float)$feeFreeMin); ?>;
                function peso(n) {
                    return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                function refreshTotals() {
                    var pickup = form.querySelector('input[name="fulfillment"]:checked').value === 'pickup';
                    var fee = (pickup || flat <= 0 || (freeMin > 0 && subtotal >= freeMin)) ? 0 : flat;
                    feeLine.hidden = fee <= 0;
                    feeAmt.textContent = peso(fee);
                    grandEl.textContent = peso(subtotal + fee);
                    var gamt = document.getElementById('gcashAmt');
                    if (gamt) { gamt.textContent = peso(subtotal + fee); }
                    var exp = document.getElementById('expectedGrand');
                    if (exp) { exp.value = (subtotal + fee).toFixed(2); }
                    var sf = document.getElementById('sumFulfill');
                    if (sf) { sf.textContent = pickup ? '🏃 Pickup' : '🚚 Delivery'; }
                    var sp = document.getElementById('sumPay');
                    var pSel = document.getElementById('paymentMethod');
                    if (sp && pSel && pSel.selectedOptions.length) { sp.textContent = pSel.selectedOptions[0].textContent; }
                }
                form.querySelectorAll('input[name="fulfillment"]').forEach(function (r) {
                    r.addEventListener('change', refreshTotals);
                });
                refreshTotals();
            })();
            </script>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
