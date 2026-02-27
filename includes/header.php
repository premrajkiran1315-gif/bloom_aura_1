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

    <style>
        /* ── Header & Nav — matches reference pill-button style ── */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #fff;
            border-bottom: 1px solid #fce4ec;
            box-shadow: 0 2px 12px rgba(214,51,132,.08);
            height: 68px;
        }
        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.25rem;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .logo {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.45rem;
            font-weight: 700;
            color: #d63384;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .35rem;
            flex-shrink: 0;
            letter-spacing: -.3px;
        }
        .logo:hover { color: #ad1457; text-decoration: none; }

        /* Pill nav buttons — matches reference */
        .main-nav {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .nav-link {
            background: white;
            border: 1.5px solid #d63384;
            color: #d63384;
            padding: 7px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: .82rem;
            transition: all .25s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            white-space: nowrap;
        }
        .nav-link:hover,
        .nav-link.active {
            background: #d63384;
            color: #fff !important;
            text-decoration: none;
        }

        .header-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        /* Icon buttons */
        .icon-btn {
            background: none;
            border: none;
            font-size: 1.15rem;
            color: #555;
            padding: .45rem .55rem;
            border-radius: 50%;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .2s, color .2s;
            cursor: pointer;
            text-decoration: none;
        }
        .icon-btn:hover { background: #fce4ec; color: #d63384; text-decoration: none; }
        .cart-badge {
            position: absolute;
            top: -3px; right: -3px;
            background: #d63384;
            color: #fff;
            font-size: .6rem;
            font-weight: 700;
            min-width: 17px;
            height: 17px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 3px;
            line-height: 1;
        }

        /* User dropdown */
        .user-menu-wrap { position: relative; }
        .user-btn {
            display: flex;
            align-items: center;
            gap: .4rem;
            background: none;
            border: 1.5px solid #fce4ec;
            border-radius: 20px;
            padding: .4rem .85rem;
            font-size: .82rem;
            font-weight: 600;
            color: #444;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            font-family: inherit;
        }
        .user-btn:hover { border-color: #d63384; background: #fff0f5; }
        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border: 1px solid #fce4ec;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(214,51,132,.12);
            min-width: 190px;
            overflow: hidden;
            display: none;
            z-index: 300;
        }
        .user-menu-wrap:hover .user-dropdown,
        .user-menu-wrap:focus-within .user-dropdown { display: block; }
        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .7rem 1rem;
            font-size: .85rem;
            color: #444;
            transition: background .15s;
            text-decoration: none;
        }
        .user-dropdown a:hover { background: #fff0f5; text-decoration: none; }
        .logout-link { color: #e53935 !important; }
        .dropdown-divider { border: none; border-top: 1px solid #fce4ec; margin: .2rem 0; }

        /* Hamburger mobile */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: none;
            border: none;
            padding: .4rem;
            cursor: pointer;
        }
        .hamburger span {
            display: block;
            width: 22px;
            height: 2px;
            background: #444;
            border-radius: 2px;
        }

        /* Flash toast messages */
        .flash-container {
            position: fixed;
            top: 76px;
            right: 1.25rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: .5rem;
            max-width: 340px;
            pointer-events: none;
        }
        .flash-container .alert {
            padding: .85rem 1.1rem;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 500;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            display: flex;
            align-items: center;
            gap: .5rem;
            animation: slideIn .3s ease;
            pointer-events: auto;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(30px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-info    { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .alert-warning { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

        /* Mobile overrides */
        @media (max-width: 767px) {
            .main-nav {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 68px;
                left: 0; right: 0;
                background: #fff;
                padding: 1rem 1.25rem;
                border-bottom: 2px solid #fce4ec;
                z-index: 200;
                gap: .5rem;
            }
            .main-nav.open { display: flex; }
            .hamburger { display: flex; }
            .user-name-label { display: none; }
        }
        @media (min-width: 768px) {
            .hamburger { display: none !important; }
        }
    </style>
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
                        <i class="fa-solid fa-circle-user" style="color:#d63384; font-size:1rem;"></i>
                        <span class="user-name-label"><?= $userName ?></span>
                        <i class="fa-solid fa-chevron-down" style="font-size:.6rem; color:#aaa;"></i>
                    </button>
                    <div class="user-dropdown" role="menu">
                        <a href="/bloom-aura/pages/profile.php" role="menuitem">
                            <i class="fa-solid fa-user" style="color:#d63384; width:16px;"></i> My Profile
                        </a>
                        <a href="/bloom-aura/pages/order-history.php" role="menuitem">
                            <i class="fa-solid fa-clock-rotate-left" style="color:#d63384; width:16px;"></i> My Orders
                        </a>
                        <a href="/bloom-aura/pages/wishlist.php" role="menuitem">
                            <i class="fa-regular fa-heart" style="color:#d63384; width:16px;"></i> Wishlist
                        </a>
                        <hr class="dropdown-divider">
                        <a href="/bloom-aura/pages/logout.php" class="logout-link" role="menuitem">
                            <i class="fa-solid fa-right-from-bracket" style="width:16px;"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Login button -->
                <a href="/bloom-aura/pages/login.php"
                   class="nav-link"
                   style="background:#d63384; color:#fff; border-color:#d63384;">
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