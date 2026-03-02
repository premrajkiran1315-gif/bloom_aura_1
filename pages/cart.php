<?php
/**
 * bloom-aura-1/pages/cart.php
 * Session-based cart — pixel-matched to bloom_aura reference UI.
 * All mutations via POST with CSRF validation. PDO. PRG pattern.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

/* ── Cart helpers ─────────────────────────────────────────────────────────── */
function cartAdd(int $productId, int $qty = 1): void {
    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id, name, price, stock, image FROM bouquets WHERE id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) { flash('Product not found.', 'error'); return; }
        if ($product['stock'] <= 0) { flash(htmlspecialchars($product['name']) . ' is out of stock.', 'error'); return; }
        $cart = &$_SESSION['cart'];
        if (isset($cart[$productId])) {
            $cart[$productId]['qty'] = min($cart[$productId]['qty'] + $qty, $product['stock']);
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
    if ($qty <= 0) { cartRemove($productId); return; }
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['qty'] = $qty;
    }
}

/* ── Handle POST ──────────────────────────────────────────────────────────── */
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
            if (is_string($_POST['product_id'] ?? null)) {
                // custom cart item (string key like 'custom_...')
                unset($_SESSION['cart'][$_POST['product_id']]);
                flash('Item removed from cart.', 'info');
            } else {
                cartRemove($productId);
            }
            break;
        case 'clear':
            $_SESSION['cart'] = [];
            flash('Cart cleared.', 'info');
            break;
        case 'promo':
            $code = strtoupper(trim($_POST['promo_code'] ?? ''));
            if ($code === 'BLOOM10') {
                $_SESSION['promo_applied'] = true;
                flash('🎉 Promo applied — 10% off!', 'success');
            } else {
                unset($_SESSION['promo_applied']);
                flash('Invalid promo code.', 'error');
            }
            break;
    }

    header('Location: /bloom-aura/pages/cart.php');
    exit;
}

/* ── Compute totals ───────────────────────────────────────────────────────── */
$cart     = $_SESSION['cart'] ?? [];
$subtotal = array_reduce($cart, fn($c, $i) => $c + ($i['price'] * ($i['qty'] ?? 1)), 0);
$delivery = ($subtotal > 999 || $subtotal === 0) ? 0 : 80;
$discount = !empty($_SESSION['promo_applied']) ? (int)round($subtotal * 0.10) : 0;
$total    = $subtotal + $delivery - $discount;

$pageTitle = 'My Cart — Bloom Aura';
$pageCss   = 'cart';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/bloom-aura/">Home</a></li>
        <li><a href="/bloom-aura/pages/shop.php">Shop</a></li>
        <li aria-current="page">Cart</li>
    </ol>
</nav>

