<?php
/**
 * bloom-aura/index.php
 * Homepage — hero banner + category strip + featured products
 *            + why-section + newsletter.
 * UI pixel-matched to bloom_aura reference HTML.
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';

/* ── Fetch featured / latest bouquets ── */
$featured = [];
try {
    $pdo  = getPDO();
    $stmt = $pdo->query(
        "SELECT b.id, b.name, b.slug, b.price, b.image, b.stock,
                c.name AS category_name,
                ROUND(COALESCE(AVG(r.rating), 0), 1) AS avg_rating,
                COUNT(r.id) AS review_count
         FROM   bouquets b
         LEFT JOIN categories c ON c.id = b.category_id
         LEFT JOIN reviews    r ON r.bouquet_id = b.id
         WHERE  b.is_active = 1
         GROUP  BY b.id, c.name
         ORDER  BY b.created_at DESC
         LIMIT  8"
    );
    $featured = $stmt->fetchAll();
} catch (RuntimeException $e) {
    $featured = [];
}

/* ── Wishlist IDs for logged-in user ── */
$wishlistIds = [];
if (!empty($_SESSION['user_id'])) {
    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT bouquet_id FROM wishlist WHERE user_id = ?'
        );
        $stmt->execute([(int)$_SESSION['user_id']]);
        $wishlistIds = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    } catch (\Exception $e) {
        $wishlistIds = [];
    }
}

$pageTitle = 'Bloom Aura — Fresh Flowers & Gifts';
$pageCss   = 'home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ═══════════════════════════════════════════
     HERO
════════════════════════════════════════════ -->
<section class="hero-section" aria-label="Hero banner">

    <!-- Floating petal decorations -->
    <span class="hero-deco" aria-hidden="true">🌸</span>
    <span class="hero-deco" aria-hidden="true">🌺</span>
    <span class="hero-deco" aria-hidden="true">🌷</span>
    <span class="hero-deco" aria-hidden="true">💐</span>

    <!-- Decorative radial blobs -->
    <div class="hero-blob hero-blob--top"    aria-hidden="true"></div>
    <div class="hero-blob hero-blob--bottom" aria-hidden="true"></div>

    <div class="hero-inner">

        <div class="hero-badge">🌸 Same-Day Delivery Available</div>

        <h1 class="hero-title">
            BloomAura<br>
            <em>"Where Every Petal Tells a Story"</em>
        </h1>

        <p class="hero-sub">
            Bouquets &middot; Hampers &middot; Calligraphy &middot; Handcrafted Gifts
        </p>

        <!-- Glass service-tag pills -->
        <div class="hero-pills" aria-label="Our offerings">
            <span class="hero-pill">💐 Custom Bouquets</span>
            <span class="hero-pill">🎁 Gift Hampers</span>
            <span class="hero-pill">✍️ Calligraphy</span>
            <span class="hero-pill">🍫 Chocolate Gifts</span>
            <span class="hero-pill">💌 Made With Love</span>
        </div>

        <!-- Thin divider line matching reference -->
        <div class="hero-divider" aria-hidden="true"></div>

        <!-- CTA buttons -->
        <div class="hero-cta-row">
            <a href="/bloom-aura/pages/shop.php" class="hero-btn-primary">
                🛍️ Shop Now
            </a>
            <?php if (empty($_SESSION['user_id'])): ?>
            <a href="/bloom-aura/pages/login.php" class="hero-btn-ghost">
                👀 Browse as Guest
            </a>
            <?php endif; ?>
        </div>

        <!-- Trust badges -->
        <div class="hero-trust" aria-label="Trust indicators">
            <span>✅ 500+ Happy Customers</span>
            <span>🚚 Free Delivery over ₹999</span>
            <span>⭐ 4.8/5 Rating</span>
        </div>

    </div>
</section>

<!-- ═══════════════════════════════════════════
     CATEGORY STRIP
════════════════════════════════════════════ -->
<nav class="category-strip" aria-label="Browse by category">
    <a href="/bloom-aura/pages/shop.php"                 class="cat-pill active">🌺 All</a>
    <a href="/bloom-aura/pages/shop.php?cat=bouquets"   class="cat-pill">💐 Bouquets</a>
    <a href="/bloom-aura/pages/shop.php?cat=hampers"    class="cat-pill">🎁 Hampers</a>
    <a href="/bloom-aura/pages/shop.php?cat=chocolates" class="cat-pill">🍫 Chocolates</a>
    <a href="/bloom-aura/pages/shop.php?cat=perfumes"   class="cat-pill">🌹 Perfumes</a>
    <a href="/bloom-aura/pages/shop.php?cat=plants"     class="cat-pill">🪴 Plants</a>
</nav>

<!-- ═══════════════════════════════════════════
     FEATURED / NEW ARRIVALS
