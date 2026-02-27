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

// Current page for active nav
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>

<!-- ── SITE HEADER ── -->
<header class="site-header" role="banner">
    <div class="header-inner">

        <!-- Logo -->
        <a href="/bloom-aura/" class="logo" aria-label="Bloom Aura — Home">
            🌸 <em>Bloom</em>&nbsp;Aura
        </a>

        <!-- Main Nav -->
        <nav class="main-nav" aria-label="Main navigation">
            <a href="/bloom-aura/" class="nav-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">Home</a>
            <a href="/bloom-aura/pages/shop.php" class="nav-link <?= ($currentPage === 'shop.php') ? 'active' : '' ?>">Shop</a>
        </nav>

        <!-- Header Actions -->
        <div class="header-actions">

            <!-- Wishlist -->
            <?php if ($isLoggedIn): ?>
            <a href="/bloom-aura/pages/wishlist.php" class="icon-btn" aria-label="Wishlist">
                <i class="fa-regular fa-heart"></i>
            </a>
            <?php endif; ?>

            <!-- Cart -->
            <a href="/bloom-aura/pages/cart.php" class="icon-btn" aria-label="Shopping cart (<?= $cartCount ?> items)">
                <i class="fa-solid fa-basket-shopping"></i>
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge" aria-hidden="true"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>

            <!-- User Menu -->
            <?php if ($isLoggedIn): ?>
                <div class="user-menu-wrap">
                    <button class="user-btn" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-circle-user"></i>
                        <span class="user-name-label"><?= $userName ?></span>
                        <i class="fa-solid fa-chevron-down" style="font-size:.7rem;"></i>
                    </button>
                    <div class="user-dropdown" role="menu">
                        <a href="/bloom-aura/pages/profile.php" role="menuitem">
                            <i class="fa-solid fa-user"></i> My Profile
                        </a>
                        <a href="/bloom-aura/pages/order-history.php" role="menuitem">
                            <i class="fa-solid fa-clock-rotate-left"></i> My Orders
                        </a>
                        <a href="/bloom-aura/pages/wishlist.php" role="menuitem">
                            <i class="fa-regular fa-heart"></i> Wishlist
                        </a>
                        <hr class="dropdown-divider">
                        <a href="/bloom-aura/pages/logout.php" class="logout-link" role="menuitem">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/bloom-aura/pages/login.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </a>
            <?php endif; ?>

            <!-- Hamburger (mobile) -->
            <button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- ── FLASH MESSAGES ── -->
<?php if (!empty($flashMessages)): ?>
    <div class="flash-container" role="alert" aria-live="polite">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Page content renders here -->
