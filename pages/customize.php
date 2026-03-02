<?php
/**
 * bloom-aura-1/pages/customize.php
 * Custom Bouquet Builder page.
 * UI pixel-matched to bloom_aura reference (section#customize).
 *
 * Requires login — guests are redirected to login with a flash message.
 * On POST: builds a cart session item and redirects to cart.php.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

/* ── Must be logged in ── */
if (empty($_SESSION['user_id'])) {
    flash('Please log in to build your custom bouquet.', 'info');
    header('Location: /bloom-aura/pages/login.php?redirect=customize');
    exit;
}

/* ════════════════════════════════════════
   POST — Add custom bouquet to cart
════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $flower    = trim($_POST['flower']    ?? '');
    $flowerP   = (int)($_POST['flower_price']  ?? 0);
    $size      = trim($_POST['size']      ?? '');
    $sizeP     = (int)($_POST['size_price']    ?? 0);
    $wrap      = trim($_POST['wrap']      ?? '');
    $wrapP     = (int)($_POST['wrap_price']    ?? 0);
    $choc      = trim($_POST['choc']      ?? 'None');
    $chocP     = (int)($_POST['choc_price']    ?? 0);

    /* extras checkboxes */
    $extras    = [];
    $extrasP   = 0;
    if (!empty($_POST['ribbon']))  { $extras[] = 'Ribbon';  $extrasP += 50; }
    if (!empty($_POST['glitter'])) { $extras[] = 'Glitter'; $extrasP += 30; }
    if (!empty($_POST['scent']))   { $extras[] = 'Scent';   $extrasP += 20; }
    if (!empty($_POST['gcard']))   { $extras[] = 'Card';    $extrasP += 40; }

    $errors = [];
    if (!$flower) $errors[] = 'Please select base flowers.';
    if (!$size)   $errors[] = 'Please select a bouquet size.';
    if (!$wrap)   $errors[] = 'Please choose a wrapping style.';

    if (empty($errors)) {
        $total     = $flowerP + $sizeP + $wrapP + $extrasP + $chocP;
        $extrasStr = $extras ? ' + ' . implode(', ', $extras) : '';
        $chocNote  = ($choc !== 'None') ? ' + 🍫 ' . $choc : '';
        $attribute = $size . ' · ' . $wrap . $extrasStr . $chocNote;

        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $_SESSION['cart'][] = [
            'id'        => 'custom_' . time(),
            'name'      => 'Custom: ' . $flower,
            'price'     => $total,
            'qty'       => 1,
            'attribute' => $attribute,
            'image'     => '',
        ];

        flash('🌸 Custom bouquet added to cart!', 'success');
        header('Location: /bloom-aura/pages/cart.php');
        exit;
    }

    /* keep errors for display */
    foreach ($errors as $e) flash($e, 'error');
}

$pageTitle = 'Customize Bouquet — Bloom Aura';
$pageCss   = 'customize';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/bloom-aura/">Home</a></li>
        <li><a href="/bloom-aura/pages/shop.php">Shop</a></li>
        <li aria-current="page">Customize Bouquet</li>
    </ol>
</nav>

<!-- ═══════════════════════════════════════════
     CUSTOMIZE PAGE WRAPPER