════════════════════════════════════════════ -->
<div class="page-container">

    <div class="section-header">
        <h2 class="section-title">🌷 New Arrivals</h2>
        <a href="/bloom-aura/pages/shop.php" class="section-link">View All →</a>
    </div>

    <?php if (empty($featured)): ?>
        <div class="empty-state">
            <div class="empty-icon">🌷</div>
            <h2>No products yet</h2>
            <p>Check back soon — beautiful bouquets are on their way!</p>
            <a href="/bloom-aura/pages/shop.php" class="btn btn-primary">Go to Shop</a>
        </div>

    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($featured as $b):
                $isWishlisted = in_array((int)$b['id'], $wishlistIds, true);
                $inStock      = (int)$b['stock'] > 0;
            ?>
            <article class="product-card">

                <!-- Image + badges -->
                <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($b['slug']) ?>"
                   class="card-img-wrap">
                    <img
                        src="/bloom-aura/uploads/<?= htmlspecialchars($b['image'], ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($b['name'],  ENT_QUOTES, 'UTF-8') ?>"
                        loading="lazy"
                        width="400" height="300"
                        onerror="this.src='/bloom-aura/assets/img/placeholder.jpg'"
                    >
                    <?php if (!$inStock): ?>
                        <span class="badge badge-oos">Out of Stock</span>
                    <?php elseif ((int)$b['stock'] <= 5): ?>
                        <span class="badge badge-low">Only <?= (int)$b['stock'] ?> left!</span>
                    <?php endif; ?>
                </a>

                <!-- Wishlist heart (logged-in only) -->
                <?php if (!empty($_SESSION['user_id'])): ?>
                <form action="/bloom-aura/pages/wishlist.php" method="POST" class="wishlist-form">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action"     value="toggle">
                    <input type="hidden" name="bouquet_id" value="<?= (int)$b['id'] ?>">
                    <button
                        type="submit"
                        class="card-wishlist-btn<?= $isWishlisted ? ' wishlisted' : '' ?>"
                        aria-label="<?= $isWishlisted ? 'Remove from wishlist' : 'Add to wishlist' ?>"
                        title="<?= $isWishlisted ? 'Remove from wishlist' : 'Save to wishlist' ?>"
                    ><?= $isWishlisted ? '❤️' : '🤍' ?></button>
                </form>
                <?php endif; ?>

                <!-- Card body -->
                <div class="card-body">
                    <p class="card-category">
                        <?= htmlspecialchars($b['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <h3 class="card-title">
                        <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($b['slug']) ?>">
                            <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </h3>

                    <!-- Stars -->
                    <?php if ((int)$b['review_count'] > 0): ?>
                    <div class="card-stars" aria-label="Rating: <?= $b['avg_rating'] ?> out of 5">
                        <?php
                        $avg = (float)$b['avg_rating'];
                        for ($i = 1; $i <= 5; $i++):
                            $cls = $avg >= $i ? 'full' : ($avg >= $i - 0.5 ? 'half' : 'empty');
                        ?>
                            <span class="star <?= $cls ?>" aria-hidden="true">★</span>
                        <?php endfor; ?>
                        <span class="review-count">(<?= (int)$b['review_count'] ?>)</span>
                    </div>
                    <?php endif; ?>

                    <!-- Price + Add to Cart -->
                    <div class="card-footer">
                        <span class="price">₹<?= number_format($b['price'], 2) ?></span>
                        <?php if ($inStock): ?>
                            <form action="/bloom-aura/pages/cart.php" method="POST" class="home-add-form">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action"     value="add">
                                <input type="hidden" name="product_id" value="<?= (int)$b['id'] ?>">
                                <input type="hidden" name="qty"        value="1">
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-sm home-add-btn"
                                    data-name="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    🛒 Add
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-disabled" disabled>Sold Out</button>
                        <?php endif; ?>
                    </div>
                </div>

            </article>
            <?php endforeach; ?>
        </div>

        <div class="view-all-center">
            <a href="/bloom-aura/pages/shop.php" class="btn btn-outline btn-lg">
                View All Products →
            </a>
        </div>

    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     WHY BLOOM AURA
════════════════════════════════════════════ -->
<section class="why-section" aria-labelledby="why-heading">
    <div class="page-container">
        <h2 class="section-title page-section-title" id="why-heading">
            💖 Why Choose Bloom Aura?
        </h2>
        <div class="why-grid">
            <div class="why-card">
                <div class="why-icon" aria-hidden="true">🌸</div>
                <h3>Fresh Every Day</h3>
                <p>Flowers sourced fresh daily. No wilted blooms — ever.</p>
            </div>
            <div class="why-card">
                <div class="why-icon" aria-hidden="true">🚚</div>
                <h3>Same-Day Delivery</h3>
                <p>Order before 2 PM and we deliver the same day.</p>
            </div>
            <div class="why-card">
                <div class="why-icon" aria-hidden="true">🎨</div>
                <h3>Custom Bouquets</h3>
                <p>Pick your flowers, colours &amp; wrapping — made just for you.</p>
            </div>
            <div class="why-card">
                <div class="why-icon" aria-hidden="true">⭐</div>
                <h3>500+ Happy Customers</h3>
                <p>Rated 4.8/5 by our lovely community of customers.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     NEWSLETTER STRIP
════════════════════════════════════════════ -->
<section class="newsletter-strip" aria-labelledby="newsletter-heading">
    <h2 id="newsletter-heading">🌺 Stay in Bloom</h2>
    <p>Get exclusive deals, new arrivals &amp; seasonal offers straight to your inbox.</p>
    <form class="newsletter-form" id="newsletter-form" novalidate>
        <label for="newsletter-email" class="sr-only">Your email address</label>
        <input
            type="email"
            id="newsletter-email"
            name="email"
            placeholder="your@email.com"
            autocomplete="email"
            required
        >
        <button type="submit">Subscribe</button>
    </form>
    <p class="newsletter-error" id="newsletter-error" role="alert" aria-live="polite"></p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>