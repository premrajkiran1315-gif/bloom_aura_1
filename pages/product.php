<?php
/**
 * bloom-aura/pages/product.php
 * Single bouquet detail — pixel-matched to bloom_aura reference UI.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$loggedIn = !empty($_SESSION['user_id']);
$userId   = (int)($_SESSION['user_id'] ?? 0);

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: /bloom-aura/pages/shop.php');
    exit;
}

// ── Fetch bouquet ─────────────────────────────────────────────────────────────
try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare(
        "SELECT b.*, c.name AS category_name, c.slug AS category_slug
         FROM bouquets b
         LEFT JOIN categories c ON c.id = b.category_id
         WHERE b.slug = ? AND b.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);
    $bouquet = $stmt->fetch();
} catch (RuntimeException $e) {
    $bouquet = null;
}

if (!$bouquet) {
    http_response_code(404);
    flash('Bouquet not found.', 'error');
    header('Location: /bloom-aura/pages/shop.php');
    exit;
}

$bouquetId = (int)$bouquet['id'];

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    // ADD TO CART
    if ($action === 'add_to_cart') {
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        if ((int)$bouquet['stock'] <= 0) {
            flash('Sorry, this item is currently out of stock.', 'error');
        } else {
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            $cart = &$_SESSION['cart'];
            if (isset($cart[$bouquetId])) {
                $cart[$bouquetId]['qty'] = min($cart[$bouquetId]['qty'] + $qty, (int)$bouquet['stock']);
            } else {
                $cart[$bouquetId] = [
                    'id'    => $bouquetId,
                    'name'  => $bouquet['name'],
                    'price' => (float)$bouquet['price'],
                    'image' => $bouquet['image'],
                    'qty'   => min($qty, (int)$bouquet['stock']),
                ];
            }
            flash(htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') . ' added to your cart! 🛒', 'success');
        }
        header('Location: /bloom-aura/pages/product.php?slug=' . urlencode($slug));
        exit;
    }

    // TOGGLE WISHLIST
    if ($action === 'toggle_wishlist') {
        if (!$loggedIn) {
            flash('Please log in to save to your wishlist.', 'info');
            header('Location: /bloom-aura/pages/login.php?redirect=' . urlencode('/bloom-aura/pages/product.php?slug=' . $slug));
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
                $pdo->prepare('INSERT INTO wishlist (user_id, bouquet_id) VALUES (?, ?)')->execute([$userId, $bouquetId]);
                flash('Added to wishlist! ❤️', 'success');
            }
        } catch (RuntimeException $e) {
            flash('Could not update wishlist.', 'error');
        }
        header('Location: /bloom-aura/pages/product.php?slug=' . urlencode($slug));
        exit;
    }

    // SUBMIT REVIEW
    if ($action === 'submit_review') {
        if (!$loggedIn) {
            flash('Please log in to leave a review.', 'info');
            header('Location: /bloom-aura/pages/login.php');
            exit;
        }
        $rating  = max(1, min(5, (int)($_POST['rating'] ?? 0)));
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            flash('Please select a rating between 1 and 5.', 'error');
        } elseif (strlen($comment) < 5) {
            flash('Please write at least a brief comment.', 'error');
        } else {
            try {
                $pdo  = getPDO();
                $dup  = $pdo->prepare('SELECT id FROM reviews WHERE user_id = ? AND bouquet_id = ?');
                $dup->execute([$userId, $bouquetId]);
                if ($dup->fetch()) {
                    flash('You have already reviewed this product.', 'error');
                } else {
                    $pdo->prepare(
                        'INSERT INTO reviews (user_id, bouquet_id, rating, comment) VALUES (?, ?, ?, ?)'
                    )->execute([$userId, $bouquetId, $rating, $comment]);
                    flash('Review submitted — thank you! 🌸', 'success');
                }
            } catch (RuntimeException $e) {
                flash('Could not save review. Please try again.', 'error');
            }
        }
        header('Location: /bloom-aura/pages/product.php?slug=' . urlencode($slug) . '#reviews');
        exit;
    }
}

// ── Wishlist status ───────────────────────────────────────────────────────────
$wishlisted = false;
if ($loggedIn) {
    try {
        $pdo   = getPDO();
        $wstmt = $pdo->prepare('SELECT id FROM wishlist WHERE user_id = ? AND bouquet_id = ?');
        $wstmt->execute([$userId, $bouquetId]);
        $wishlisted = (bool)$wstmt->fetch();
    } catch (RuntimeException $e) {}
}

// ── Reviews ───────────────────────────────────────────────────────────────────
$reviews     = [];
$avgRating   = 0;
$reviewCount = 0;
try {
    $pdo = getPDO();

    $rStmt = $pdo->prepare(
        "SELECT r.rating, r.comment, r.created_at,
                u.name AS reviewer
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.bouquet_id = ?
         ORDER BY r.created_at DESC"
    );
    $rStmt->execute([$bouquetId]);
    $reviews = $rStmt->fetchAll();

    $ratingData  = $pdo->prepare(
        'SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS review_count
         FROM reviews WHERE bouquet_id = ?'
    );
    $ratingData->execute([$bouquetId]);
    $ratingData  = $ratingData->fetch();
    $avgRating   = (float)($ratingData['avg_rating']  ?? 0);
    $reviewCount = (int)($ratingData['review_count']   ?? 0);
} catch (RuntimeException $e) {}

// ── Related products ──────────────────────────────────────────────────────────
$related = [];
try {
    $pdo   = getPDO();
    $rStmt = $pdo->prepare(
        "SELECT b.name, b.slug, b.price, b.image, c.name AS category_name
         FROM bouquets b
         LEFT JOIN categories c ON c.id = b.category_id
         WHERE b.category_id = ? AND b.id != ? AND b.is_active = 1
         ORDER BY RAND()
         LIMIT 4"
    );
    $rStmt->execute([(int)$bouquet['category_id'], $bouquetId]);
    $related = $rStmt->fetchAll();
} catch (RuntimeException $e) {}

// ── Cart count for header ─────────────────────────────────────────────────────
$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $ci) {
    $cartCount += (int)($ci['qty'] ?? 1);
}

$pageTitle = htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') . ' — Bloom Aura';
$pageCss   = 'product';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <a href="/bloom-aura/">Home</a>
    <span class="bc-sep">/</span>
    <a href="/bloom-aura/pages/shop.php">Shop</a>
    <?php if (!empty($bouquet['category_name'])): ?>
    <span class="bc-sep">/</span>
    <a href="/bloom-aura/pages/shop.php?cat=<?= urlencode($bouquet['category_slug'] ?? '') ?>">
        <?= htmlspecialchars($bouquet['category_name'], ENT_QUOTES, 'UTF-8') ?>
    </a>
    <?php endif; ?>
    <span class="bc-sep">/</span>
    <span><?= htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') ?></span>
</nav>

<div class="product-wrap">

    <!-- ═══════════════════ FLASH MESSAGES ═════════════════════ -->
    <?php
    $flashMessages = [];
    if (!empty($_SESSION['flash'])) {
        $flashMessages = $_SESSION['flash'];
        unset($_SESSION['flash']);
    }
    foreach ($flashMessages as $fl): ?>
        <div class="flash flash-<?= htmlspecialchars($fl['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($fl['msg'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endforeach; ?>

    <!-- ═══════════════════ PRODUCT HERO ═════════════════════ -->
    <div class="product-detail-layout">

        <!-- LEFT: Image pane -->
        <div class="product-img-pane">
            <div class="product-img-frame">
                <img
                    id="product-main-img"
                    class="product-main-img"
                    src="/bloom-aura/uploads/bouquets/<?= htmlspecialchars($bouquet['image'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') ?>"
                    onerror="this.src='/bloom-aura/assets/img/placeholder.jpg'"
                >
                <?php if ((int)$bouquet['stock'] <= 0): ?>
                    <span class="product-badge-oos">Out of Stock</span>
                <?php elseif ((int)$bouquet['stock'] <= 5): ?>
                    <span class="product-badge-low">⚡ Only <?= (int)$bouquet['stock'] ?> left!</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: Info pane -->
        <div class="product-info-pane">

            <!-- Category tag -->
            <a href="/bloom-aura/pages/shop.php?cat=<?= urlencode($bouquet['category_slug'] ?? '') ?>"
               class="product-cat-tag">
                🏷️ <?= htmlspecialchars($bouquet['category_name'] ?? 'Bouquets', ENT_QUOTES, 'UTF-8') ?>
            </a>

            <!-- Title -->
            <h1 class="product-title"><?= htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') ?></h1>

            <!-- Star rating row -->
            <div class="product-stars-row">
                <?php for ($i = 1; $i <= 5; $i++):
                    $cls = $avgRating >= $i ? 'full' : ($avgRating >= $i - 0.5 ? 'half' : 'empty');
                ?>
                    <span class="star-lg <?= $cls ?>">★</span>
                <?php endfor; ?>
                <span class="avg-score"><?= $avgRating > 0 ? $avgRating : '—' ?></span>
                <a href="#reviews" class="rev-link">
                    (<?= $reviewCount ?> review<?= $reviewCount !== 1 ? 's' : '' ?>)
                </a>
            </div>

            <!-- Price + stock -->
            <div class="product-price-row">
                <span class="product-price">₹<?= number_format((float)$bouquet['price'], 2) ?></span>
                <?php if ((int)$bouquet['stock'] > 0): ?>
                    <span class="stock-pill in-stock">✓ In Stock</span>
                <?php else: ?>
                    <span class="stock-pill out-stock">✕ Out of Stock</span>
                <?php endif; ?>
            </div>

            <!-- Divider -->
            <div class="product-divider"></div>

            <!-- Description -->
            <?php if (!empty($bouquet['description'])): ?>
            <p class="product-desc">
                <?= nl2br(htmlspecialchars($bouquet['description'], ENT_QUOTES, 'UTF-8')) ?>
            </p>
            <?php endif; ?>

            <!-- Add to cart -->
            <?php if ((int)$bouquet['stock'] > 0): ?>
            <form method="POST"
                  action="/bloom-aura/pages/product.php?slug=<?= urlencode($slug) ?>"
                  class="product-add-form"
                  id="add-to-cart-form">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_to_cart">

                <div class="product-cart-row">
                    <!-- Qty stepper -->
                    <div class="qty-control">
                        <button type="button" class="qty-btn" id="qty-minus" aria-label="Decrease quantity">−</button>
                        <input type="number" id="qty" name="qty" value="1" min="1"
                               max="<?= (int)$bouquet['stock'] ?>" class="qty-input"
                               aria-label="Quantity">
                        <button type="button" class="qty-btn" id="qty-plus" aria-label="Increase quantity">+</button>
                    </div>

                    <!-- Add to cart button -->
                    <button type="submit" class="add-cart-btn" id="add-cart-btn">
                        <span class="btn-icon">🛒</span> Add to Cart
                    </button>
                </div>
            </form>

            <?php else: ?>
            <p class="stock-error">
                😔 Currently out of stock — check back soon!
            </p>
            <?php endif; ?>

            <!-- Wishlist -->
            <form method="POST"
                  action="/bloom-aura/pages/product.php?slug=<?= urlencode($slug) ?>"
                  class="wishlist-form-inline">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_wishlist">
                <button type="submit"
                        class="wishlist-toggle-btn <?= $wishlisted ? 'wishlisted' : '' ?>">
                    <?= $wishlisted ? '❤️ Saved to Wishlist' : '🤍 Save to Wishlist' ?>
                </button>
            </form>

            <!-- Meta badges -->
            <div class="product-meta-row">
                <div class="meta-badge">
                    <span class="meta-icon">🏷️</span>
                    <?= htmlspecialchars($bouquet['category_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="meta-badge">
                    <span class="meta-icon">📦</span>
                    <?= (int)$bouquet['stock'] > 0
                        ? (int)$bouquet['stock'] . ' available'
                        : 'Out of stock' ?>
                </div>
                <div class="meta-badge">
                    <span class="meta-icon">🚚</span>
                    1–2 business days
                </div>
                <div class="meta-badge">
                    <span class="meta-icon">✅</span>
                    Secure checkout
                </div>
            </div>

        </div><!-- /.product-info-pane -->
    </div><!-- /.product-detail-layout -->

    <!-- ═══════════════════ RELATED PRODUCTS ═════════════════════ -->
    <?php if (!empty($related)): ?>
    <section class="section-block" aria-labelledby="related-heading">
        <h2 class="section-block-title" id="related-heading">🌷 You May Also Like</h2>
        <div class="related-grid">
            <?php foreach ($related as $r): ?>
            <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($r['slug']) ?>"
               class="rel-card">
                <div class="rel-img-wrap">
                    <img
                        src="/bloom-aura/uploads/bouquets/<?= htmlspecialchars($r['image'], ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>"
                        loading="lazy"
                        onerror="this.src='/bloom-aura/assets/img/placeholder.jpg'"
                    >
                </div>
                <div class="rel-body">
                    <p class="rel-cat"><?= htmlspecialchars($r['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="rel-name"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="rel-price">₹<?= number_format((float)$r['price'], 2) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════════ REVIEWS ═════════════════════ -->
    <section class="section-block" id="reviews" aria-labelledby="reviews-heading">

        <!-- Reviews header -->
        <div class="reviews-header">
            <h2 class="section-block-title" id="reviews-heading">
                ⭐ Customer Reviews
                <?php if ($reviewCount > 0): ?>
                    <span class="review-count-badge"><?= $reviewCount ?></span>
                <?php endif; ?>
            </h2>
        </div>

        <!-- Average rating summary -->
        <?php if ($reviewCount > 0): ?>
        <div class="avg-rating-box">
            <div class="avg-score-big"><?= $avgRating ?></div>
            <div class="avg-right">
                <div class="avg-stars-row">
                    <?php for ($i = 1; $i <= 5; $i++):
                        $cls = $avgRating >= $i ? 'full' : ($avgRating >= $i - 0.5 ? 'half' : 'empty');
                    ?>
                        <span class="avg-star <?= $cls ?>">★</span>
                    <?php endfor; ?>
                </div>
                <div class="avg-label">
                    Based on <?= $reviewCount ?> review<?= $reviewCount !== 1 ? 's' : '' ?>
                </div>
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
                    <div class="reviewer-info">
                        <div class="reviewer-name">
                            <?= htmlspecialchars($rev['reviewer'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="review-date">
                            <?= date('d M Y', strtotime($rev['created_at'])) ?>
                        </div>
                    </div>
                    <div class="review-stars">
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
        <p class="reviews-empty">No reviews yet — be the first! 🌸</p>
        <?php endif; ?>

        <!-- Write a review form -->
        <div class="review-form-wrap">
            <h3 class="review-form-title">✍️ Write a Review</h3>

            <?php if (!$loggedIn): ?>
                <p class="review-login-note">
                    <a href="/bloom-aura/pages/login.php?redirect=<?= urlencode('/bloom-aura/pages/product.php?slug=' . $slug) ?>">
                        Log in
                    </a> to leave a review.
                </p>
            <?php else: ?>
            <form method="POST"
                  action="/bloom-aura/pages/product.php?slug=<?= urlencode($slug) ?>#reviews"
                  class="review-form"
                  novalidate>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="submit_review">

                <!-- Star rating picker -->
                <div class="star-rating-input" role="group" aria-label="Rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>">
                        <label for="star<?= $i ?>" aria-label="<?= $i ?> stars">★</label>
                    <?php endfor; ?>
                </div>

                <div class="form-group">
                    <textarea name="comment" rows="4"
                              placeholder="Share your thoughts about this bouquet…"
                              class="review-textarea"
                              required></textarea>
                </div>

                <button type="submit" class="review-submit-btn">
                    Submit Review 🌸
                </button>
            </form>
            <?php endif; ?>
        </div>

    </section>

</div><!-- /.product-wrap -->

<script src="/bloom-aura/assets/js/product.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>