════════════════════════════════════════════ -->
<div class="customize-page">

    <!-- Page heading -->
    <div class="customize-heading">
        <h1 class="customize-title">🌸 Build Your Dream Bouquet</h1>
        <p class="customize-sub">Pick your flowers, style &amp; extras — we'll make it just for you</p>
    </div>

    <form class="customize-form" id="customizeForm" method="POST"
          action="/bloom-aura/pages/customize.php" novalidate>
        <?php csrf_field(); ?>

        <!-- Hidden price fields updated by JS -->
        <input type="hidden" name="flower"       id="inp-flower">
        <input type="hidden" name="flower_price" id="inp-flower-price" value="0">
        <input type="hidden" name="size"         id="inp-size">
        <input type="hidden" name="size_price"   id="inp-size-price" value="0">
        <input type="hidden" name="wrap"         id="inp-wrap">
        <input type="hidden" name="wrap_price"   id="inp-wrap-price" value="0">
        <input type="hidden" name="choc"         id="inp-choc" value="None">
        <input type="hidden" name="choc_price"   id="inp-choc-price" value="0">

        <div class="customize-layout">

            <!-- ═══════════════════
                 LEFT — selections
            ════════════════════ -->
            <div class="customize-left">

                <!-- ① Base Flowers -->
                <div class="cust-step">
                    <div class="step-header">
                        <span class="step-num">1</span>
                        <h2 class="step-title">Choose Your Base Flowers</h2>
                    </div>
                    <div class="option-grid option-grid--3">
                        <label class="flower-option" data-name="Red Roses" data-price="999">
                            <div class="opt-emoji">🌹</div>
                            <div class="opt-name">Red Roses</div>
                            <div class="opt-price">₹999</div>
                        </label>
                        <label class="flower-option" data-name="White Lilies" data-price="899">
                            <div class="opt-emoji">🌷</div>
                            <div class="opt-name">White Lilies</div>
                            <div class="opt-price">₹899</div>
                        </label>
                        <label class="flower-option" data-name="Pink Tulips" data-price="849">
                            <div class="opt-emoji">🌸</div>
                            <div class="opt-name">Pink Tulips</div>
                            <div class="opt-price">₹849</div>
                        </label>
                        <label class="flower-option" data-name="Sunflowers" data-price="799">
                            <div class="opt-emoji">🌻</div>
                            <div class="opt-name">Sunflowers</div>
                            <div class="opt-price">₹799</div>
                        </label>
                        <label class="flower-option" data-name="Mixed Seasonal" data-price="949">
                            <div class="opt-emoji">💐</div>
                            <div class="opt-name">Mixed Seasonal</div>
                            <div class="opt-price">₹949</div>
                        </label>
                        <label class="flower-option" data-name="Orchids" data-price="1199">
                            <div class="opt-emoji">🪻</div>
                            <div class="opt-name">Orchids</div>
                            <div class="opt-price">₹1,199</div>
                        </label>
                    </div>
                </div>

                <!-- ② Bouquet Size -->
                <div class="cust-step">
                    <div class="step-header">
                        <span class="step-num">2</span>
                        <h2 class="step-title">Select Bouquet Size</h2>
                    </div>
                    <div class="option-grid option-grid--3">
                        <label class="size-option" data-name="Small (5 stems)" data-price="0">
                            <div class="opt-emoji">🌱</div>
                            <div class="opt-name">Small</div>
                            <div class="opt-meta">5 stems</div>
                            <div class="opt-extra">+₹0</div>
                        </label>
                        <label class="size-option" data-name="Medium (10 stems)" data-price="150">
                            <div class="opt-emoji">🌿</div>
                            <div class="opt-name">Medium</div>
                            <div class="opt-meta">10 stems</div>
                            <div class="opt-extra">+₹150</div>
                        </label>
                        <label class="size-option" data-name="Large (20 stems)" data-price="350">
                            <div class="opt-emoji">🌳</div>
                            <div class="opt-name">Large</div>
                            <div class="opt-meta">20 stems</div>
                            <div class="opt-extra">+₹350</div>
                        </label>
                    </div>
                </div>

                <!-- ③ Wrapping Style -->
                <div class="cust-step">
                    <div class="step-header">
                        <span class="step-num">3</span>
                        <h2 class="step-title">Choose Wrapping Style</h2>
                    </div>
                    <div class="option-grid option-grid--3">
                        <label class="wrap-option" data-name="Classic Paper" data-price="0">
                            <div class="opt-emoji">📄</div>
                            <div class="opt-name">Classic Paper</div>
                            <div class="opt-extra">+₹0</div>
                        </label>
                        <label class="wrap-option" data-name="Luxury Jute" data-price="80">
                            <div class="opt-emoji">🎋</div>
                            <div class="opt-name">Luxury Jute</div>
                            <div class="opt-extra">+₹80</div>
                        </label>
                        <label class="wrap-option" data-name="Glass Vase" data-price="200">
                            <div class="opt-emoji">🫙</div>
                            <div class="opt-name">Glass Vase</div>
                            <div class="opt-extra">+₹200</div>
                        </label>
                    </div>
                </div>

                <!-- ④ Add Extras (optional) -->
                <div class="cust-step">
                    <div class="step-header">
                        <span class="step-num">4</span>
                        <h2 class="step-title">
                            Add Extras
                            <span class="step-optional">(optional)</span>
                        </h2>
                    </div>
                    <div class="extras-list">
                        <label class="extra-option" id="extra-ribbon">
                            <input type="checkbox" name="ribbon" id="ribbon" class="extra-cb">
                            <span class="extra-emoji">🎀</span>
                            <div class="extra-info">
                                <div class="extra-name">Ribbon Wrap</div>
                                <div class="extra-desc">Elegant satin ribbon finish</div>
                            </div>
                            <span class="extra-price">+₹50</span>
                        </label>
                        <label class="extra-option" id="extra-glitter">
                            <input type="checkbox" name="glitter" id="glitter" class="extra-cb">
                            <span class="extra-emoji">✨</span>
                            <div class="extra-info">
                                <div class="extra-name">Glitter Dust</div>
                                <div class="extra-desc">Sparkling shimmer effect</div>
                            </div>
                            <span class="extra-price">+₹30</span>
                        </label>
                        <label class="extra-option" id="extra-scent">
                            <input type="checkbox" name="scent" id="scent" class="extra-cb">
                            <span class="extra-emoji">🌿</span>
                            <div class="extra-info">
                                <div class="extra-name">Scented Spritz</div>
                                <div class="extra-desc">Light floral fragrance spray</div>
                            </div>
                            <span class="extra-price">+₹20</span>
                        </label>
                        <label class="extra-option" id="extra-gcard">
                            <input type="checkbox" name="gcard" id="gcard" class="extra-cb">
                            <span class="extra-emoji">💌</span>
                            <div class="extra-info">
                                <div class="extra-name">Greeting Card</div>
                                <div class="extra-desc">Handwritten message included</div>
                            </div>
                            <span class="extra-price">+₹40</span>
                        </label>
                    </div>
                </div>

                <!-- ⑤ Add Chocolates (optional) -->
                <div class="cust-step">
                    <div class="step-header">
                        <span class="step-num">5</span>
                        <h2 class="step-title">
                            Add Chocolates
                            <span class="step-optional">(optional)</span>
                        </h2>
                    </div>
                    <div class="option-grid option-grid--2">
                        <label class="choc-option choc-option--none selected" data-name="None" data-price="0">
                            <span class="choc-emoji">🚫</span>
                            <div class="choc-name">No Chocolate</div>
                            <div class="choc-price">+₹0</div>
                        </label>
                        <label class="choc-option" data-name="Cadbury Dairy Milk" data-price="149">
                            <span class="choc-emoji">🍫</span>
                            <div class="choc-name">Cadbury Dairy Milk</div>
                            <div class="choc-sub">Classic milk chocolate</div>
                            <div class="choc-price">+₹149</div>
                        </label>
                        <label class="choc-option" data-name="Ferrero Rocher Box" data-price="399">
                            <span class="choc-emoji">🍫</span>
                            <div class="choc-name">Ferrero Rocher Box</div>
                            <div class="choc-sub">Box of 16 pralines</div>
                            <div class="choc-price">+₹399</div>
                        </label>
                        <label class="choc-option" data-name="Toblerone Twin Pack" data-price="799">
                            <span class="choc-emoji">🍫</span>
                            <div class="choc-name">Toblerone Twin Pack</div>
                            <div class="choc-sub">Milk &amp; dark combo</div>
                            <div class="choc-price">+₹799</div>
                        </label>
                    </div>
                </div>

            </div><!-- /.customize-left -->

            <!-- ═══════════════════
                 RIGHT — preview
            ════════════════════ -->
            <div class="customize-right">
                <div class="preview-card">

                    <h3 class="preview-title">🌸 Your Bouquet Preview</h3>

                    <!-- Emoji preview box -->
                    <div class="preview-img-wrap">
                        <div class="preview-emoji-box" id="preview-emoji">💐</div>
                    </div>

                    <!-- Name / size / wrap lines -->
                    <div class="preview-info-box">
                        <div class="preview-name"  id="preview-name">Select your flowers</div>
                        <div class="preview-line"  id="preview-size">— choose size —</div>
                        <div class="preview-line"  id="preview-wrap">— choose wrapping —</div>
                    </div>

                    <!-- Price breakdown -->
                    <div class="preview-breakdown">
                        <div class="breakdown-label">PRICE BREAKDOWN</div>
                        <div class="breakdown-row">
                            <span>Base flowers</span>
                            <span id="breakdown-base">₹0</span>
                        </div>
                        <div class="breakdown-row">
                            <span>Size</span>
                            <span id="breakdown-size">₹0</span>
                        </div>
                        <div class="breakdown-row">
                            <span>Wrapping</span>
                            <span id="breakdown-wrap">₹0</span>
                        </div>
                        <div class="breakdown-row">
                            <span>Extras</span>
                            <span id="breakdown-extras">₹0</span>
                        </div>
                        <div class="breakdown-row">
                            <span>🍫 Chocolate</span>
                            <span id="breakdown-choc">₹0</span>
                        </div>
                        <div class="breakdown-total">
                            <span>Total</span>
                            <span id="custom-price-display" class="total-price">₹0</span>
                        </div>
                    </div>

                    <!-- Selection tags -->
                    <div class="preview-tags" id="preview-tags"></div>

                    <!-- CTA -->
                    <button type="submit" class="add-custom-btn" id="addCustomBtn">
                        🛒 Add Custom Bouquet to Cart
                    </button>
                    <p class="add-custom-hint">Select all options above to proceed</p>

                </div><!-- /.preview-card -->
            </div><!-- /.customize-right -->

        </div><!-- /.customize-layout -->
    </form>

</div><!-- /.customize-page -->

<!-- Toast -->
<div class="shop-toast" id="custToast" role="status" aria-live="polite">
    <div class="toast-icon">🌸</div>
    <div>
        <div class="toast-title" id="custToastTitle">Please complete your selection</div>
        <div class="toast-sub"   id="custToastSub"></div>
    </div>
</div>

<script src="/bloom-aura/assets/js/customize.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>