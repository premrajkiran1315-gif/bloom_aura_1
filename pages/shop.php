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

<!-- ══════════════════════════════════════
     SHOP PAGE STYLES
══════════════════════════════════════ -->
<style>
/* Reset body bg for shop */
body { background: #f0f2f5; }

/* ── TOP BAR ── */
.shop-topbar {
    background: white;
    border-bottom: 1px solid #e0e0e0;
    padding: 13px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 68px;
    z-index: 90;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
}
.shop-topbar-left h2 {
    margin: 0;
    font-size: 1.05rem;
    color: #1e1218;
    font-weight: 700;
    font-family: 'Playfair Display', serif;
}
.shop-topbar-left p {
    margin: 2px 0 0;
    color: #888;
    font-size: .78rem;
}
.shop-topbar-right { display: flex; gap: 10px; align-items: center; }

.sort-select {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: .82rem;
    background: white;
    color: #333;
    outline: none;
    cursor: pointer;
    font-family: inherit;
}
.sort-select:focus { border-color: #d63384; }

.cart-pill {
    background: #d63384;
    color: white;
    padding: 8px 18px;
    border-radius: 30px;
    font-weight: 700;
    font-size: .82rem;
    cursor: pointer;
    border: none;
    display: flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: background .2s;
}
.cart-pill:hover { background: #ad1457; color: white; text-decoration: none; }

/* ── BODY LAYOUT ── */
.shop-body {
    display: grid;
    grid-template-columns: 210px 1fr;
    gap: 0;
    min-height: calc(100vh - 120px);
    max-width: 1400px;
    margin: 0 auto;
}

/* ── SIDEBAR ── */
.shop-sidebar-panel {
    background: white;
    border-right: 1px solid #e8e8e8;
    padding: 20px 14px;
    position: sticky;
    top: 120px;
    height: calc(100vh - 120px);
    overflow-y: auto;
}
.shop-sidebar-panel::-webkit-scrollbar { width: 4px; }
.shop-sidebar-panel::-webkit-scrollbar-thumb { background: #fce4ec; border-radius: 2px; }

.filter-group {
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f3f4f6;
}
.filter-group:last-child { border-bottom: none; margin-bottom: 0; }
.filter-group h4 {
    font-size: .65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.4px;
    color: #9ca3af;
    margin: 0 0 10px;
    font-family: 'Inter', sans-serif;
}

/* Category rows */
.cat-list { display: flex; flex-direction: column; gap: 2px; }
.cat-row {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 10px;
    border-radius: 10px;
    cursor: pointer;
    transition: background .15s, transform .12s;
    text-decoration: none;
    color: inherit;
}
.cat-row:hover { background: #fdf2f8; transform: translateX(2px); text-decoration: none; }
.cat-row.active { background: #fce7f3; }
.cat-row.active .cat-label { color: #be185d; font-weight: 700; }
.cat-row.active .cat-count { background: #ec4899; color: white; }
.cat-icon { font-size: 1.05rem; width: 22px; text-align: center; flex-shrink: 0; }
.cat-label { flex: 1; font-size: .82rem; color: #374151; }
.cat-count {
    font-size: .68rem;
    font-weight: 700;
    background: #f3f4f6;
    color: #6b7280;
    padding: 2px 7px;
    border-radius: 8px;
    min-width: 22px;
    text-align: center;
}

/* Price radio rows */
.price-list { display: flex; flex-direction: column; gap: 2px; }
.price-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 8px;
    border-radius: 8px;
    cursor: pointer;
    font-size: .82rem;
    color: #374151;
    transition: background .15s;
    text-decoration: none;
}
.price-row:hover { background: #fdf2f8; text-decoration: none; }
.price-row.active { background: #fce7f3; color: #be185d; font-weight: 700; }
.price-row input { accent-color: #ec4899; margin: 0; width: 15px; height: 15px; flex-shrink: 0; }

/* ── MAIN CONTENT ── */
.shop-main-panel { padding: 20px 24px; }

/* Search bar */
.shop-search-form {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}
.shop-search-form input {
    flex: 1;
    padding: 10px 16px;
    border: 1.5px solid #e0e0e0;
    border-radius: 10px;
    font-size: .9rem;
    font-family: inherit;
    outline: none;
    transition: border-color .2s;
}
.shop-search-form input:focus { border-color: #d63384; }
.search-btn {
    background: #d63384;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-size: .88rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: background .2s;
}
.search-btn:hover { background: #ad1457; }

/* Results info */
.results-info {
    font-size: .8rem;
    color: #888;
    margin-bottom: 16px;
}

/* ── PRODUCT GRID ── */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 18px;
}
.product-card {
    background: white;
    border: 1px solid #fce4ec;
    border-radius: 14px;
    overflow: hidden;
    transition: transform .3s, box-shadow .3s;
    position: relative;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 28px rgba(214,51,132,.15);
}
.card-img-link {
    display: block;
    height: 185px;
    overflow: hidden;
    background: #fce4ec;
    position: relative;
}
.card-img-link img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .4s;
}
.product-card:hover .card-img-link img { transform: scale(1.07); }

/* Wishlist heart */
.card-wishlist {
    position: absolute;
    top: 8px; right: 8px;
    background: white;
    border: none;
    border-radius: 50%;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
    font-size: .95rem;
    transition: transform .2s;
    z-index: 2;
}
.card-wishlist:hover { transform: scale(1.2); }

.badge {
    position: absolute;
    bottom: 8px; left: 8px;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: .7rem;
    font-weight: 700;
}
.badge-oos { background: #fee2e2; color: #dc2626; }
.badge-low { background: #fef3c7; color: #d97706; }

.card-body { padding: 12px 13px 14px; }
.card-category {
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #d63384;
    margin-bottom: 4px;
}
.card-title {
    font-size: .92rem;
    font-weight: 700;
    margin-bottom: 6px;
    line-height: 1.3;
}
.card-title a { color: #1e1218; text-decoration: none; }
.card-title a:hover { color: #d63384; }

.card-stars {
    display: flex;
    align-items: center;
    gap: 1px;
    margin-bottom: 8px;
}
.star-full  { color: #f59e0b; font-size: .88rem; }
.star-half  { color: #f59e0b; font-size: .88rem; }
.star-empty { color: #d1d5db; font-size: .88rem; }
.rev-count  { font-size: .72rem; color: #aaa; margin-left: 3px; }

.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 6px;
}
.price-tag {
    font-size: 1.15rem;
    font-weight: 800;
    color: #d63384;
    font-family: 'Playfair Display', serif;
    transition: transform .25s, color .25s;
}
.product-card:hover .price-tag { transform: scale(1.08); }

.add-btn {
    background: #d63384;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: background .2s, transform .2s;
    display: flex;
    align-items: center;
    gap: 4px;
}
.add-btn:hover { background: #ad1457; transform: translateY(-1px); }
.add-btn.added { background: #16a34a !important; }
.add-btn:disabled { background: #ddd; color: #aaa; cursor: not-allowed; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 5rem 2rem;
    color: #888;
}
.empty-icon { font-size: 3.5rem; margin-bottom: 1rem; }
.empty-state h2 { margin-bottom: .5rem; color: #444; }
.empty-state p  { margin-bottom: 1.5rem; font-size: .9rem; }

/* Pagination */
.pagination {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin-top: 2rem;
    flex-wrap: wrap;
}
.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 .6rem;
    border: 1.5px solid #e0e0e0;
    border-radius: 8px;
    font-size: .85rem;
    font-weight: 600;
    color: #444;
    text-decoration: none;
    transition: all .2s;
}
.page-link:hover, .page-link.active {
    background: #d63384;
    border-color: #d63384;
    color: white;
    text-decoration: none;
}

/* ── Toast notification ── */
.shop-toast {
    position: fixed;
    bottom: 28px; right: 28px;
    background: #1e1218;
    color: white;
    padding: 14px 20px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 8px 28px rgba(0,0,0,.25);
    z-index: 9999;
    transform: translateY(80px);
    opacity: 0;
    transition: all .35s cubic-bezier(.34,1.56,.64,1);
    max-width: 320px;
}
.shop-toast.show { transform: translateY(0); opacity: 1; }
.toast-icon { font-size: 1.4rem; flex-shrink: 0; }
.toast-title { font-weight: 700; font-size: .9rem; }
.toast-sub { font-size: .75rem; color: rgba(255,255,255,.5); margin-top: 2px; }
.toast-price { font-weight: 800; color: #ff79b0; font-size: .95rem; margin-left: auto; white-space: nowrap; }

/* Responsive */
@media (max-width: 768px) {
    .shop-body { grid-template-columns: 1fr; }
    .shop-sidebar-panel { position: static; height: auto; border-right: none; border-bottom: 1px solid #e0e0e0; }
    .shop-topbar { top: 60px; padding: 10px 16px; }
    .shop-topbar-left h2 { font-size: .9rem; }
    .product-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .shop-main-panel { padding: 14px 16px; }
}
</style>

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
        <form method="GET" action="/bloom-aura/pages/shop.php" style="display:flex;gap:6px;align-items:center;">
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
            if ($cartCount > 0) echo ' <span style="background:white;color:#d63384;border-radius:50%;width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:900;">' . $cartCount . '</span>';
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
                       style="width:100%;padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:.82rem;font-family:inherit;outline:none;margin-bottom:6px;"
                       onfocus="this.style.borderColor='#d63384'" onblur="this.style.borderColor='#e0e0e0'">
                <button type="submit" style="width:100%;padding:8px;background:#d63384;color:white;border:none;border-radius:8px;font-weight:700;font-size:.8rem;cursor:pointer;">Search</button>
            </form>
        </div>

    </aside>

    <!-- MAIN -->
    <main class="shop-main-panel">

        <?php if ($search): ?>
            <p class="results-info">
                Results for "<strong><?= htmlspecialchars($search,ENT_QUOTES,'UTF-8') ?></strong>"
                — <a href="<?= buildUrl(['q'=>'','page'=>1]) ?>" style="color:#d63384;">Clear ✕</a>
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:1rem;border-radius:10px;margin-bottom:1rem;">
                ❌ <?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?>
            </div>

        <?php elseif (empty($bouquets)): ?>
            <div class="empty-state">
                <div class="empty-icon">🌷</div>
                <h2>No bouquets found</h2>
                <p>Try adjusting your search or filters.</p>
                <a href="/bloom-aura/pages/shop.php"
                   style="background:#d63384;color:white;padding:10px 24px;border-radius:10px;font-weight:700;text-decoration:none;display:inline-block;">
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