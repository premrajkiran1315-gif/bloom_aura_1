<?php
/**
 * bloom-aura/index.php
 * Homepage — shows hero banner + featured products.
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/flash.php';

// Fetch featured/latest bouquets
$featured = [];
try {
    $pdo = getPDO();
    $stmt = $pdo->query(
        "SELECT b.id, b.name, b.slug, b.price, b.image, b.stock,
                c.name AS category_name
         FROM bouquets b
         LEFT JOIN categories c ON c.id = b.category_id
         WHERE b.is_active = 1
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

<!-- ── HERO ── -->
<section class="hero-section" style="
    background: linear-gradient(135deg, #fff0f5 0%, #fce4ec 100%);
    padding: 4rem 1rem;
    text-align: center;
">
    <div style="max-width: 680px; margin: 0 auto;">
        <h1 style="font-size: 2.8rem; color: #c2185b; margin-bottom: 1rem;">
            🌸 Fresh Blooms, Delivered with Love
        </h1>
        <p style="font-size: 1.15rem; color: #555; margin-bottom: 2rem;">
            Hand-crafted bouquets, hampers &amp; gifts for every occasion.
            Same-day delivery available.
        </p>
        <a href="/pages/shop.php" class="btn btn-primary" style="font-size: 1.1rem; padding: .85rem 2.5rem;">
            Shop Now
        </a>
    </div>
</section>

<!-- ── FEATURED PRODUCTS ── -->
<div class="page-container">
    <h2 class="section-title" style="margin-top: 2.5rem; margin-bottom: 1.5rem;">
        🌷 New Arrivals
    </h2>

    <?php if (empty($featured)): ?>
        <div class="empty-state">
            <div class="empty-icon">🌷</div>
            <h2>No products yet</h2>
            <p>Check back soon — beautiful blooms are on their way!</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($featured as $b): ?>
                <article class="product-card">
                    <a href="/pages/product.php?slug=<?= urlencode($b['slug']) ?>" class="card-img-wrap">
                        <img
                            src="/uploads/bouquets/<?= htmlspecialchars($b['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy" width="300" height="250"
                            onerror="this.src='/assets/img/placeholder.jpg'"
                        >
                        <?php if ($b['stock'] <= 0): ?>
                            <span class="badge badge-oos">Out of Stock</span>
                        <?php elseif ($b['stock'] <= 5): ?>
                            <span class="badge badge-low">Only <?= (int)$b['stock'] ?> left</span>
                        <?php endif; ?>
                    </a>
                    <div class="card-body">
                        <p class="card-category"><?= htmlspecialchars($b['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <h2 class="card-title">
                            <a href="/pages/product.php?slug=<?= urlencode($b['slug']) ?>">
                                <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h2>
                        <div class="card-footer">
                            <span class="price">₹<?= number_format($b['price'], 2) ?></span>
                            <?php if ($b['stock'] > 0): ?>
                                <form action="/pages/cart.php" method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="bouquet_id" value="<?= (int)$b['id'] ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button type="submit" class="btn btn-primary btn-sm">Add to Cart</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center; margin: 2.5rem 0;">
            <a href="/pages/shop.php" class="btn btn-outline">View All Products</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
