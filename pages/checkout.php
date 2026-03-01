<?php
/**
 * bloom-aura/pages/checkout.php
 * Requires login. Processes an order using a DB transaction:
 *  1. Insert into orders
 *  2. Insert into order_items
 *  3. Decrement stock
 *  4. Clear session cart
 * If any step fails, the whole transaction rolls back.
 */

session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// Redirect to cart if it's empty
if (empty($_SESSION['cart'])) {
    flash('Your cart is empty.', 'error');
    header('Location: /pages/cart.php');
    exit;
}

$cart     = $_SESSION['cart'];
$subtotal = array_reduce($cart, fn($c, $i) => $c + ($i['price'] * $i['qty']), 0);
$delivery = $subtotal > 999 ? 0 : 80;
$discount = !empty($_SESSION['promo_applied']) ? (int) round($subtotal * 0.10) : 0;
$total    = $subtotal + $delivery - $discount;

$errors = [];
$old    = [];

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    // Collect form data
    $name    = trim($_POST['name']    ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city']    ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $payment = $_POST['payment']      ?? '';

    $old = compact('name', 'address', 'city', 'pincode', 'phone', 'payment');

    // Validate
    if ($name === '')                              $errors['name']    = 'Full name is required.';
    if ($address === '')                           $errors['address'] = 'Street address is required.';
    if ($city === '')                              $errors['city']    = 'City is required.';
    if (!preg_match('/^\d{6}$/', $pincode))       $errors['pincode'] = 'Enter a valid 6-digit PIN code.';
    if (!preg_match('/^\d{10}$/', $phone))        $errors['phone']   = 'Enter a valid 10-digit phone number.';
    if (!in_array($payment, ['cod','upi','card'])) $errors['payment'] = 'Please select a payment method.';

    if (empty($errors)) {
        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            // 1. Insert order
            $stmt = $pdo->prepare(
                "INSERT INTO orders
                    (user_id, total, status, delivery_name, delivery_address, delivery_city,
                     delivery_pincode, delivery_phone, payment_method, created_at)
                 VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $_SESSION['user_id'], $total,
                $name, $address, $city, $pincode, $phone, $payment
            ]);
            $orderId = (int) $pdo->lastInsertId();

            // 2. Insert order_items + decrement stock
            $itemStmt  = $pdo->prepare(
                'INSERT INTO order_items (order_id, bouquet_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE bouquets SET stock = stock - ? WHERE id = ? AND stock >= ?'
            );

            foreach ($cart as $productId => $item) {
                $itemStmt->execute([$orderId, $productId, $item['qty'], $item['price']]);

                // Decrement stock — check affected rows to catch race condition
                $stockStmt->execute([$item['qty'], $productId, $item['qty']]);
                if ($stockStmt->rowCount() === 0) {
                    // Not enough stock — rollback
                    $pdo->rollBack();
                    flash(
                        htmlspecialchars($item['name']) . ' is no longer available in the requested quantity.',
                        'error'
                    );
                    header('Location: /pages/cart.php');
                    exit;
                }
            }

            $pdo->commit();

            // Clear session cart and promo
            unset($_SESSION['cart'], $_SESSION['promo_applied']);

            // Store order ID for confirmation page
            $_SESSION['last_order_id'] = $orderId;

            flash('Order placed successfully! 🌸', 'success');
            header('Location: /pages/order-confirmation.php');
            exit;

        } catch (RuntimeException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['db'] = 'Unable to process your order. Please try again.';
        }
    }
}

$pageTitle = 'Checkout — Bloom Aura';
$pageCss = 'checkout';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/">Home</a></li>
        <li><a href="/pages/cart.php">Cart</a></li>
        <li aria-current="page">Checkout</li>
    </ol>
</nav>

<div class="page-container">
    <h1 class="page-title">Checkout</h1>

    <?php if (!empty($errors['db'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errors['db'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="checkout-layout">
        <!-- Delivery form -->
        <div class="checkout-form-wrap">
            <form action="/pages/checkout.php" method="POST" class="checkout-form" novalidate>
                <?php csrf_field(); ?>

                <h2 class="section-heading">Delivery Details</h2>

                <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name"
                           value="<?= htmlspecialchars($old['name'] ?? $_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required autocomplete="name">
                    <?php if (isset($errors['name'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['address']) ? 'has-error' : '' ?>">
                    <label for="address">Street Address</label>
                    <input type="text" id="address" name="address"
                           value="<?= htmlspecialchars($old['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required autocomplete="street-address">
                    <?php if (isset($errors['address'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['address'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group <?= isset($errors['city']) ? 'has-error' : '' ?>">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city"
                               value="<?= htmlspecialchars($old['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               required autocomplete="address-level2">
                        <?php if (isset($errors['city'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['city'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group <?= isset($errors['pincode']) ? 'has-error' : '' ?>">
                        <label for="pincode">PIN Code</label>
                        <input type="text" id="pincode" name="pincode"
                               value="<?= htmlspecialchars($old['pincode'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               required maxlength="6" inputmode="numeric" autocomplete="postal-code">
                        <?php if (isset($errors['pincode'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['pincode'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone"
                           value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required maxlength="10" inputmode="tel" autocomplete="tel">
                    <?php if (isset($errors['phone'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <h2 class="section-heading">Payment Method</h2>
                <div class="form-group payment-group <?= isset($errors['payment']) ? 'has-error' : '' ?>">
                    <?php
                    $paymentOptions = [
                        'cod'  => ['label' => 'Cash on Delivery', 'icon' => 'fa-money-bill'],
                        'upi'  => ['label' => 'UPI (Simulated)',  'icon' => 'fa-mobile-screen'],
                        'card' => ['label' => 'Card (Simulated)', 'icon' => 'fa-credit-card'],
                    ];
                    foreach ($paymentOptions as $val => $opt):
                    ?>
                        <label class="payment-option <?= ($old['payment'] ?? '') === $val ? 'selected' : '' ?>">
                            <input type="radio" name="payment" value="<?= $val ?>"
                                   <?= ($old['payment'] ?? '') === $val ? 'checked' : '' ?>>
                            <i class="fa-solid <?= $opt['icon'] ?>"></i>
                            <?= $opt['label'] ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (isset($errors['payment'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['payment'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">
                    Place Order ₹<?= number_format($total, 2) ?>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>

        <!-- Order summary -->
        <aside class="checkout-summary">
            <h2 class="section-heading">Order Summary</h2>
            <?php foreach ($cart as $item): ?>
                <div class="checkout-item">
                    <span class="checkout-item-name">
                        <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="checkout-item-qty">× <?= (int)$item['qty'] ?></span>
                    </span>
                    <span class="checkout-item-price">₹<?= number_format($item['price'] * $item['qty'], 2) ?></span>
                </div>
            <?php endforeach; ?>
            <hr class="summary-divider">
            <div class="summary-row"><span>Subtotal</span><span>₹<?= number_format($subtotal, 2) ?></span></div>
            <div class="summary-row"><span>Delivery</span><span><?= $delivery === 0 ? 'FREE' : '₹' . number_format($delivery, 2) ?></span></div>
            <?php if ($discount > 0): ?>
                <div class="summary-row discount-row"><span>Discount</span><span>–₹<?= number_format($discount, 2) ?></span></div>
            <?php endif; ?>
            <div class="summary-row total-row"><span>Total</span><span>₹<?= number_format($total, 2) ?></span></div>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
