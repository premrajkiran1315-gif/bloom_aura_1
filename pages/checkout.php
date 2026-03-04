<?php
/**
 * bloom-aura/pages/checkout.php
 *
 * FIX LOG:
 *  - Broadened exception catch to \Throwable (covers PDOException, Error, etc.)
 *    so a missing column never causes a blank white page — user sees error instead.
 *  - Added explicit column existence guard for `is_active` on bouquets.
 *  - Added ob_start() output buffering so any stray output before header()
 *    never causes "headers already sent" blank page.
 *  - Improved error logging with context so you can find bugs in error.log.
 *  - All other security/logic from original preserved.
 */

// ── Output buffer: prevents blank page from premature output ─────────────────
ob_start();

session_start();
require_once __DIR__ . '/../includes/auth_check.php';  // Redirects if not logged in
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// ── Guard: empty cart ─────────────────────────────────────────────────────────
if (empty($_SESSION['cart'])) {
    flash('Your cart is empty.', 'error');
    header('Location: /bloom-aura/pages/cart.php');
    ob_end_flush();
    exit;
}

// ── Re-validate cart items against the database ───────────────────────────────
// NEVER trust session prices — always fetch current price and stock from DB.
$cart          = $_SESSION['cart'];
$validatedCart = [];
$cartWarnings  = [];

