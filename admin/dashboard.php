<?php
/**
 * bloom-aura/admin/dashboard.php
 * Admin dashboard — requires admin session.
 * Shows real data from MySQL: revenue, orders, users, top products.
 */

session_start();
require_once __DIR__ . '/../includes/admin_auth_check.php';
require_once __DIR__ . '/../config/db.php';

$adminName = htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8');

try {
    $pdo = getPDO();

    // KPI stats — single queries with aggregates
    $revenue  = $pdo->query('SELECT COALESCE(SUM(total), 0) FROM orders')->fetchColumn();
    $orders   = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $customers= $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer" AND is_active = 1')->fetchColumn();
    $avgRating= $pdo->query('SELECT COALESCE(ROUND(AVG(rating), 1), 0) FROM reviews')->fetchColumn();

    // Pending orders count
    $pending  = $pdo->query('SELECT COUNT(*) FROM orders WHERE status = "pending"')->fetchColumn();

    // Recent 5 orders
    $recentOrders = $pdo->query(
        'SELECT o.id, u.name AS customer, o.total, o.status, o.created_at
         FROM orders o
         JOIN users u ON o.user_id = u.id
         ORDER BY o.created_at DESC
         LIMIT 5'
    )->fetchAll();

    // Top 5 products by sales volume
    $topProducts = $pdo->query(
        'SELECT b.name, SUM(oi.quantity) AS units_sold, SUM(oi.quantity * oi.unit_price) AS revenue
         FROM order_items oi
         JOIN bouquets b ON oi.bouquet_id = b.id
         GROUP BY oi.bouquet_id
         ORDER BY units_sold DESC
         LIMIT 5'
    )->fetchAll();

} catch (RuntimeException $e) {
    $error = 'Unable to load dashboard data.';
    $revenue = $orders = $customers = $avgRating = $pending = 0;
    $recentOrders = $topProducts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Bloom Aura Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js" defer></script>
</head>
<body class="admin-body">

<!-- Admin sidebar -->
<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <h1 class="admin-page-title">Dashboard</h1>
            <div class="admin-topbar-right">
                <span class="admin-greeting">Hello, <?= $adminName ?> 👑</span>
                <a href="/admin/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-pink">
                <div class="kpi-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value">₹<?= number_format((float)$revenue, 2) ?></div>
                    <div class="kpi-label">Total Revenue</div>
                </div>
            </div>
            <div class="kpi-card kpi-gold">
                <div class="kpi-icon"><i class="fa-solid fa-box"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= (int)$orders ?></div>
                    <div class="kpi-label">Total Orders</div>
                    <?php if ($pending > 0): ?>
                        <div class="kpi-sub"><?= $pending ?> pending</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="kpi-card kpi-green">
                <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= (int)$customers ?></div>
                    <div class="kpi-label">Active Customers</div>
                </div>
            </div>
            <div class="kpi-card kpi-blue">
                <div class="kpi-icon"><i class="fa-solid fa-star"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?= $avgRating > 0 ? $avgRating : '—' ?></div>
                    <div class="kpi-label">Avg Rating</div>
                </div>
            </div>
        </div>

        <!-- Tables row -->
        <div class="admin-grid-2col">
            <!-- Recent orders -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2>Recent Orders</h2>
                    <a href="/admin/orders.php" class="btn btn-outline btn-xs">View All</a>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($recentOrders)): ?>
                        <div class="empty-state-sm">No orders yet.</div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $o): ?>
                                    <tr>
                                        <td>#<?= (int)$o['id'] ?></td>
                                        <td><?= htmlspecialchars($o['customer'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>₹<?= number_format($o['total'], 2) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= ucfirst(htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8')) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top products -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2>Top Products</h2>
                    <span class="label-muted">by units sold</span>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($topProducts)): ?>
                        <div class="empty-state-sm">No sales data yet.</div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr><th>#</th><th>Product</th><th>Units</th><th>Revenue</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $i => $p): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int)$p['units_sold'] ?></td>
                                        <td>₹<?= number_format($p['revenue'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
