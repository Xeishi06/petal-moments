<?php
require_once __DIR__ . '/config/functions.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $eventType  = trim($_POST['event_type'] ?? '');
    $eventDate  = $_POST['event_date'] ?? '';
    $guestCount = (int)($_POST['guest_count'] ?? 0);
    $budget     = (float)($_POST['budget'] ?? 0);
    $message    = trim($_POST['message'] ?? '');

    if ($eventType === '') {
        flash('error', 'Please select an event type.');
    } else {
        $stmt = db()->prepare("
            INSERT INTO event_inquiries (user_id, event_type, event_date, guest_count, budget, message, status)
            VALUES (:uid, :et, :ed, :gc, :bd, :msg, 'pending')
        ");
        $stmt->execute([
            ':uid' => is_logged_in() ? $_SESSION['user_id'] : null,
            ':et'  => $eventType,
            ':ed'  => $eventDate !== '' ? $eventDate : null,
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
                <?php if (is_logged_in()): ?>
                <form method="post" action="events.php">
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
                            <label>Guest count</label>
                            <input type="number" name="guest_count" min="0">
                        </div>
                        <div>
                            <label>Budget (₱)</label>
                            <input type="number" name="budget" min="0" step="0.01">
                        </div>
                    </div>

                    <label>Tell us about your event</label>
                    <textarea name="message" rows="4" placeholder="Venue, theme, colors, flowers you like..."></textarea>

                    <button type="submit" class="btn btn-primary">Submit Inquiry</button>
                </form>
                <?php else: ?>
                    <h2>Log in to send an inquiry</h2>
                    <p class="empty-state">You must be signed in to submit an event styling inquiry.</p>
                    <a class="btn btn-primary" href="login.php">Log In</a>
                    <a class="btn btn-dark" href="register.php">Create Account</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
