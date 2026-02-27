<?php
/**
 * bloom-aura/pages/order-confirmation.php
 * Shown after a successful checkout. Reads last_order_id from session.
 * Clears the order ID from session after display (one-time view).
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flash.php';

// Guard: must be logged in and have a valid order just placed
if (empty($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}
if (empty($_SESSION['last_order_id'])) {
    header('Location: /pages/order-history.php');
    exit;
}

$orderId = (int)$_SESSION['last_order_id'];
$userId  = (int)$_SESSION['user_id'];

// Consume the session key so the page can't be revisited
unset($_SESSION['last_order_id']);

// ── Fetch order details ───────────────────────────────────────────────────────
try {
    $pdo = getPDO();

    $orderStmt = $pdo->prepare(
        "SELECT o.*, u.name AS customer_name, u.email AS customer_email
         FROM orders o
         JOIN users u ON u.id = o.user_id
         WHERE o.id = ? AND o.user_id = ?
         LIMIT 1"
    );
    $orderStmt->execute([$orderId, $userId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        header('Location: /pages/order-history.php');
        exit;
    }

    $itemsStmt = $pdo->prepare(
        "SELECT oi.quantity, oi.unit_price,
                b.name AS bouquet_name, b.image AS bouquet_image, b.slug AS bouquet_slug
         FROM order_items oi
         JOIN bouquets b ON b.id = oi.bouquet_id
         WHERE oi.order_id = ?"
    );
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll();

} catch (RuntimeException $e) {
    $order = null;
    $items = [];
}

$pageTitle = 'Order Confirmed — Bloom Aura';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container narrow-page confirmation-page">

    <div class="confirm-banner">
        <div class="confirm-icon" aria-hidden="true">🌸</div>
        <h1>Thank You! Your Order Is Confirmed</h1>
        <p class="confirm-sub">
            A confirmation has been sent to
            <strong><?= htmlspecialchars($order['customer_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>.
        </p>
        <p class="confirm-order-id">Order #<?= $orderId ?></p>
    </div>

    <?php if ($order): ?>
        <!-- Order summary table -->
        <div class="confirm-card">
            <h2 class="confirm-section-heading">Order Summary</h2>
            <table class="confirm-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <a href="/pages/product.php?slug=<?= urlencode($item['bouquet_slug']) ?>">
                                    <?= htmlspecialchars($item['bouquet_name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td>₹<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="total-label">Total Paid</td>
                        <td class="total-value">₹<?= number_format($order['total'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Delivery details -->
        <div class="confirm-card">
            <h2 class="confirm-section-heading">Delivery Details</h2>
            <ul class="confirm-detail-list">
                <li><strong>Name:</strong> <?= htmlspecialchars($order['delivery_name'], ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>Address:</strong> <?= htmlspecialchars($order['delivery_address'], ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>City:</strong> <?= htmlspecialchars($order['delivery_city'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($order['delivery_pincode'], ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>Phone:</strong> <?= htmlspecialchars($order['delivery_phone'], ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>Payment:</strong> <?= htmlspecialchars(ucfirst($order['payment_method']), ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>Status:</strong> <span class="status-badge status-<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>"><?= ucfirst(htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8')) ?></span></li>
                <li><strong>Placed:</strong> <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></li>
            </ul>
        </div>

        <!-- Actions -->
        <div class="confirm-actions">
            <a href="/pages/order-history.php" class="btn btn-outline">
                <i class="fa-solid fa-clock-rotate-left"></i> View All Orders
            </a>
            <a href="/pages/shop.php" class="btn btn-primary">
                <i class="fa-solid fa-basket-shopping"></i> Continue Shopping
            </a>
            <button onclick="window.print()" class="btn btn-ghost">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    <?php endif; ?>

</div><!-- /.page-container -->

<style>
/* Print styles for order confirmation */
@media print {
    header, footer, .confirm-actions, nav { display: none !important; }
    .confirm-card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
