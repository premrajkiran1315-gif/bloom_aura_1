<?php
/**
 * bloom-aura/admin/orders.php
 * Admin: view all orders, filter by status, update order status.
 * UI pixel-matched to bloom_aura reference HTML.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

$validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// ── Handle status update POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    csrf_validate();
    $orderId   = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if ($orderId > 0 && in_array($newStatus, $validStatuses, true)) {
        try {
            $pdo = getPDO();
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $orderId]);
            flash('Order #' . $orderId . ' updated to ' . ucfirst($newStatus) . '.', 'success');
        } catch (RuntimeException $e) {
            flash('Could not update order status.', 'error');
        }
    }
    $qs = !empty($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '';
    header('Location: /bloom-aura/admin/orders.php' . $qs);
    exit;
}

// ── Filters & pagination ──────────────────────────────────────────────────────
define('ORDERS_PER_PAGE', 20);
$page         = max(1, (int)($_GET['page']     ?? 1));
$filterStatus = $_GET['status']   ?? '';
$searchId     = (int)($_GET['order_id'] ?? 0);

if ($filterStatus !== '' && !in_array($filterStatus, $validStatuses, true)) {
    $filterStatus = '';
}

// ── Fetch orders ──────────────────────────────────────────────────────────────
$orders     = [];
$totalPages = 1;
$total      = 0;
$error      = '';

try {
    $pdo    = getPDO();
    $where  = [];
    $params = [];

    if ($filterStatus !== '') { $where[] = 'o.status = ?'; $params[] = $filterStatus; }
    if ($searchId > 0)        { $where[] = 'o.id = ?';     $params[] = $searchId; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $whereSql");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / ORDERS_PER_PAGE));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * ORDERS_PER_PAGE;

    $orderStmt = $pdo->prepare(
        "SELECT o.id, o.total, o.status, o.delivery_name, o.delivery_city,
                o.payment_method, o.created_at,
                u.name AS customer_name, u.email AS customer_email
         FROM orders o JOIN users u ON u.id = o.user_id
         $whereSql
         ORDER BY o.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $orderStmt->execute(array_merge($params, [ORDERS_PER_PAGE, $offset]));
    $orders = $orderStmt->fetchAll();

} catch (RuntimeException $e) {
    $error = 'Could not load orders.';
}

// ── Flash messages ────────────────────────────────────────────────────────────
$flashMessages = [];
if (!empty($_SESSION['flash'])) {
    $flashMessages = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

$statusIcons = [
    'pending'    => '⏳',
    'processing' => '⟳',
    'shipped'    => '🚚',
    'delivered'  => '✅',
    'cancelled'  => '✕',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — Bloom Aura Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin_orders.css">
</head>
<body class="admin-body">

<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
            <h1 class="admin-page-title">Orders</h1>
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
                    <h2 class="adm-section-title">Manage Orders</h2>
                    <p class="adm-section-sub">View, filter and update all customer orders.</p>
                </div>
                <?php if ($total > 0): ?>
                    <span class="adm-total-badge"><?= $total ?> order<?= $total !== 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>

            <!-- Toolbar: filters + search -->
            <div class="adm-order-toolbar">
                <div class="adm-filter-tabs">
                    <a href="/bloom-aura/admin/orders.php"
                       class="adm-filter-btn <?= $filterStatus === '' ? 'active' : '' ?>">All</a>
                    <?php foreach ($validStatuses as $s): ?>
                        <a href="/bloom-aura/admin/orders.php?status=<?= urlencode($s) ?>"
                           class="adm-filter-btn <?= $filterStatus === $s ? 'active' : '' ?>">
                            <?= $statusIcons[$s] ?? '' ?> <?= ucfirst($s) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <form action="/bloom-aura/admin/orders.php" method="GET" class="adm-id-search-form">
                    <?php if ($filterStatus): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <div class="adm-search-wrap">
                        <i class="fa-solid fa-magnifying-glass adm-search-icon"></i>
                        <input type="number" name="order_id" placeholder="Search order #…"
                               value="<?= $searchId ?: '' ?>" min="1" class="adm-search-input">
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm">Find</button>
                    <?php if ($searchId): ?>
                        <a href="/bloom-aura/admin/orders.php<?= $filterStatus ? '?status='.urlencode($filterStatus) : '' ?>"
                           class="btn btn-ghost btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Error -->
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

            <!-- Empty -->
            <?php elseif (empty($orders)): ?>
                <div class="adm-orders-wrap">
                    <div class="adm-empty">
                        <div class="ei">📦</div>
                        <h4>No orders found</h4>
                        <p><?= $searchId || $filterStatus ? 'Try a different filter or search.' : "When customers place orders, they'll appear here." ?></p>
                    </div>
                </div>

            <!-- Table -->
            <?php else: ?>
                <div class="adm-orders-wrap">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Delivery To</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <span class="adm-order-id">#<?= (int)$order['id'] ?></span>
                                    </td>
                                    <td>
                                        <div class="adm-customer-cell">
                                            <div class="adm-cust-av-sm">
                                                <?= strtoupper(mb_substr($order['customer_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="adm-cust-name-text">
                                                    <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <div class="adm-cust-email">
                                                    <?= htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="adm-delivery-name">
                                            <?= htmlspecialchars($order['delivery_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="adm-delivery-city">
                                            <i class="fa-solid fa-location-dot fa-xs"></i>
                                            <?= htmlspecialchars($order['delivery_city'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="adm-amount">₹<?= number_format($order['total'], 2) ?></span>
                                    </td>
                                    <td>
                                        <span class="adm-payment-badge adm-pay-<?= htmlspecialchars($order['payment_method'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?php
                                            $payIcons = ['cod' => '💵', 'upi' => '📱', 'card' => '💳'];
                                            echo $payIcons[$order['payment_method']] ?? '💰';
                                            ?>
                                            <?= strtoupper(htmlspecialchars($order['payment_method'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="adm-date-main"><?= date('d M Y', strtotime($order['created_at'])) ?></div>
                                        <div class="adm-date-time"><?= date('h:i A', strtotime($order['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="adm-status <?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $statusIcons[$order['status']] ?? '' ?>
                                            <?= ucfirst(htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form action="/bloom-aura/admin/orders.php" method="POST" class="adm-status-form">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action"   value="update_status">
                                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                            <select name="status" class="adm-status-select"
                                                    aria-label="Update status for order #<?= (int)$order['id'] ?>">
                                                <?php foreach ($validStatuses as $s): ?>
                                                    <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                                                        <?= ucfirst($s) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="adm-update-btn" title="Update status">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Orders pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($filterStatus) ?>" class="page-link">&laquo;</a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <a href="?page=<?= $p ?>&status=<?= urlencode($filterStatus) ?>"
                               class="page-link <?= $p === $page ? 'active' : '' ?>"
                               <?= $p === $page ? 'aria-current="page"' : '' ?>>
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($filterStatus) ?>" class="page-link">&raquo;</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>

        </div><!-- /.admin-content -->
    </main>
</div><!-- /.admin-layout -->

</body>
</html>