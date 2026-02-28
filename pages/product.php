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
            <p class="stock-error">
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
                <span class="review-badge">
                    <?= $reviewCount ?>
                </span>
                <?php endif; ?>
            </h2>
        </div>

        <?php if ($reviewCount > 0): ?>
        <div class="avg-big">
            <div>
                <div class="avg-score-big"><?= $avgRating ?></div>
                <div class="rating-label">out of 5</div>
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
                    <div class="review-stars-row">
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
        <p class="reviews-empty">
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
        <div class="login-cta-box">
            <p class="login-cta-text">Login to leave a review</p>
            <a href="/bloom-aura/pages/login.php"
               class="btn btn-primary btn-sm">
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