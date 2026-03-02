<?php
/**
 * bloom-aura/pages/checkout.php
 * Pixel-matched to bloom_aura reference UI.
 * Backend logic (PDO, CSRF, session, transaction) preserved.
 */

session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// Redirect to cart if empty
if (empty($_SESSION['cart'])) {
    flash('Your cart is empty.', 'error');
    header('Location: /bloom-aura/pages/cart.php');
    exit;
}

$cart     = $_SESSION['cart'];
$subtotal = array_reduce($cart, fn($c, $i) => $c + ($i['price'] * $i['qty']), 0);
$delivery = $subtotal > 999 ? 0 : 80;
$discount = !empty($_SESSION['promo_applied']) ? (int) round($subtotal * 0.10) : 0;
$total    = $subtotal + $delivery - $discount;

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $name    = trim($_POST['name']    ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city']    ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $payment = $_POST['payment']      ?? '';

    $old = compact('name', 'address', 'city', 'pincode', 'phone', 'payment');

    if ($name === '')                               $errors['name']    = 'Full name is required.';
    if ($address === '')                            $errors['address'] = 'Street address is required.';
    if ($city === '')                               $errors['city']    = 'City is required.';
    if (!preg_match('/^\d{6}$/', $pincode))        $errors['pincode'] = 'Enter a valid 6-digit PIN code.';
    if (!preg_match('/^\d{10}$/', $phone))         $errors['phone']   = 'Enter a valid 10-digit phone number.';
    if (!in_array($payment, ['cod','upi','card']))  $errors['payment'] = 'Please select a payment method.';

    if (empty($errors)) {
        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

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

            $itemStmt  = $pdo->prepare(
                'INSERT INTO order_items (order_id, bouquet_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE bouquets SET stock = stock - ? WHERE id = ? AND stock >= ?'
            );

            foreach ($cart as $productId => $item) {
                $itemStmt->execute([$orderId, $productId, $item['qty'], $item['price']]);
                $stockStmt->execute([$item['qty'], $productId, $item['qty']]);
                if ($stockStmt->rowCount() === 0) {
                    $pdo->rollBack();
                    flash(htmlspecialchars($item['name']) . ' is no longer available in the requested quantity.', 'error');
                    header('Location: /bloom-aura/pages/cart.php');
                    exit;
                }
            }

            $pdo->commit();
            unset($_SESSION['cart'], $_SESSION['promo_applied']);
            $_SESSION['last_order_id'] = $orderId;
            flash('Order placed successfully! 🌸', 'success');
            header('Location: /bloom-aura/pages/order-confirmation.php');
            exit;

        } catch (RuntimeException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['db'] = 'Unable to process your order. Please try again.';
        }
    }
}

$paymentOptions = [
    'cod'  => ['label' => 'Cash on Delivery',  'icon' => '💵'],
    'upi'  => ['label' => 'UPI (Simulated)',    'icon' => '📱'],
    'card' => ['label' => 'Card (Simulated)',   'icon' => '💳'],
];

$pageTitle = 'Checkout — Bloom Aura';
$pageCss   = 'checkout';
require_once __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/bloom-aura/">Home</a></li>
        <li><a href="/bloom-aura/pages/cart.php">Cart</a></li>
        <li aria-current="page">Checkout</li>
    </ol>
</nav>

