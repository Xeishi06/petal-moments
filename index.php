<?php
require_once __DIR__ . '/config/functions.php';
start_session();

$cats = db()->query("SELECT id, name, slug, image FROM categories WHERE is_active = 1 ORDER BY id")->fetchAll();
$featured = db()->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1 AND p.is_featured = 1
    ORDER BY p.id LIMIT 4
")->fetchAll();
$savedIds = [];
if (is_logged_in()) {
    $savedIds = db()->prepare("SELECT product_id FROM wishlist WHERE user_id = :u");
    $savedIds->execute([':u' => $_SESSION['user_id']]);
    $savedIds = array_map('intval', array_column($savedIds->fetchAll(), 'product_id'));
}
?>
<?php $pageTitle = 'Petal Moments | Flowers & Events'; include __DIR__ . '/includes/header.php'; ?>

        <section class="hero" id="home">
            <div class="container hero-grid">

                <div class="hero-copy">
                    <span class="eyebrow">FLOWERS FOR EVERY MOMENT</span>

                    <h1>
                        We create your dream event,
                        <em>into reality.</em>
                    </h1>

                    <p>
                        Handcrafted floral arrangements and event styling made with care
                        for birthdays, anniversaries, celebrations, and everything in between.
                    </p>

                    <div class="hero-actions">
                        <a class="btn btn-primary" href="shop.php">Shop Bouquets</a>
                        <a class="text-link" href="#events">
                            Explore Event Styling <span>→</span>
                        </a>
                    </div>

                    <div class="hero-features">
                        <div>
                            <span class="feature-icon">✿</span>
                            <div>
                                <strong>Fresh Blooms</strong>
                                <small>Carefully selected flowers</small>
                            </div>
                        </div>
                        <div>
                            <span class="feature-icon">♡</span>
                            <div>
                                <strong>Made with Love</strong>
                                <small>Designed for your special moment</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hero-visual">
                    <div class="hero-main-photo">
                        <?php
                        $hero = $featured[0] ?? null;
                        $heroSlides = [];
                        foreach ($featured as $fp) {
                            $heroSlides[] = [
                                'id'    => (int)$fp['id'],
                                'image' => $fp['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=1200&q=85',
                                'name'  => $fp['name'] ?? 'Arrangement',
                                'price' => '₱' . number_format((float)($fp['price'] ?? 0), 2),
                            ];
                        }
                        if (empty($heroSlides)) {
                            $heroSlides[] = ['id' => 0, 'image' => 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=1200&q=85', 'name' => 'Blush Garden Bouquet', 'price' => '₱899.00'];
                        }
                        ?>
                        <?php foreach ($heroSlides as $sIdx => $slide): ?>
                        <img
                            class="hero-slide<?php echo $sIdx === 0 ? ' active' : ''; ?>"
                            src="<?php echo e($slide['image']); ?>"
                            alt="<?php echo e($slide['name']); ?>"
                            <?php if ($slide['id'] > 0): ?>data-href="product.php?id=<?php echo (int)$slide['id']; ?>"<?php endif; ?>
                        />
                        <?php endforeach; ?>
                        <button type="button" class="hero-arrow hero-prev" id="heroPrev" aria-label="Previous bestseller">‹</button>
                        <button type="button" class="hero-arrow hero-next" id="heroNext" aria-label="Next bestseller">›</button>
                    </div>
                    <a class="floating-card" id="heroCard" href="<?php echo ($hero && (int)$hero['id'] > 0) ? 'product.php?id=' . (int)$hero['id'] : 'shop.php'; ?>">
                        <span>Bestseller</span>
                        <strong id="heroName"><?php echo e($hero['name'] ?? 'Blush Garden Bouquet'); ?></strong>
                        <small><span id="heroPrice">₱<?php echo number_format((float)($hero['price'] ?? 899), 2); ?></span> · <span class="hero-shop">Shop now →</span></small>
                    </a>
                    <div class="decor-flower decor-one">✿</div>
                    <div class="decor-flower decor-two">❀</div>
                </div>

            </div>

            <script>
                window.heroSlides = <?php echo json_encode($heroSlides, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            </script>
        </section>

        <section class="section categories" id="shop">
            <div class="container">

                <div class="section-heading centered">
                    <span class="eyebrow">SHOP BY OCCASION</span>
                    <h2>Flowers made for <em>your moment</em></h2>
                    <p>Find the perfect arrangement for the people and moments that matter most.</p>
                </div>

                <div class="category-grid">
                    <?php foreach ($cats as $cat): ?>
                    <a class="category-card" href="shop.php?category=<?php echo e($cat['slug']); ?>">
                        <div class="category-photo">
                            <img src="<?php echo e($cat['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=700&q=80'); ?>"
                                 alt="<?php echo e($cat['name']); ?> flowers">
                        </div>
                        <h3><?php echo e($cat['name']); ?></h3>
                        <span>Shop now →</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section featured">
            <div class="container">

                <div class="section-top">
                    <div>
                        <span class="eyebrow">CUSTOMER FAVORITES</span>
                        <h2>Fresh from our <em>flower bar</em></h2>
                    </div>
                    <a class="text-link" href="shop.php">View all arrangements <span>→</span></a>
                </div>

                <div class="product-grid">
                    <?php if (empty($featured)): ?>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <article class="product-card">
                            <div class="product-image">
                                <span class="product-badge">Bestseller</span>
                                <img src="https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=700&q=80" alt="Sample bouquet placeholder">
                            </div>
                            <div class="product-details">
                                <div>
                                    <h3>Sample Bouquet <?php echo $i; ?></h3>
                                    <p>For any occasion</p>
                                </div>
                                <strong>₱899.00</strong>
                            </div>
                        </article>
                        <?php endfor; ?>
                    <?php else: ?>
                    <?php foreach ($featured as $p): ?>
                    <article class="product-card">
                        <div class="product-image">
                            <?php if ($p['is_featured']): ?><span class="product-badge">Bestseller</span><?php endif; ?>
                            <a class="view-btn" href="product.php?id=<?php echo (int)$p['id']; ?>" aria-label="View arrangement">View</a>
                            <button type="button" class="fav-btn<?php echo in_array((int)$p['id'], $savedIds, true) ? ' active' : ''; ?>" data-wishlist="<?php echo (int)$p['id']; ?>" aria-label="Save to favorites" aria-pressed="<?php echo in_array((int)$p['id'], $savedIds, true) ? 'true' : 'false'; ?>">♥</button>
                            <img src="<?php echo e($p['image'] ?: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=700&q=80'); ?>" alt="<?php echo e($p['name'] ?? 'Arrangement'); ?>">
                        </div>
                        <div class="product-details">
                            <div>
                                <h3><?php echo e($p['name'] ?? 'Arrangement'); ?></h3>
                                <p><?php echo e($p['category_name'] ?? 'For any occasion'); ?></p>
                            </div>
                            <strong>₱<?php echo number_format((float)($p['price'] ?? 0), 2); ?></strong>
                        </div>
                    </article>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="event-section" id="events">
            <div class="container event-grid">

                <div class="event-photo-wrap">
                    <img
                        class="event-photo"
                        src="https://images.unsplash.com/photo-1507501336603-6e31db2be093?auto=format&fit=crop&w=1100&q=85"
                        alt="Elegant floral event styling"
                    />
                    <div class="event-stat">
                        <strong>100+</strong>
                        <span>moments styled with love</span>
                    </div>
                </div>

                <div class="event-copy">
                    <span class="eyebrow">PETAL MOMENTS EVENTS</span>
                    <h2>Turning celebrations into <em>beautiful memories.</em></h2>
                    <p>
                        From intimate gatherings to weddings and milestone celebrations,
                        our event styling service brings your vision to life through thoughtful
                        floral design and elegant details.
                    </p>
                    <div class="service-list">
                        <div><span>01</span><p>Wedding floral styling</p></div>
                        <div><span>02</span><p>Birthday & debut setups</p></div>
                        <div><span>03</span><p>Corporate & special events</p></div>
                        <div><span>04</span><p>Custom floral installations</p></div>
                    </div>
                    <button type="button" class="btn btn-dark" data-modal-open="eventModal">Plan Your Event</button>
                </div>

            </div>
        </section>

        <section class="event-section about-section" id="about">
            <div class="container event-grid">

                <div class="event-copy">
                    <span class="eyebrow">ABOUT PETAL MOMENTS</span>
                    <h2>Flowers with <em>heart & meaning.</em></h2>
                    <p>
                        Petal Moments Flowers & Events is a local flower shop in
                        Montalban, Rizal crafting hand-tied bouquets and event styling
                        for birthdays, weddings, debuts, and everyday moments.
                        Every arrangement is made to order with fresh, carefully
                        selected blooms.
                    </p>
                    <div class="service-list">
                        <div><span>✿</span><p>Fresh, made-to-order arrangements</p></div>
                        <div><span>✿</span><p>Wedding & event styling</p></div>
                        <div><span>✿</span><p>Personal messages & delivery notes</p></div>
                    </div>
                    <div class="hero-actions">
                        <a class="btn btn-dark" href="shop.php">Browse Flowers</a>
                        <a class="text-link" href="#contact">Visit us <span>→</span></a>
                    </div>
                </div>

                <div class="event-photo-wrap">
                    <img
                        class="event-photo about-photo"
                        src="https://images.unsplash.com/photo-1518895949257-7621c3c786d7?auto=format&fit=crop&w=1100&q=85"
                        alt="Petal Moments floral arrangements"
                    />
                </div>

            </div>
        </section>

        <section class="section how-it-works" id="how">
            <div class="container">
                <div class="section-heading centered">
                    <span class="eyebrow">SIMPLE & THOUGHTFUL</span>
                    <h2>From our hands to <em>theirs</em></h2>
                </div>
                <div class="steps-grid">
                    <div class="step">
                        <span class="step-number">01</span>
                        <div class="step-icon">✿</div>
                        <h3>Choose Your Flowers</h3>
                        <p>Browse our arrangements and find the one that feels just right.</p>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <span class="step-number">02</span>
                        <div class="step-icon">✎</div>
                        <h3>Make It Personal</h3>
                        <p>Add your recipient details, message, and preferred delivery date.</p>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <span class="step-number">03</span>
                        <div class="step-icon">♡</div>
                        <h3>We Create & Deliver</h3>
                        <p>We prepare your flowers with care and send them on their way.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="newsletter">
            <div class="container newsletter-inner">
                <div>
                    <span class="eyebrow">A LITTLE MORE BEAUTY IN YOUR INBOX</span>
                    <h2>Stay in bloom.</h2>
                    <p>Get notified first when new flowers arrive or when blooms go on sale — straight to your inbox.</p>
                </div>
                <form class="newsletter-form" method="post" action="newsletter.php">
                    <input type="email" name="email" placeholder="Enter your email address" aria-label="Email address" required>
                    <button type="submit" class="btn btn-primary">Join Us</button>
                </form>
            </div>
        </section>

        <div class="modal-overlay" id="eventModal" aria-hidden="true">
            <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="eventModalTitle">
                <button type="button" class="modal-close" data-modal-close aria-label="Close">✕</button>

                <div class="modal-head">
                    <span class="modal-flowers" aria-hidden="true">✿ ❀ ✿</span>
                    <span class="eyebrow">PLAN YOUR EVENT</span>
                    <h2 id="eventModalTitle">Tell us about <em>your celebration</em></h2>
                    <p>Fill this out and our team will reach out to craft a floral experience for your moment.</p>
                </div>

                <form id="eventInquiryForm" novalidate>
                    <div class="event-form">
                        <p class="form-section">Your details</p>
                        <div class="form-row">
                            <div>
                                <label>Full name <span class="req">*</span></label>
                                <input type="text" name="name" placeholder="Juan Dela Cruz" required>
                            </div>
                            <div>
                                <label>Email <span class="req">*</span></label>
                                <input type="email" name="email" placeholder="you@email.com" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label>Phone</label>
                                <input type="text" name="phone" placeholder="09xx xxx xxxx">
                            </div>
                            <div>
                                <label>Event date</label>
                                <input type="date" name="event_date">
                            </div>
                        </div>

                        <p class="form-section">Event details</p>
                        <div class="form-row">
                            <div>
                                <label>Time</label>
                                <input type="time" name="event_time">
                            </div>
                            <div>
                                <label>Venue</label>
                                <input type="text" name="venue" placeholder="Casa Verde, Montalban">
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label>Type <span class="req">*</span></label>
                                <select name="event_type" required>
                                    <option value="">-- Select --</option>
                                    <option value="Wedding">Wedding</option>
                                    <option value="Birthday">Birthday</option>
                                    <option value="Debut">Debut</option>
                                    <option value="Corporate">Corporate</option>
                                    <option value="Funeral">Funeral</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label>Service needed</label>
                                <select name="service_type">
                                    <option value="">-- Select --</option>
                                    <option value="full_styling">Full styling</option>
                                    <option value="flowers_only">Flowers only</option>
                                    <option value="setup_teardown">Setup & teardown</option>
                                </select>
                            </div>
                        </div>

                        <p class="form-section">Style & budget</p>
                        <div class="form-row">
                            <div>
                                <label>Guests</label>
                                <input type="number" name="guest_count" min="0" placeholder="0">
                            </div>
                            <div>
                                <label>Budget (₱)</label>
                                <input type="text" name="budget" inputmode="decimal" placeholder="e.g. 5,000">
                            </div>
                        </div>

                        <label>Theme, colors & flowers</label>
                        <textarea name="preferences" rows="2" placeholder="Blush pink & white, roses..."></textarea>

                        <label>Anything else?</label>
                        <textarea name="message" rows="2" placeholder="Program flow, setup time..."></textarea>

                        <div class="submit-wrap">
                            <button type="submit" class="btn btn-primary">Send Inquiry</button>
                        </div>
                        <p class="modal-note">No account needed — we'll contact you directly.</p>
                        <div class="modal-message" role="status"></div>
                    </div>
                </form>
            </div>
        </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
