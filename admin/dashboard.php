<?php
/**
 * bloom-aura/admin/dashboard.php
 * Admin dashboard — pixel-matched to bloom_aura reference UI.
 */

session_start();
require_once __DIR__ . '/../includes/admin_auth_check.php';
require_once __DIR__ . '/../config/db.php';

$adminName = htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8');

try {
    $pdo = getPDO();
    $revenue   = $pdo->query('SELECT COALESCE(SUM(total), 0) FROM orders')->fetchColumn();
    $orders    = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $customers = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer" AND is_active = 1')->fetchColumn();
    $avgRating = $pdo->query('SELECT COALESCE(ROUND(AVG(rating), 1), 0) FROM reviews')->fetchColumn();
    $pending   = $pdo->query('SELECT COUNT(*) FROM orders WHERE status = "pending"')->fetchColumn();

    $recentOrders = $pdo->query(
        'SELECT o.id, u.name AS customer, o.total, o.status, o.created_at
         FROM orders o JOIN users u ON o.user_id = u.id
         ORDER BY o.created_at DESC LIMIT 5'
    )->fetchAll();

    $topProducts = $pdo->query(
        'SELECT b.name, SUM(oi.quantity) AS units_sold, SUM(oi.quantity * oi.unit_price) AS revenue
         FROM order_items oi JOIN bouquets b ON oi.bouquet_id = b.id
         GROUP BY oi.bouquet_id ORDER BY units_sold DESC LIMIT 5'
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
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin_dashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js" defer></script>
</head>
<body class="admin-body">

<div class="admin-layout">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">

        <div class="admin-topbar">
            <h1 class="admin-page-title">Dashboard</h1>
            <div class="admin-topbar-right">
                <span class="admin-greeting">Hello, <?= $adminName ?> 👑</span>
                <a href="/bloom-aura/admin/logout.php" class="adm-logout-top-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>

        <div class="admin-content">

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <!-- KPI Grid -->
            <div class="adm-kpi-grid">
                <div class="adm-kpi kpi-pink">
                    <div class="adm-kpi-icon">💰</div>
                    <div class="adm-kpi-val">₹<?= number_format((float)$revenue, 2) ?></div>
                    <div class="adm-kpi-label">Total Revenue</div>
                </div>
                <div class="adm-kpi kpi-gold">
                    <div class="adm-kpi-icon">📦</div>
                    <div class="adm-kpi-val"><?= (int)$orders ?></div>
                    <div class="adm-kpi-label">Total Orders</div>
                    <?php if ($pending > 0): ?>
                        <div class="adm-kpi-sub"><?= (int)$pending ?> pending</div>
                    <?php endif; ?>
                </div>
                <div class="adm-kpi kpi-green">
                    <div class="adm-kpi-icon">👥</div>
                    <div class="adm-kpi-val"><?= (int)$customers ?></div>
                    <div class="adm-kpi-label">Active Customers</div>
                </div>
                <div class="adm-kpi kpi-blue">
                    <div class="adm-kpi-icon">⭐</div>
                    <div class="adm-kpi-val"><?= $avgRating > 0 ? $avgRating : '—' ?></div>
                    <div class="adm-kpi-label">Avg Rating</div>
                </div>
            </div>

            <!-- Dashboard 2-col row -->
            <div class="adm-dash-row">

                <!-- Recent Orders -->
                <div class="adm-card">
                    <div class="adm-card-head">
                        <h4>Recent Orders</h4>
                        <a href="/bloom-aura/admin/orders.php" class="adm-view-all-link">View All →</a>
                    </div>
                    <div class="adm-card-body">
                        <?php if (empty($recentOrders)): ?>
                            <div class="adm-empty">
                                <div class="ei">📦</div>
                                <h4>No orders yet</h4>
                                <p>Orders will appear here once customers start purchasing.</p>
                            </div>
                        <?php else: ?>
                            <table class="adm-table">
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
                                                <span class="adm-status <?= htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8') ?>">
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

                <!-- Top Products -->
                <div class="adm-card">
                    <div class="adm-card-head">
                        <h4>Top Products</h4>
                        <span class="label-muted">by units sold</span>
                    </div>
                    <div class="adm-card-body">
                        <?php if (empty($topProducts)): ?>
                            <div class="adm-empty">
                                <div class="ei">🌸</div>
                                <h4>No sales yet</h4>
                                <p>Top products will appear once orders are placed.</p>
                            </div>
                        <?php else: ?>
                            <table class="adm-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th>Units</th>
                                        <th>Revenue</th>
                                    </tr>
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

            </div><!-- /.adm-dash-row -->

        </div><!-- /.admin-content -->
    </main>
</div><!-- /.admin-layout -->

</body>
</html>