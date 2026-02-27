<?php
/**
 * bloom-aura/pages/order-history.php
 * Shows the logged-in customer's past orders, paginated.
 * Each order is expandable to show its line items.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/auth_check.php';  // Redirects if not logged in

$userId = (int)$_SESSION['user_id'];

// ── Pagination ────────────────────────────────────────────────────────────────
define('ORDERS_PER_PAGE', 10);
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * ORDERS_PER_PAGE;

// ── Fetch orders ──────────────────────────────────────────────────────────────
$orders = [];
$total  = 0;
$error  = '';

try {
    $pdo = getPDO();

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $countStmt->execute([$userId]);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = (int)ceil($total / ORDERS_PER_PAGE);

    $orderStmt = $pdo->prepare(
        "SELECT id, total, status, delivery_name, delivery_city, payment_method, created_at
         FROM orders
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    $orderStmt->execute([$userId, ORDERS_PER_PAGE, $offset]);
    $orders = $orderStmt->fetchAll();

    // Fetch items for each order
    if (!empty($orders)) {
        $ids      = array_column($orders, 'id');
        $inClause = implode(',', array_fill(0, count($ids), '?'));

        $itemStmt = $pdo->prepare(
            "SELECT oi.order_id, oi.quantity, oi.unit_price,
                    b.name AS bouquet_name, b.slug AS bouquet_slug, b.image AS bouquet_image
             FROM order_items oi
             JOIN bouquets b ON b.id = oi.bouquet_id
             WHERE oi.order_id IN ($inClause)"
        );
        $itemStmt->execute($ids);
        $allItems = $itemStmt->fetchAll();

        // Group items by order_id
        $itemsByOrder = [];
        foreach ($allItems as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }
    }

} catch (RuntimeException $e) {
    $error = 'Unable to load your orders. Please try again later.';
    $totalPages = 1;
}

$pageTitle = 'My Orders — Bloom Aura';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/">Home</a></li>
        <li><a href="/pages/profile.php">My Account</a></li>
        <li aria-current="page">Order History</li>
    </ol>
</nav>

<div class="page-container">
    <h1 class="page-title">My Orders</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

    <?php elseif (empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h2>No orders yet</h2>
            <p>You haven't placed any orders. Start shopping to see them here!</p>
            <a href="/pages/shop.php" class="btn btn-primary">Browse Bouquets</a>
        </div>

    <?php else: ?>
        <p class="results-count"><?= $total ?> order<?= $total !== 1 ? 's' : '' ?> found</p>

        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
                <?php
                $orderItems = $itemsByOrder[$order['id']] ?? [];
                $statusClass = htmlspecialchars(strtolower($order['status']), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="order-card" id="order-<?= (int)$order['id'] ?>">
                    <!-- Order header -->
                    <div class="order-card-header">
                        <div class="order-meta">
                            <span class="order-id">#<?= (int)$order['id'] ?></span>
                            <time class="order-date" datetime="<?= $order['created_at'] ?>">
                                <?= date('d M Y', strtotime($order['created_at'])) ?>
                            </time>
                        </div>
                        <div class="order-right">
                            <span class="status-badge status-<?= $statusClass ?>">
                                <?= ucfirst(htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8')) ?>
                            </span>
                            <strong class="order-total">₹<?= number_format($order['total'], 2) ?></strong>
                            <button class="order-toggle-btn" aria-expanded="false"
                                    aria-controls="order-items-<?= (int)$order['id'] ?>">
                                View Details <i class="fa-solid fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Collapsible items -->
                    <div class="order-items-wrap" id="order-items-<?= (int)$order['id'] ?>" hidden>
                        <div class="order-items-inner">
                            <?php foreach ($orderItems as $item): ?>
                                <div class="order-item-row">
                                    <img
                                        src="/uploads/bouquets/<?= htmlspecialchars($item['bouquet_image'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($item['bouquet_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        loading="lazy" width="60" height="60"
                                        class="order-item-img"
                                    >
                                    <div class="order-item-info">
                                        <a href="/pages/product.php?slug=<?= urlencode($item['bouquet_slug']) ?>">
                                            <?= htmlspecialchars($item['bouquet_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="order-item-qty">Qty: <?= (int)$item['quantity'] ?></span>
                                    </div>
                                    <span class="order-item-price">
                                        ₹<?= number_format($item['unit_price'] * $item['quantity'], 2) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>

                            <div class="order-delivery-note">
                                <i class="fa-solid fa-location-dot"></i>
                                <?= htmlspecialchars($order['delivery_name'], ENT_QUOTES, 'UTF-8') ?>,
                                <?= htmlspecialchars($order['delivery_city'], ENT_QUOTES, 'UTF-8') ?>
                                &nbsp;·&nbsp;
                                <i class="fa-solid fa-credit-card"></i>
                                <?= htmlspecialchars(ucfirst($order['payment_method']), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Orders pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="page-link">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="?page=<?= $p ?>"
                       class="page-link <?= $p === $page ? 'active' : '' ?>"
                       <?= $p === $page ? 'aria-current="page"' : '' ?>>
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="page-link">Next &raquo;</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div><!-- /.page-container -->

<script>
// Toggle order details accordion
document.querySelectorAll('.order-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('aria-controls');
        const target   = document.getElementById(targetId);
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));
        target.hidden = expanded;
        btn.querySelector('i').style.transform = expanded ? '' : 'rotate(180deg)';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
