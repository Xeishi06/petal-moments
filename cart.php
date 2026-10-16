<?php
require_once __DIR__ . '/config/functions.php';
start_session();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if (isset($_POST['remove'])) { $action = 'remove'; }
if ($action !== '' && ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf'] ?? ''))) {
    http_response_code(403);
    exit('Invalid cart request. Refresh the cart and try again.');
}

$variants = cart_variants();

switch ($action) {
    case 'add':
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity  = max(1, min(10, (int)($_POST['quantity'] ?? 1)));
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $dedication = mb_substr(trim($_POST['dedication'] ?? ''), 0, 255);
        if (!isset($variants[$variantId])) { $variantId = cart_default_variant(); }
        if ($productId > 0) {
            $stmt = db()->prepare("SELECT id FROM products WHERE id = :id AND is_active = 1");
            $stmt->execute([':id' => $productId]);
            if (!$stmt->fetch()) {
                flash('error', 'Product not found.');
                redirect('shop.php');
            }
            if (!empty($variants[$variantId]['needs_message']) && $dedication === '') {
                flash('error', 'Please write your personalized message for the card.');
                redirect('product.php?id=' . $productId);
            }
            if (!empty($_POST['buy_now'])) {
                // Buy Now: check out this item ONLY, leave the saved cart untouched
                $_SESSION['buy_now'] = ['buynow' => ['pid' => $productId, 'qty' => $quantity, 'vid' => $variantId, 'ded' => $dedication]];
                redirect('checkout.php?mode=buynow');
            }
            $lines = cart_lines('cart');
            $merged = false;
            foreach ($lines as $rk => $ln) {
                if ($ln['pid'] === $productId && $ln['vid'] === $variantId && $ln['ded'] === $dedication) {
                    $lines[$rk]['qty'] = min(10, $ln['qty'] + $quantity);
                    $merged = true;
                    break;
                }
            }
            if (!$merged) {
                $lines['r' . substr(md5($productId . $variantId . $dedication . microtime(true)), 0, 10)] = [
                    'pid' => $productId, 'qty' => $quantity, 'vid' => $variantId, 'ded' => $dedication,
                ];
            }
            cart_save_lines('cart', $lines);
            flash('success', 'Item added to your cart.');
        }
        redirect('cart.php');
        break;

    case 'update':
        $lines = cart_lines('cart');
        foreach (($_POST['qty'] ?? []) as $rk => $qty) {
            if (!isset($lines[$rk])) { continue; }
            $q = max(0, min(10, (int)$qty));
            if ($q <= 0) {
                unset($lines[$rk]);
            } else {
                $lines[$rk]['qty'] = $q;
            }
        }
        cart_save_lines('cart', $lines);
        redirect('cart.php');
        break;

    case 'remove':
        $lines = cart_lines('cart');
        unset($lines[$_POST['remove'] ?? '']);
        cart_save_lines('cart', $lines);
        redirect('cart.php');
        break;

    case 'clear':
        cart_save_lines('cart', []);
        redirect('cart.php');
        break;
}

$cartItems = [];
$cartTotal = 0.0;
$lines = cart_lines('cart');
if (!empty($lines)) {
    $ids = array_unique(array_map(function ($ln) { return $ln['pid']; }, $lines));
    $in  = implode(',', array_map('intval', $ids));
    $rows = [];
    foreach (db()->query("SELECT id, name, price, sale_price, image, stock FROM products WHERE id IN ($in)")->fetchAll() as $r) {
        $rows[(int)$r['id']] = $r;
    }
    $kept = [];
    foreach ($lines as $rk => $ln) {
        if (!isset($rows[$ln['pid']])) { continue; }
        $r = $rows[$ln['pid']];
        $qty = max(1, $ln['qty']);
        // Clamp to available stock so checkout never oversells
        if ((int)$r['stock'] > 0) { $qty = min($qty, (int)$r['stock']); }
        $adj = isset($variants[$ln['vid']]) ? $variants[$ln['vid']]['adjust'] : 0;
        $unit = eff_price($r) + $adj;
        $subtotal = $unit * $qty;
        $cartTotal += $subtotal;
        $cartItems[] = array_merge($r, [
            'row' => $rk,
            'quantity' => $qty,
            'variant' => isset($variants[$ln['vid']]) ? $variants[$ln['vid']]['name'] : '',
            'dedication' => $ln['ded'],
            'unit' => $unit,
            'subtotal' => $subtotal,
        ]);
        $ln['qty'] = $qty;
        $kept[$rk] = $ln;
    }
    cart_save_lines('cart', $kept);
}
?>
<?php $pageTitle = 'Your Cart | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">YOUR SELECTION</span>
            <h2>Your <em>cart</em></h2>
        </div>

        <?php if (empty($cartItems)): ?>
            <p class="empty-state">Your cart is empty. <a href="shop.php">Browse our flowers →</a></p>
        <?php else: ?>
            <form method="post" action="cart.php?action=update">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $item): ?>
                        <tr>
                            <td><img class="order-thumb" src="<?php echo e($item['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=200&q=70'); ?>" alt=""></td>
                            <td>
                                <a href="product.php?id=<?php echo (int)$item['id']; ?>"><?php echo e($item['name']); ?></a>
                                <?php if ($item['variant'] !== '' && $item['variant'] !== 'Standard'): ?><br><small class="line-variant"><?php echo e($item['variant']); ?></small><?php endif; ?>
                                <?php if ($item['dedication'] !== ''): ?><br><small class="line-ded">✎ <?php echo e(mb_strimwidth($item['dedication'], 0, 60, '…')); ?></small><?php endif; ?>
                            </td>
                            <td>₱<?php echo number_format((float)$item['unit'], 2); ?></td>
                            <td>
                                <input type="number" name="qty[<?php echo e($item['row']); ?>]"
                                       value="<?php echo (int)$item['quantity']; ?>" min="0" max="<?php echo max(1,(int)$item['stock']); ?>">
                            </td>
                            <td>₱<?php echo number_format((float)$item['subtotal'], 2); ?></td>
                            <td><button type="submit" class="btn btn-light btn-small" name="remove" value="<?php echo e($item['row']); ?>" formnovalidate onclick="return confirm('Remove this arrangement from your cart?');">Remove</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="right">Total</td>
                            <td colspan="2">₱<?php echo number_format((float)$cartTotal, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="cart-actions">
                    <button type="submit" class="btn btn-dark">Update Quantities</button>
                    <?php if (is_logged_in()): ?>
                        <a class="btn btn-primary" href="checkout.php">Proceed to Checkout →</a>
                    <?php else: ?>
                        <a class="btn btn-primary" href="login.php?next=checkout.php">Log in to Checkout →</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-light" name="action" value="clear" formnovalidate onclick="return confirm('Remove every item from your cart?');">Clear Cart</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
