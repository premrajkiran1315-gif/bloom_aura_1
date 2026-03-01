<?php
/**
 * bloom-aura/pages/shop.php
 * Shop page — fully synced to bloom_aura reference UI.
 * Sticky topbar · sidebar (category, price, rating, search) ·
 * product grid with wishlist hearts · Add-to-Cart forms · toast · pagination.
 * All PDO/CSRF/session logic preserved.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

const ITEMS_PER_PAGE = 12;

/* ── Input sanitisation ── */
$search    = trim($_GET['q']           ?? '');
$catSlug   = trim($_GET['cat']         ?? '');
$priceMin  = max(0, (int)($_GET['price_min']  ?? 0));
$priceMax  = (int)($_GET['price_max']   ?? 0);
$ratingMin = (float)($_GET['rating_min'] ?? 0);
$sort      = in_array($_GET['sort'] ?? '', ['price_asc', 'price_desc', 'newest', 'rating'])
               ? $_GET['sort'] : 'newest';
$page      = max(1, (int)($_GET['page'] ?? 1));

$bouquets   = [];
$categories = [];
$totalItems = 0;
$totalPages = 1;
$error      = '';

/* ── Handle Add-to-Cart POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    csrf_validate();
    $bouquetId = (int)($_POST['bouquet_id'] ?? 0);
    $qty       = max(1, (int)($_POST['qty'] ?? 1));

    if ($bouquetId > 0) {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare('SELECT id, name, price, stock, image FROM bouquets WHERE id = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$bouquetId]);
            $product = $stmt->fetch();

            if ($product && $product['stock'] > 0) {
                if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                $cart = &$_SESSION['cart'];
                if (isset($cart[$bouquetId])) {
                    $cart[$bouquetId]['qty'] = min($cart[$bouquetId]['qty'] + $qty, (int)$product['stock']);
                } else {
                    $cart[$bouquetId] = [
                        'id'    => $bouquetId,
                        'name'  => $product['name'],
                        'price' => $product['price'],
                        'image' => $product['image'],
                        'qty'   => min($qty, (int)$product['stock']),
                    ];
                }
                flash(htmlspecialchars($product['name']) . ' added to cart! 🛒', 'success');
            } else {
                flash('Sorry, this item is out of stock.', 'error');
            }
        } catch (RuntimeException $e) {
            flash('Could not add to cart. Please try again.', 'error');
        }
    }

    /* Redirect-after-POST to preserve all filters */
    $redirect = '/bloom-aura/pages/shop.php?' . http_build_query(array_filter([
        'q'          => $search,
        'cat'        => $catSlug,
        'price_min'  => $priceMin ?: null,
        'price_max'  => $priceMax ?: null,
        'rating_min' => $ratingMin ?: null,
        'sort'       => $sort !== 'newest' ? $sort : null,
        'page'       => $page > 1 ? $page : null,
    ]));
    header('Location: ' . $redirect);
    exit;
}

/* ── Helper: build URL preserving current filters ── */
function buildUrl(array $overrides = []): string {
    global $search, $catSlug, $priceMin, $priceMax, $ratingMin, $sort, $page;
    $params = array_filter(array_merge([
        'q'          => $search,
        'cat'        => $catSlug,
        'price_min'  => $priceMin  ?: null,
        'price_max'  => $priceMax  ?: null,
        'rating_min' => $ratingMin ?: null,
        'sort'       => $sort !== 'newest' ? $sort : null,
        'page'       => $page > 1 ? $page : null,
    ], $overrides), fn($v) => $v !== null && $v !== '' && $v !== 0);
    return '/bloom-aura/pages/shop.php' . ($params ? '?' . http_build_query($params) : '');
}

/* ── Category icon map ── */
$catIcons = [
    'bouquets'   => '💐',
    'hampers'    => '🎁',
    'chocolates' => '🍫',
    'perfumes'   => '🌹',
    'plants'     => '🪴',
];

/* ── Price range options ── */
$priceRanges = [
    ['min' => 0,    'max' => 0,    'label' => 'Any Price'],
    ['min' => 0,    'max' => 799,  'label' => 'Under ₹799'],
    ['min' => 800,  'max' => 1199, 'label' => '₹800 – ₹1,199'],
    ['min' => 1200, 'max' => 0,    'label' => '₹1,200 & above'],
];

