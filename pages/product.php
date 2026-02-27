<?php
/**
 * bloom-aura/pages/product.php
 * Single bouquet detail — redesigned UI matching reference.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$loggedIn = !empty($_SESSION['user_id']);
$userId   = (int)($_SESSION['user_id'] ?? 0);

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') { header('Location: /bloom-aura/pages/shop.php'); exit; }

try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare(
        "SELECT b.*, c.name AS category_name, c.slug AS category_slug
         FROM bouquets b
         LEFT JOIN categories c ON c.id = b.category_id
         WHERE b.slug = ? LIMIT 1"
    );
    $stmt->execute([$slug]);
    $bouquet = $stmt->fetch();
} catch (RuntimeException $e) { $bouquet = null; }

if (!$bouquet) {
    http_response_code(404);
    flash('Bouquet not found.', 'error');
    header('Location: /bloom-aura/pages/shop.php');
    exit;
}
$bouquetId = (int)$bouquet['id'];

// ── Handle POST actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_to_cart') {
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        if ($bouquet['stock'] <= 0) {
            flash('Sorry, this item is out of stock.', 'error');
        } else {
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            $cart = &$_SESSION['cart'];
            if (isset($cart[$bouquetId])) {
                $cart[$bouquetId]['qty'] = min($cart[$bouquetId]['qty'] + $qty, $bouquet['stock']);
            } else {
                $cart[$bouquetId] = [
                    'id'    => $bouquetId,
                    'name'  => $bouquet['name'],
                    'price' => $bouquet['price'],
                    'image' => $bouquet['image'],
                    'qty'   => min($qty, $bouquet['stock']),
                ];
            }
            flash(htmlspecialchars($bouquet['name']) . ' added to cart! 🛒', 'success');
        }
        header('Location: /bloom-aura/pages/product.php?slug=' . urlencode($slug));
        exit;
    }

    if ($action === 'toggle_wishlist') {
        if (!$loggedIn) {
            flash('Please log in to save to your wishlist.', 'info');
            header('Location: /bloom-aura/pages/login.php');
            exit;
        }
        try {
            $pdo   = getPDO();
            $check = $pdo->prepare('SELECT id FROM wishlist WHERE user_id = ? AND bouquet_id = ?');
            $check->execute([$userId, $bouquetId]);
            if ($check->fetch()) {
                $pdo->prepare('DELETE FROM wishlist WHERE user_id = ? AND bouquet_id = ?')->execute([$userId, $bouquetId]);
                flash('Removed from wishlist.', 'info');
            } else {
                $pdo->prepare('INSERT INTO wishlist (user_id, bouquet_id, added_at) VALUES (?, ?, NOW())')->execute([$userId, $bouquetId]);
                flash('Added to wishlist! 💖', 'success');
            }
        } catch (RuntimeException $e) {
            flash('Could not update wishlist.', 'error');
        }
        header('Location: /bloom-aura/pages/product.php?slug=' . urlencode($slug));
        exit;
    }

    if ($action === 'submit_review') {
        if (!$loggedIn) {
            flash('Please log in to leave a review.', 'info');
            header('Location: /bloom-aura/pages/login.php');
            exit;
        }
        $rating  = (int)($_POST['rating']  ?? 0);
        $comment = trim($_POST['comment']  ?? '');
        $errors  = [];
        if ($rating < 1 || $rating > 5) $errors[] = 'Please select a star rating.';
        if (strlen($comment) < 10)      $errors[] = 'Review must be at least 10 characters.';
        if (strlen($comment) > 1000)    $errors[] = 'Review must be under 1000 characters.';
        if (empty($errors)) {
            try {
                $pdo    = getPDO();
                $exists = $pdo->prepare('SELECT id FROM reviews WHERE user_id = ? AND bouquet_id = ?');
                $exists->execute([$userId, $bouquetId]);
                if ($exists->fetch()) {
                    flash('You have already reviewed this bouquet.', 'error');
                } else {
                    $pdo->prepare(
                        'INSERT INTO reviews (user_id, bouquet_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())'
                    )->execute([$userId, $bouquetId, $rating, $comment]);
                    flash('Thank you for your review! 🌸', 'success');
                }
            } catch (RuntimeException $e) {
                flash('Could not save your review. Please try again.', 'error');
            }
        } else {
            flash(implode(' ', $errors), 'error');
        }
        header('Location: /bloom-aura/pages/product.php?slug=' . urlencode($slug) . '#reviews');
        exit;
    }
}

// ── Fetch supporting data ─────────────────────────────────────────────────────
try {
    $pdo = getPDO();

    $revStmt = $pdo->prepare(
        "SELECT r.rating, r.comment, r.created_at, u.name AS reviewer
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.bouquet_id = ?
         ORDER BY r.created_at DESC LIMIT 50"
    );
    $revStmt->execute([$bouquetId]);
    $reviews = $revStmt->fetchAll();

    $avgStmt = $pdo->prepare(
        'SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE bouquet_id = ?'
    );
    $avgStmt->execute([$bouquetId]);
    $ratingData = $avgStmt->fetch();

    $wishlisted = false;
    if ($loggedIn) {
        $wlStmt = $pdo->prepare('SELECT id FROM wishlist WHERE user_id = ? AND bouquet_id = ?');
        $wlStmt->execute([$userId, $bouquetId]);
        $wishlisted = (bool)$wlStmt->fetch();
    }

    $relStmt = $pdo->prepare(
        "SELECT b.name, b.slug, b.price, b.image, c.name AS category_name,
                ROUND(COALESCE(AVG(r.rating),0),1) AS avg_rating
         FROM bouquets b
         LEFT JOIN categories c ON c.id = b.category_id
         LEFT JOIN reviews r ON r.bouquet_id = b.id
         WHERE b.category_id = ? AND b.id != ? AND b.is_active = 1
         GROUP BY b.id ORDER BY RAND() LIMIT 4"
    );
    $relStmt->execute([$bouquet['category_id'], $bouquetId]);
    $related = $relStmt->fetchAll();

} catch (RuntimeException $e) {
    $reviews    = [];
    $ratingData = ['avg_rating' => 0, 'review_count' => 0];
    $wishlisted = false;
    $related    = [];
}

$avgRating   = (float)($ratingData['avg_rating']  ?? 0);
$reviewCount = (int)($ratingData['review_count']   ?? 0);
$pageTitle   = htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') . ' — Bloom Aura';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
body { background: #f5f5f7; }

/* ── Breadcrumb ── */
.breadcrumb-bar {
    max-width: 1100px;
    margin: 0 auto;
    padding: .85rem 1.25rem .4rem;
    font-size: .8rem;
    color: #888;
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-wrap: wrap;
}
.breadcrumb-bar a { color: #d63384; text-decoration: none; }
.breadcrumb-bar a:hover { text-decoration: underline; }
.breadcrumb-bar span { color: #bbb; }

/* ── Product Hero ── */
.product-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 1.25rem 3rem;
}
.product-hero {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
    margin-bottom: 2.5rem;
}

/* Image pane */
.product-img-pane {
    position: relative;
    background: linear-gradient(135deg, #fff0f5, #fce4ec);
    min-height: 440px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.product-main-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: absolute;
    inset: 0;
    transition: transform .5s;
}
.product-img-pane:hover .product-main-img { transform: scale(1.04); }

.product-badge-oos {
    position: absolute;
    top: 16px; left: 16px;
    background: rgba(220,38,38,.9);
    color: white;
    font-size: .8rem;
    font-weight: 700;
    padding: .4rem .9rem;
    border-radius: 20px;
    backdrop-filter: blur(4px);
}
.product-badge-low {
    position: absolute;
    top: 16px; left: 16px;
    background: rgba(217,119,6,.9);
    color: white;
    font-size: .8rem;
    font-weight: 700;
    padding: .4rem .9rem;
    border-radius: 20px;
}

/* Info pane */
.product-info-pane {
    padding: 2.5rem 2.5rem 2.5rem 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.product-cat-tag {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: #fff0f5;
    color: #d63384;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: .35rem .85rem;
    border-radius: 20px;
    text-decoration: none;
    margin-bottom: .9rem;
    width: fit-content;
}
.product-cat-tag:hover { background: #fce4ec; text-decoration: none; }

.product-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 2rem;
    font-weight: 700;
    color: #1e1218;
    margin-bottom: .75rem;
    line-height: 1.25;
}

/* Stars */
.product-stars-row {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: 1.1rem;
}
.star-lg { font-size: 1.15rem; }
.star-lg.full  { color: #f59e0b; }
.star-lg.half  { color: #f59e0b; }
.star-lg.empty { color: #e0e0e0; }
.avg-score {
    font-weight: 800;
    font-size: .95rem;
    color: #333;
}
.rev-link {
    font-size: .82rem;
    color: #888;
    text-decoration: none;
}
.rev-link:hover { color: #d63384; text-decoration: underline; }

/* Price */
.product-price-row {
    display: flex;
    align-items: baseline;
    gap: .5rem;
    margin-bottom: 1.25rem;
}
.product-price {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    font-weight: 800;
    color: #d63384;
}
.product-price-note {
    font-size: .8rem;
    color: #16a34a;
    font-weight: 600;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: .2rem .6rem;
    border-radius: 8px;
}

.product-desc {
    font-size: .92rem;
    color: #555;
    line-height: 1.7;
    margin-bottom: 1.5rem;
    border-left: 3px solid #fce4ec;
    padding-left: .85rem;
}

/* Qty + cart */
.product-cart-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: 1rem;
}
.qty-box {
    display: flex;
    align-items: center;
    border: 1.5px solid #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}
.qty-btn {
    background: none;
    border: none;
    width: 36px;
    height: 44px;
    font-size: 1.15rem;
    cursor: pointer;
    color: #555;
    transition: background .15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.qty-btn:hover { background: #fce4ec; color: #d63384; }
.qty-input {
    width: 48px;
    height: 44px;
    border: none;
    border-left: 1.5px solid #e0e0e0;
    border-right: 1.5px solid #e0e0e0;
    text-align: center;
    font-size: .95rem;
    font-weight: 700;
    outline: none;
    font-family: inherit;
}
.add-cart-btn {
    flex: 1;
    background: linear-gradient(135deg, #d63384, #ff4d94);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 0 1.5rem;
    height: 44px;
    font-size: .95rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    box-shadow: 0 4px 16px rgba(214,51,132,.35);
    transition: all .25s;
}
.add-cart-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(214,51,132,.45); }

.wishlist-btn {
    background: none;
    border: 1.5px solid #e0e0e0;
    border-radius: 12px;
    height: 44px;
    padding: 0 1.1rem;
    font-size: .88rem;
    font-weight: 600;
    color: #555;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: .4rem;
    transition: all .2s;
}
.wishlist-btn:hover, .wishlist-btn.wishlisted {
    border-color: #d63384;
    color: #d63384;
    background: #fff0f5;
}

/* Meta badges */
.product-meta-row {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid #fce4ec;
}
.meta-badge {
    display: flex;
    align-items: center;
    gap: .35rem;
    background: #f9fafb;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
    padding: .45rem .85rem;
    font-size: .78rem;
    color: #555;
    font-weight: 500;
}

/* ── Related ── */
.section-block {
    background: white;
    border-radius: 20px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.section-block-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    margin-bottom: 1.25rem;
    color: #1e1218;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.related-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
}
.rel-card {
    border: 1px solid #fce4ec;
    border-radius: 14px;
    overflow: hidden;
    transition: transform .3s, box-shadow .3s;
    text-decoration: none;
    color: inherit;
    display: block;
    background: white;
}
.rel-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(214,51,132,.15); text-decoration: none; }
.rel-img {
    height: 160px;
    overflow: hidden;
    background: #fce4ec;
}
.rel-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .4s;
}
.rel-card:hover .rel-img img { transform: scale(1.07); }
.rel-body { padding: .8rem .9rem 1rem; }
.rel-cat { font-size: .65rem; font-weight: 800; text-transform: uppercase; color: #d63384; letter-spacing: .06em; }
.rel-name { font-size: .88rem; font-weight: 700; color: #1e1218; margin: .25rem 0 .4rem; line-height: 1.3; }
.rel-price { font-weight: 800; color: #d63384; font-family: 'Playfair Display', serif; }

/* ── Reviews ── */
.reviews-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}
.avg-big {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: linear-gradient(135deg, #fff0f5, #fce4ec);
    padding: 1.25rem 1.75rem;
    border-radius: 14px;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.avg-score-big {
    font-family: 'Playfair Display', serif;
    font-size: 3rem;
    font-weight: 800;
    color: #d63384;
    line-height: 1;
}
.avg-stars-row { display: flex; gap: 3px; }
.avg-star { font-size: 1.3rem; }
.avg-star.full  { color: #f59e0b; }
.avg-star.half  { color: #f59e0b; }
.avg-star.empty { color: #e0e0e0; }
.avg-count { font-size: .88rem; color: #888; margin-top: 4px; }

.review-card {
    border: 1px solid #fce4ec;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    margin-bottom: .85rem;
    transition: box-shadow .2s;
}
.review-card:hover { box-shadow: 0 4px 16px rgba(214,51,132,.1); }
.review-top {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: .65rem;
}
.reviewer-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #d63384, #ff4d94);
    color: white;
    font-size: .9rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.reviewer-name { font-weight: 700; font-size: .9rem; color: #1e1218; }
.review-date { font-size: .75rem; color: #aaa; }
.review-stars { display: flex; gap: 2px; }
.rev-star { font-size: .9rem; }
.rev-star.full  { color: #f59e0b; }
.rev-star.empty { color: #e0e0e0; }
.review-comment { font-size: .88rem; color: #555; line-height: 1.65; }

/* Write review form */
.write-review {
    background: #f9fafb;
    border: 1.5px dashed #fce4ec;
    border-radius: 14px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}
.write-review h3 { font-size: 1rem; margin-bottom: 1rem; color: #1e1218; }
.star-picker { display: flex; gap: 6px; margin-bottom: 1rem; }
.star-pick {
    font-size: 1.6rem;
    cursor: pointer;
    color: #e0e0e0;
    transition: transform .15s, color .15s;
    background: none;
    border: none;
    padding: 0;
    line-height: 1;
}
.star-pick:hover, .star-pick.on { color: #f59e0b; transform: scale(1.15); }
.review-textarea {
    width: 100%;
    min-height: 90px;
    padding: .75rem 1rem;
    border: 1.5px solid #e0e0e0;
    border-radius: 10px;
    font-size: .88rem;
    font-family: inherit;
    resize: vertical;
    outline: none;
    transition: border-color .2s;
    box-sizing: border-box;
    margin-bottom: .75rem;
}
.review-textarea:focus { border-color: #d63384; }
.submit-review-btn {
    background: linear-gradient(135deg, #d63384, #ff4d94);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px 24px;
    font-size: .88rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all .25s;
}
.submit-review-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(214,51,132,.4); }

/* Responsive */
@media (max-width: 768px) {
    .product-hero { grid-template-columns: 1fr; }
    .product-img-pane { min-height: 280px; }
    .product-info-pane { padding: 1.5rem; }
    .product-title { font-size: 1.5rem; }
    .related-grid { grid-template-columns: repeat(2, 1fr); }
    .section-block { padding: 1.5rem; }
}
</style>

<!-- Breadcrumb -->
<div class="breadcrumb-bar">
    <a href="/bloom-aura/">Home</a>
    <span>/</span>
    <a href="/bloom-aura/pages/shop.php">Shop</a>
    <?php if ($bouquet['category_name']): ?>
    <span>/</span>
    <a href="/bloom-aura/pages/shop.php?cat=<?= urlencode($bouquet['category_slug'] ?? '') ?>">
        <?= htmlspecialchars($bouquet['category_name'], ENT_QUOTES, 'UTF-8') ?>
    </a>
    <?php endif; ?>
    <span>/</span>
    <strong><?= htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') ?></strong>
</div>

<div class="product-wrap">

    <!-- ══ PRODUCT HERO ══ -->
    <div class="product-hero">

        <!-- Image -->
        <div class="product-img-pane">
            <img class="product-main-img"
                 src="/bloom-aura/uploads/<?= htmlspecialchars($bouquet['image'], ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') ?>"
                 onerror="this.src='/bloom-aura/assets/img/placeholder.jpg'">
            <?php if ($bouquet['stock'] <= 0): ?>
                <span class="product-badge-oos">Out of Stock</span>
            <?php elseif ($bouquet['stock'] <= 5): ?>
                <span class="product-badge-low">⚡ Only <?= (int)$bouquet['stock'] ?> left!</span>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="product-info-pane">

            <a href="/bloom-aura/pages/shop.php?cat=<?= urlencode($bouquet['category_slug'] ?? '') ?>"
               class="product-cat-tag">
                🏷️ <?= htmlspecialchars($bouquet['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </a>

            <h1 class="product-title"><?= htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') ?></h1>

            <!-- Stars -->
            <div class="product-stars-row">
                <?php for ($i = 1; $i <= 5; $i++):
                    $cls = $avgRating >= $i ? 'full' : ($avgRating >= $i - .5 ? 'half' : 'empty');
                ?>
                    <span class="star-lg <?= $cls ?>">★</span>
                <?php endfor; ?>
                <span class="avg-score"><?= $avgRating ?: '—' ?></span>
                <a href="#reviews" class="rev-link">(<?= $reviewCount ?> review<?= $reviewCount !== 1 ? 's' : '' ?>)</a>
            </div>

            <!-- Price -->
            <div class="product-price-row">
                <span class="product-price">₹<?= number_format($bouquet['price'], 2) ?></span>
                <?php if ($bouquet['stock'] > 0): ?>
                <span class="product-price-note">✓ In Stock</span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <?php if (!empty($bouquet['description'])): ?>
            <p class="product-desc">
                <?= nl2br(htmlspecialchars($bouquet['description'], ENT_QUOTES, 'UTF-8')) ?>
            </p>
            <?php endif; ?>

            <!-- Add to cart -->
            <?php if ($bouquet['stock'] > 0): ?>
            <form method="POST" action="/bloom-aura/pages/product.php?slug=<?= urlencode($slug) ?>">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_to_cart">
                <div class="product-cart-row">
                    <div class="qty-box">
                        <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
                        <input type="number" id="qty" name="qty" value="1" min="1"
                               max="<?= (int)$bouquet['stock'] ?>" class="qty-input">
                        <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
                    </div>
                    <button type="submit" class="add-cart-btn">
                        🛒 Add to Cart
                    </button>
                </div>
            </form>
            <?php else: ?>
            <p style="color:#dc2626;font-weight:600;font-size:.95rem;margin-bottom:1rem;">
                😔 Currently out of stock — check back soon!
            </p>
            <?php endif; ?>

            <!-- Wishlist -->
            <form method="POST" action="/bloom-aura/pages/product.php?slug=<?= urlencode($slug) ?>">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_wishlist">
                <button type="submit" class="wishlist-btn <?= $wishlisted ? 'wishlisted' : '' ?>">
                    <?= $wishlisted ? '❤️ Saved to Wishlist' : '🤍 Save to Wishlist' ?>
                </button>
            </form>

            <!-- Meta badges -->
            <div class="product-meta-row">
                <span class="meta-badge">🏷️ <?= htmlspecialchars($bouquet['category_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                <span class="meta-badge">📦 <?= $bouquet['stock'] > 0 ? (int)$bouquet['stock'] . ' available' : 'Out of stock' ?></span>
                <span class="meta-badge">🚚 1–2 business days</span>
            </div>

        </div>
    </div>

    <!-- ══ RELATED PRODUCTS ══ -->
    <?php if (!empty($related)): ?>
    <div class="section-block">
        <h2 class="section-block-title">🌷 You May Also Like</h2>
        <div class="related-grid">
            <?php foreach ($related as $r): ?>
            <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($r['slug']) ?>" class="rel-card">
                <div class="rel-img">
                    <img src="/bloom-aura/uploads/<?= htmlspecialchars($r['image'], ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>"
                         loading="lazy"
                         onerror="this.src='/bloom-aura/assets/img/placeholder.jpg'">
                </div>
                <div class="rel-body">
                    <p class="rel-cat"><?= htmlspecialchars($r['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="rel-name"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="rel-price">₹<?= number_format($r['price'], 2) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ REVIEWS ══ -->
    <div class="section-block" id="reviews">

        <div class="reviews-header">
            <h2 class="section-block-title">⭐ Customer Reviews
                <?php if ($reviewCount): ?>
                <span style="background:#fce4ec;color:#d63384;font-size:.75rem;padding:.2rem .65rem;border-radius:20px;font-family:'Inter',sans-serif;">
                    <?= $reviewCount ?>
                </span>
                <?php endif; ?>
            </h2>
        </div>

        <?php if ($reviewCount > 0): ?>
        <div class="avg-big">
            <div>
                <div class="avg-score-big"><?= $avgRating ?></div>
                <div style="font-size:.75rem;color:#888;margin-top:4px;">out of 5</div>
            </div>
            <div>
                <div class="avg-stars-row">
                    <?php for ($i = 1; $i <= 5; $i++):
                        $cls = $avgRating >= $i ? 'full' : ($avgRating >= $i-.5 ? 'half' : 'empty');
                    ?>
                    <span class="avg-star <?= $cls ?>">★</span>
                    <?php endfor; ?>
                </div>
                <div class="avg-count"><?= $reviewCount ?> review<?= $reviewCount !== 1 ? 's' : '' ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Review list -->
        <?php if (!empty($reviews)): ?>
        <div class="reviews-list">
            <?php foreach ($reviews as $rev): ?>
            <div class="review-card">
                <div class="review-top">
                    <div class="reviewer-avatar">
                        <?= strtoupper(mb_substr($rev['reviewer'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="reviewer-name"><?= htmlspecialchars($rev['reviewer'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="review-date"><?= date('d M Y', strtotime($rev['created_at'])) ?></div>
                    </div>
                    <div class="review-stars" style="margin-left:auto;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="rev-star <?= $i <= $rev['rating'] ? 'full' : 'empty' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                </div>
                <p class="review-comment">
                    <?= nl2br(htmlspecialchars($rev['comment'], ENT_QUOTES, 'UTF-8')) ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:#aaa;font-size:.9rem;text-align:center;padding:2rem 0;">
            No reviews yet — be the first! 🌸
        </p>
        <?php endif; ?>

        <!-- Write a review -->
        <?php if ($loggedIn): ?>
        <div class="write-review">
            <h3>✍️ Write a Review</h3>
            <form method="POST" action="/bloom-aura/pages/product.php?slug=<?= urlencode($slug) ?>#reviews">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="rating" id="rating-val" value="0">

                <div class="star-picker">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="star-pick" data-val="<?= $i ?>"
                            onclick="setRating(<?= $i ?>)">★</button>
                    <?php endfor; ?>
                </div>

                <textarea name="comment" class="review-textarea"
                          placeholder="Share your experience with this bouquet… (min 10 chars)"
                          required minlength="10" maxlength="1000"></textarea>

                <button type="submit" class="submit-review-btn">Submit Review 🌸</button>
            </form>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:1.5rem;background:#f9fafb;border-radius:12px;margin-top:1rem;">
            <p style="color:#666;margin-bottom:.75rem;">Login to leave a review</p>
            <a href="/bloom-aura/pages/login.php"
               style="background:#d63384;color:white;padding:9px 22px;border-radius:10px;font-weight:700;font-size:.85rem;text-decoration:none;">
                Login to Review
            </a>
        </div>
        <?php endif; ?>

    </div>

</div><!-- /.product-wrap -->

<script>
function changeQty(delta) {
    var inp = document.getElementById('qty');
    if (!inp) return;
    var v = parseInt(inp.value) + delta;
    v = Math.max(1, Math.min(v, parseInt(inp.max) || 99));
    inp.value = v;
}

function setRating(val) {
    document.getElementById('rating-val').value = val;
    document.querySelectorAll('.star-pick').forEach(function(btn) {
        btn.classList.toggle('on', parseInt(btn.dataset.val) <= val);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>