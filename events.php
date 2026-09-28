<?php
require_once __DIR__ . '/config/functions.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $eventType  = trim($_POST['event_type'] ?? '');
    $eventDate  = $_POST['event_date'] ?? '';
    $venue      = trim($_POST['venue'] ?? '');
    $eventTime  = trim($_POST['event_time'] ?? '');
    $serviceType = trim($_POST['service_type'] ?? '');
    $prefs      = trim($_POST['preferences'] ?? '');
    $guestCount = (int)str_replace(',', '', $_POST['guest_count'] ?? 0);
    $budget     = (float)str_replace(',', '', $_POST['budget'] ?? 0);
    $message    = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $eventType === '') {
        flash('error', 'Name, email and event type are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
    } else {
        $stmt = db()->prepare("
            INSERT INTO event_inquiries (user_id, name, email, phone, event_type, event_date, venue, event_time, service_type, preferences, guest_count, budget, message, status)
            VALUES (:uid, :name, :email, :phone, :et, :ed, :venue, :etime, :stype, :prefs, :gc, :bd, :msg, 'pending')
        ");
        $stmt->execute([
            ':uid' => is_logged_in() ? $_SESSION['user_id'] : null,
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone !== '' ? $phone : null,
            ':et'  => $eventType,
            ':ed'  => $eventDate !== '' ? $eventDate : null,
            ':venue' => $venue !== '' ? $venue : null,
            ':etime' => $eventTime !== '' ? $eventTime : null,
            ':stype' => $serviceType !== '' ? $serviceType : null,
            ':prefs' => $prefs !== '' ? $prefs : null,
            ':gc'  => $guestCount > 0 ? $guestCount : null,
            ':bd'  => $budget > 0 ? $budget : null,
            ':msg' => $message,
        ]);
        flash('success', 'Your event inquiry has been sent. We will contact you soon!');
        redirect('events.php');
    }
}
?>
<?php $pageTitle = 'Events | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="event-section">
    <div class="container event-grid">
        <div class="event-photo-wrap">
            <img class="event-photo"
                 src="https://images.unsplash.com/photo-1507501336603-6e31db2be093?auto=format&fit=crop&w=1100&q=85"
                 alt="Elegant floral event styling">
            <div class="event-stat">
                <strong>100+</strong>
                <span>moments styled with love</span>
            </div>
        </div>
        <div class="event-copy">
            <span class="eyebrow">PETAL MOMENTS EVENTS</span>
            <h2>Turning celebrations into <em>beautiful memories.</em></h2>
            <p>Tell us about your event and our team will craft a floral experience to match your vision.</p>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading centered">
            <span class="eyebrow">PLAN YOUR EVENT</span>
            <h2>Send us your <em>inquiry</em></h2>
        </div>

        <div class="auth-wrap">
            <div class="auth-card">
                <form method="post" action="events.php">
                    <div class="form-row">
                        <div>
                            <label>Full name *</label>
                            <input type="text" name="name" value="<?php echo e(is_logged_in() ? $_SESSION['name'] : ''); ?>" required>
                        </div>
                        <div>
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo e(is_logged_in() ? ($_SESSION['email'] ?? '') : ''); ?>" required>
                        </div>
                    </div>

                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="09xx xxx xxxx">

                    <label>Event type *</label>
                    <select name="event_type" required>
                        <option value="">-- Select --</option>
                        <option value="Wedding">Wedding</option>
                        <option value="Birthday">Birthday</option>
                        <option value="Debut">Debut</option>
                        <option value="Corporate">Corporate</option>
                        <option value="Funeral">Funeral</option>
                        <option value="Other">Other</option>
                    </select>

                    <label>Event date</label>
                    <input type="date" name="event_date">

                    <div class="form-row">
                        <div>
                            <label>Event time</label>
                            <input type="time" name="event_time">
                        </div>
                        <div>
                            <label>Venue / location</label>
                            <input type="text" name="venue" placeholder="e.g. Casa Verde, Montalban">
                        </div>
                    </div>

                    <label>What do you need?</label>
                    <select name="service_type">
                        <option value="">-- Select --</option>
                        <option value="full_styling">Full event styling</option>
                        <option value="flowers_only">Flowers only</option>
                        <option value="setup_teardown">Setup & teardown included</option>
                    </select>

                    <div class="form-row">
                        <div>
                            <label>Guest count</label>
                            <input type="number" name="guest_count" min="0">
                        </div>
                            <div>
                                <label>Budget (₱)</label>
                                <input type="text" name="budget" inputmode="decimal" placeholder="e.g. 5,000">
                            </div>
                    </div>

                    <label>Theme, colors & flowers you like</label>
                    <textarea name="preferences" rows="2" placeholder="e.g. blush pink & white, roses and baby's breath..."></textarea>

                    <label>Anything else we should know?</label>
                    <textarea name="message" rows="4" placeholder="Program flow, setup time, other requests..."></textarea>

                    <button type="submit" class="btn btn-primary">Submit Inquiry</button>
                </form>
                <p class="auth-alt">No account needed — we'll contact you directly.</p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
