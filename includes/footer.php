</main>

<footer class="footer" id="contact">

    <div class="container footer-grid">

        <div class="footer-brand">
            <a class="brand light" href="index.php#top">
                <img class="brand-logo" src="Petal Moments Logo.png" alt="Petal Moments Logo">
                <span>
                    <strong>Petal Moments</strong>
                    <small>Flowers & Events</small>
                </span>
            </a>
            <p>Thoughtful flowers and event styling for life's sweetest moments.</p>
        </div>

        <div>
            <h4>Shop</h4>
            <a href="shop.php?category=birthday">Birthday</a>
            <a href="shop.php?category=weddings">Weddings</a>
            <a href="shop.php?category=bouquet">Bouquets</a>
            <a href="shop.php?category=funeral">Funeral</a>
        </div>

        <div>
            <h4>Services</h4>
            <a href="index.php#events">Event Styling</a>
            <a href="index.php#events">Weddings</a>
            <a href="index.php#events">Custom Orders</a>
            <a href="index.php#events">Corporate Events</a>
        </div>

        <div>
            <h4>Contact</h4>
            <p>Address: 688 B. Manuel St., Montalban, Rizal</p>
            <p>Facebook: Petal Moments Flowers & Events</p>
            <p>Contact Person: Ashlei Burdeos</p>
            <p>Gmail: petalmoments@gmail.com</p>
            <p>Philippines</p>
        </div>

    </div>

    <div class="container footer-bottom">
        <p>© 2025 Petal Moments Flowers & Events. All rights reserved.</p>
        <div>
            <a href="#">Privacy</a>
            <a href="#">Terms</a>
        </div>
    </div>

</footer>

<div class="modal-overlay" id="logoutModal" aria-hidden="true">
    <div class="modal-card auth-modal-card" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
        <button type="button" class="modal-close" data-logout-close aria-label="Close">✕</button>
        <div class="auth-modal-body">
            <span class="auth-modal-heart" aria-hidden="true">👋</span>
            <span class="eyebrow">SEE YOU SOON</span>
            <h2 id="logoutModalTitle">Log out of <em>Petal Moments?</em></h2>
            <p>You'll need to log back in to order flowers or view your favorites.</p>
            <div class="auth-modal-actions">
                <button type="button" class="btn btn-dark" data-logout-close>Stay Logged In</button>
                <a class="btn btn-primary" id="logoutConfirm" href="logout.php">Log Out</a>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="authModal" aria-hidden="true">
    <div class="modal-card auth-modal-card" role="dialog" aria-modal="true" aria-labelledby="authModalTitle">
        <button type="button" class="modal-close" data-auth-close aria-label="Close">✕</button>
        <div class="auth-modal-body">
            <span class="auth-modal-heart" aria-hidden="true">♡</span>
            <span class="eyebrow">SAVE YOUR FAVORITES</span>
            <h2 id="authModalTitle">Log in to keep <em>this bloom</em></h2>
            <p>Create a free account to save favorites, check out faster, and track your orders.</p>
            <div class="auth-modal-actions">
                <a class="btn btn-primary" href="login.php">Log In</a>
                <a class="btn btn-dark" href="register.php">Create Account</a>
            </div>
            <p class="modal-note">Free forever — no spam, just flowers.</p>
        </div>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>
