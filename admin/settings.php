<?php
require_once __DIR__ . '/../config/functions.php';
start_session();
require_admin();

$pdo = db();

$fields = [
    'gcash_number'      => 'GCash number',
    'gcash_name'        => 'GCash account name',
    'pickup_address'    => 'Pickup address',
    'delivery_fee'      => 'Delivery fee (₱)',
    'free_delivery_min' => 'Free delivery over (₱, 0 = always charge)',
    'announcement'      => 'Announcement bar text',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'settings') {
    $stmt = $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES (:k, :v) ON DUPLICATE KEY UPDATE svalue = :v");
    foreach ($fields as $key => $label) {
        $stmt->execute([':k' => $key, ':v' => mb_substr(trim($_POST[$key] ?? ''), 0, 255)]);
    }
    flash('success', 'Settings saved. The shop updates right away.');
    redirect('settings.php');
}

$active = 'settings';
$adminTitle = 'Settings | Petal Moments Admin';
include __DIR__ . '/includes/admin_header.php';

$current = [];
foreach (array_keys($fields) as $key) { $current[$key] = setting($key); }
?>

<div class="admin-card">
    <h1>Settings</h1>
    <p>Shop details customers see at checkout and on the site.</p>
</div>

<div class="admin-card">
    <form method="post" action="settings.php" class="admin-form">
        <input type="hidden" name="form" value="settings">
        <label>GCash number <small>(shown in the blue GCash box at checkout)</small></label>
        <input type="text" name="gcash_number" value="<?php echo e($current['gcash_number']); ?>" placeholder="e.g. 0917 123 4567" required>
        <label>GCash account name</label>
        <input type="text" name="gcash_name" value="<?php echo e($current['gcash_name']); ?>" placeholder="e.g. Ashlei B.">
        <label>Pickup address <small>(shown for pickup orders)</small></label>
        <input type="text" name="pickup_address" value="<?php echo e($current['pickup_address']); ?>" required>
        <div class="form-row">
            <div>
                <label>Delivery fee (₱) <small>(pickup is always free)</small></label>
                <input type="number" name="delivery_fee" step="0.01" min="0" value="<?php echo e($current['delivery_fee']); ?>">
            </div>
            <div>
                <label>Free delivery over (₱) <small>(0 = always charge)</small></label>
                <input type="number" name="free_delivery_min" step="0.01" min="0" value="<?php echo e($current['free_delivery_min']); ?>">
            </div>
        </div>
        <label>Announcement bar <small>(top strip on every page)</small></label>
        <input type="text" name="announcement" value="<?php echo e($current['announcement']); ?>" maxlength="255">
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
