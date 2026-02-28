<?php
/**
 * bloom-aura/pages/shop.php
 * Shop page — reference UI with topbar, sticky sidebar, product grid.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/header.php';

const ITEMS_PER_PAGE = 12;

$search   = trim($_GET['q']         ?? '');
$catSlug  = trim($_GET['cat']       ?? '');
$priceMin = max(0, (int)($_GET['price_min'] ?? 0));
$priceMax = (int)($_GET['price_max'] ?? 0);
$sort     = in_array($_GET['sort'] ?? '', ['price_asc','price_desc','newest','rating'])
              ? $_GET['sort'] : 'newest';
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * ITEMS_PER_PAGE;

$bouquets   = [];
$categories = [];
$totalItems = 0;
$totalPages = 1;
$error      = '';

try {
    $pdo = getPDO();

    $where  = ['b.is_active = 1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(b.name LIKE ? OR b.description LIKE ? OR c.name LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = array_merge($params, [$like, $like, $like]);
    }
    if ($catSlug !== '') { $where[] = 'c.slug = ?'; $params[] = $catSlug; }
    if ($priceMin > 0)   { $where[] = 'b.price >= ?'; $params[] = $priceMin; }
    if ($priceMax > 0)   { $where[] = 'b.price <= ?'; $params[] = $priceMax; }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);
    $orderSQL = match ($sort) {
        'price_asc'  => 'ORDER BY b.price ASC',
        'price_desc' => 'ORDER BY b.price DESC',
        'rating'     => 'ORDER BY avg_rating DESC',
        default      => 'ORDER BY b.created_at DESC',
    };

    $countStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT b.id) FROM bouquets b
         JOIN categories c ON b.category_id = c.id $whereSQL"
    );
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalItems / ITEMS_PER_PAGE));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * ITEMS_PER_PAGE;

    $stmt = $pdo->prepare(
        "SELECT b.id, b.name, b.slug, b.price, b.stock, b.image,
                c.name AS category_name, c.slug AS category_slug,
                ROUND(COALESCE(AVG(r.rating),0),1) AS avg_rating,
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

    // Categories with counts
    $categories = $pdo->query(
        "SELECT c.id, c.name, c.slug, COUNT(b.id) AS product_count
         FROM categories c
         LEFT JOIN bouquets b ON b.category_id = c.id AND b.is_active = 1
         GROUP BY c.id ORDER BY c.name ASC"
    )->fetchAll();

} catch (RuntimeException $e) {
    $error = 'Unable to load products. Please try again.';
}

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
?>

<!-- ── TOPBAR ── -->
<div class="shop-topbar">
    <div class="shop-topbar-left">
        <h2>🌸 Bloom Aura Collections</h2>
        <p>
            <?php if ($totalItems > 0): ?>
                Showing <?= min($offset+1,$totalItems) ?>–<?= min($offset+ITEMS_PER_PAGE,$totalItems) ?> of <?= $totalItems ?> products
                <?= $catSlug ? ' in <strong>' . htmlspecialchars(ucfirst($catSlug), ENT_QUOTES, 'UTF-8') . '</strong>' : '' ?>
            <?php else: ?>
                No products found
            <?php endif; ?>
        </p>
    </div>
    <div class="shop-topbar-right">
        <form method="GET" action="/bloom-aura/pages/shop.php" class="sort-form">
            <?php if ($catSlug): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($catSlug, ENT_QUOTES,'UTF-8') ?>"><?php endif; ?>
            <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES,'UTF-8') ?>"><?php endif; ?>
            <select class="sort-select" name="sort" onchange="this.form.submit()">
                <option value="newest"    <?= $sort==='newest'    ?'selected':'' ?>>Sort: Newest</option>
                <option value="price_asc" <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low → High</option>
                <option value="price_desc"<?= $sort==='price_desc'?'selected':'' ?>>Price: High → Low</option>
                <option value="rating"    <?= $sort==='rating'    ?'selected':'' ?>>Top Rated</option>
            </select>
        </form>
        <a href="/bloom-aura/pages/cart.php" class="cart-pill">
            🛒 Cart<?php
            $cartCount = 0;
            if (!empty($_SESSION['cart'])) foreach ($_SESSION['cart'] as $i) $cartCount += (int)($i['qty']??1);
            if ($cartCount > 0) echo ' <span class="cart-badge">' . $cartCount . '</span>';
            ?>
        </a>
    </div>
</div>

<!-- ── BODY ── -->
<div class="shop-body">

    <!-- SIDEBAR -->
    <aside class="shop-sidebar-panel" aria-label="Filters">

        <!-- Categories -->
        <div class="filter-group">
            <h4>Category</h4>
            <div class="cat-list">
                <a href="<?= buildUrl(['cat'=>'','page'=>1]) ?>"
                   class="cat-row <?= $catSlug==='' ? 'active' : '' ?>">
                    <span class="cat-icon">🌺</span>
                    <span class="cat-label">All Products</span>
                    <span class="cat-count"><?= $totalItems ?></span>
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="<?= buildUrl(['cat'=>htmlspecialchars($cat['slug'],ENT_QUOTES,'UTF-8'),'page'=>1]) ?>"
                   class="cat-row <?= $catSlug===$cat['slug'] ? 'active' : '' ?>">
                    <span class="cat-icon"><?= $catIcons[$cat['slug']] ?? '🌸' ?></span>
                    <span class="cat-label"><?= htmlspecialchars($cat['name'],ENT_QUOTES,'UTF-8') ?></span>
                    <span class="cat-count"><?= (int)$cat['product_count'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Price Range -->
        <div class="filter-group">
            <h4>Price Range</h4>
            <div class="price-list">
                <?php
                $priceRanges = [
                    ['label'=>'Any price',       'min'=>0,    'max'=>0],
                    ['label'=>'Under ₹799',       'min'=>0,    'max'=>799],
                    ['label'=>'₹800 – ₹1,199',   'min'=>800,  'max'=>1199],
                    ['label'=>'₹1,200 & above',   'min'=>1200, 'max'=>0],
                ];
                foreach ($priceRanges as $range):
                    $isActive = ($priceMin === $range['min'] && $priceMax === $range['max']);
                ?>
                <a href="<?= buildUrl(['price_min'=>$range['min'],'price_max'=>$range['max'],'page'=>1]) ?>"
                   class="price-row <?= $isActive ? 'active' : '' ?>">
                    <span><?= $isActive ? '✓' : '○' ?></span>
                    <?= $range['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Search -->
        <div class="filter-group">
            <h4>Search</h4>
            <form method="GET" action="/bloom-aura/pages/shop.php">
                <?php if ($catSlug): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($catSlug,ENT_QUOTES,'UTF-8') ?>"><?php endif; ?>
                <input type="search" name="q"
                       value="<?= htmlspecialchars($search,ENT_QUOTES,'UTF-8') ?>"
                       placeholder="Search bouquets…"
                       class="search-input"
                       onfocus="this.style.borderColor='#d63384'" onblur="this.style.borderColor='#e0e0e0'">
                <button type="submit" class="search-button">Search</button>
            </form>
        </div>

    </aside>

    <!-- MAIN -->
    <main class="shop-main-panel">

        <?php if ($search): ?>
            <p class="results-info">
                Results for "<strong><?= htmlspecialchars($search,ENT_QUOTES,'UTF-8') ?></strong>"
                — <a href="<?= buildUrl(['q'=>'','page'=>1]) ?>" class="clear-link">Clear ✗</a>
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-box">
                ❌ <?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?>
            </div>

        <?php elseif (empty($bouquets)): ?>
            <div class="empty-state">
                <div class="empty-icon">🌷</div>
                <h2>No bouquets found</h2>
                <p>Try adjusting your search or filters.</p>
                <a href="/bloom-aura/pages/shop.php"
                   class="btn btn-primary btn-lg">
                    Clear Filters
                </a>
            </div>

        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($bouquets as $b): ?>
                <article class="product-card">

                    <!-- Image -->
                    <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($b['slug']) ?>"
                       class="card-img-link">
                        <img src="/bloom-aura/uploads/<?= htmlspecialchars($b['image'],ENT_QUOTES,'UTF-8') ?>"
                             alt="<?= htmlspecialchars($b['name'],ENT_QUOTES,'UTF-8') ?>"
                             loading="lazy"
                             onerror="this.src='/bloom-aura/assets/img/placeholder.jpg'">
                        <?php if ($b['stock'] <= 0): ?>
                            <span class="badge badge-oos">Out of Stock</span>
                        <?php elseif ($b['stock'] <= 5): ?>
                            <span class="badge badge-low">Only <?= (int)$b['stock'] ?> left!</span>
                        <?php endif; ?>
                    </a>

                    <!-- Wishlist button -->
                    <button class="card-wishlist" onclick="addWishlist(<?= (int)$b['id'] ?>, '<?= htmlspecialchars($b['name'],ENT_QUOTES,'UTF-8') ?>', this)" title="Save to wishlist">🤍</button>

                    <!-- Body -->
                    <div class="card-body">
                        <p class="card-category"><?= htmlspecialchars($b['category_name']??'',ENT_QUOTES,'UTF-8') ?></p>
                        <h3 class="card-title">
                            <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($b['slug']) ?>">
                                <?= htmlspecialchars($b['name'],ENT_QUOTES,'UTF-8') ?>
                            </a>
                        </h3>

                        <?php if ($b['review_count'] > 0): ?>
                        <div class="card-stars">
                            <?php
                            $avg = (float)$b['avg_rating'];
                            for ($i = 1; $i <= 5; $i++):
                                $cls = $avg >= $i ? 'star-full' : ($avg >= $i-.5 ? 'star-half' : 'star-empty');
                            ?>
                                <span class="<?= $cls ?>">★</span>
                            <?php endfor; ?>
                            <span class="rev-count">(<?= (int)$b['review_count'] ?>)</span>
                        </div>
                        <?php endif; ?>

                        <div class="card-footer">
                            <span class="price-tag">₹<?= number_format($b['price'],2) ?></span>
                            <?php if ($b['stock'] > 0): ?>
                                <form method="POST" action="/bloom-aura/pages/cart.php">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= (int)$b['id'] ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button type="submit" class="add-btn"
                                            onclick="showToast('<?= htmlspecialchars($b['name'],ENT_QUOTES,'UTF-8') ?>', '₹<?= number_format($b['price'],2) ?>')">
                                        🛒 Add
                                    </button>
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
            <nav class="pagination" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars(buildUrl(['page'=>$page-1]),ENT_QUOTES,'UTF-8') ?>" class="page-link">‹ Prev</a>
                <?php endif; ?>
                <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                    <a href="<?= htmlspecialchars(buildUrl(['page'=>$p]),ENT_QUOTES,'UTF-8') ?>"
                       class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= htmlspecialchars(buildUrl(['page'=>$page+1]),ENT_QUOTES,'UTF-8') ?>" class="page-link">Next ›</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        <?php endif; ?>

    </main>
</div>

<!-- ── Toast ── -->
<div class="shop-toast" id="shopToast">
    <div class="toast-icon">🌸</div>
    <div>
        <div class="toast-title" id="toastTitle">Added to Cart!</div>
        <div class="toast-sub" id="toastSub">Your item is ready</div>
    </div>
    <div class="toast-price" id="toastPrice"></div>
</div>

<script>
function showToast(name, price) {
    var t = document.getElementById('shopToast');
    document.getElementById('toastTitle').textContent = 'Added to Cart!';
    document.getElementById('toastSub').textContent = name;
    document.getElementById('toastPrice').textContent = price;
    t.classList.add('show');
    setTimeout(function(){ t.classList.remove('show'); }, 3000);
}

function addWishlist(id, name, btn) {
    <?php if (empty($_SESSION['user_id'])): ?>
        window.location = '/bloom-aura/pages/login.php';
    <?php else: ?>
        // POST to wishlist endpoint
        btn.textContent = '❤️';
        btn.title = 'Saved!';
        showToast('Saved to Wishlist!', name);
    <?php endif; ?>
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>