<?php
/**
 * bloom-aura/pages/checkout.php
 * Pixel-matched to bloom_aura reference UI.
 * FIX: Prices are now re-validated against the database at checkout.
 *      Session prices are never trusted for order totals.
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

// ── Re-validate cart items against the database ───────────────────────────────
// NEVER trust session prices — always fetch current price and stock from DB.
$cart          = $_SESSION['cart'];
$validatedCart = []; // will hold DB-verified items
$cartWarnings  = []; // items that had issues (removed or adjusted)

try {
    $pdo = getPDO();

    foreach ($cart as $productId => $item) {
        // Skip custom bouquets (string keys like 'custom_1234') — they have no DB row
        if (!is_numeric($productId)) {
            $validatedCart[$productId] = $item;
            continue;
        }

        $stmt = $pdo->prepare(
            'SELECT id, name, price, stock, is_active
             FROM bouquets WHERE id = ? LIMIT 1'
        );
        $stmt->execute([(int)$productId]);
        $dbProduct = $stmt->fetch();

        // Product deleted or deactivated since added to cart
        if (!$dbProduct || !$dbProduct['is_active']) {
            $cartWarnings[] = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8')
                            . ' is no longer available and was removed from your cart.';
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        // Out of stock entirely
        if ((int)$dbProduct['stock'] <= 0) {
            $cartWarnings[] = htmlspecialchars($dbProduct['name'], ENT_QUOTES, 'UTF-8')
                            . ' is out of stock and was removed from your cart.';
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        // Quantity exceeds available stock — clamp it
        $qty = (int)$item['qty'];
        if ($qty > (int)$dbProduct['stock']) {
            $qty = (int)$dbProduct['stock'];
            $_SESSION['cart'][$productId]['qty'] = $qty;
            $cartWarnings[] = htmlspecialchars($dbProduct['name'], ENT_QUOTES, 'UTF-8')
                            . ' quantity adjusted to ' . $qty . ' (maximum available).';
        }

        // ✅ Use DB price — never session price
        $validatedCart[$productId] = [
            'id'    => (int)$dbProduct['id'],
            'name'  => $dbProduct['name'],
            'price' => (float)$dbProduct['price'], // ← DB price, not session price
            'image' => $item['image'],
            'qty'   => $qty,
        ];

        // Also update session price to stay in sync for cart display
        $_SESSION['cart'][$productId]['price'] = (float)$dbProduct['price'];
    }

} catch (RuntimeException $e) {
    flash('Unable to verify cart items. Please try again.', 'error');
    header('Location: /bloom-aura/pages/cart.php');
    exit;
}

// If validation removed everything, send back to cart
if (empty($validatedCart)) {
    foreach ($cartWarnings as $w) flash($w, 'error');
    header('Location: /bloom-aura/pages/cart.php');
    exit;
}

// Show warnings but continue if some items are still valid
foreach ($cartWarnings as $w) {
    flash($w, 'error');
}

// ── Recalculate totals using DB-verified prices ───────────────────────────────
$subtotal = array_reduce($validatedCart, fn($c, $i) => $c + ($i['price'] * $i['qty']), 0);
$delivery = $subtotal > 999 ? 0 : 80;
$discount = !empty($_SESSION['promo_applied']) ? (int) round($subtotal * 0.10) : 0;
$total    = $subtotal + $delivery - $discount;

// Use $validatedCart everywhere below instead of $cart
$cart = $validatedCart;

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
                'INSERT INTO order_items (order_id, bouquet_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE bouquets SET stock = stock - ? WHERE id = ? AND stock >= ?'
            );

            foreach ($cart as $productId => $item) {
                // Skip custom bouquets — no DB row to update stock for
                if (!is_numeric($productId)) continue;

                // ✅ $item['price'] is now the DB-verified price from $validatedCart
                $itemStmt->execute([$orderId, $productId, $item['qty'], $item['price']]);
                $stockStmt->execute([$item['qty'], $productId, $item['qty']]);

                if ($stockStmt->rowCount() === 0) {
                    $pdo->rollBack();
                    flash(
                        htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8')
                        . ' is no longer available in the requested quantity.',
                        'error'
                    );
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
    'cod'  => ['label' => 'Cash on Delivery', 'icon' => '💵'],
    'upi'  => ['label' => 'UPI (Simulated)',   'icon' => '📱'],
    'card' => ['label' => 'Card (Simulated)',  'icon' => '💳'],
];

$pageTitle = 'Checkout — Bloom Aura';
$pageCss   = 'checkout';
require_once __DIR__ . '/../includes/header.php';