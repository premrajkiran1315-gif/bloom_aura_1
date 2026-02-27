<?php
/**
 * bloom-aura/pages/shop.php
 * Product listing with server-side search, category filter, price filter,
 * and pagination. No user input is interpolated into SQL — PDO only.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php'; // starts HTML

// ── Pagination config ──────────────────────────
const ITEMS_PER_PAGE = 12;

// ── Read and sanitise query params ─────────────
$search   = trim($_GET['q']       ?? '');
$catSlug  = trim($_GET['cat']     ?? '');
$priceMin = max(0, (int)($_GET['price_min'] ?? 0));
$priceMax = (int)($_GET['price_max'] ?? 0); // 0 = no upper limit
$sort     = in_array($_GET['sort'] ?? '', ['price_asc','price_desc','newest','rating'])
              ? $_GET['sort'] : 'newest';
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * ITEMS_PER_PAGE;

try {
    $pdo = getPDO();

    // ── Build WHERE clause dynamically ──────────
    $where  = ['b.is_active = 1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(b.name LIKE ? OR b.description LIKE ? OR c.name LIKE ?)';
        $like     = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($catSlug !== '') {
        $where[]  = 'c.slug = ?';
        $params[] = $catSlug;
    }
    if ($priceMin > 0) {
        $where[]  = 'b.price >= ?';
        $params[] = $priceMin;
    }
    if ($priceMax > 0) {
        $where[]  = 'b.price <= ?';
        $params[] = $priceMax;
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    // ── ORDER BY ────────────────────────────────
    $orderSQL = match ($sort) {
        'price_asc'  => 'ORDER BY b.price ASC',
        'price_desc' => 'ORDER BY b.price DESC',
        'rating'     => 'ORDER BY avg_rating DESC',
        default      => 'ORDER BY b.created_at DESC',
    };

    // ── Count total for pagination ──────────────
    $countStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT b.id)
         FROM bouquets b
         JOIN categories c ON b.category_id = c.id
         $whereSQL"
    );
    $countStmt->execute($params);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalItems / ITEMS_PER_PAGE));
    $page       = min($page, $totalPages);

    // ── Fetch products ──────────────────────────
    $stmt = $pdo->prepare(
        "SELECT b.id, b.name, b.slug, b.price, b.stock, b.image,
                c.name AS category_name, c.slug AS category_slug,
                COALESCE(AVG(r.rating), 0) AS avg_rating,
                COUNT(r.id) AS review_count
         FROM bouquets b
         JOIN categories c ON b.category_id = c.id
         LEFT JOIN reviews r ON r.bouquet_id = b.id
         $whereSQL
         GROUP BY b.id
         $orderSQL
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([...$params, ITEMS_PER_PAGE, $offset]);
    $bouquets = $stmt->fetchAll();

    // ── Fetch all categories for sidebar ────────
    $categories = $pdo->query(
        'SELECT id, name, slug FROM categories ORDER BY name ASC'
    )->fetchAll();

} catch (RuntimeException $e) {
    $bouquets   = [];
    $categories = [];
    $totalPages = 1;
    $error = 'Unable to load products. Please try again.';
}

// Helper to preserve existing query params when building pagination URLs
function buildUrl(array $overrides): string {
    $params = array_merge($_GET, $overrides);
    return '/pages/shop.php?' . http_build_query($params);
}
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/">Home</a></li>
        <li aria-current="page">Shop</li>
    </ol>
</nav>

<div class="shop-layout">
    <!-- ── SIDEBAR FILTERS ── -->
    <aside class="shop-sidebar" aria-label="Product filters">
        <form action="/pages/shop.php" method="GET" id="filter-form">
            <!-- Preserve search query -->
            <?php if ($search !== ''): ?>
                <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>

            <div class="filter-section">
                <h3 class="filter-heading">Category</h3>
                <ul class="filter-list">
                    <li>
                        <a href="<?= buildUrl(['cat' => '', 'page' => 1]) ?>"
                           class="filter-link <?= $catSlug === '' ? 'active' : '' ?>">
                            All Products
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="<?= buildUrl(['cat' => htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8'), 'page' => 1]) ?>"
                               class="filter-link <?= $catSlug === $cat['slug'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="filter-section">
                <h3 class="filter-heading">Price Range</h3>
                <div class="price-range-inputs">
                    <label for="price_min" class="sr-only">Minimum price</label>
                    <input type="number" id="price_min" name="price_min" placeholder="₹ Min"
                           value="<?= $priceMin > 0 ? $priceMin : '' ?>" min="0">
                    <span>–</span>
                    <label for="price_max" class="sr-only">Maximum price</label>
                    <input type="number" id="price_max" name="price_max" placeholder="₹ Max"
                           value="<?= $priceMax > 0 ? $priceMax : '' ?>" min="0">
                </div>
                <button type="submit" class="btn btn-outline btn-sm btn-full">Apply</button>
            </div>

            <div class="filter-section">
                <h3 class="filter-heading">Sort By</h3>
                <select name="sort" id="sort-select" class="sort-select" onchange="this.form.submit()">
                    <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest First</option>
                    <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="rating"     <?= $sort === 'rating'     ? 'selected' : '' ?>>Top Rated</option>
                </select>
            </div>
        </form>
    </aside>

    <!-- ── PRODUCT GRID ── -->
    <section class="shop-main">
        <!-- Search bar -->
        <form action="/pages/shop.php" method="GET" class="shop-search-bar" role="search">
            <?php if ($catSlug !== ''): ?>
                <input type="hidden" name="cat" value="<?= htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <label for="shop-search" class="sr-only">Search products</label>
            <input type="search" id="shop-search" name="q"
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Search bouquets, occasions…"
                   autocomplete="off">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <p class="results-count">
            <?php if ($totalItems > 0): ?>
                Showing <?= min($offset + 1, $totalItems) ?>–<?= min($offset + ITEMS_PER_PAGE, $totalItems) ?> of <?= $totalItems ?> products
            <?php else: ?>
                No products found
            <?php endif; ?>
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif (empty($bouquets)): ?>
            <!-- Empty state -->
            <div class="empty-state">
                <div class="empty-icon">🌷</div>
                <h2>No bouquets found</h2>
                <p>Try adjusting your search or filters.</p>
                <a href="/pages/shop.php" class="btn btn-primary">Clear Filters</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($bouquets as $b): ?>
                    <article class="product-card">
                        <a href="/pages/product.php?slug=<?= urlencode($b['slug']) ?>" class="card-img-wrap">
                            <img
                                src="/uploads/bouquets/<?= htmlspecialchars($b['image'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                width="300" height="250"
                            >
                            <?php if ($b['stock'] <= 0): ?>
                                <span class="badge badge-oos">Out of Stock</span>
                            <?php elseif ($b['stock'] <= 5): ?>
                                <span class="badge badge-low">Only <?= $b['stock'] ?> left</span>
                            <?php endif; ?>
                        </a>
                        <div class="card-body">
                            <p class="card-category">
                                <?= htmlspecialchars($b['category_name'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <h2 class="card-title">
                                <a href="/pages/product.php?slug=<?= urlencode($b['slug']) ?>">
                                    <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </h2>
                            <div class="card-rating" aria-label="Rating: <?= round($b['avg_rating'], 1) ?> out of 5">
                                <?php
                                $rating = round($b['avg_rating'], 1);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($rating >= $i) echo '<span class="star full">★</span>';
                                    elseif ($rating >= $i - 0.5) echo '<span class="star half">★</span>';
                                    else echo '<span class="star empty">☆</span>';
                                }
                                ?>
                                <span class="review-count">(<?= $b['review_count'] ?>)</span>
                            </div>
                            <div class="card-footer">
                                <span class="price">₹<?= number_format($b['price'], 2) ?></span>
                                <?php if ($b['stock'] > 0): ?>
                                    <button class="btn btn-primary btn-sm add-to-cart-btn"
                                            data-id="<?= (int)$b['id'] ?>"
                                            data-name="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-basket-shopping"></i> Add
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-disabled btn-sm" disabled>Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Product pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars(buildUrl(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>"
                           class="page-link" aria-label="Previous page">
                            &laquo; Prev
                        </a>
                    <?php endif; ?>

                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <a href="<?= htmlspecialchars(buildUrl(['page' => $p]), ENT_QUOTES, 'UTF-8') ?>"
                           class="page-link <?= $p === $page ? 'active' : '' ?>"
                           aria-label="Page <?= $p ?>"
                           <?= $p === $page ? 'aria-current="page"' : '' ?>>
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= htmlspecialchars(buildUrl(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>"
                           class="page-link" aria-label="Next page">
                            Next &raquo;
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<script src="/assets/js/cart.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