try {
    $pdo = getPDO();

    // ── Check if bouquets table has is_active column ──────────────────────────
    // This prevents a fatal if your schema is older and lacks the column.
    $hasIsActive = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM `bouquets` LIKE 'is_active'");
        $hasIsActive = ($colCheck->rowCount() > 0);
    } catch (\Throwable $e) {
        $hasIsActive = false;
    }

    // Build SELECT based on whether column exists
    $selectCols = $hasIsActive
        ? 'id, name, price, stock, is_active'
        : 'id, name, price, stock';

    foreach ($cart as $productId => $item) {
        // Skip custom bouquets (string keys like 'custom_1234') — no DB row
        if (!is_numeric($productId)) {
            $validatedCart[$productId] = $item;
            continue;
        }

        $stmt = $pdo->prepare(
            "SELECT {$selectCols} FROM bouquets WHERE id = ? LIMIT 1"
        );
        $stmt->execute([(int)$productId]);
        $dbProduct = $stmt->fetch();

        // Product deleted since added to cart
        if (!$dbProduct) {
            $cartWarnings[] = htmlspecialchars($item['name'] ?? 'A product', ENT_QUOTES, 'UTF-8')
                            . ' is no longer available and was removed from your cart.';
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        // Only check is_active if column exists
        if ($hasIsActive && !(int)$dbProduct['is_active']) {
            $cartWarnings[] = htmlspecialchars($dbProduct['name'], ENT_QUOTES, 'UTF-8')
                            . ' is no longer available and was removed from your cart.';
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        // Out of stock
        if ((int)$dbProduct['stock'] <= 0) {
            $cartWarnings[] = htmlspecialchars($dbProduct['name'], ENT_QUOTES, 'UTF-8')
                            . ' is out of stock and was removed from your cart.';
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        // Clamp quantity to available stock
        $qty = (int)$item['qty'];
        if ($qty > (int)$dbProduct['stock']) {
            $qty = (int)$dbProduct['stock'];
            $_SESSION['cart'][$productId]['qty'] = $qty;
            $cartWarnings[] = htmlspecialchars($dbProduct['name'], ENT_QUOTES, 'UTF-8')
                            . ' quantity adjusted to ' . $qty . ' (maximum available).';
        }

        // Always use DB price — never trust session price
        $validatedCart[$productId] = [
            'id'    => (int)$dbProduct['id'],
            'name'  => $dbProduct['name'],
            'price' => (float)$dbProduct['price'],
            'image' => $item['image'] ?? '',
            'qty'   => $qty,
        ];

        // Keep session in sync
        $_SESSION['cart'][$productId]['price'] = (float)$dbProduct['price'];
    }

} catch (\Throwable $e) {
    // Log the real error — never show it to the user
    error_log('[BloomAura Checkout - Cart Validation Error] ' . $e->getMessage()
              . ' | File: ' . $e->getFile() . ':' . $e->getLine());
    flash('Unable to verify your cart items. Please try again.', 'error');
    header('Location: /bloom-aura/pages/cart.php');
    ob_end_flush();
    exit;
}

// If everything was removed, go back to cart
if (empty($validatedCart)) {
    foreach ($cartWarnings as $w) flash($w, 'error');
    header('Location: /bloom-aura/pages/cart.php');
    ob_end_flush();
    exit;
}

// Show stock warnings but still allow checkout with remaining valid items
foreach ($cartWarnings as $w) {
    flash($w, 'warning');
}

// ── Recalculate totals from DB-verified prices ────────────────────────────────
$subtotal = array_reduce($validatedCart, fn($c, $i) => $c + ($i['price'] * $i['qty']), 0.0);
$delivery = $subtotal > 999 ? 0 : 80;
$discount = !empty($_SESSION['promo_applied']) ? (int) round($subtotal * 0.10) : 0;
$total    = $subtotal + $delivery - $discount;

// Use validated cart from here on
$cart = $validatedCart;

$errors = [];
$old    = [];

// ── Handle POST: place order ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check — exits with 403 if token invalid
    csrf_validate();

    // Collect and sanitise inputs
    $name    = trim($_POST['name']    ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city']    ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $payment = $_POST['payment']      ?? '';

    // Repopulate form on error
    $old = compact('name', 'address', 'city', 'pincode', 'phone', 'payment');

    // Server-side validation
    if ($name === '')                               $errors['name']    = 'Full name is required.';
    if ($address === '')                            $errors['address'] = 'Street address is required.';
    if ($city === '')                               $errors['city']    = 'City is required.';
    if (!preg_match('/^\d{6}$/', $pincode))        $errors['pincode'] = 'Enter a valid 6-digit PIN code.';
    if (!preg_match('/^\d{10}$/', $phone))         $errors['phone']   = 'Enter a valid 10-digit phone number.';
    if (!in_array($payment, ['cod','upi','card'], true))
                                                    $errors['payment'] = 'Please select a payment method.';

    if (empty($errors)) {
        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            // ── Insert order ──────────────────────────────────────────────────
            $stmt = $pdo->prepare(
                "INSERT INTO orders
                    (user_id, total, status,
                     delivery_name, delivery_address, delivery_city,
                     delivery_pincode, delivery_phone, payment_method, created_at)
                 VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                (int)$_SESSION['user_id'],
                $total,
                $name,
                $address,
                $city,
                $pincode,
                $phone,
                $payment,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            if ($orderId === 0) {
                throw new \RuntimeException('Order insert returned no ID.');
            }

            // ── Insert order items & decrement stock ──────────────────────────
            $itemStmt  = $pdo->prepare(
                'INSERT INTO order_items (order_id, bouquet_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE bouquets SET stock = stock - ? WHERE id = ? AND stock >= ?'
            );

            foreach ($cart as $productId => $item) {
                // Skip custom bouquets — no DB row to update
                if (!is_numeric($productId)) continue;

                $itemStmt->execute([
                    $orderId,
                    (int)$productId,
                    (int)$item['qty'],
                    (float)$item['price'],   // DB-verified price
                ]);

                $stockStmt->execute([(int)$item['qty'], (int)$productId, (int)$item['qty']]);

                // rowCount() === 0 means stock check failed (race condition)
                if ($stockStmt->rowCount() === 0) {
                    $pdo->rollBack();
                    flash(
                        htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8')
                        . ' is no longer available in the requested quantity.',
                        'error'
                    );
                    header('Location: /bloom-aura/pages/cart.php');
                    ob_end_flush();
                    exit;
                }
            }

            // ── Commit & clean up ─────────────────────────────────────────────
            $pdo->commit();
            unset($_SESSION['cart'], $_SESSION['promo_applied']);
            $_SESSION['last_order_id'] = $orderId;
            flash('Order placed successfully! 🌸', 'success');
            header('Location: /bloom-aura/pages/order-confirmation.php');
            ob_end_flush();
            exit;

        } catch (\Throwable $e) {
            // Roll back if a transaction is still open
            try {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Throwable $rb) { /* ignore rollback error */ }

            // Log real error details for the developer
            error_log('[BloomAura Checkout - Order Error] ' . $e->getMessage()
                      . ' | File: ' . $e->getFile() . ':' . $e->getLine());

            $errors['db'] = 'Unable to process your order. Please try again.';
        }
    }
}

// ── Payment options displayed in the form ─────────────────────────────────────
$paymentOptions = [
    'cod'  => ['label' => 'Cash on Delivery', 'icon' => '💵'],
    'upi'  => ['label' => 'UPI (Simulated)',   'icon' => '📱'],
    'card' => ['label' => 'Card (Simulated)',  'icon' => '💳'],
];

$pageTitle = 'Checkout — Bloom Aura';
$pageCss   = 'checkout';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/bloom-aura/">Home</a></li>
        <li><a href="/bloom-aura/pages/cart.php">Cart</a></li>
        <li aria-current="page">Checkout</li>
    </ol>
</nav>

<div class="page-container checkout-page">
    <h1 class="page-title">Checkout</h1>

    <?php if (!empty($errors['db'])): ?>
        <div class="alert alert-error" role="alert">
            <?= htmlspecialchars($errors['db'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">

        <!-- ── Delivery Form ── -->
        <section class="checkout-form-wrap" aria-labelledby="delivery-heading">
            <h2 id="delivery-heading" class="section-title">Delivery Details</h2>

            <form method="POST" action="/bloom-aura/pages/checkout.php" novalidate id="checkout-form">
                <?php csrf_field(); ?>

                <!-- Full Name -->
                <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
                    <label for="name">Full Name <span class="required" aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        autocomplete="name"
                        required
                        maxlength="150"
                        value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        aria-describedby="<?= isset($errors['name']) ? 'err-name' : '' ?>"
                    >
                    <?php if (isset($errors['name'])): ?>
                        <span class="field-error" id="err-name" role="alert">
                            <?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Street Address -->
                <div class="form-group <?= isset($errors['address']) ? 'has-error' : '' ?>">
                    <label for="address">Street Address <span class="required" aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        id="address"
                        name="address"
                        autocomplete="street-address"
                        required
                        maxlength="255"
                        value="<?= htmlspecialchars($old['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        aria-describedby="<?= isset($errors['address']) ? 'err-address' : '' ?>"
                    >
                    <?php if (isset($errors['address'])): ?>
                        <span class="field-error" id="err-address" role="alert">
                            <?= htmlspecialchars($errors['address'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- City & PIN row -->
                <div class="form-row">
                    <div class="form-group <?= isset($errors['city']) ? 'has-error' : '' ?>">
                        <label for="city">City <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            id="city"
                            name="city"
                            autocomplete="address-level2"
                            required
                            maxlength="100"
                            value="<?= htmlspecialchars($old['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            aria-describedby="<?= isset($errors['city']) ? 'err-city' : '' ?>"
                        >
                        <?php if (isset($errors['city'])): ?>
                            <span class="field-error" id="err-city" role="alert">
                                <?= htmlspecialchars($errors['city'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group <?= isset($errors['pincode']) ? 'has-error' : '' ?>">
                        <label for="pincode">PIN Code <span class="required" aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            id="pincode"
                            name="pincode"
                            autocomplete="postal-code"
                            required
                            maxlength="6"
                            pattern="\d{6}"
                            inputmode="numeric"
                            value="<?= htmlspecialchars($old['pincode'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            aria-describedby="<?= isset($errors['pincode']) ? 'err-pincode' : '' ?>"
                        >
                        <?php if (isset($errors['pincode'])): ?>
                            <span class="field-error" id="err-pincode" role="alert">
                                <?= htmlspecialchars($errors['pincode'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Phone -->
                <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                    <label for="phone">Phone Number <span class="required" aria-hidden="true">*</span></label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        autocomplete="tel"
                        required
                        maxlength="10"
                        pattern="\d{10}"
                        inputmode="numeric"
                        value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        aria-describedby="<?= isset($errors['phone']) ? 'err-phone' : '' ?>"
                    >
                    <?php if (isset($errors['phone'])): ?>
                        <span class="field-error" id="err-phone" role="alert">
                            <?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Payment Method -->
                <div class="form-group <?= isset($errors['payment']) ? 'has-error' : '' ?>">
                    <fieldset>
                        <legend>Payment Method <span class="required" aria-hidden="true">*</span></legend>
                        <div class="payment-options">
                            <?php foreach ($paymentOptions as $value => $opt): ?>
                                <label class="payment-option <?= ($old['payment'] ?? '') === $value ? 'selected' : '' ?>">
                                    <input
                                        type="radio"
                                        name="payment"
                                        value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= ($old['payment'] ?? '') === $value ? 'checked' : '' ?>
                                        required
                                    >
                                    <span class="payment-icon"><?= $opt['icon'] ?></span>
                                    <span class="payment-label"><?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($errors['payment'])): ?>
                            <span class="field-error" role="alert">
                                <?= htmlspecialchars($errors['payment'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                    </fieldset>
                </div>

                <!-- Secure badges -->
                <div class="secure-badges">
                    <span class="secure-badge">🔒 SSL Secured</span>
                    <span class="secure-badge">✅ Safe Checkout</span>
                    <span class="secure-badge">🌸 Bloom Aura</span>
                </div>

                <button type="submit" class="btn-place-order">
                    Place Order 🌸
                </button>
            </form>
        </section><!-- /.checkout-form-wrap -->

        <!-- ── Order Summary Sidebar ── -->
        <aside class="checkout-summary" aria-labelledby="summary-heading">
            <div class="checkout-summary-head">
                <h2 id="summary-heading">Order Summary</h2>
                <p><?= count($cart) ?> item<?= count($cart) !== 1 ? 's' : '' ?> in your cart</p>
            </div>

            <div class="checkout-summary-body">
                <!-- Line items -->
                <?php foreach ($cart as $item): ?>
                    <div class="checkout-item">
                        <span class="checkout-item-name">
                            <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                            <span class="checkout-item-qty">× <?= (int)$item['qty'] ?></span>
                        </span>
                        <span class="checkout-item-price">
                            ₹<?= number_format($item['price'] * $item['qty'], 2) ?>
                        </span>
                    </div>
                <?php endforeach; ?>

                <hr class="summary-divider">

                <!-- Totals -->
                <div class="summary-line">
                    <span>Subtotal</span>
                    <span>₹<?= number_format($subtotal, 2) ?></span>
                </div>

                <div class="summary-line">
                    <span>Delivery</span>
                    <span><?= $delivery === 0 ? '<span class="free-tag">FREE</span>' : '₹' . number_format($delivery, 2) ?></span>
                </div>

                <?php if ($discount > 0): ?>
                    <div class="summary-line discount-line">
                        <span>Promo (BLOOM10)</span>
                        <span>−₹<?= number_format($discount, 2) ?></span>
                    </div>
                <?php endif; ?>

                <div class="summary-line summary-total">
                    <span>Total</span>
                    <span>₹<?= number_format($total, 2) ?></span>
                </div>

                <?php if ($subtotal <= 999): ?>
                    <p class="free-delivery-hint">
                        Add ₹<?= number_format(999 - $subtotal, 2) ?> more for free delivery!
                    </p>
                <?php endif; ?>
            </div><!-- /.checkout-summary-body -->
        </aside>

    </div><!-- /.checkout-layout -->
</div><!-- /.page-container -->

<script src="/bloom-aura/assets/js/validate.js" defer></script>

<?php
require_once __DIR__ . '/../includes/footer.php';
ob_end_flush(); // Send buffered output
?>