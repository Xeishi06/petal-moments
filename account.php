<?php
require_once __DIR__ . '/config/functions.php';
start_session();
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    if ($firstName === '' || $lastName === '') {
        flash('error', 'Name fields are required.');
    } else {
        db()->prepare("UPDATE users SET first_name=:fn, last_name=:ln, phone=:ph, address=:ad WHERE id=:id")
            ->execute([':fn' => $firstName, ':ln' => $lastName, ':ph' => $phone, ':ad' => $address, ':id' => $_SESSION['user_id']]);
        $_SESSION['name'] = $firstName . ' ' . $lastName;
        flash('success', 'Profile updated.');
        redirect('account.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
        redirect('account.php?tab=security');
    }
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $row = db()->prepare("SELECT password FROM users WHERE id = :id");
    $row->execute([':id' => $_SESSION['user_id']]);
    $hash = ($row->fetch())['password'] ?? '';
    if (!pw_change_allowed()) {
        pw_change_hit();
        flash('error', 'Too many tries. Wait 15 minutes before changing your password again.');
    } elseif (!password_verify($current, $hash)) {
        pw_change_hit();
        flash('error', 'Your current password is incorrect.');
    } elseif (($pwErr = password_strength($new)) !== '') {
        flash('error', $pwErr);
    } elseif ($new !== $confirm) {
        flash('error', 'New passwords do not match.');
    } elseif (password_verify($new, $hash)) {
        flash('error', 'New password must be different from the current one.');
    } else {
        db()->prepare("UPDATE users SET password = :pw WHERE id = :id")
            ->execute([':pw' => password_hash($new, PASSWORD_BCRYPT), ':id' => $_SESSION['user_id']]);
        unset($_SESSION['pw_attempts']);
        session_regenerate_id(true);
        flash('success', 'Password changed. Use it next time you log in.');
    }
    redirect('account.php?tab=security');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'payment_add') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
        redirect('account.php?tab=payments');
    }
    $label  = mb_substr(trim($_POST['plabel'] ?? ''), 0, 60);
    $method = ($_POST['pmethod'] ?? 'gcash') === 'card' ? 'card' : 'gcash';
    $detail = mb_substr(trim($_POST['pdetail'] ?? ''), 0, 100);
    $normDetail = $method === 'gcash' ? preg_replace('/\s+/', '', $detail) : $detail;
    if ($label === '' || $detail === '') {
        flash('error', 'Give your payment method a name and its number.');
    } elseif ($method === 'gcash' && !preg_match('/^[0-9]{10,13}$/', preg_replace('/\s+/', '', $detail))) {
        flash('error', 'GCash number should be 10–13 digits.');
    } else {
        $dup = db()->prepare("SELECT id FROM saved_payments WHERE user_id = :u AND method = :m AND detail = :d");
        $dup->execute([':u' => $_SESSION['user_id'], ':m' => $method, ':d' => $method === 'gcash' ? $normDetail : $detail]);
        if ($dup->fetch()) {
            flash('error', 'You already saved that ' . ($method === 'gcash' ? 'GCash number' : 'card') . '.');
        } else {
            $first = db()->prepare("SELECT COUNT(*) FROM saved_payments WHERE user_id = :u");
            $first->execute([':u' => $_SESSION['user_id']]);
            $isFirst = ((int)$first->fetchColumn() === 0);
            db()->prepare("INSERT INTO saved_payments (user_id, label, method, detail, is_default) VALUES (:u, :l, :m, :d, :df)")
                ->execute([':u' => $_SESSION['user_id'], ':l' => $label, ':m' => $method, ':d' => $method === 'gcash' ? $normDetail : $detail, ':df' => $isFirst ? 1 : 0]);
            flash('success', $isFirst ? 'Payment method saved — set as your default. 🎉' : 'Payment method saved.');
        }
    }
    redirect('account.php?tab=payments');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'payment_default') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
    } else {
        $pid = (int)($_POST['pay_id'] ?? 0);
        $own = db()->prepare("SELECT id FROM saved_payments WHERE id = :id AND user_id = :u");
        $own->execute([':id' => $pid, ':u' => $_SESSION['user_id']]);
        if (!$own->fetch()) {
            flash('error', 'Payment method not found.');
        } else {
            db()->prepare("UPDATE saved_payments SET is_default = 0 WHERE user_id = :u")->execute([':u' => $_SESSION['user_id']]);
            db()->prepare("UPDATE saved_payments SET is_default = 1 WHERE id = :id")->execute([':id' => $pid]);
            flash('success', 'Default payment method updated. ⭐');
        }
    }
    redirect('account.php?tab=payments');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'payment_edit') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
    } else {
        $pid   = (int)($_POST['pay_id'] ?? 0);
        $label = mb_substr(trim($_POST['plabel'] ?? ''), 0, 60);
        $detail = mb_substr(trim($_POST['pdetail'] ?? ''), 0, 100);
        $own = db()->prepare("SELECT * FROM saved_payments WHERE id = :id AND user_id = :u");
        $own->execute([':id' => $pid, ':u' => $_SESSION['user_id']]);
        $existing = $own->fetch();
        if (!$existing) {
            flash('error', 'Payment method not found.');
        } elseif ($label === '' || $detail === '') {
            flash('error', 'Nickname and number cannot be empty.');
        } elseif ($existing['method'] === 'gcash' && !preg_match('/^[0-9]{10,13}$/', preg_replace('/\s+/', '', $detail))) {
            flash('error', 'GCash number should be 10–13 digits.');
        } else {
            $dup = db()->prepare("SELECT id FROM saved_payments WHERE user_id = :u AND method = :m AND detail = :d AND id != :id");
            $dup->execute([
                ':u' => $_SESSION['user_id'], ':m' => $existing['method'],
                ':d' => $existing['method'] === 'gcash' ? preg_replace('/\s+/', '', $detail) : $detail,
                ':id' => $pid,
            ]);
            if ($dup->fetch()) {
                flash('error', 'Another saved method already uses those details.');
            } else {
                db()->prepare("UPDATE saved_payments SET label = :l, detail = :d WHERE id = :id")
                    ->execute([
                        ':l' => $label,
                        ':d' => $existing['method'] === 'gcash' ? preg_replace('/\s+/', '', $detail) : $detail,
                        ':id' => $pid,
                    ]);
                flash('success', 'Payment method updated.');
            }
        }
    }
    redirect('account.php?tab=payments');
}