/* ── Rating filter options ── */
$ratingOptions = [
    ['val' => 0,   'stars' => 5,   'label' => 'All'],
    ['val' => 4.5, 'stars' => 4.5, 'label' => '4.5 & up'],
    ['val' => 4.0, 'stars' => 4.0, 'label' => '4.0 & up'],
    ['val' => 3.5, 'stars' => 3.5, 'label' => '3.5 & up'],
];

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

    /* Sort clause */
    $sortSQL = match ($sort) {
        'price_asc'  => 'ORDER BY b.price ASC',
        'price_desc' => 'ORDER BY b.price DESC',
        'rating'     => 'ORDER BY avg_rating DESC, b.created_at DESC',
        default      => 'ORDER BY b.created_at DESC',
    };

    /* Total count for pagination */
    $countSQL = "
        SELECT COUNT(*) FROM (
            SELECT b.id,
                   ROUND(COALESCE(AVG(r.rating), 0), 1) AS avg_rating
            FROM   bouquets b
            LEFT JOIN categories c ON c.id = b.category_id
            LEFT JOIN reviews    r ON r.bouquet_id = b.id
            $whereSQL
            GROUP BY b.id
            $havingSQL
        ) sub
    ";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute(array_merge($params, $havingParams));
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalItems / ITEMS_PER_PAGE));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * ITEMS_PER_PAGE;

    /* Products */
    $productSQL = "
        SELECT b.id, b.name, b.slug, b.price, b.image, b.stock, b.description,
               c.name AS category_name, c.slug AS category_slug,
               ROUND(COALESCE(AVG(r.rating), 0), 1) AS avg_rating,
               COUNT(r.id) AS review_count
        FROM   bouquets b
        LEFT JOIN categories c ON c.id = b.category_id
        LEFT JOIN reviews    r ON r.bouquet_id = b.id
        $whereSQL
        GROUP BY b.id, c.name, c.slug
        $havingSQL
        $sortSQL
        LIMIT ? OFFSET ?
    ";
    $productStmt = $pdo->prepare($productSQL);
    $productStmt->execute(array_merge($params, $havingParams, [ITEMS_PER_PAGE, $offset]));
    $bouquets = $productStmt->fetchAll();

    /* Categories with counts */
    $catSQL = "
        SELECT c.name, c.slug,
               COUNT(b.id) AS product_count
        FROM   categories c
        JOIN   bouquets b ON b.category_id = c.id AND b.is_active = 1
        GROUP  BY c.id, c.name, c.slug
        ORDER  BY c.name ASC
    ";
    $categories = $pdo->query($catSQL)->fetchAll();

    /* Wishlist IDs for logged-in user */
    $wishlistIds = [];
    if (!empty($_SESSION['user_id'])) {
        $wStmt = $pdo->prepare('SELECT bouquet_id FROM wishlist WHERE user_id = ?');
        $wStmt->execute([(int) $_SESSION['user_id']]);
        $wishlistIds = array_map('intval', $wStmt->fetchAll(\PDO::FETCH_COLUMN));
    }

} catch (RuntimeException $e) {
    $error = 'Unable to load products. Please try again later.';
}

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
                of <strong><?= $totalItems ?></strong> product<?= $totalItems !== 1 ? 's' : '' ?>
                <?php if ($catSlug): ?>
                    in <strong><?= htmlspecialchars(ucfirst($catSlug), ENT_QUOTES, 'UTF-8') ?></strong>
                <?php endif; ?>
            <?php else: ?>
                No products found
            <?php endif; ?>
        </p>
    </div>
    <div class="shop-topbar-right">
        <!-- Sort form — auto-submits via JS -->
        <form method="GET" action="/bloom-aura/pages/shop.php" class="sort-form" id="sort-form">
            <?php if ($catSlug):   ?><input type="hidden" name="cat"        value="<?= htmlspecialchars($catSlug,  ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
            <?php if ($search):    ?><input type="hidden" name="q"          value="<?= htmlspecialchars($search,   ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
            <?php if ($priceMin):  ?><input type="hidden" name="price_min"  value="<?= $priceMin ?>"><?php endif; ?>
            <?php if ($priceMax):  ?><input type="hidden" name="price_max"  value="<?= $priceMax ?>"><?php endif; ?>
            <?php if ($ratingMin): ?><input type="hidden" name="rating_min" value="<?= $ratingMin ?>"><?php endif; ?>
            <label for="sort-select" class="sr-only">Sort products</label>
            <select class="sort-select" name="sort" id="sort-select">
                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Sort: Featured</option>
                <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low → High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
                <option value="rating"     <?= $sort === 'rating'     ? 'selected' : '' ?>>Top Rated</option>
            </select>
        </form>

        <!-- Cart pill -->
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

        <!-- Mobile filter toggle -->
        <button class="filter-toggle-btn" id="filterToggleBtn" aria-expanded="false" aria-controls="shopSidebar" aria-label="Toggle filters">
            ☰ Filters
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     SHOP BODY — sidebar + main
════════════════════════════════════════════ -->
<div class="shop-body">

    <!-- ════════════════════════
         SIDEBAR FILTERS
    ════════════════════════ -->
    <aside class="shop-sidebar-panel" id="shopSidebar" aria-label="Product filters">

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

        <!-- CUSTOMER RATING — card style matching reference -->
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
                    <span class="star-row-render" aria-label="<?= htmlspecialchars($ro['label'], ENT_QUOTES, 'UTF-8') ?>"><?= $starHtml ?></span>
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

        <!-- Empty state -->
        <?php elseif (empty($bouquets)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h2>No bouquets found</h2>
            <p>Try adjusting your search or filters.</p>
            <a href="/bloom-aura/pages/shop.php" class="btn btn-primary btn-lg">Clear Filters</a>
        </div>

        <!-- Products grid -->
        <?php else: ?>
        <div class="product-grid" id="product-grid">
            <?php foreach ($bouquets as $b):
                $isWishlisted = in_array((int)$b['id'], $wishlistIds, true);
                $inStock      = (int)$b['stock'] > 0;
            ?>
            <article class="product-card">

                <!-- Image link -->
                <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($b['slug']) ?>"
                   class="card-img-wrap">
                    <img
                        src="/bloom-aura/uploads/<?= htmlspecialchars($b['image'], ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>"
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
                    <?php if ((int)$b['review_count'] > 0): ?>
                    <div class="card-stars" aria-label="Rated <?= $b['avg_rating'] ?> out of 5">
                        <?php
                        $avg = (float)$b['avg_rating'];
                        for ($i = 1; $i <= 5; $i++):
                            $cls = $avg >= $i ? 'full' : ($avg >= $i - 0.5 ? 'half' : 'empty');
                        ?>
                            <span class="star <?= $cls ?>">★</span>
                        <?php endfor; ?>
                        <span class="star-count">(<?= (int)$b['review_count'] ?>)</span>
                    </div>
                    <?php endif; ?>

                    <!-- Price -->
                    <p class="card-price">₹<?= number_format((float)$b['price'], 2) ?></p>

                    <!-- Add to Cart / Out of Stock -->
                    <?php if ($inStock): ?>
                    <form method="POST" action="/bloom-aura/pages/shop.php" class="add-cart-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action"     value="add_to_cart">
                        <input type="hidden" name="bouquet_id" value="<?= (int)$b['id'] ?>">
                        <input type="hidden" name="qty"        value="1">
                        <!-- Preserve current filters for redirect back -->
                        <?php if ($catSlug):   ?><input type="hidden" name="cat"        value="<?= htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                        <?php if ($search):    ?><input type="hidden" name="q"          value="<?= htmlspecialchars($search,  ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                        <?php if ($priceMin):  ?><input type="hidden" name="price_min"  value="<?= $priceMin ?>"><?php endif; ?>
                        <?php if ($priceMax):  ?><input type="hidden" name="price_max"  value="<?= $priceMax ?>"><?php endif; ?>
                        <?php if ($ratingMin): ?><input type="hidden" name="rating_min" value="<?= $ratingMin ?>"><?php endif; ?>
                        <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                        <?php if ($page > 1):  ?><input type="hidden" name="page"       value="<?= $page ?>"><?php endif; ?>
                        <button
                            type="submit"
                            class="add-btn"
                            data-name="<?= htmlspecialchars($b['name'],  ENT_QUOTES, 'UTF-8') ?>"
                            data-price="₹<?= number_format((float)$b['price'], 2) ?>"
                        >
                            🛒 Add to Cart
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="add-btn add-btn-disabled" disabled>Out of Stock</button>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Shop pagination">
            <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars(buildUrl(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>"
                   class="page-link">‹ Prev</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
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