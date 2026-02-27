<?php
/**
 * bloom-aura/pages/cart.php
 * Session-based cart — view, update quantity, remove items, apply promo.
 * All mutations happen via POST with CSRF validation.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// ── Cart helpers ──────────────────────────────────────────────────────────────

/**
 * Add or increment a product in the session cart.
 * Validates that the product exists in the DB and has stock before adding.
 */
function cartAdd(int $productId, int $qty = 1): void {
    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id, name, price, stock, image FROM bouquets WHERE id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            flash('Product not found.', 'error');
            return;
        }
        if ($product['stock'] <= 0) {
            flash(htmlspecialchars($product['name']) . ' is out of stock.', 'error');
            return;
        }

        $cart = &$_SESSION['cart'];

        if (isset($cart[$productId])) {
            // Cap at available stock
            $newQty = min($cart[$productId]['qty'] + $qty, $product['stock']);
            $cart[$productId]['qty'] = $newQty;
        } else {
            $cart[$productId] = [
                'id'    => $product['id'],
                'name'  => $product['name'],
                'price' => $product['price'],
                'image' => $product['image'],
                'qty'   => min($qty, $product['stock']),
            ];
        }
        flash(htmlspecialchars($product['name']) . ' added to cart! 🛒', 'success');
    } catch (RuntimeException $e) {
        flash('Could not add item. Please try again.', 'error');
    }
}

function cartRemove(int $productId): void {
    unset($_SESSION['cart'][$productId]);
    flash('Item removed from cart.', 'info');
}

function cartUpdate(int $productId, int $qty): void {
    if ($qty <= 0) {
        cartRemove($productId);
        return;
    }
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['qty'] = $qty;
    }
}

// ── Handle POST mutations ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $action    = $_POST['action']     ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    switch ($action) {
        case 'add':
            cartAdd($productId, max(1, (int)($_POST['qty'] ?? 1)));
            break;
        case 'update':
            cartUpdate($productId, (int)($_POST['qty'] ?? 0));
            break;
        case 'remove':
            cartRemove($productId);
            break;
        case 'clear':
            $_SESSION['cart'] = [];
            flash('Cart cleared.', 'info');
            break;
    }

    // PRG pattern — redirect to prevent form resubmission on refresh
    header('Location: /pages/cart.php');
    exit;
}

// ── Compute totals ────────────────────────────────────────────────────────────
$cart     = $_SESSION['cart'] ?? [];
$subtotal = array_reduce($cart, fn($carry, $item) => $carry + ($item['price'] * $item['qty']), 0);
$delivery = ($subtotal > 999 || $subtotal === 0) ? 0 : 80; // Free delivery over ₹999
$discount = 0;

// Promo code
if (!empty($_SESSION['promo_applied'])) {
    $discount = (int) round($subtotal * 0.10);
}

$total = $subtotal + $delivery - $discount;

$pageTitle = 'My Cart — Bloom Aura';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/">Home</a></li>
        <li><a href="/pages/shop.php">Shop</a></li>
        <li aria-current="page">Cart</li>
    </ol>
</nav>

<div class="page-container">
    <h1 class="page-title">My Cart</h1>

    <?php if (empty($cart)): ?>
        <!-- Empty cart state -->
        <div class="empty-state">
            <div class="empty-icon">🛒</div>
            <h2>Your cart is empty</h2>
            <p>Add some beautiful bouquets to get started.</p>
            <a href="/pages/shop.php" class="btn btn-primary">Browse Shop</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <!-- Cart items -->
            <div class="cart-items">
                <?php foreach ($cart as $productId => $item): ?>
                    <div class="cart-item">
                        <img
                            src="/uploads/bouquets/<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                            class="cart-item-img"
                            loading="lazy"
                        >
                        <div class="cart-item-info">
                            <h3 class="cart-item-name">
                                <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <p class="cart-item-price">₹<?= number_format($item['price'], 2) ?> each</p>
                        </div>

                        <!-- Quantity update form -->
                        <form action="/pages/cart.php" method="POST" class="qty-form">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
                            <label for="qty-<?= (int)$productId ?>" class="sr-only">Quantity</label>
                            <input
                                type="number"
                                id="qty-<?= (int)$productId ?>"
                                name="qty"
                                value="<?= (int)$item['qty'] ?>"
                                min="1" max="20"
                                class="qty-input"
                                onchange="this.form.submit()"
                            >
                        </form>

                        <p class="cart-item-subtotal">
                            ₹<?= number_format($item['price'] * $item['qty'], 2) ?>
                        </p>

                        <!-- Remove form -->
                        <form action="/pages/cart.php" method="POST" class="remove-form">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
                            <button type="submit" class="btn-icon remove-btn" aria-label="Remove item">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <!-- Clear cart -->
                <form action="/pages/cart.php" method="POST" class="clear-form">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Clear your entire cart?')">
                        Clear Cart
                    </button>
                </form>
            </div>

            <!-- Order summary -->
            <aside class="cart-summary">
                <h2 class="summary-title">Order Summary</h2>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₹<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span>Delivery</span>
                    <span><?= $delivery === 0 ? '<span class="free-label">FREE</span>' : '₹' . number_format($delivery, 2) ?></span>
                </div>
                <?php if ($discount > 0): ?>
                    <div class="summary-row discount-row">
                        <span>Promo (BLOOM10)</span>
                        <span>–₹<?= number_format($discount, 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="summary-row total-row">
                    <span>Total</span>
                    <span>₹<?= number_format($total, 2) ?></span>
                </div>

                <?php if ($subtotal <= 999 && $subtotal > 0): ?>
                    <p class="free-delivery-notice">
                        Add ₹<?= number_format(999 - $subtotal + 0.01, 2) ?> more for free delivery!
                    </p>
                <?php endif; ?>

                <!-- Promo code -->
                <form action="/pages/cart.php" method="POST" class="promo-form">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="promo">
                    <label for="promo-code" class="sr-only">Promo code</label>
                    <div class="promo-input-group">
                        <input type="text" id="promo-code" name="promo_code"
                               placeholder="Promo code"
                               value="<?= !empty($_SESSION['promo_applied']) ? 'BLOOM10' : '' ?>"
                               <?= !empty($_SESSION['promo_applied']) ? 'readonly' : '' ?>>
                        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
                    </div>
                </form>

                <?php if (!empty($_SESSION['user_id'])): ?>
                    <a href="/pages/checkout.php" class="btn btn-primary btn-full checkout-btn">
                        Proceed to Checkout <i class="fa-solid fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <a href="/pages/login.php?redirect=/pages/checkout.php" class="btn btn-primary btn-full">
                        Login to Checkout
                    </a>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
