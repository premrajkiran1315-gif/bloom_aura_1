<?php
/**
 * bloom-aura/includes/header.php
 * Global HTML head + sticky navigation.
 * Requires session to already be started before including.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cart item count from session
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$loggedIn  = !empty($_SESSION['user_id']);
$userName  = $loggedIn ? htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bloom Aura — Fresh, beautiful flower bouquets delivered to your door.">

    <title><?= htmlspecialchars($pageTitle ?? 'Bloom Aura', ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Font Awesome (icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Global stylesheet -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body>

<!-- ── STICKY HEADER ── -->
<header class="site-header">
    <div class="header-inner">

        <!-- Logo -->
        <a href="/" class="logo" aria-label="Bloom Aura Home">
            <span class="logo-icon">🌸</span>
            <span class="logo-text">Bloom <em>Aura</em></span>
        </a>

        <!-- Main navigation -->
        <nav class="main-nav" aria-label="Main navigation">
            <a href="/pages/shop.php" class="nav-link">Shop</a>
            <a href="/pages/shop.php?cat=bouquets" class="nav-link">Bouquets</a>
            <a href="/pages/shop.php?cat=hampers" class="nav-link">Hampers</a>
            <?php if ($loggedIn): ?>
                <a href="/pages/wishlist.php" class="nav-link">Wishlist</a>
            <?php endif; ?>
        </nav>

        <!-- Header actions -->
        <div class="header-actions">
            <!-- Search toggle -->
            <button class="icon-btn" id="search-toggle-btn" aria-label="Toggle search" aria-expanded="false">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>

            <!-- Cart -->
            <a href="/pages/cart.php" class="icon-btn cart-btn" aria-label="View cart (<?= $cartCount ?> items)">
                <i class="fa-solid fa-basket-shopping"></i>
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge" aria-live="polite"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>

            <!-- User menu -->
            <?php if ($loggedIn): ?>
                <div class="user-menu-wrap">
                    <button class="user-btn" aria-label="Account menu" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-circle-user"></i>
                        <span class="user-name-label"><?= $userName ?></span>
                        <i class="fa-solid fa-chevron-down fa-xs"></i>
                    </button>
                    <div class="user-dropdown" role="menu">
                        <a href="/pages/profile.php" role="menuitem"><i class="fa-solid fa-user fa-fw"></i> My Profile</a>
                        <a href="/pages/order-history.php" role="menuitem"><i class="fa-solid fa-clock-rotate-left fa-fw"></i> Orders</a>
                        <a href="/pages/wishlist.php" role="menuitem"><i class="fa-solid fa-heart fa-fw"></i> Wishlist</a>
                        <hr class="dropdown-divider">
                        <a href="/pages/logout.php" role="menuitem" class="logout-link"><i class="fa-solid fa-right-from-bracket fa-fw"></i> Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/pages/login.php" class="btn btn-outline btn-sm">Login</a>
                <a href="/pages/register.php" class="btn btn-primary btn-sm">Sign Up</a>
            <?php endif; ?>

            <!-- Mobile hamburger -->
            <button class="hamburger" id="hamburger-btn" aria-label="Open mobile menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <!-- Search bar (hidden by default, toggled by JS) -->
    <div class="search-bar-wrap" id="search-bar" hidden>
        <form action="/pages/shop.php" method="GET" role="search" class="search-form">
            <label for="global-search" class="sr-only">Search bouquets</label>
            <input type="search" id="global-search" name="q"
                   placeholder="Search bouquets, hampers, occasions…"
                   value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   autocomplete="off">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>
</header>

<!-- Mobile nav drawer -->
<div class="mobile-nav-overlay" id="mobile-nav-overlay" hidden>
    <nav class="mobile-nav" aria-label="Mobile navigation">
        <a href="/pages/shop.php">Shop</a>
        <a href="/pages/shop.php?cat=bouquets">Bouquets</a>
        <a href="/pages/shop.php?cat=hampers">Hampers</a>
        <?php if ($loggedIn): ?>
            <a href="/pages/wishlist.php">Wishlist</a>
            <a href="/pages/profile.php">My Profile</a>
            <a href="/pages/order-history.php">My Orders</a>
            <a href="/pages/logout.php">Logout</a>
        <?php else: ?>
            <a href="/pages/login.php">Login</a>
            <a href="/pages/register.php">Sign Up</a>
        <?php endif; ?>
    </nav>
</div>

<!-- Flash message (success / error / info) -->
<?php if (!empty($_SESSION['flash'])): ?>
    <?php foreach ($_SESSION['flash'] as $flash): ?>
        <div class="flash-msg flash-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
            <button class="flash-close" aria-label="Dismiss">&times;</button>
        </div>
    <?php endforeach; ?>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<main id="main-content">
