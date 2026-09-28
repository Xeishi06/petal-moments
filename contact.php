<?php
require_once __DIR__ . '/config/functions.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        flash('error', 'Session expired. Please try again.');
        redirect('contact.php');
    }
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        flash('error', 'Name, email and message are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
    } elseif (mb_strlen($message) > 2000) {
        flash('error', 'Message must be under 2000 characters.');
    } else {
        db()->prepare("
            INSERT INTO contact_messages (user_id, name, email, subject, message, status)
            VALUES (:uid, :name, :email, :subject, :msg, 'new')
        ")->execute([
            ':uid' => is_logged_in() ? $_SESSION['user_id'] : null,
            ':name' => $name,
            ':email' => $email,
            ':subject' => $subject !== '' ? $subject : null,
            ':msg' => $message,
        ]);
        flash('success', 'Message sent! We usually reply within a day.');
        redirect('contact.php');
    }
}
?>
<?php $pageTitle = 'Contact Us | Petal Moments'; include __DIR__ . '/includes/header.php'; ?>

<section class="section">
    <div class="container">
        <div class="section-heading centered">
            <span class="eyebrow">GET IN TOUCH</span>
            <h2>Contact <em>us</em></h2>
            <p>Questions about flowers, orders, or events? Send us a message.</p>
        </div>

        <div class="checkout-grid">
            <div class="checkout-form">
                <form method="post" action="contact.php" novalidate>
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
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
                    <label>Subject</label>
                    <input type="text" name="subject" maxlength="150" placeholder="e.g. Question about my order">
                    <label>Message *</label>
                    <textarea name="message" rows="5" maxlength="2000" required placeholder="How can we help?"></textarea>
                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:18px;">Send Message</button>
                </form>
            </div>

            <div class="checkout-summary">
                <h3>Visit the shop</h3>
                <div class="summary-line"><span>Address</span><span>688 B. Manuel St., Montalban, Rizal</span></div>
                <div class="summary-line"><span>Facebook</span><span>Petal Moments Flowers & Events</span></div>
                <div class="summary-line"><span>Contact</span><span>Ashlei Burdeos</span></div>
                <div class="summary-line"><span>Email</span><span>petalmoments@gmail.com</span></div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
