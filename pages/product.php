<?php
/**
 * bloom-aura/pages/product.php
 * Single bouquet detail page.
 * Handles: add-to-cart POST, add/remove wishlist POST, submit review POST.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$loggedIn = !empty($_SESSION['user_id']);
$userId   = (int)($_SESSION['user_id'] ?? 0);

// ── Resolve bouquet by slug ───────────────────────────────────────────────────
$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: /pages/shop.php');
    exit;
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        "SELECT b.*, c.name AS category_name, c.slug AS category_slug
         FROM bouquets b
         LEFT JOIN categories c ON c.id = b.category_id
         WHERE b.slug = ?
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
    header('Location: /pages/shop.php');
    exit;
}

$bouquetId = (int)$bouquet['id'];

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    // Add to cart
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
        header('Location: /pages/product.php?slug=' . urlencode($slug));
        exit;
    }

    // Wishlist toggle
    if ($action === 'toggle_wishlist') {
        if (!$loggedIn) {
            flash('Please log in to save to your wishlist.', 'info');
            header('Location: /pages/login.php');
            exit;
        }
        try {
            $pdo = getPDO();
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
        header('Location: /pages/product.php?slug=' . urlencode($slug));
        exit;
    }

    // Submit review
    if ($action === 'submit_review') {
        if (!$loggedIn) {
            flash('Please log in to leave a review.', 'info');
            header('Location: /pages/login.php');
            exit;
        }
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $errors  = [];
        if ($rating < 1 || $rating > 5)  $errors[] = 'Please select a star rating.';
        if (strlen($comment) < 10)        $errors[] = 'Review must be at least 10 characters.';
        if (strlen($comment) > 1000)      $errors[] = 'Review must be under 1000 characters.';

        if (empty($errors)) {
            try {
                $pdo = getPDO();
                // One review per user per bouquet
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
        header('Location: /pages/product.php?slug=' . urlencode($slug) . '#reviews');
        exit;
    }
}

// ── Fetch supporting data ─────────────────────────────────────────────────────
try {
    $pdo = getPDO();

    // Reviews
    $revStmt = $pdo->prepare(
        "SELECT r.rating, r.comment, r.created_at, u.name AS reviewer
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.bouquet_id = ?
         ORDER BY r.created_at DESC
         LIMIT 50"
    );
    $revStmt->execute([$bouquetId]);
    $reviews = $revStmt->fetchAll();

    // Avg rating
    $avgStmt = $pdo->prepare(
        'SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE bouquet_id = ?'
    );
    $avgStmt->execute([$bouquetId]);
    $ratingData = $avgStmt->fetch();

    // Is wishlisted?
    $wishlisted = false;
    if ($loggedIn) {
        $wlStmt = $pdo->prepare('SELECT id FROM wishlist WHERE user_id = ? AND bouquet_id = ?');
        $wlStmt->execute([$userId, $bouquetId]);
        $wishlisted = (bool)$wlStmt->fetch();
    }

    // Related products (same category, different bouquet)
    $relStmt = $pdo->prepare(
        "SELECT b.name, b.slug, b.price, b.image,
                ROUND(AVG(r.rating),1) AS avg_rating
         FROM bouquets b
         LEFT JOIN reviews r ON r.bouquet_id = b.id
         WHERE b.category_id = ? AND b.id != ?
         GROUP BY b.id
         ORDER BY RAND()
         LIMIT 4"
    );
    $relStmt->execute([$bouquet['category_id'], $bouquetId]);
    $related = $relStmt->fetchAll();

} catch (RuntimeException $e) {
    $reviews = [];
    $ratingData = ['avg_rating' => 0, 'review_count' => 0];
    $wishlisted = false;
    $related = [];
}

$avgRating    = $ratingData['avg_rating'] ?? 0;
$reviewCount  = $ratingData['review_count'] ?? 0;
$pageTitle    = htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') . ' — Bloom Aura';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/">Home</a></li>
        <li><a href="/pages/shop.php">Shop</a></li>
        <?php if ($bouquet['category_name']): ?>
            <li>
                <a href="/pages/shop.php?cat=<?= urlencode($bouquet['category_slug']) ?>">
                    <?= htmlspecialchars($bouquet['category_name'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            </li>
        <?php endif; ?>
        <li aria-current="page"><?= htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') ?></li>
    </ol>
</nav>

<div class="page-container">

    <!-- ── PRODUCT HERO ── -->
    <section class="product-hero">
        <div class="product-image-wrap">
            <img
                src="/uploads/bouquets/<?= htmlspecialchars($bouquet['image'], ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') ?>"
                class="product-main-img"
                loading="eager"
                width="560" height="480"
            >
            <?php if ($bouquet['stock'] <= 0): ?>
                <span class="product-oos-overlay">Out of Stock</span>
            <?php elseif ($bouquet['stock'] <= 5): ?>
                <span class="product-low-badge">Only <?= (int)$bouquet['stock'] ?> left!</span>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <p class="product-category">
                <a href="/pages/shop.php?cat=<?= urlencode($bouquet['category_slug'] ?? '') ?>">
                    <?= htmlspecialchars($bouquet['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </a>
            </p>

            <h1 class="product-title"><?= htmlspecialchars($bouquet['name'], ENT_QUOTES, 'UTF-8') ?></h1>

            <!-- Rating summary -->
            <div class="product-rating-row">
                <span class="stars-display" aria-label="Rated <?= $avgRating ?> out of 5">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?= $avgRating >= $i ? 'full' : ($avgRating >= $i - 0.5 ? 'half' : 'empty') ?>">★</span>
                    <?php endfor; ?>
                </span>
                <span class="rating-score"><?= $avgRating ?: '—' ?></span>
                <a href="#reviews" class="rating-count">(<?= $reviewCount ?> review<?= $reviewCount !== 1 ? 's' : '' ?>)</a>
            </div>

            <p class="product-price">₹<?= number_format($bouquet['price'], 2) ?></p>

            <p class="product-description">
                <?= nl2br(htmlspecialchars($bouquet['description'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
            </p>

            <!-- Add to cart form -->
            <?php if ($bouquet['stock'] > 0): ?>
                <form action="/pages/product.php?slug=<?= urlencode($slug) ?>" method="POST" class="product-cart-form">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="add_to_cart">
                    <div class="qty-selector">
                        <label for="qty">Qty:</label>
                        <input type="number" id="qty" name="qty" value="1" min="1"
                               max="<?= (int)$bouquet['stock'] ?>" class="qty-input">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-basket-shopping"></i> Add to Cart
                    </button>
                </form>
            <?php else: ?>
                <p class="out-of-stock-msg">😔 This bouquet is currently out of stock.</p>
            <?php endif; ?>

            <!-- Wishlist toggle -->
            <form action="/pages/product.php?slug=<?= urlencode($slug) ?>" method="POST" class="wishlist-form">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_wishlist">
                <button type="submit" class="btn btn-outline wishlist-btn <?= $wishlisted ? 'wishlisted' : '' ?>">
                    <i class="fa-<?= $wishlisted ? 'solid' : 'regular' ?> fa-heart"></i>
                    <?= $wishlisted ? 'Saved to Wishlist' : 'Save to Wishlist' ?>
                </button>
            </form>

            <!-- Meta info -->
            <ul class="product-meta">
                <li><i class="fa-solid fa-tag"></i> Category: <?= htmlspecialchars($bouquet['category_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></li>
                <li><i class="fa-solid fa-box"></i> Stock: <?= $bouquet['stock'] > 0 ? (int)$bouquet['stock'] . ' available' : 'Out of stock' ?></li>
                <li><i class="fa-solid fa-truck"></i> Usually delivered in 1–2 business days</li>
            </ul>
        </div>
    </section>

    <!-- ── RELATED PRODUCTS ── -->
    <?php if (!empty($related)): ?>
        <section class="related-section">
            <h2 class="section-title">You May Also Like</h2>
            <div class="product-grid product-grid--sm">
                <?php foreach ($related as $r): ?>
                    <article class="product-card">
                        <a href="/pages/product.php?slug=<?= urlencode($r['slug']) ?>" class="card-img-wrap">
                            <img
                                src="/uploads/bouquets/<?= htmlspecialchars($r['image'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy" width="280" height="220"
                            >
                        </a>
                        <div class="card-body">
                            <h3 class="card-title">
                                <a href="/pages/product.php?slug=<?= urlencode($r['slug']) ?>">
                                    <?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </h3>
                            <div class="card-footer">
                                <span class="price">₹<?= number_format($r['price'], 2) ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ── REVIEWS ── -->
    <section class="reviews-section" id="reviews">
        <h2 class="section-title">Customer Reviews <?php if ($reviewCount): ?><span class="review-count-badge"><?= $reviewCount ?></span><?php endif; ?></h2>

        <?php if (!empty($reviews)): ?>
            <div class="reviews-list">
                <?php foreach ($reviews as $rev): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <strong class="reviewer-name"><?= htmlspecialchars($rev['reviewer'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="review-stars" aria-label="<?= $rev['rating'] ?> stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?= $i <= $rev['rating'] ? 'full' : 'empty' ?>">★</span>
                                <?php endfor; ?>
                            </span>
                            <time class="review-date" datetime="<?= $rev['created_at'] ?>">
                                <?= date('d M Y', strtotime($rev['created_at'])) ?>
                            </time>
                        </div>
                        <p class="review-comment"><?= nl2br(htmlspecialchars($rev['comment'], ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state-sm">
                <p>No reviews yet. Be the first to share your experience!</p>
            </div>
        <?php endif; ?>

        <!-- Write a review -->
        <div class="write-review-box">
            <h3>Write a Review</h3>
            <?php if ($loggedIn): ?>
                <form action="/pages/product.php?slug=<?= urlencode($slug) ?>#reviews" method="POST" class="review-form" novalidate>
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="submit_review">

                    <div class="form-group">
                        <label>Your Rating <span class="required">*</span></label>
                        <div class="star-picker" role="radiogroup" aria-label="Star rating">
                            <?php for ($s = 5; $s >= 1; $s--): ?>
                                <input type="radio" name="rating" id="star<?= $s ?>" value="<?= $s ?>">
                                <label for="star<?= $s ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="comment">Your Review <span class="required">*</span></label>
                        <textarea id="comment" name="comment" rows="4"
                                  placeholder="Share your experience with this bouquet…"
                                  maxlength="1000" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </form>
            <?php else: ?>
                <p class="login-prompt">
                    <a href="/pages/login.php">Log in</a> to leave a review.
                </p>
            <?php endif; ?>
        </div>
    </section>

</div><!-- /.page-container -->

<script src="/assets/js/cart.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
