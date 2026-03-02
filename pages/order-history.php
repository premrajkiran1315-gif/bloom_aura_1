<?php
/**
 * bloom-aura/pages/order-history.php
 * Shows the logged-in customer's past orders, paginated.
 * Each order is expandable to show its line items.
 * ADDED: Inline "Rate this product" review form for delivered orders.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/auth_check.php';

$userId = (int)$_SESSION['user_id'];

/* ── Handle review submission ────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    csrf_validate();

    $bouquetId = (int)($_POST['bouquet_id'] ?? 0);
    $rating    = (int)($_POST['rating']     ?? 0);
    $comment   = trim($_POST['comment']     ?? '');
    $errors    = [];

    if ($bouquetId <= 0)            $errors[] = 'Invalid product.';
    if ($rating < 1 || $rating > 5) $errors[] = 'Please select a star rating (1–5).';
    if (strlen($comment) < 10)      $errors[] = 'Review must be at least 10 characters.';
    if (strlen($comment) > 1000)    $errors[] = 'Review must be under 1000 characters.';

    if (empty($errors)) {
        try {
            $pdo = getPDO();

            /* Verify customer actually ordered this bouquet */
            $verifyStmt = $pdo->prepare(
                "SELECT 1
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 WHERE o.user_id = ? AND oi.bouquet_id = ? AND o.status = 'delivered'
                 LIMIT 1"
            );
            $verifyStmt->execute([$userId, $bouquetId]);

            if (!$verifyStmt->fetch()) {
                flash('You can only review products from delivered orders.', 'error');
            } else {
                /* Check not already reviewed */
                $existsStmt = $pdo->prepare(
                    'SELECT id FROM reviews WHERE user_id = ? AND bouquet_id = ?'
                );
                $existsStmt->execute([$userId, $bouquetId]);

                if ($existsStmt->fetch()) {
                    flash('You have already reviewed this product.', 'info');
                } else {
                    $pdo->prepare(
                        'INSERT INTO reviews (user_id, bouquet_id, rating, comment, created_at)
                         VALUES (?, ?, ?, ?, NOW())'
                    )->execute([$userId, $bouquetId, $rating, $comment]);
                    flash('Thank you for your review! 🌸', 'success');
                }
            }
        } catch (RuntimeException $e) {
            flash('Could not save your review. Please try again.', 'error');
        }
    } else {
        flash(implode(' ', $errors), 'error');
    }

    header('Location: /bloom-aura/pages/order-history.php?page=' . max(1, (int)($_POST['page'] ?? 1)));
    exit;
}

/* ── Pagination ──────────────────────────────────────────────────────────── */
define('ORDERS_PER_PAGE', 10);
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * ORDERS_PER_PAGE;

/* ── Fetch orders ────────────────────────────────────────────────────────── */
$orders       = [];
$total        = 0;
$totalPages   = 1;
$error        = '';
$itemsByOrder = [];

try {
    $pdo = getPDO();

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $countStmt->execute([$userId]);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / ORDERS_PER_PAGE));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * ORDERS_PER_PAGE;

    $orderStmt = $pdo->prepare(
        "SELECT id, total, status, delivery_name, delivery_city, payment_method, created_at
         FROM orders
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    $orderStmt->execute([$userId, ORDERS_PER_PAGE, $offset]);
    $orders = $orderStmt->fetchAll();

    /* Fetch items for each order */
    if (!empty($orders)) {
        $ids      = array_column($orders, 'id');
        $inClause = implode(',', array_fill(0, count($ids), '?'));

        $itemStmt = $pdo->prepare(
            "SELECT oi.order_id, oi.quantity, oi.unit_price,
                    b.id   AS bouquet_id,
                    b.name AS bouquet_name,
                    b.slug AS bouquet_slug,
                    b.image AS bouquet_image
             FROM order_items oi
             JOIN bouquets b ON b.id = oi.bouquet_id
             WHERE oi.order_id IN ($inClause)"
        );
        $itemStmt->execute($ids);
        $allItems = $itemStmt->fetchAll();

        foreach ($allItems as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }

        /* Which bouquets has this user already reviewed? */
        $bouquetIds   = array_unique(array_column($allItems, 'bouquet_id'));
        $reviewedSet  = [];
        if (!empty($bouquetIds)) {
            $revIn   = implode(',', array_fill(0, count($bouquetIds), '?'));
            $revStmt = $pdo->prepare(
                "SELECT bouquet_id, rating, comment
                 FROM reviews
                 WHERE user_id = ? AND bouquet_id IN ($revIn)"
            );
            $revStmt->execute(array_merge([$userId], $bouquetIds));
            foreach ($revStmt->fetchAll() as $rev) {
                $reviewedSet[(int)$rev['bouquet_id']] = $rev;
            }
        }
    }

} catch (RuntimeException $e) {
    $error = 'Unable to load your orders. Please try again later.';
}

