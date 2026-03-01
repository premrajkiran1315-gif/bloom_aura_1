<?php
/**
 * bloom-aura-1/includes/header.php
 * ─────────────────────────────────────────────────────────────
 * Global site header.
 *
 * HOW PER-PAGE CSS WORKS:
 *   Each page sets  $pageCss = 'shop';  (or 'cart', 'product', etc.)
 *   BEFORE require_once-ing this file.
 *   This header then loads:
 *     1. base.css        — always (reset, variables, shared components)
 *     2. {page}.css      — only the CSS for this specific page
 *     3. responsive.css  — always last (breakpoints)
 *
 *   Example — pages/shop.php:
 *     $pageCss   = 'shop';        // → loads assets/css/shop.css
 *     $pageTitle = 'Shop';
 *     require_once __DIR__ . '/../includes/header.php';
 *
 * AVAILABLE PAGE CSS FILES:
 *   home              → index.php
 *   shop              → pages/shop.php
 *   product           → pages/product.php
 *   cart              → pages/cart.php
 *   checkout          → pages/checkout.php
 *   order-confirmation → pages/order-confirmation.php
 *   order-history     → pages/order-history.php
 *   profile           → pages/profile.php
 *   wishlist          → pages/wishlist.php
 *   auth              → pages/login.php + pages/register.php
 * ─────────────────────────────────────────────────────────────
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['user_id']);
$userName   = htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8');

// Cart count
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

$currentPage = basename($_SERVER['PHP_SELF']);

// Sanitise the page CSS slug — only allow safe file names
$allowedPageCss = [
    'home', 'shop', 'product', 'cart', 'checkout',
    'order-confirmation', 'order-history', 'profile', 'wishlist', 'auth',
];
$safeCssSlug = in_array($pageCss ?? '', $allowedPageCss, true) ? ($pageCss ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Bloom Aura', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="Bloom Aura — Fresh flowers, bouquets &amp; gifts delivered with love.">

    <!-- CSRF token for JS AJAX cart requests -->
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Inter:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <!-- Font Awesome icons -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- ① Global base (variables, reset, shared components) — ALWAYS -->
    <link rel="stylesheet" href="/bloom-aura/assets/css/base.css">

    <!-- ② Page-specific CSS — only for this page -->
    <?php if ($safeCssSlug): ?>
    <link rel="stylesheet" href="/bloom-aura/assets/css/<?= $safeCssSlug ?>.css">
    <?php endif; ?>

    <!-- ③ Responsive breakpoints — ALWAYS last -->
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

        <!-- Main Nav -->
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

            <!-- Cart icon -->
            <a href="/bloom-aura/pages/cart.php"
               class="icon-btn"
               aria-label="Cart, <?= $cartCount ?> item<?= $cartCount !== 1 ? 's' : '' ?>">
                🛒
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge" aria-live="polite"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>

            <!-- User menu / login link -->
            <?php if ($isLoggedIn): ?>
                <div class="user-menu-wrap">
                    <button class="user-menu-btn" aria-haspopup="true" aria-expanded="false">
                        👤 <span class="user-name-label"><?= $userName ?></span>
                        <i class="fa-solid fa-chevron-down" style="font-size:.65rem"></i>
                    </button>
                    <div class="user-dropdown" role="menu">
                        <a href="/bloom-aura/pages/profile.php"      class="dropdown-item" role="menuitem">👤 My Profile</a>
                        <a href="/bloom-aura/pages/order-history.php" class="dropdown-item" role="menuitem">📦 Orders</a>
                        <a href="/bloom-aura/pages/wishlist.php"      class="dropdown-item" role="menuitem">🤍 Wishlist</a>
                        <hr class="dropdown-divider">
                        <a href="/bloom-aura/pages/logout.php"        class="dropdown-item danger" role="menuitem">🚪 Sign Out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/bloom-aura/pages/login.php" class="btn btn-primary btn-sm">Login</a>
            <?php endif; ?>

            <!-- Hamburger (mobile) -->
            <button class="hamburger"
                    id="hamburger"
                    aria-label="Open navigation menu"
                    aria-controls="mainNav"
                    aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Flash messages -->
<?php foreach ($flashMessages as $flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?>" role="alert">
        <?= htmlspecialchars($flash['msg'] ?? '', ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endforeach; ?>

<!-- Cart add toast (populated by cart.js) -->
<div class="shop-toast" id="shopToast" role="status" aria-live="polite">
    <div class="toast-icon">🌸</div>
    <div>
        <div class="toast-title" id="toastTitle">Added to cart!</div>
        <div class="toast-sub"   id="toastSub">Your bouquet is saved</div>
    </div>
    <div class="toast-price" id="toastPrice"></div>
</div>