<?php
/**
 * bloom-aura/admin/products.php
 * Admin: list / search / delete bouquets (products).
 * UI pixel-matched to bloom_aura reference HTML — inventory card grid view.
 * Sidebar links here as "Products".
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

// ── Handle DELETE ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_validate();
    $id = (int)($_POST['bouquet_id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo = getPDO();
            $imgStmt = $pdo->prepare('SELECT image FROM bouquets WHERE id = ?');
            $imgStmt->execute([$id]);
            $imgRow = $imgStmt->fetch();

            $pdo->prepare('DELETE FROM bouquets WHERE id = ?')->execute([$id]);

            if ($imgRow && $imgRow['image']) {
                $imgPath = __DIR__ . '/../uploads/bouquets/' . $imgRow['image'];
                if (file_exists($imgPath)) @unlink($imgPath);
            }
            flash('Product deleted successfully.', 'success');
        } catch (RuntimeException $e) {
            flash('Could not delete product. It may be linked to existing orders.', 'error');
        }
    }
    header('Location: /bloom-aura/admin/products.php');
    exit;
}

// ── Filters & pagination ──────────────────────────────────────────────────────
define('PRODUCTS_PER_PAGE', 15);
$page        = max(1, (int)($_GET['page']   ?? 1));
$search      = trim($_GET['q']             ?? '');
$filterStock = $_GET['stock']              ?? '';   // 'ok' | 'low' | 'out' | ''

$validStockFilters = ['ok', 'low', 'out'];
if (!in_array($filterStock, $validStockFilters, true)) $filterStock = '';

// ── Fetch products ────────────────────────────────────────────────────────────
$bouquets   = [];
$totalPages = 1;
$total      = 0;
$error      = '';

try {
    $pdo    = getPDO();
    $where  = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(b.name LIKE ? OR c.name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Stock filter applied post-query via HAVING equivalent
    $stockHaving = '';
    $havingParams = [];
    if ($filterStock === 'out') {
        $stockHaving  = 'AND b.stock <= 0';
    } elseif ($filterStock === 'low') {
        $stockHaving  = 'AND b.stock > 0 AND b.stock <= 5';
    } elseif ($filterStock === 'ok') {
        $stockHaving  = 'AND b.stock > 5';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM bouquets b
         LEFT JOIN categories c ON c.id = b.category_id
         $whereSql $stockHaving"
    );
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / PRODUCTS_PER_PAGE));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * PRODUCTS_PER_PAGE;

    $listStmt = $pdo->prepare(
        "SELECT b.id, b.name, b.slug, b.price, b.stock, b.image,
                b.is_active, b.created_at,
                c.name AS category_name
         FROM bouquets b
         LEFT JOIN categories c ON c.id = b.category_id
         $whereSql $stockHaving
         ORDER BY b.id DESC
         LIMIT ? OFFSET ?"
    );
    $listStmt->execute(array_merge($params, [PRODUCTS_PER_PAGE, $offset]));
    $bouquets = $listStmt->fetchAll();

    // Stock summary counts for filter badges
    $stockCounts = $pdo->query(
        "SELECT
            SUM(CASE WHEN stock > 5  THEN 1 ELSE 0 END) AS cnt_ok,
            SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END) AS cnt_low,
            SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS cnt_out
         FROM bouquets"
    )->fetch();

} catch (RuntimeException $e) {
    $error = 'Could not load products.';
    $stockCounts = ['cnt_ok' => 0, 'cnt_low' => 0, 'cnt_out' => 0];
}

// ── Flash messages ────────────────────────────────────────────────────────────
$flashMessages = [];
if (!empty($_SESSION['flash'])) {
    $flashMessages = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

// Helper: derive stock status class
function stockStatus(int $stock): string {
    if ($stock <= 0)  return 'out';
    if ($stock <= 5)  return 'low';
    return 'ok';
}
function stockLabel(int $stock): string {
    if ($stock <= 0)  return 'Out of Stock';
    if ($stock <= 5)  return 'Low Stock';
    return 'In Stock';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products — Bloom Aura Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin_products.css">
</head>
<body class="admin-body">

<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
            <h1 class="admin-page-title">Products</h1>
            <div class="admin-topbar-right">
                <span class="admin-greeting">Hello, <?= $adminName ?> 👑</span>
                <a href="/bloom-aura/admin/logout.php" class="adm-logout-top-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>

        <!-- Content -->
        <div class="admin-content">

            <!-- Flash messages -->
            <?php foreach ($flashMessages as $flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>" role="alert">
                    <?= htmlspecialchars($flash['msg'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endforeach; ?>

            <!-- Page header -->
            <div class="adm-page-header">
                <div>
                    <h2 class="adm-section-title">Manage Products</h2>
                    <p class="adm-section-sub">View, filter and manage all bouquets in the shop.</p>
                </div>
                <a href="/bloom-aura/admin/add-bouquet.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Add New Product
                </a>
            </div>

            <!-- Toolbar: stock filters + search -->
            <div class="adm-products-toolbar">

                <!-- Stock filter tabs -->
                <div class="adm-filter-tabs">
                    <a href="/bloom-aura/admin/products.php<?= $search ? '?q='.urlencode($search) : '' ?>"
                       class="adm-filter-btn <?= $filterStock === '' ? 'active' : '' ?>">
                        All Items
                    </a>
                    <a href="/bloom-aura/admin/products.php?stock=ok<?= $search ? '&q='.urlencode($search) : '' ?>"
                       class="adm-filter-btn <?= $filterStock === 'ok' ? 'active' : '' ?>">
                        ✅ In Stock
                        <?php if ($stockCounts['cnt_ok'] > 0): ?>
                            <span class="adm-filter-count"><?= (int)$stockCounts['cnt_ok'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/bloom-aura/admin/products.php?stock=low<?= $search ? '&q='.urlencode($search) : '' ?>"
                       class="adm-filter-btn <?= $filterStock === 'low' ? 'active' : '' ?>">
                        ⚠️ Low Stock
                        <?php if ($stockCounts['cnt_low'] > 0): ?>
                            <span class="adm-filter-count adm-filter-count-low"><?= (int)$stockCounts['cnt_low'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/bloom-aura/admin/products.php?stock=out<?= $search ? '&q='.urlencode($search) : '' ?>"
                       class="adm-filter-btn <?= $filterStock === 'out' ? 'active' : '' ?>">
                        ❌ Out of Stock
                        <?php if ($stockCounts['cnt_out'] > 0): ?>
                            <span class="adm-filter-count adm-filter-count-out"><?= (int)$stockCounts['cnt_out'] ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Search form -->
                <form action="/bloom-aura/admin/products.php" method="GET" class="adm-prod-search-form">
                    <?php if ($filterStock): ?>
                        <input type="hidden" name="stock" value="<?= htmlspecialchars($filterStock, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <div class="adm-search-wrap">
                        <i class="fa-solid fa-magnifying-glass adm-search-icon"></i>
                        <input type="search" name="q" placeholder="Search products…"
                               value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                               class="adm-prod-search-input">
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm">Search</button>
                    <?php if ($search): ?>
                        <a href="/bloom-aura/admin/products.php<?= $filterStock ? '?stock='.urlencode($filterStock) : '' ?>"
                           class="btn btn-ghost btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Error -->
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

            <!-- Empty state -->
            <?php elseif (empty($bouquets)): ?>
                <div class="adm-products-empty">
                    <div class="ei">🌷</div>
                    <h4><?= $search || $filterStock ? 'No products match your filter.' : 'No products yet.' ?></h4>
                    <p><?= $search || $filterStock ? 'Try a different search or filter.' : 'Add your first bouquet to get started.' ?></p>
                    <?php if (!$search && !$filterStock): ?>
                        <a href="/bloom-aura/admin/add-bouquet.php" class="btn btn-primary" style="margin-top:16px;">
                            <i class="fa-solid fa-plus"></i> Add Product
                        </a>
                    <?php endif; ?>
                </div>

            <!-- Products -->
            <?php else: ?>

                <?php if ($total > 0): ?>
                    <p class="adm-results-count"><?= $total ?> product<?= $total !== 1 ? 's' : '' ?></p>
                <?php endif; ?>

                <!-- Inventory Card Grid — matches reference adm-inv-grid -->
                <div class="adm-inv-grid">
                    <?php foreach ($bouquets as $b):
                        $st     = stockStatus((int)$b['stock']);
                        $stLbl  = stockLabel((int)$b['stock']);
                        $imgSrc = $b['image']
                            ? '/bloom-aura/uploads/bouquets/' . htmlspecialchars($b['image'], ENT_QUOTES, 'UTF-8')
                            : '/bloom-aura/assets/img/placeholder.jpg';
                        $maxStock = 50; // display bar relative to 50
                        $barPct   = $maxStock > 0 ? min(100, round(($b['stock'] / $maxStock) * 100)) : 0;
                    ?>
                        <div class="adm-inv-item">

                            <!-- Product image -->
                            <div class="adm-inv-img-wrap">
                                <img src="<?= $imgSrc ?>"
                                     alt="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>"
                                     class="adm-inv-img" loading="lazy">
                                <?php if (!(bool)$b['is_active']): ?>
                                    <span class="adm-inv-inactive-tag">Inactive</span>
                                <?php endif; ?>
                            </div>

                            <!-- Name + category -->
                            <div class="adm-inv-name">
                                <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="adm-inv-cat">
                                <?= htmlspecialchars($b['category_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                &nbsp;·&nbsp; ₹<?= number_format($b['price'], 2) ?>
                            </div>

                            <!-- Stock row: qty + badge -->
                            <div class="adm-inv-stock-row">
                                <span class="adm-inv-qty"><?= (int)$b['stock'] ?> units</span>
                                <span class="adm-inv-badge <?= $st ?>"><?= $stLbl ?></span>
                            </div>

                            <!-- Stock progress bar -->
                            <div class="adm-inv-progress">
                                <div class="adm-inv-bar <?= $st ?>" style="width:<?= $barPct ?>%"></div>
                            </div>

                            <!-- Action row: Edit + Delete -->
                            <div class="adm-inv-actions">
                                <a href="/bloom-aura/admin/edit-bouquet.php?id=<?= (int)$b['id'] ?>"
                                   class="adm-inv-edit-btn">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form action="/bloom-aura/admin/products.php" method="POST"
                                      onsubmit="return confirm('Delete «<?= htmlspecialchars(addslashes($b['name']), ENT_QUOTES, 'UTF-8') ?>»? This cannot be undone.')">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action"     value="delete">
                                    <input type="hidden" name="bouquet_id" value="<?= (int)$b['id'] ?>">
                                    <button type="submit" class="adm-inv-del-btn">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Products pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&stock=<?= urlencode($filterStock) ?>"
                               class="page-link">&laquo;</a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&stock=<?= urlencode($filterStock) ?>"
                               class="page-link <?= $p === $page ? 'active' : '' ?>"
                               <?= $p === $page ? 'aria-current="page"' : '' ?>>
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&stock=<?= urlencode($filterStock) ?>"
                               class="page-link">&raquo;</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>

        </div><!-- /.admin-content -->
    </main>
</div><!-- /.admin-layout -->

</body>
</html>