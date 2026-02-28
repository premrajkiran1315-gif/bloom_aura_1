<?php
/**
 * bloom-aura/includes/header.php
 * Global site header — nav, cart icon, user menu.
 * Must be included AFTER session_start() and $pageTitle is set.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8');

// Cart count from session
$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['qty'] ?? 1);
    }
}

// Flash messages
$flashMessages = [];
if (!empty($_SESSION['flash'])) {
    $flashMessages = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// Current page for active nav highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Bloom Aura', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="Bloom Aura — Fresh flowers, bouquets & gifts delivered with love.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- ✅ FIXED: correct subfolder paths -->
    <link rel="stylesheet" href="/bloom-aura/assets/css/style.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/responsive.css">


</head>
<body>

<!-- ── SITE HEADER ── -->
<header class="site-header" role="banner">
    <div class="header-inner">

        <!-- Logo -->
        <a href="/bloom-aura/" class="logo" aria-label="Bloom Aura Home">
            🌸 <em>Bloom</em>&thinsp;Aura
        </a>

        <!-- Main Nav — pill buttons like reference -->
        <nav class="main-nav" id="mainNav" aria-label="Main navigation">
            <a href="/bloom-aura/"
               class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                🏠 Home
            </a>
            <a href="/bloom-aura/pages/shop.php"
               class="nav-link <?= $currentPage === 'shop.php' ? 'active' : '' ?>">
                🌸 Shop
            </a>
            <?php if ($isLoggedIn): ?>
            <a href="/bloom-aura/pages/wishlist.php"
               class="nav-link <?= $currentPage === 'wishlist.php' ? 'active' : '' ?>">
                🤍 Wishlist
            </a>
            <a href="/bloom-aura/pages/order-history.php"
               class="nav-link <?= $currentPage === 'order-history.php' ? 'active' : '' ?>">
                📦 Orders
            </a>
            <?php endif; ?>
        </nav>

        <!-- Header Actions -->
        <div class="header-actions">

            <!-- Cart icon with badge -->
            <a href="/bloom-aura/pages/cart.php"
               class="icon-btn"
               aria-label="Cart, <?= $cartCount ?> item<?= $cartCount !== 1 ? 's' : '' ?>">
                <i class="fa-solid fa-basket-shopping"></i>
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge" aria-hidden="true"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>

            <?php if ($isLoggedIn): ?>
                <!-- Logged-in user dropdown -->
                <div class="user-menu-wrap">
                    <button class="user-btn" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-circle-user" class="user-icon"></i>
                        <span class="user-name-label"><?= $userName ?></span>
                        <i class="fa-solid fa-chevron-down" class="user-chevron"></i>
                    </button>
                    <div class="user-dropdown" role="menu">
                        <a href="/bloom-aura/pages/profile.php" role="menuitem">
                            <i class="fa-solid fa-user" class="dropdown-icon"></i> My Profile
                        </a>
                        <a href="/bloom-aura/pages/order-history.php" role="menuitem">
                            <i class="fa-solid fa-clock-rotate-left" class="dropdown-icon"></i> My Orders
                        </a>
                        <a href="/bloom-aura/pages/wishlist.php" role="menuitem">
                            <i class="fa-regular fa-heart" class="dropdown-icon"></i> Wishlist
                        </a>
                        <hr class="dropdown-divider">
                        <a href="/bloom-aura/pages/logout.php" class="logout-link" role="menuitem">
                            <i class="fa-solid fa-right-from-bracket" class="logout-icon"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Login button -->
                <a href="/bloom-aura/pages/login.php"
                   class="nav-link nav-link-login">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </a>
            <?php endif; ?>

            <!-- Hamburger (mobile only) -->
            <button class="hamburger" id="hamburgerBtn"
                    aria-label="Toggle menu"
                    aria-expanded="false"
                    aria-controls="mainNav">
                <span></span><span></span><span></span>
            </button>
        </div>

    </div>
</header>

<!-- ── FLASH TOAST MESSAGES ── -->
<?php if (!empty($flashMessages)): ?>
    <div class="flash-container" role="alert" aria-live="polite">
        <?php foreach ($flashMessages as $flash): ?>
            <?php
                $icons = ['success' => '✅', 'error' => '❌', 'info' => 'ℹ️', 'warning' => '⚠️'];
                $icon  = $icons[$flash['type']] ?? 'ℹ️';
            ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= $icon ?>
                <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ── JS: mobile nav + flash auto-dismiss ── -->
<script>
(function () {
    // Mobile hamburger toggle
    var btn = document.getElementById('hamburgerBtn');
    var nav = document.getElementById('mainNav');
    if (btn && nav) {
        btn.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Auto-dismiss flash toasts after 4s
    document.querySelectorAll('.flash-container .alert').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .4s, transform .4s';
            el.style.opacity = '0';
            el.style.transform = 'translateX(30px)';
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });
})();
</script>