if (isset($_GET['delpay'])) {
    if (!verify_csrf($_GET['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
    } else {
        db()->prepare("DELETE FROM saved_payments WHERE id = :id AND user_id = :u")
            ->execute([':id' => (int)$_GET['delpay'], ':u' => $_SESSION['user_id']]);
        flash('success', 'Saved payment removed.');
    }
    redirect('account.php?tab=payments');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'theme') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
    } else {
        $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
        db()->prepare("UPDATE users SET theme = :t WHERE id = :id")
            ->execute([':t' => $theme, ':id' => $_SESSION['user_id']]);
        $_SESSION['theme'] = $theme;
        flash('success', $theme === 'dark' ? 'Dark mode on. Easy on the eyes. 🌙' : 'Light mode on. ☀️');
    }
    redirect('account.php?tab=appearance');
}

$stmt = db()->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

$orders = db()->prepare("
    SELECT o.*, COUNT(oi.id) AS items,
           (SELECT p.image FROM order_items oi2 JOIN products p ON p.id = oi2.product_id WHERE oi2.order_id = o.id LIMIT 1) AS thumb
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = :id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders->execute([':id' => $_SESSION['user_id']]);
$myOrders = $orders->fetchAll();

$statSpent = 0.0;
foreach ($myOrders as $so) {
    if ($so['status'] !== 'cancelled') { $statSpent += (float)$so['total_amount']; }
}
$statFav = db()->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = :id");
$statFav->execute([':id' => $_SESSION['user_id']]);
$statFav = (int)$statFav->fetchColumn();
$statRev = db()->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = :id");
$statRev->execute([':id' => $_SESSION['user_id']]);
$statRev = (int)$statRev->fetchColumn();

$myReviews = db()->prepare("
    SELECT r.*, p.name AS product_name
    FROM reviews r JOIN products p ON p.id = r.product_id
    WHERE r.user_id = :id
    ORDER BY r.created_at DESC
");
$myReviews->execute([':id' => $_SESSION['user_id']]);
$myReviews = $myReviews->fetchAll();

$favAll = db()->prepare("
    SELECT p.id, p.name, p.price, p.image
    FROM wishlist w JOIN products p ON p.id = w.product_id AND p.is_active = 1
    WHERE w.user_id = :id
    ORDER BY w.created_at DESC
");
$favAll->execute([':id' => $_SESSION['user_id']]);
$favAll = $favAll->fetchAll();

$myPayments = db()->prepare("SELECT * FROM saved_payments WHERE user_id = :id ORDER BY created_at DESC");
$myPayments->execute([':id' => $_SESSION['user_id']]);
$myPayments = $myPayments->fetchAll();
$myTheme = $user['theme'] ?? 'light';

$validTabs = ['account', 'security', 'payments', 'appearance', 'favorites', 'reviews'];
$tab = $_GET['tab'] ?? 'account';
if (!in_array($tab, $validTabs, true)) { $tab = 'account'; }
?>
<?php $pageTitle = 'My Account | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">MY ACCOUNT</span>
            <h2>Hello, <em><?php echo e($user['first_name']); ?></em></h2>
        </div>

        <div class="settings-layout">
            <aside class="settings-side">
                <a href="account.php?tab=account" class="<?php echo $tab === 'account' ? 'active' : ''; ?>">Account info</a>
                <a href="account.php?tab=security" class="<?php echo $tab === 'security' ? 'active' : ''; ?>">Security</a>
                <a href="account.php?tab=payments" class="<?php echo $tab === 'payments' ? 'active' : ''; ?>">Payment methods</a>
                <a href="account.php?tab=appearance" class="<?php echo $tab === 'appearance' ? 'active' : ''; ?>">Appearance</a>
                <a href="account.php?tab=favorites" class="<?php echo $tab === 'favorites' ? 'active' : ''; ?>">Favorites</a>
                <a href="account.php?tab=reviews" class="<?php echo $tab === 'reviews' ? 'active' : ''; ?>">My reviews</a>
                <a href="my-orders.php">Your orders →</a>
            </aside>

            <div class="settings-panel">
                <?php if ($tab === 'account'): ?>
                <h3>Account info</h3>
                <div class="settings-row"><span>Display name</span><strong><?php echo e($user['first_name'] . ' ' . $user['last_name']); ?></strong></div>
                <div class="settings-row"><span>Email</span><strong><?php echo e($user['email']); ?> <small class="chip chip-yes">Verified</small></strong></div>
                <div class="settings-row"><span>Phone</span><strong><?php echo e($user['phone'] ?: '—'); ?></strong></div>
                <div class="settings-row"><span>Address</span><strong><?php echo e($user['address'] ?: '—'); ?></strong></div>
                <div class="settings-row"><span>Member since</span><strong><?php echo date('M Y', strtotime($user['created_at'])); ?></strong></div>
                <h3 style="margin-top:22px;">Edit profile</h3>
                <form method="post" action="account.php?tab=account">
                    <input type="hidden" name="form" value="profile">
                    <div class="form-row">
                        <div>
                            <label>First name</label>
                            <input type="text" name="first_name" value="<?php echo e($user['first_name']); ?>" required>
                        </div>
                        <div>
                            <label>Last name</label>
                            <input type="text" name="last_name" value="<?php echo e($user['last_name']); ?>" required>
                        </div>
                    </div>
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo e($user['phone']); ?>">
                    <label>Address</label>
                    <input type="text" name="address" value="<?php echo e($user['address']); ?>">
                    <button type="submit" class="btn btn-primary" style="margin-top:14px;">Save Changes</button>
                </form>

                <?php elseif ($tab === 'security'): ?>
                <h3>Security</h3>
                <p class="acct-note">Signed in as <?php echo e($user['email']); ?>. Change your password below — you'll need the current one.</p>
                <form method="post" action="account.php?tab=security">
                    <input type="hidden" name="form" value="password">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <label>Current password *</label>
                    <div class="pass-wrap">
                        <input type="password" name="current_password" required autocomplete="current-password" id="curPass">
                        <button type="button" class="pass-eye" data-showpass="curPass" aria-label="Show password">👁</button>
                    </div>
                    <label>New password * <small class="opt">(min 8 chars, letters + numbers)</small></label>
                    <div class="pass-wrap">
                        <input type="password" name="new_password" required autocomplete="new-password" id="newPass">
                        <button type="button" class="pass-eye" data-showpass="newPass" aria-label="Show password">👁</button>
                    </div>
                    <label>Confirm new password *</label>
                    <div class="pass-wrap">
                        <input type="password" name="confirm_password" required autocomplete="new-password" id="cfmPass">
                        <button type="button" class="pass-eye" data-showpass="cfmPass" aria-label="Show password">👁</button>
                    </div>
                    <button type="submit" class="btn btn-dark" style="margin-top:14px;">Change Password</button>
                </form>

                <?php elseif ($tab === 'payments'): ?>
                <h3>Payment methods</h3>
                <p class="acct-note">GCash numbers appear as one-tap picks at checkout. Cards are reminders only — pay by card in person.</p>
                <?php if (!empty($myPayments)): ?>
                    <?php $editPm = null;
                    if (isset($_GET['editpay'])) {
                        foreach ($myPayments as $cand) {
                            if ((int)$cand['id'] === (int)$_GET['editpay']) { $editPm = $cand; break; }
                        }
                    } ?>
                    <?php foreach ($myPayments as $pm): ?>
                    <div class="pay-row">
                        <div>
                            <strong><?php if (!empty($pm['is_default'])): ?><span title="Default payment">⭐ </span><?php endif; ?><?php echo $pm['method'] === 'gcash' ? 'GCash' : '💳 Card'; ?> — <?php echo e($pm['label']); ?></strong>
                            <small><?php echo e($pm['detail']); ?></small>
                        </div>
                        <div class="pay-actions">
                            <?php if (empty($pm['is_default'])): ?>
                            <form method="post" action="account.php?tab=payments" style="display:inline;">
                                <input type="hidden" name="form" value="payment_default">
                                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="pay_id" value="<?php echo (int)$pm['id']; ?>">
                                <button type="submit" class="remove-link" style="border:0;background:none;cursor:pointer;">Make default</button>
                            </form>
                            <?php endif; ?>
                            <a class="remove-link" href="account.php?tab=payments&editpay=<?php echo (int)$pm['id']; ?>">Edit</a>
                            <a class="remove-link" href="account.php?tab=payments&delpay=<?php echo (int)$pm['id']; ?>&csrf=<?php echo e(csrf_token()); ?>" onclick="return confirm('Remove this saved payment?');">Remove</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($editPm): ?>
                    <form method="post" action="account.php?tab=payments" class="pay-add pay-edit">
                        <input type="hidden" name="form" value="payment_edit">
                        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="pay_id" value="<?php echo (int)$editPm['id']; ?>">
                        <h3 style="margin-top:6px;">Edit — <?php echo e($editPm['label']); ?></h3>
                        <div class="form-row">
                            <div>
                                <label>Nickname *</label>
                                <input type="text" name="plabel" maxlength="60" value="<?php echo e($editPm['label']); ?>" required>
                            </div>
                            <div>
                                <label>Number / details *</label>
                                <input type="text" name="pdetail" maxlength="100" value="<?php echo e($editPm['detail']); ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-dark btn-small" style="margin-top:10px;">Save Changes</button>
                        <a class="btn btn-light btn-small" style="margin-top:10px;" href="account.php?tab=payments">Cancel</a>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="empty-state">No saved payments yet.</p>
                <?php endif; ?>
                <h3 style="margin-top:20px;">Add payment method</h3>
                <form method="post" action="account.php?tab=payments" class="pay-add">
                    <input type="hidden" name="form" value="payment_add">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <div class="form-row">
                        <div>
                            <label>Nickname *</label>
                            <input type="text" name="plabel" maxlength="60" placeholder="e.g. My GCash" required>
                        </div>
                        <div>
                            <label>Type *</label>
                            <select name="pmethod">
                                <option value="gcash">GCash number</option>
                                <option value="card">Card (nickname + last 4)</option>
                            </select>
                        </div>
                    </div>
                    <label>Number / details *</label>
                    <input type="text" name="pdetail" maxlength="100" placeholder="e.g. 09171234567" required>
                    <button type="submit" class="btn btn-dark btn-small" style="margin-top:10px;">Save Payment</button>
                </form>

                <?php elseif ($tab === 'appearance'): ?>
                <h3>Appearance</h3>
                <p class="acct-note">Easy on the eyes for late-night flower shopping. Applies to your whole visit.</p>
                <form method="post" action="account.php?tab=appearance" class="theme-row">
                    <input type="hidden" name="form" value="theme">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <button type="submit" name="theme" value="light" class="theme-pick<?php echo $myTheme !== 'dark' ? ' picked' : ''; ?>" aria-pressed="<?php echo $myTheme !== 'dark' ? 'true' : 'false'; ?>">☀️ Light</button>
                    <button type="submit" name="theme" value="dark" class="theme-pick<?php echo $myTheme === 'dark' ? ' picked' : ''; ?>" aria-pressed="<?php echo $myTheme === 'dark' ? 'true' : 'false'; ?>">🌙 Dark</button>
                </form>

                <?php elseif ($tab === 'favorites'): ?>
                <h3>Favorites <a class="text-link" style="font-size:0.8rem;" href="wishlist.php">Open full page →</a></h3>
                <?php if (empty($favAll)): ?>
                    <p class="empty-state">Nothing saved yet. <a href="shop.php">Find flowers you love →</a></p>
                <?php else: ?>
                    <div class="fav-strip">
                        <?php foreach ($favAll as $fp): ?>
                        <a class="fav-thumb" href="product.php?id=<?php echo (int)$fp['id']; ?>" title="<?php echo e($fp['name']); ?>">
                            <img src="<?php echo e($fp['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=200&q=70'); ?>" alt="<?php echo e($fp['name']); ?>">
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php elseif ($tab === 'reviews'): ?>
                <h3>My reviews</h3>
                <?php if (empty($myReviews)): ?>
                    <p class="empty-state">No reviews yet — they appear here after you rate your flowers.</p>
                <?php else: ?>
                    <?php foreach ($myReviews as $rv): ?>
                    <div class="review-item" style="margin-bottom:10px;">
                        <div class="review-head"><a href="product.php?id=<?php echo (int)$rv['product_id']; ?>"><strong><?php echo e($rv['product_name']); ?></strong></a> <?php echo stars_html($rv['rating']); ?></div>
                        <?php if (!empty($rv['comment'])): ?><p><?php echo nl2br(e($rv['comment'])); ?></p><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
