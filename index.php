<?php
/**
 * bloom-aura/index.php
 * Homepage — hero banner + featured products.
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';

// Fetch featured/latest bouquets
$featured = [];
try {
    $pdo  = getPDO();
    $stmt = $pdo->query(
        "SELECT b.id, b.name, b.slug, b.price, b.image, b.stock,
                c.name AS category_name,
                ROUND(COALESCE(AVG(r.rating), 0), 1) AS avg_rating,
                COUNT(r.id) AS review_count
         FROM bouquets b
         LEFT JOIN categories c  ON c.id = b.category_id
         LEFT JOIN reviews   r  ON r.bouquet_id = b.id
         WHERE b.is_active = 1
         GROUP BY b.id, c.name
         ORDER BY b.created_at DESC
         LIMIT 8"
    );
    $featured = $stmt->fetchAll();
} catch (RuntimeException $e) {
    $featured = [];
}

$pageTitle = 'Bloom Aura — Fresh Flowers & Gifts';
$pageCss = 'home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ═══════════════════════════════════════════
     HERO — dark gradient matching reference
════════════════════════════════════════════ -->
<section class="hero-section">
    <!-- Decorative blobs (like reference radial gradients) -->
    <div class="hero-blob hero-blob--top"></div>
    <div class="hero-blob hero-blob--bottom"></div>

    <div class="hero-inner">
        <div class="hero-badge">🌸 Same-Day Delivery Available</div>

        <h1 class="hero-title">
            Fresh Blooms,<br>
            <em>Delivered with Love</em>
        </h1>

        <p class="hero-sub">
            Hand-crafted bouquets, hampers &amp; gifts for every occasion.<br>
            Surprise someone special today.
        </p>

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
        <div class="hero-trust">
            <span>✅ 500+ Happy Customers</span>
            <span>🚚 Free Delivery over ₹999</span>
            <span>⭐ 4.8/5 Rating</span>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     CATEGORY PILLS
════════════════════════════════════════════ -->
<div class="category-strip">
    <a href="/bloom-aura/pages/shop.php" class="cat-pill active">🌺 All</a>
    <a href="/bloom-aura/pages/shop.php?cat=bouquets"   class="cat-pill">💐 Bouquets</a>
    <a href="/bloom-aura/pages/shop.php?cat=hampers"    class="cat-pill">🎁 Hampers</a>
    <a href="/bloom-aura/pages/shop.php?cat=chocolates" class="cat-pill">🍫 Chocolates</a>
    <a href="/bloom-aura/pages/shop.php?cat=perfumes"   class="cat-pill">🌹 Perfumes</a>
    <a href="/bloom-aura/pages/shop.php?cat=plants"     class="cat-pill">🪴 Plants</a>
</div>

<!-- ═══════════════════════════════════════════
     FEATURED PRODUCTS
════════════════════════════════════════════ -->
<div class="page-container">

    <div class="section-header">
        <h2 class="section-title">🌷 New Arrivals</h2>
        <a href="/bloom-aura/pages/shop.php" class="section-link">View All →</a>
    </div>

    <?php if (empty($featured)): ?>
        <!-- Empty state — DB not set up yet -->
        <div class="empty-state">
            <div class="empty-icon">🌷</div>
            <h2>No products yet</h2>
            <p>Set up the database to see products here.</p>
            <a href="/bloom-aura/pages/shop.php" class="btn btn-primary">Go to Shop</a>
        </div>

    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($featured as $b): ?>
                <article class="product-card">

                    <!-- Image -->
                    <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($b['slug']) ?>"
                       class="card-img-wrap">
                        <img
                            src="/bloom-aura/uploads/<?= htmlspecialchars($b['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy"
                            onerror="this.src='/bloom-aura/assets/img/placeholder.jpg'"
                        >
                        <?php if ($b['stock'] <= 0): ?>
                            <span class="badge badge-oos">Out of Stock</span>
                        <?php elseif ($b['stock'] <= 5): ?>
                            <span class="badge badge-low">Only <?= (int)$b['stock'] ?> left!</span>
                        <?php endif; ?>
                    </a>

                    <!-- Body -->
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
                        <?php if ($b['review_count'] > 0): ?>
                        <div class="card-stars">
                            <?php
                            $avg = (float)$b['avg_rating'];
                            for ($i = 1; $i <= 5; $i++):
                                $cls = $avg >= $i ? 'full' : ($avg >= $i - 0.5 ? 'half' : 'empty');
                            ?>
                                <span class="star <?= $cls ?>">★</span>
                            <?php endfor; ?>
                            <span class="review-count">(<?= (int)$b['review_count'] ?>)</span>
                        </div>
                        <?php endif; ?>

                        <div class="card-footer">
                            <span class="price">₹<?= number_format($b['price'], 2) ?></span>
                            <?php if ($b['stock'] > 0): ?>
                                <form action="/bloom-aura/pages/cart.php" method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action"     value="add">
                                    <input type="hidden" name="product_id" value="<?= (int)$b['id'] ?>">
                                    <input type="hidden" name="qty"        value="1">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        🛒 Add
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-disabled" disabled>
                                    Sold Out
                                </button>
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
     WHY BLOOM AURA SECTION
════════════════════════════════════════════ -->
<section class="why-section">
    <div class="page-container">
        <h2 class="section-title page-section-title">
            💖 Why Choose Bloom Aura?
        </h2>
        <div class="why-grid">
            <div class="why-card">
                <div class="why-icon">🌸</div>
                <h3>Fresh Every Day</h3>
                <p>Flowers sourced fresh daily. No wilted blooms — ever.</p>
            </div>
            <div class="why-card">
                <div class="why-icon">🚚</div>
                <h3>Same-Day Delivery</h3>
                <p>Order before 2 PM and we deliver the same day.</p>
            </div>
            <div class="why-card">
                <div class="why-icon">🎨</div>
                <h3>Custom Bouquets</h3>
                <p>Pick your flowers, colours & wrapping — made just for you.</p>
            </div>
            <div class="why-card">
                <div class="why-icon">⭐</div>
                <h3>500+ Happy Customers</h3>
                <p>Rated 4.8/5 by our lovely community of customers.</p>
            </div>
        </div>
    </div>
</section>



<?php require_once __DIR__ . '/includes/footer.php'; ?>