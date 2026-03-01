<?php
/**
 * bloom-aura/pages/shop.php
 * Shop page — fully synced to bloom_aura reference UI.
 * Sticky topbar · sidebar (category, price, rating, search) ·
 * product grid with wishlist hearts · toast · pagination.
 * All PDO/CSRF/session logic preserved.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

const ITEMS_PER_PAGE = 12;

/* ── Input sanitisation ── */
$search    = trim($_GET['q']          ?? '');
$catSlug   = trim($_GET['cat']        ?? '');
$priceMin  = max(0, (int)($_GET['price_min']  ?? 0));
$priceMax  = (int)($_GET['price_max']  ?? 0);
$ratingMin = (float)($_GET['rating_min'] ?? 0);
$sort      = in_array($_GET['sort'] ?? '', ['price_asc','price_desc','newest','rating'])
               ? $_GET['sort'] : 'newest';
$page      = max(1, (int)($_GET['page'] ?? 1));

$bouquets   = [];
$categories = [];
$totalItems = 0;
$totalPages = 1;
$error      = '';

/* ── Database queries ── */
try {
    $pdo = getPDO();

    $where  = ['b.is_active = 1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(b.name LIKE ? OR b.description LIKE ? OR c.name LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = array_merge($params, [$like, $like, $like]);
    }
    if ($catSlug !== '') { $where[] = 'c.slug = ?';   $params[] = $catSlug; }
    if ($priceMin > 0)   { $where[] = 'b.price >= ?'; $params[] = $priceMin; }
    if ($priceMax > 0)   { $where[] = 'b.price <= ?'; $params[] = $priceMax; }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $havingSQL    = '';
    $havingParams = [];
    if ($ratingMin > 0) {
        $havingSQL    = 'HAVING avg_rating >= ?';
        $havingParams = [$ratingMin];
    }

    $orderSQL = match ($sort) {
        'price_asc'  => 'ORDER BY b.price ASC',
        'price_desc' => 'ORDER BY b.price DESC',
        'rating'     => 'ORDER BY avg_rating DESC',
        default      => 'ORDER BY b.created_at DESC',
    };

    /* Total count (wraps HAVING subquery) */
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM (
            SELECT b.id,
                   ROUND(COALESCE(AVG(r.rating),0),1) AS avg_rating
            FROM   bouquets b
            JOIN   categories c ON b.category_id = c.id
            LEFT JOIN reviews r ON r.bouquet_id  = b.id
            $whereSQL
            GROUP  BY b.id
            $havingSQL
         ) AS sub"
    );
    $countStmt->execute(array_merge($params, $havingParams));
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalItems / ITEMS_PER_PAGE));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * ITEMS_PER_PAGE;

    /* Products */
    $stmt = $pdo->prepare(
        "SELECT b.id, b.name, b.slug, b.price, b.stock, b.image,
                c.name AS category_name, c.slug AS category_slug,
                ROUND(COALESCE(AVG(r.rating),0),1) AS avg_rating,
                COUNT(r.id) AS review_count
         FROM   bouquets b
         JOIN   categories c ON b.category_id = c.id
         LEFT JOIN reviews r ON r.bouquet_id  = b.id
         $whereSQL
         GROUP  BY b.id
         $havingSQL
         $orderSQL
         LIMIT ? OFFSET ?"
    );
    $stmt->execute(array_merge($params, $havingParams, [ITEMS_PER_PAGE, $offset]));
    $bouquets = $stmt->fetchAll();

    /* Categories with product counts */
    $categories = $pdo->query(
        "SELECT c.id, c.name, c.slug, COUNT(b.id) AS product_count
         FROM   categories c
         LEFT JOIN bouquets b ON b.category_id = c.id AND b.is_active = 1
         GROUP  BY c.id
         ORDER  BY c.name ASC"
    )->fetchAll();

} catch (RuntimeException $e) {
    $error = 'Unable to load products. Please try again.';
}

