<?php
/**
 * bloom-aura/includes/footer.php
 * Global site footer — closes <body> and <html>.
 * Include at the bottom of every page.
 */
?>

<!-- ── SITE FOOTER ── -->
<footer class="site-footer" role="contentinfo">
    <div class="footer-inner">

        <div class="footer-brand">
            <a href="/bloom-aura/" class="logo" style="margin-bottom:.5rem;">
                🌸 <em>Bloom</em>&nbsp;Aura
            </a>
            <p style="font-size:.85rem; color:var(--color-text-muted); margin-top:.5rem;">
                Hand-crafted bouquets & gifts, delivered with love.
            </p>
        </div>

        <div class="footer-links">
            <h4>Shop</h4>
            <ul>
                <li><a href="/bloom-aura/pages/shop.php">All Products</a></li>
                <li><a href="/bloom-aura/pages/shop.php?category=bouquets">Bouquets</a></li>
                <li><a href="/bloom-aura/pages/shop.php?category=hampers">Hampers</a></li>
                <li><a href="/bloom-aura/pages/shop.php?category=chocolates">Chocolates</a></li>
            </ul>
        </div>

        <div class="footer-links">
            <h4>Account</h4>
            <ul>
                <li><a href="/bloom-aura/pages/login.php">Login</a></li>
                <li><a href="/bloom-aura/pages/register.php">Register</a></li>
                <li><a href="/bloom-aura/pages/profile.php">My Profile</a></li>
                <li><a href="/bloom-aura/pages/order-history.php">My Orders</a></li>
            </ul>
        </div>

        <div class="footer-links">
            <h4>Help</h4>
            <ul>
                <li><a href="#">Delivery Info</a></li>
                <li><a href="#">Returns Policy</a></li>
                <li><a href="#">Contact Us</a></li>
                <li><a href="#">FAQs</a></li>
            </ul>
        </div>

    </div>

    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Bloom Aura. All rights reserved.</p>
    </div>
</footer>

<!-- ── Mobile nav toggle ── -->
<script>
(function () {
    const btn = document.getElementById('hamburgerBtn');
    const nav = document.querySelector('.main-nav');
    if (!btn || !nav) return;

    btn.addEventListener('click', function () {
        const expanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', String(!expanded));
        nav.style.display = expanded ? '' : 'flex';
        nav.style.flexDirection = 'column';
        nav.style.position = 'absolute';
        nav.style.top = 'var(--header-h)';
        nav.style.left = '0';
        nav.style.right = '0';
        nav.style.background = 'var(--color-surface)';
        nav.style.padding = '1rem';
        nav.style.borderBottom = '1px solid var(--color-border)';
        nav.style.zIndex = '99';
    });

    // Close flash messages after 4 seconds
    const flashes = document.querySelectorAll('.flash-container .alert');
    flashes.forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });
})();
</script>

</body>
</html>
