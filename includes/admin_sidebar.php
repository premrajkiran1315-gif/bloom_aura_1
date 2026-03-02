<?php
/**
 * bloom-aura/includes/admin_sidebar.php
 * Admin panel sidebar navigation.
 */

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" role="navigation" aria-label="Admin navigation">

    <div class="admin-logo-wrap">
        <span class="admin-logo-tag">Admin</span>
        <span class="admin-logo-name">🌸 Bloom Aura</span>
        <span class="admin-logo-sub">Management Panel</span>
    </div>

    <nav>
        <span class="admin-nav-label">Main</span>

        <a href="/bloom-aura/admin/dashboard.php"
           class="admin-nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-gauge"></i></span>
            Dashboard
        </a>

        <a href="/bloom-aura/admin/orders.php"
           class="admin-nav-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-box"></i></span>
            Orders
        </a>

        <a href="/bloom-aura/admin/products.php"
           class="admin-nav-link <?= $currentPage === 'products.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-store"></i></span>
            Products
        </a>

        <a href="/bloom-aura/admin/categories.php"
           class="admin-nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-tags"></i></span>
            Categories
        </a>

        <span class="admin-nav-label">Users</span>

        <a href="/bloom-aura/admin/users.php"
           class="admin-nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
            Customers
        </a>

        <span class="admin-nav-label">Content</span>

        <a href="/bloom-aura/admin/reviews.php"
           class="admin-nav-link <?= $currentPage === 'reviews.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-star"></i></span>
            Reviews
        </a>
    </nav>

    <div class="admin-sidebar-footer">
        <div class="admin-user-card">
            <div class="admin-avatar">👑</div>
            <div>
                <div class="admin-user-name">
                    <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="admin-user-role">Administrator</div>
            </div>
        </div>
        <a href="/bloom-aura/admin/logout.php" class="admin-logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>

</aside>