<div class="page-container">
    <h1 class="checkout-page-title">🛍️ Checkout</h1>

    <?php if (!empty($errors['db'])): ?>
        <div class="alert alert-error" role="alert">
            <?= htmlspecialchars($errors['db'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">

        <!-- ── LEFT: Delivery + Payment Form ── -->
        <div class="checkout-form-wrap">
            <div class="checkout-form">

                <div class="checkout-form-header">
                    <h2>🚚 Delivery Details</h2>
                    <p>Tell us where to send your blooms</p>
                </div>

                <div class="checkout-form-body">
                    <form action="/bloom-aura/pages/checkout.php" method="POST" novalidate>
                        <?php csrf_field(); ?>

                        <!-- Full Name -->
                        <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
                            <label for="co-name">Full Name</label>
                            <input type="text" id="co-name" name="name"
                                   value="<?= htmlspecialchars($old['name'] ?? $_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="e.g. Kiran Sharma"
                                   required autocomplete="name">
                            <?php if (isset($errors['name'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Street Address -->
                        <div class="form-group <?= isset($errors['address']) ? 'has-error' : '' ?>">
                            <label for="co-address">Street Address</label>
                            <input type="text" id="co-address" name="address"
                                   value="<?= htmlspecialchars($old['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="House / Flat No, Street, Locality"
                                   required autocomplete="street-address">
                            <?php if (isset($errors['address'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['address'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- City + PIN -->
                        <div class="form-row">
                            <div class="form-group <?= isset($errors['city']) ? 'has-error' : '' ?>">
                                <label for="co-city">City</label>
                                <input type="text" id="co-city" name="city"
                                       value="<?= htmlspecialchars($old['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="e.g. Bangalore"
                                       required autocomplete="address-level2">
                                <?php if (isset($errors['city'])): ?>
                                    <span class="field-error"><?= htmlspecialchars($errors['city'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group <?= isset($errors['pincode']) ? 'has-error' : '' ?>">
                                <label for="co-pincode">PIN Code</label>
                                <input type="text" id="co-pincode" name="pincode"
                                       value="<?= htmlspecialchars($old['pincode'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="6-digit PIN"
                                       maxlength="6" inputmode="numeric" pattern="\d{6}"
                                       required autocomplete="postal-code">
                                <?php if (isset($errors['pincode'])): ?>
                                    <span class="field-error"><?= htmlspecialchars($errors['pincode'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Phone -->
                        <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                            <label for="co-phone">Phone Number</label>
                            <input type="tel" id="co-phone" name="phone"
                                   value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="10-digit mobile number"
                                   maxlength="10" inputmode="numeric" pattern="\d{10}"
                                   required autocomplete="tel">
                            <?php if (isset($errors['phone'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Payment Method -->
                        <h3 class="checkout-section-title">💳 Payment Method</h3>

                        <div class="payment-options" role="group" aria-label="Payment method">
                            <?php foreach ($paymentOptions as $val => $opt): ?>
                                <label class="payment-option">
                                    <input type="radio" name="payment" value="<?= $val ?>"
                                           <?= ($old['payment'] ?? '') === $val ? 'checked' : '' ?>>
                                    <span class="payment-option-icon"><?= $opt['icon'] ?></span>
                                    <?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <?php if (isset($errors['payment'])): ?>
                            <span class="field-error" style="margin-top:.4rem;display:block;">
                                <?= htmlspecialchars($errors['payment'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>

                        <!-- Submit -->
                        <button type="submit" class="checkout-submit-btn">
                            Place Order ₹<?= number_format($total, 2) ?> ✨
                        </button>

                        <!-- Secure badges -->
                        <div class="secure-badges" aria-label="Security assurances">
                            <span class="secure-badge">🔒 Secure Checkout</span>
                            <span class="secure-badge">💳 Cards</span>
                            <span class="secure-badge">📱 UPI</span>
                            <span class="secure-badge">💵 COD</span>
                        </div>

                    </form>
                </div><!-- /.checkout-form-body -->
            </div><!-- /.checkout-form -->
        </div><!-- /.checkout-form-wrap -->

        <!-- ── RIGHT: Order Summary ── -->
        <aside class="checkout-summary" aria-label="Order Summary">

            <div class="checkout-summary-head">
                <h2>Order Summary</h2>
                <p><?= count($cart) ?> item<?= count($cart) !== 1 ? 's' : '' ?> in your bag</p>
            </div>

            <div class="checkout-summary-body">

                <?php if ($delivery === 0): ?>
                    <div class="checkout-delivery-banner">
                        🚚 <span>You qualify for <strong>FREE delivery!</strong></span>
                    </div>
                <?php endif; ?>

                <!-- Items -->
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

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span class="val">₹<?= number_format($subtotal, 2) ?></span>
                </div>

                <div class="summary-row">
                    <span>Delivery</span>
                    <span class="val <?= $delivery === 0 ? 'free' : '' ?>">
                        <?= $delivery === 0 ? 'FREE' : '₹' . number_format($delivery, 2) ?>
                    </span>
                </div>

                <?php if ($discount > 0): ?>
                    <div class="summary-row">
                        <span>Discount (BLOOM10)</span>
                        <span class="val discount">–₹<?= number_format($discount, 2) ?></span>
                    </div>
                <?php endif; ?>

                <div class="summary-total-row">
                    <span class="t-lbl">Total</span>
                    <span class="t-val">₹<?= number_format($total, 2) ?></span>
                </div>

            </div><!-- /.checkout-summary-body -->
        </aside>

    </div><!-- /.checkout-layout -->
</div><!-- /.page-container -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>