$pageTitle = 'My Orders — Bloom Aura';
$pageCss   = 'order-history';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/bloom-aura/">Home</a></li>
        <li><a href="/bloom-aura/pages/profile.php">My Account</a></li>
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
            <a href="/bloom-aura/pages/shop.php" class="btn btn-primary">Browse Bouquets</a>
        </div>

    <?php else: ?>
        <p class="results-count"><?= $total ?> order<?= $total !== 1 ? 's' : '' ?> found</p>

        <div class="orders-list">
            <?php foreach ($orders as $order):
                $orderItems  = $itemsByOrder[$order['id']] ?? [];
                $statusClass = htmlspecialchars(strtolower($order['status']), ENT_QUOTES, 'UTF-8');
                $isDelivered = $order['status'] === 'delivered';
            ?>
                <div class="order-card" id="order-<?= (int)$order['id'] ?>">

                    <!-- ── Order header ── -->
                    <div class="order-card-header">
                        <div class="order-meta">
                            <span class="order-id">#<?= (int)$order['id'] ?></span>
                            <time class="order-date" datetime="<?= htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8') ?>">
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

                    <!-- ── Collapsible items ── -->
                    <div class="order-items-wrap" id="order-items-<?= (int)$order['id'] ?>" hidden>
                        <div class="order-items-inner">

                            <?php foreach ($orderItems as $item):
                                $bid        = (int)$item['bouquet_id'];
                                $reviewed   = $reviewedSet[$bid] ?? null;
                            ?>
                            <div class="order-item-row">

                                <!-- Product thumb -->
                                <img
                                    src="/bloom-aura/uploads/bouquets/<?= htmlspecialchars($item['bouquet_image'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($item['bouquet_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    loading="lazy" width="60" height="60"
                                    class="order-item-img"
                                    onerror="this.src='/bloom-aura/assets/img/placeholder.jpg'"
                                >

                                <!-- Product info -->
                                <div class="order-item-info">
                                    <a href="/bloom-aura/pages/product.php?slug=<?= urlencode($item['bouquet_slug']) ?>">
                                        <?= htmlspecialchars($item['bouquet_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <span class="order-item-qty">Qty: <?= (int)$item['quantity'] ?></span>
                                </div>

                                <!-- Price -->
                                <span class="order-item-price">
                                    ₹<?= number_format($item['unit_price'] * $item['quantity'], 2) ?>
                                </span>

                                <!-- ── Review area (delivered orders only) ── -->
                                <?php if ($isDelivered): ?>
                                    <div class="order-item-review-area">
                                        <?php if ($reviewed): ?>
                                            <!-- Already reviewed — show their rating -->
                                            <div class="review-done-badge">
                                                <span class="review-done-stars">
                                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                                        <span class="<?= $s <= (int)$reviewed['rating'] ? 'rstar-full' : 'rstar-empty' ?>">★</span>
                                                    <?php endfor; ?>
                                                </span>
                                                <span class="review-done-label">Your review</span>
                                            </div>
                                        <?php else: ?>
                                            <!-- Rate this product button -->
                                            <button class="rate-btn"
                                                    data-target="review-form-<?= (int)$order['id'] ?>-<?= $bid ?>"
                                                    aria-expanded="false">
                                                ⭐ Rate this
                                            </button>

                                            <!-- Inline review form (hidden until button clicked) -->
                                            <div class="inline-review-form"
                                                 id="review-form-<?= (int)$order['id'] ?>-<?= $bid ?>"
                                                 hidden>
                                                <form action="/bloom-aura/pages/order-history.php" method="POST" novalidate>
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action"     value="submit_review">
                                                    <input type="hidden" name="bouquet_id" value="<?= $bid ?>">
                                                    <input type="hidden" name="page"       value="<?= $page ?>">
                                                    <input type="hidden" name="rating" class="rating-input" value="0">

                                                    <!-- Star picker -->
                                                    <div class="inline-star-picker" aria-label="Your rating">
                                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                                            <button type="button"
                                                                    class="inline-star-btn"
                                                                    data-val="<?= $s ?>"
                                                                    aria-label="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">★</button>
                                                        <?php endfor; ?>
                                                    </div>

                                                    <!-- Comment -->
                                                    <textarea
                                                        name="comment"
                                                        class="inline-review-textarea"
                                                        placeholder="Share your experience… (min 10 chars)"
                                                        rows="3"
                                                        minlength="10"
                                                        maxlength="1000"
                                                        required
                                                    ></textarea>

                                                    <!-- Actions -->
                                                    <div class="inline-review-actions">
                                                        <button type="submit" class="inline-submit-btn">
                                                            🌸 Submit Review
                                                        </button>
                                                        <button type="button"
                                                                class="inline-cancel-btn"
                                                                data-target="review-form-<?= (int)$order['id'] ?>-<?= $bid ?>">
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                            </div><!-- /.order-item-row -->
                            <?php endforeach; ?>

                            <!-- Delivery note -->
                            <div class="order-delivery-note">
                                <i class="fa-solid fa-location-dot"></i>
                                <?= htmlspecialchars($order['delivery_name'], ENT_QUOTES, 'UTF-8') ?>,
                                <?= htmlspecialchars($order['delivery_city'], ENT_QUOTES, 'UTF-8') ?>
                                &nbsp;·&nbsp;
                                <i class="fa-solid fa-credit-card"></i>
                                <?= htmlspecialchars(ucfirst($order['payment_method']), ENT_QUOTES, 'UTF-8') ?>
                            </div>

                            <?php if ($isDelivered): ?>
                                <p class="review-hint-banner">
                                    ✅ Order delivered — you can rate each product above.
                                </p>
                            <?php endif; ?>

                        </div><!-- /.order-items-inner -->
                    </div><!-- /.order-items-wrap -->

                </div><!-- /.order-card -->
            <?php endforeach; ?>
        </div><!-- /.orders-list -->

        <!-- ── Pagination ── -->
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

<script src="/bloom-aura/assets/js/order-history.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>