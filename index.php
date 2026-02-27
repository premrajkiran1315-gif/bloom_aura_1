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
                                <button class="btn btn-sm" disabled
                                        style="background:#eee;color:#aaa;cursor:not-allowed;">
                                    Sold Out
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center; margin: 2.5rem 0 1rem;">
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
        <h2 class="section-title" style="text-align:center; margin-bottom:2rem;">
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

<!-- ── Page-specific styles ── -->
<style>
/* ── HERO ── */
.hero-section {
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #b5005b 0%, #d63384 40%, #ff4d94 75%, #ffb3d1 100%);
    min-height: 88vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 5rem 1.5rem;
    text-align: center;
}
.hero-blob {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}
.hero-blob--top {
    top: -15%;
    left: -10%;
    width: 55%;
    height: 55%;
    background: radial-gradient(circle, rgba(255,255,255,.12) 0%, transparent 65%);
}
.hero-blob--bottom {
    bottom: -15%;
    right: -10%;
    width: 50%;
    height: 50%;
    background: radial-gradient(circle, rgba(173,20,87,.3) 0%, transparent 65%);
}
.hero-inner {
    position: relative;
    z-index: 1;
    max-width: 700px;
}
.hero-badge {
    display: inline-block;
    background: rgba(255,255,255,.18);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.3);
    color: #fff;
    font-size: .82rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: .45rem 1.1rem;
    border-radius: 20px;
    margin-bottom: 1.75rem;
}
.hero-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(2.4rem, 6vw, 4rem);
    font-weight: 700;
    color: #fff;
    line-height: 1.18;
    margin-bottom: 1.25rem;
    text-shadow: 0 2px 20px rgba(0,0,0,.15);
}
.hero-title em {
    font-style: italic;
    color: #ffe0ef;
}
.hero-sub {
    font-size: 1.1rem;
    color: rgba(255,255,255,.85);
    margin-bottom: 2.25rem;
    line-height: 1.65;
}
.hero-cta-row {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}
.hero-btn-primary {
    background: #fff;
    color: #d63384;
    border: 2px solid #fff;
    padding: 14px 38px;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .25s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    box-shadow: 0 6px 24px rgba(0,0,0,.15);
}
.hero-btn-primary:hover {
    background: #d63384;
    color: #fff;
    border-color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0,0,0,.2);
    text-decoration: none;
}
.hero-btn-ghost {
    background: rgba(255,255,255,.15);
    color: #fff;
    border: 2px solid rgba(255,255,255,.5);
    padding: 14px 36px;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    backdrop-filter: blur(8px);
}
.hero-btn-ghost:hover {
    background: rgba(255,255,255,.28);
    border-color: rgba(255,255,255,.8);
    transform: translateY(-2px);
    text-decoration: none;
    color: #fff;
}
.hero-trust {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    flex-wrap: wrap;
    color: rgba(255,255,255,.75);
    font-size: .82rem;
    font-weight: 600;
}
.hero-trust span {
    display: flex;
    align-items: center;
    gap: .3rem;
}

/* ── Category strip ── */
.category-strip {
    display: flex;
    gap: .6rem;
    justify-content: center;
    flex-wrap: wrap;
    padding: 1.5rem 1rem;
    background: #fff;
    border-bottom: 1px solid #fce4ec;
}
.cat-pill {
    background: #fff;
    border: 1.5px solid #fce4ec;
    color: #555;
    padding: .5rem 1.1rem;
    border-radius: 20px;
    font-size: .85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s;
    white-space: nowrap;
}
.cat-pill:hover, .cat-pill.active {
    background: #d63384;
    border-color: #d63384;
    color: #fff;
    text-decoration: none;
}

/* ── Section header ── */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-top: 1rem;
}
.section-title { font-size: 1.75rem; color: #2d2d2d; }
.section-link {
    font-size: .88rem;
    font-weight: 600;
    color: #d63384;
    text-decoration: none;
}
.section-link:hover { text-decoration: underline; }

/* ── Product grid ── */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 20px;
}
.product-card {
    border: 1px solid #fce4ec;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    transition: transform .3s, box-shadow .3s;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 28px rgba(214,51,132,.15);
}
.card-img-wrap {
    display: block;
    position: relative;
    overflow: hidden;
    height: 200px;
    background: #fce4ec;
}
.card-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .4s;
}
.product-card:hover .card-img-wrap img { transform: scale(1.06); }
.badge {
    position: absolute;
    top: 10px; left: 10px;
    padding: .3rem .65rem;
    border-radius: 6px;
    font-size: .72rem;
    font-weight: 700;
}
.badge-oos { background: #fee2e2; color: #dc2626; }
.badge-low { background: #fef3c7; color: #d97706; }
.card-body { padding: .9rem 1rem 1rem; }
.card-category {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #d63384;
    margin-bottom: .3rem;
}
.card-title {
    font-size: .95rem;
    font-weight: 600;
    margin-bottom: .5rem;
    line-height: 1.3;
}
.card-title a { color: #2d2d2d; text-decoration: none; }
.card-title a:hover { color: #d63384; }
.card-stars {
    display: flex;
    align-items: center;
    gap: 1px;
    margin-bottom: .5rem;
}
.star.full  { color: #f59e0b; }
.star.half  { color: #f59e0b; }
.star.empty { color: #d1d5db; }
.review-count { font-size: .72rem; color: #888; margin-left: .3rem; }
.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: .6rem;
}
.price {
    font-size: 1.15rem;
    font-weight: 700;
    color: #d63384;
    font-family: 'Playfair Display', Georgia, serif;
}

/* ── Why section ── */
.why-section {
    background: linear-gradient(135deg, #fff0f5, #fce4ec);
    padding: 4rem 1rem;
    margin-top: 3rem;
}
.why-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}
.why-card {
    background: #fff;
    border-radius: 14px;
    padding: 2rem 1.5rem;
    text-align: center;
    box-shadow: 0 4px 16px rgba(214,51,132,.08);
    border: 1px solid #fce4ec;
    transition: transform .3s;
}
.why-card:hover { transform: translateY(-4px); }
.why-icon { font-size: 2.2rem; margin-bottom: .75rem; }
.why-card h3 { font-size: 1rem; margin-bottom: .4rem; color: #2d2d2d; }
.why-card p  { font-size: .85rem; color: #777; line-height: 1.5; }

/* Responsive */
@media (max-width: 600px) {
    .hero-section { min-height: 70vh; padding: 4rem 1rem; }
    .hero-title { font-size: 2rem; }
    .hero-cta-row { flex-direction: column; align-items: center; }
    .hero-btn-primary, .hero-btn-ghost { width: 100%; max-width: 280px; justify-content: center; }
    .product-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>