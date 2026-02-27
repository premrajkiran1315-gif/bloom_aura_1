<?php
/**
 * bloom-aura/admin/orders.php
 * Admin: view all orders, filter by status, update order status.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';

// ── Admin guard ───────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php');
    exit;
}

$validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// ── Handle status update ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    csrf_validate();
    $orderId   = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if ($orderId > 0 && in_array($newStatus, $validStatuses, true)) {
        try {
            $pdo = getPDO();
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $orderId]);
            flash('Order #' . $orderId . ' status updated to ' . ucfirst($newStatus) . '.', 'success');
        } catch (RuntimeException $e) {
            flash('Could not update order status.', 'error');
        }
    }
    $qs = !empty($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '';
    header('Location: /admin/orders.php' . $qs);
    exit;
}

// ── Filters + pagination ──────────────────────────────────────────────────────
define('ORDERS_PER_PAGE', 20);
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * ORDERS_PER_PAGE;
$filterStatus = $_GET['status'] ?? '';
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
    $pdo = getPDO();

    $where  = [];
    $params = [];

    if ($filterStatus !== '') {
        $where[]  = 'o.status = ?';
        $params[] = $filterStatus;
    }
    if ($searchId > 0) {
        $where[]  = 'o.id = ?';
        $params[] = $searchId;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $whereSql");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / ORDERS_PER_PAGE));

    $orderStmt = $pdo->prepare(
        "SELECT o.id, o.total, o.status, o.delivery_name, o.delivery_city,
                o.payment_method, o.created_at,
                u.name AS customer_name, u.email AS customer_email
         FROM orders o
         JOIN users u ON u.id = o.user_id
         $whereSql
         ORDER BY o.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $orderStmt->execute(array_merge($params, [ORDERS_PER_PAGE, $offset]));
    $orders = $orderStmt->fetchAll();

} catch (RuntimeException $e) {
    $error = 'Could not load orders.';
}

$pageTitle = 'Manage Orders — Admin';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/admin/dashboard.php">Dashboard</a></li>
        <li aria-current="page">Orders</li>
    </ol>
</nav>

<div class="page-container admin-page">
    <h1 class="page-title">Orders</h1>

    <!-- Filters -->
    <div class="admin-filter-bar">
        <a href="/admin/orders.php"
           class="filter-tab <?= $filterStatus === '' ? 'active' : '' ?>">All</a>
        <?php foreach ($validStatuses as $s): ?>
            <a href="/admin/orders.php?status=<?= urlencode($s) ?>"
               class="filter-tab <?= $filterStatus === $s ? 'active' : '' ?>">
                <?= ucfirst($s) ?>
            </a>
        <?php endforeach; ?>

        <!-- Quick search by order ID -->
        <form action="/admin/orders.php" method="GET" class="admin-id-search">
            <?php if ($filterStatus): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <input type="number" name="order_id" placeholder="Order #…"
                   value="<?= $searchId ?: '' ?>" min="1" class="admin-search-input" style="width:130px;">
            <button type="submit" class="btn btn-outline btn-sm">Find</button>
            <?php if ($searchId): ?>
                <a href="/admin/orders.php<?= $filterStatus ? '?status=' . urlencode($filterStatus) : '' ?>"
                   class="btn btn-ghost btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h2>No orders found</h2>
            <p>Try a different filter or wait for customers to place orders.</p>
        </div>
    <?php else: ?>
        <p class="results-count"><?= $total ?> order<?= $total !== 1 ? 's' : '' ?></p>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Customer</th>
                        <th>Delivery</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?= (int)$order['id'] ?></strong></td>
                            <td>
                                <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                                <div class="table-sub"><?= htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars($order['delivery_name'], ENT_QUOTES, 'UTF-8') ?>
                                <div class="table-sub"><?= htmlspecialchars($order['delivery_city'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td><strong>₹<?= number_format($order['total'], 2) ?></strong></td>
                            <td><?= htmlspecialchars(ucfirst($order['payment_method']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= ucfirst(htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8')) ?>
                                </span>
                            </td>
                            <td>
                                <!-- Inline status update form -->
                                <form action="/admin/orders.php" method="POST" class="inline-status-form">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <select name="status" class="status-select"
                                            aria-label="Update status for order #<?= (int)$order['id'] ?>">
                                        <?php foreach ($validStatuses as $s): ?>
                                            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                                                <?= ucfirst($s) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-outline btn-xs">Update</button>
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
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?= $p ?>&status=<?= urlencode($filterStatus) ?>"
                       class="page-link <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div><!-- /.page-container -->

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
