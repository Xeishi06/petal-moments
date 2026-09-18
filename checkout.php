<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cartItems = [];
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in  = implode(',', array_map('intval', $ids));
    foreach (db()->query("SELECT id, name FROM products WHERE id IN ($in)")->fetchAll() as $r) {
        $qty = (int)$_SESSION['cart'][$r['id']];
        $cartItems[] = array_merge($r, ['quantity' => $qty]);
    }
}

if (empty($cartItems)) {
    flash('error', 'Your cart is empty.');
    redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $deliveryDate    = $_POST['delivery_date'] ?? '';
    $deliveryNotes   = trim($_POST['delivery_notes'] ?? '');
    $paymentMethod   = $_POST['payment_method'] ?? 'cash_on_delivery';

    if ($deliveryAddress === '') {
        flash('error', 'Delivery address is required.');
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, total_amount, delivery_address, delivery_date, delivery_notes, payment_method, status)
                VALUES (:user, 0, :address, :date, :notes, :method, 'pending')
            ");
            $stmt->execute([
                ':user'    => $_SESSION['user_id'],
                ':address' => $deliveryAddress,
                ':date'    => $deliveryDate !== '' ? $deliveryDate : null,
                ':notes'   => $deliveryNotes,
                ':method'  => $paymentMethod,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                VALUES (:oid, :pid, :qty, 0, 0)
            ");
            foreach ($cartItems as $item) {
                $itemStmt->execute([
                    ':oid' => $orderId,
                    ':pid' => $item['id'],
                    ':qty' => $item['quantity'],
                ]);
            }

            $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (:oid, 'pending', 'Order placed by customer')")
                ->execute([':oid' => $orderId]);

            $pdo->commit();

            $_SESSION['cart'] = [];
            flash('success', 'Thank you! Your order has been placed.');
            redirect('thanks.php?order=' . $orderId);
        } catch (Exception $ex) {
            $pdo->rollBack();
            flash('error', 'There was a problem placing your order.');
        }
    }
}
?>
<?php $pageTitle = 'Checkout | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">ALMOST THERE</span>
            <h2>Checkout</h2>
        </div>

        <div class="checkout-grid">
            <div class="checkout-form">
                <form method="post" action="checkout.php">
                    <label>Delivery Address *</label>
                    <input type="text" name="delivery_address"
                           value="<?php echo e($_SESSION['address'] ?? ''); ?>" required>

                    <label>Delivery Date</label>
                    <input type="date" name="delivery_date">

                    <label>Delivery Notes</label>
                    <textarea name="delivery_notes" rows="3" placeholder="Recipient details, message on the card, etc."></textarea>

                    <label>Payment Method</label>
                    <select name="payment_method">
                        <option value="cash_on_delivery">Cash on Delivery</option>
                        <option value="gcash">GCash</option>
                    </select>

                    <button type="submit" class="btn btn-primary">Place Order</button>
                </form>
            </div>

            <div class="checkout-summary">
                <h3>Order Summary</h3>
                <?php foreach ($cartItems as $item): ?>
                    <div class="summary-line">
                        <span>Arrangement × <?php echo (int)$item['quantity']; ?></span>
                        <span>₱0.00</span>
                    </div>
                <?php endforeach; ?>
                <div class="summary-total">
                    <strong>Total</strong>
                    <strong>₱0.00</strong>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