<div class="page-container">

    <!-- Page heading row -->
    <div class="cart-heading-row">
        <div>
            <h1 class="cart-page-title">Your Cart 🛒</h1>
            <p class="cart-page-sub">
                <?php if (!empty($cart)): ?>
                    <?= count($cart) ?> item<?= count($cart) !== 1 ? 's' : '' ?> in your cart
                <?php else: ?>
                    Your cart is empty
                <?php endif; ?>
            </p>
        </div>
        <a href="/bloom-aura/pages/shop.php" class="cart-continue-btn">← Continue Shopping</a>
    </div>

    <?php if (empty($cart)): ?>
    <!-- ── EMPTY STATE ── -->
    <div class="empty-state">
        <div class="empty-icon">🛒</div>
        <h2>Your cart is empty</h2>
        <p>Add some beautiful bouquets to get started.</p>
        <a href="/bloom-aura/pages/shop.php" class="btn btn-primary">Browse Collections 🌸</a>
    </div>

    <?php else: ?>
    <!-- ── CART LAYOUT ── -->
    <div class="cart-layout">

        <!-- ════ LEFT — CART ITEMS ════ -->
        <div class="cart-items-col">

            <?php foreach ($cart as $productId => $item):
                $isCustom = str_starts_with((string)$productId, 'custom');
                $imgSrc   = !$isCustom && !empty($item['image'])
                    ? '/bloom-aura/uploads/' . htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8')
                    : '';
                $itemTotal = $item['price'] * ($item['qty'] ?? 1);
            ?>
            <div class="cart-item-card" id="cartcard-<?= htmlspecialchars((string)$productId, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Thumbnail -->
                <div class="cart-item-thumb">
                    <?php if ($imgSrc): ?>
                        <img
                            src="<?= $imgSrc ?>"
                            alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                        >
                        <span class="thumb-fallback" style="display:none;">🌸</span>
                    <?php else: ?>
                        <span class="thumb-fallback">🌸</span>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="cart-item-info">
                    <div class="cart-item-name">
                        <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php if (!empty($item['attribute'])): ?>
                    <div class="cart-item-chips">
                        <?php foreach (explode('·', $item['attribute']) as $chip): ?>
                            <span class="item-chip"><?= htmlspecialchars(trim($chip), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="cart-item-unit-price">₹<?= number_format($item['price'], 2) ?> each</div>
                </div>

                <!-- Right side: price + qty + remove -->
                <div class="cart-item-right">
                    <div class="cart-item-price">₹<?= number_format($itemTotal, 2) ?></div>

                    <!-- Qty form -->
                    <?php if (!$isCustom): ?>
                    <form action="/bloom-aura/pages/cart.php" method="POST" class="cart-qty-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action"     value="update">
                        <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
                        <label for="qty-<?= (int)$productId ?>" class="sr-only">Quantity</label>
                        <input
                            type="number"
                            id="qty-<?= (int)$productId ?>"
                            name="qty"
                            value="<?= (int)$item['qty'] ?>"
                            min="1" max="20"
                            class="cart-qty-input"
                            onchange="this.form.submit()"
                        >
                    </form>
                    <?php else: ?>
                        <span class="cart-qty-badge">Qty: <?= (int)($item['qty'] ?? 1) ?></span>
                    <?php endif; ?>

                    <!-- Remove -->
                    <form action="/bloom-aura/pages/cart.php" method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action"     value="remove">
                        <input type="hidden" name="product_id" value="<?= htmlspecialchars((string)$productId, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-remove-item" aria-label="Remove item">✕</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Clear cart -->
            <div class="cart-clear-wrap">
                <form action="/bloom-aura/pages/cart.php" method="POST"
                      onsubmit="return confirm('Clear your entire cart?')">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn-clear-cart">🗑 Clear Cart</button>
                </form>
            </div>

        </div><!-- /.cart-items-col -->

        <!-- ════ RIGHT — ORDER SUMMARY ════ -->
        <aside class="summary-panel" aria-label="Order summary">

            <!-- Gradient header -->
            <div class="summary-head">
                <h2>Order Summary</h2>
                <p>Review before placing your order</p>
            </div>

            <div class="summary-body">

                <!-- Delivery banner -->
                <div class="delivery-banner">
                    <div class="delivery-icon">🚀</div>
                    <div class="delivery-text">
                        Estimated delivery in <strong>2–4 days</strong><br>
                        Free delivery on orders above ₹999
                    </div>
                </div>

                <!-- Rows -->
                <div class="sum-row">
                    <span class="lbl">Subtotal</span>
                    <span class="val">₹<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="sum-row">
                    <span class="lbl">Delivery</span>
                    <span class="val green">
                        <?php if ($subtotal === 0): ?>—
                        <?php elseif ($delivery === 0): ?>🎉 Free
                        <?php else: ?>₹<?= number_format($delivery, 2) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($discount > 0): ?>
                <div class="sum-row">
                    <span class="lbl">Promo Discount</span>
                    <span class="val green">–₹<?= number_format($discount, 2) ?></span>
                </div>
                <?php endif; ?>

                <hr class="sum-divider">

                <!-- Total -->
                <div class="sum-total">
                    <div class="t-lbl">Total</div>
                    <div class="t-val">₹<?= number_format($total, 2) ?></div>
                </div>

                <?php if ($subtotal > 0 && $subtotal <= 999): ?>
                <p class="free-delivery-hint">
                    Add ₹<?= number_format(1000 - $subtotal, 2) ?> more for free delivery!
                </p>
                <?php endif; ?>

                <!-- Promo code -->
                <form action="/bloom-aura/pages/cart.php" method="POST" class="promo-row">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="promo">
                    <input
                        type="text"
                        name="promo_code"
                        class="promo-input"
                        placeholder="Promo code (try BLOOM10)"
                        value="<?= !empty($_SESSION['promo_applied']) ? 'BLOOM10' : '' ?>"
                        <?= !empty($_SESSION['promo_applied']) ? 'readonly' : '' ?>
                    >
                    <button type="submit" class="promo-btn">Apply</button>
                </form>
                <?php if (!empty($_SESSION['promo_applied'])): ?>
                <div class="promo-ok">✅ BLOOM10 applied — 10% off!</div>
                <?php endif; ?>

                <!-- Checkout button -->
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <a href="/bloom-aura/pages/checkout.php" class="btn-checkout">
                        Place Order ✨
                    </a>
                <?php else: ?>
                    <a href="/bloom-aura/pages/login.php?redirect=checkout" class="btn-checkout">
                        Login to Checkout
                    </a>
                <?php endif; ?>

                <!-- Payment badges -->
                <div class="pay-badges">
                    <span class="pay-badge">💳 Cards</span>
                    <span class="pay-badge">📱 UPI</span>
                    <span class="pay-badge">💵 COD</span>
                    <span class="pay-badge">🔒 Secure</span>
                </div>

            </div><!-- /.summary-body -->
        </aside>

    </div><!-- /.cart-layout -->
    <?php endif; ?>

</div><!-- /.page-container -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>