/* ── Wishlist IDs for logged-in user ── */
$wishlistIds = [];
if (!empty($_SESSION['user_id'])) {
    try {
        $ws = getPDO()->prepare("SELECT bouquet_id FROM wishlists WHERE user_id = ?");
        $ws->execute([$_SESSION['user_id']]);
        $wishlistIds = array_map('intval', $ws->fetchAll(\PDO::FETCH_COLUMN));
    } catch (\Exception $e) {
        $wishlistIds = [];
    }
}

/* ── Helpers ── */
function buildUrl(array $overrides): string {
    $params = array_merge($_GET, $overrides);
    return '/bloom-aura/pages/shop.php?' . http_build_query($params);
}

$catIcons = [
    'bouquets'   => '💐',
    'hampers'    => '🎁',
    'chocolates' => '🍫',
    'perfumes'   => '🌹',
    'plants'     => '🪴',
];

$priceRanges = [
    ['label' => 'Any price',       'min' => 0,    'max' => 0],
    ['label' => 'Under ₹799',      'min' => 0,    'max' => 799],
    ['label' => '₹800 – ₹1,199',  'min' => 800,  'max' => 1199],
    ['label' => '₹1,200 & above',  'min' => 1200, 'max' => 0],
];

$ratingOptions = [
    ['label' => 'All Ratings', 'val' => 0,   'stars' => 5],
    ['label' => '4.5 & up',    'val' => 4.5, 'stars' => 4.5],
    ['label' => '4.0 & up',    'val' => 4.0, 'stars' => 4.0],
    ['label' => '3.5 & up',    'val' => 3.5, 'stars' => 3.5],
];

$pageTitle = 'Shop — Bloom Aura';
$pageCss   = 'shop';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ═══════════════════════════════════════════
     STICKY TOPBAR
════════════════════════════════════════════ -->
<div class="shop-topbar">
    <div class="shop-topbar-left">
        <h2>🌸 Bloom Aura Collections</h2>
        <p>
            <?php if ($totalItems > 0): ?>
                Showing
                <?= min($offset + 1, $totalItems) ?>–<?= min($offset + ITEMS_PER_PAGE, $totalItems) ?>
                of <?= $totalItems ?> product<?= $totalItems !== 1 ? 's' : '' ?>
                <?php if ($catSlug): ?>
                    in <strong><?= htmlspecialchars(ucfirst($catSlug), ENT_QUOTES, 'UTF-8') ?></strong>
                <?php endif; ?>
            <?php else: ?>
                No products found
            <?php endif; ?>
        </p>
    </div>
    <div class="shop-topbar-right">
        <form method="GET" action="/bloom-aura/pages/shop.php" class="sort-form" id="sort-form">
            <?php if ($catSlug):   ?><input type="hidden" name="cat"        value="<?= htmlspecialchars($catSlug,  ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
            <?php if ($search):    ?><input type="hidden" name="q"          value="<?= htmlspecialchars($search,   ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
            <?php if ($priceMin):  ?><input type="hidden" name="price_min"  value="<?= $priceMin ?>"><?php endif; ?>
            <?php if ($priceMax):  ?><input type="hidden" name="price_max"  value="<?= $priceMax ?>"><?php endif; ?>
            <?php if ($ratingMin): ?><input type="hidden" name="rating_min" value="<?= $ratingMin ?>"><?php endif; ?>
            <label for="sort-select" class="sr-only">Sort products</label>
            <select class="sort-select" name="sort" id="sort-select">
                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Sort: Newest</option>
                <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low → High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
                <option value="rating"     <?= $sort === 'rating'     ? 'selected' : '' ?>>Top Rated</option>
            </select>
        </form>

        <a href="/bloom-aura/pages/cart.php" class="cart-pill" aria-label="View cart">
            🛒 Cart
            <?php
            $cartPillCount = 0;
            if (!empty($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $ci) $cartPillCount += (int)($ci['qty'] ?? 1);
            }
            ?>
            <?php if ($cartPillCount > 0): ?>
                <span class="cart-badge"><?= $cartPillCount ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     SHOP BODY — sidebar + main
