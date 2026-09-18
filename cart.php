<?php
require_once __DIR__ . '/config/functions.php';
start_session();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

switch ($action) {
    case 'add':
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity  = max(1, (int)($_POST['quantity'] ?? 1));
        if ($productId > 0) {
            $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $quantity;
            flash('success', 'Item added to your cart.');
        }
        redirect('cart.php');
        break;

    case 'update':
        foreach (($_POST['qty'] ?? []) as $pid => $qty) {
            $q = max(0, (int)$qty);
            if ($q <= 0) {
                unset($_SESSION['cart'][(int)$pid]);
            } else {
                $_SESSION['cart'][(int)$pid] = $q;
            }
        }
        redirect('cart.php');
        break;

    case 'remove':
        $pid = (int)($_GET['id'] ?? 0);
        unset($_SESSION['cart'][$pid]);
        redirect('cart.php');
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        redirect('cart.php');
        break;
}

$cartItems = [];
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in  = implode(',', array_map('intval', $ids));
    $rows = db()->query("SELECT id, name, price, image, stock FROM products WHERE id IN ($in)")->fetchAll();
    foreach ($rows as $r) {
        $qty = (int)$_SESSION['cart'][$r['id']];
        $cartItems[] = array_merge($r, ['quantity' => $qty]);
    }
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
                <table class="cart-table">
                    <thead>
                        <tr>
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
                            <td>
                                <a href="product.php?id=<?php echo (int)$item['id']; ?>">Arrangement</a>
                            </td>
                            <td>₱0.00</td>
                            <td>
                                <input type="number" name="qty[<?php echo (int)$item['id']; ?>]"
                                       value="<?php echo (int)$item['quantity']; ?>" min="0" max="<?php echo max(1,(int)$item['stock']); ?>">
                            </td>
                            <td>₱0.00</td>
                            <td><a class="remove-link" href="cart.php?action=remove&id=<?php echo (int)$item['id']; ?>">Remove</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="right">Total</td>
                            <td colspan="2">₱0.00</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="cart-actions">
                    <button type="submit" class="btn btn-dark">Update Quantities</button>
                    <?php if (is_logged_in()): ?>
                        <a class="btn btn-primary" href="checkout.php">Proceed to Checkout →</a>
                    <?php else: ?>
                        <a class="btn btn-primary" href="login.php">Log in to Checkout →</a>
                    <?php endif; ?>
                    <a class="btn btn-light" href="cart.php?action=clear">Clear Cart</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