════════════════════════════════════════════ -->
<div class="shop-body">

    <!-- ════════════════════════
         SIDEBAR FILTERS
    ════════════════════════ -->
    <aside class="shop-sidebar-panel" aria-label="Product filters">

        <!-- CATEGORY -->
        <div class="filter-group">
            <h4>Category</h4>
            <div class="cat-list">
                <a href="<?= htmlspecialchars(buildUrl(['cat' => '', 'page' => 1]), ENT_QUOTES, 'UTF-8') ?>"
                   class="cat-row <?= $catSlug === '' ? 'active' : '' ?>">
                    <span class="cat-icon">🌺</span>
                    <span class="cat-label">All Products</span>
                    <span class="cat-count"><?= $totalItems ?></span>
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="<?= htmlspecialchars(buildUrl(['cat' => $cat['slug'], 'page' => 1]), ENT_QUOTES, 'UTF-8') ?>"
                   class="cat-row <?= $catSlug === $cat['slug'] ? 'active' : '' ?>">
                    <span class="cat-icon"><?= $catIcons[$cat['slug']] ?? '🌸' ?></span>
                    <span class="cat-label"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="cat-count"><?= (int)$cat['product_count'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PRICE RANGE -->
        <div class="filter-group">
            <h4>Price Range</h4>
            <div class="price-list">
                <?php foreach ($priceRanges as $range):
                    $isActive = ($priceMin === $range['min'] && $priceMax === $range['max']);
                ?>
                <a href="<?= htmlspecialchars(buildUrl(['price_min' => $range['min'], 'price_max' => $range['max'], 'page' => 1]), ENT_QUOTES, 'UTF-8') ?>"
                   class="price-row <?= $isActive ? 'active' : '' ?>">
                    <span class="price-check"><?= $isActive ? '✓' : '' ?></span>
                    <?= htmlspecialchars($range['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CUSTOMER RATING — card style (matching reference) -->
        <div class="filter-group">
            <h4>Customer Rating</h4>
            <div class="rating-cards-list">
                <?php foreach ($ratingOptions as $ro):
                    $isActive = (string)$ratingMin === (string)$ro['val'];
                    $starHtml = '';
                    for ($si = 1; $si <= 5; $si++):
                        $fill = min(max($ro['stars'] - $si + 1, 0), 1);
                        if ($fill >= 1)       $starHtml .= '<span class="rs full">★</span>';
                        elseif ($fill >= 0.5) $starHtml .= '<span class="rs half">★</span>';
                        else                  $starHtml .= '<span class="rs empty">★</span>';
                    endfor;
                ?>
                <a href="<?= htmlspecialchars(buildUrl(['rating_min' => $ro['val'], 'page' => 1]), ENT_QUOTES, 'UTF-8') ?>"
                   class="rating-card <?= $isActive ? 'selected' : '' ?>">
                    <span class="r-check" aria-hidden="true"><?= $isActive ? '✓' : '' ?></span>
                    <span class="star-row-render" aria-label="<?= $ro['label'] ?>"><?= $starHtml ?></span>
                    <span class="r-label"><?= htmlspecialchars($ro['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SEARCH -->
        <div class="filter-group">
            <h4>Search</h4>
            <form method="GET" action="/bloom-aura/pages/shop.php" class="sidebar-search-form">
                <?php if ($catSlug):   ?><input type="hidden" name="cat"        value="<?= htmlspecialchars($catSlug,  ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                <?php if ($priceMin):  ?><input type="hidden" name="price_min"  value="<?= $priceMin ?>"><?php endif; ?>
                <?php if ($priceMax):  ?><input type="hidden" name="price_max"  value="<?= $priceMax ?>"><?php endif; ?>
                <?php if ($ratingMin): ?><input type="hidden" name="rating_min" value="<?= $ratingMin ?>"><?php endif; ?>
                <label for="sidebar-search" class="sr-only">Search bouquets</label>
                <input
                    type="search"
                    id="sidebar-search"
                    name="q"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Search bouquets…"
                    class="search-input"
                    autocomplete="off"
                >
                <button type="submit" class="search-button">Search</button>
            </form>
        </div>

        <!-- CLEAR ALL FILTERS -->
        <?php if ($catSlug || $priceMin || $priceMax || $ratingMin || $search): ?>
        <div class="filter-group" style="border-bottom:none;">
            <a href="/bloom-aura/pages/shop.php" class="clear-filters-btn">✕ Clear All Filters</a>
        </div>
        <?php endif; ?>

    </aside>

    <!-- ════════════════════════
         MAIN PRODUCT AREA
    ════════════════════════ -->
    <main class="shop-main-panel" id="shop-main">

        <!-- Active search banner -->
        <?php if ($search): ?>
        <p class="results-info">
            Results for "<strong><?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?></strong>"
            <a href="<?= htmlspecialchars(buildUrl(['q' => '', 'page' => 1]), ENT_QUOTES, 'UTF-8') ?>"
               class="clear-link">Clear ✗</a>
        </p>
        <?php endif; ?>

        <!-- Error -->
        <?php if ($error): ?>
        <div class="error-box" role="alert">❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

        <!-- Empty -->
        <?php elseif (empty($bouquets)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h2>No bouquets found</h2>
            <p>Try adjusting your search or filters.</p>
            <a href="/bloom-aura/pages/shop.php" class="btn btn-primary btn-lg">Clear Filters</a>
        </div>

        <!-- Products -->
        <?php else: ?>
        <div class="product-grid" id="product-grid">
            <?php foreach ($bouquets as $b):
                $isWishlisted = in_array((int)$b['id'], $wishlistIds, true);
            ?>
            <article class="product-card">

                <!-- Image wrap -->
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

                <!-- Wishlist heart (logged-in users only) -->
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

                    <!-- Star rating -->
                    <?php if ($b['review_count'] > 0): ?>
                    <div class="card-stars"
                         aria-label="Rated <?= $b['avg_rating'] ?> out of 5">
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
                        <span class="price-tag">₹<?= number_format($b['price'], 2) ?></span>
                        <?php if ($b['stock'] > 0): ?>
                            <form method="POST" action="/bloom-aura/pages/cart.php">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action"     value="add">
                                <input type="hidden" name="product_id" value="<?= (int)$b['id'] ?>">
                                <input type="hidden" name="qty"        value="1">
                                <button
                                    type="submit"
                                    class="add-btn"
                                    data-name="<?= htmlspecialchars($b['name'],  ENT_QUOTES, 'UTF-8') ?>"
                                    data-price="₹<?= number_format($b['price'], 2) ?>"
                                >🛒 Add</button>
                            </form>
                        <?php else: ?>
                            <button class="add-btn" disabled>Sold Out</button>
                        <?php endif; ?>
                    </div>
                </div>

            </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Page navigation">
            <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars(buildUrl(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>"
                   class="page-link">‹ Prev</a>
            <?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <a href="<?= htmlspecialchars(buildUrl(['page' => $p]), ENT_QUOTES, 'UTF-8') ?>"
                   class="page-link <?= $p === $page ? 'active' : '' ?>"
                   <?= $p === $page ? 'aria-current="page"' : '' ?>>
                    <?= $p ?>
                </a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= htmlspecialchars(buildUrl(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>"
                   class="page-link">Next ›</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php endif; // end products block ?>

    </main>
</div><!-- /.shop-body -->

<!-- ═══════════════════════════════════════════
     TOAST — matching reference bloom_aura style
════════════════════════════════════════════ -->
<div class="shop-toast" id="shopToast" role="status" aria-live="polite" aria-atomic="true">
    <div class="toast-icon">🌸</div>
    <div>
        <div class="toast-title" id="toastTitle">Added to Cart!</div>
        <div class="toast-sub"   id="toastSub">Your item is ready</div>
    </div>
    <div class="toast-price" id="toastPrice"></div>
</div>

<script src="/bloom-aura/assets/js/shop.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>