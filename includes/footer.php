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

        <!-- Brand column -->
        <div class="footer-brand">
            <a href="/bloom-aura/" class="footer-logo">
                🌸 <em>Bloom</em>&thinsp;Aura
            </a>
            <p class="footer-tagline">
                Hand-crafted bouquets, hampers &amp; gifts,<br>
                delivered with love. 🌹
            </p>
            <!-- Social icons -->
            <div class="footer-socials">
                <a href="#" aria-label="Instagram" class="social-icon">
                    <i class="fa-brands fa-instagram"></i>
                </a>
                <a href="#" aria-label="Facebook" class="social-icon">
                    <i class="fa-brands fa-facebook-f"></i>
                </a>
                <a href="#" aria-label="WhatsApp" class="social-icon">
                    <i class="fa-brands fa-whatsapp"></i>
                </a>
                <a href="#" aria-label="Pinterest" class="social-icon">
                    <i class="fa-brands fa-pinterest-p"></i>
                </a>
            </div>
        </div>

        <!-- Shop links -->
        <div class="footer-col">
            <h4 class="footer-heading">Shop</h4>
            <ul class="footer-links">
                <li><a href="/bloom-aura/pages/shop.php">All Products</a></li>
                <li><a href="/bloom-aura/pages/shop.php?cat=bouquets">💐 Bouquets</a></li>
                <li><a href="/bloom-aura/pages/shop.php?cat=hampers">🎁 Hampers</a></li>
                <li><a href="/bloom-aura/pages/shop.php?cat=chocolates">🍫 Chocolates</a></li>
                <li><a href="/bloom-aura/pages/shop.php?cat=perfumes">🌹 Perfumes</a></li>
                <li><a href="/bloom-aura/pages/shop.php?cat=plants">🪴 Plants</a></li>
            </ul>
        </div>

        <!-- Account links -->
        <div class="footer-col">
            <h4 class="footer-heading">Account</h4>
            <ul class="footer-links">
                <li><a href="/bloom-aura/pages/login.php">Login</a></li>
                <li><a href="/bloom-aura/pages/register.php">Create Account</a></li>
                <li><a href="/bloom-aura/pages/profile.php">My Profile</a></li>
                <li><a href="/bloom-aura/pages/order-history.php">My Orders</a></li>
                <li><a href="/bloom-aura/pages/wishlist.php">Wishlist</a></li>
            </ul>
        </div>

        <!-- Help links -->
        <div class="footer-col">
            <h4 class="footer-heading">Help</h4>
            <ul class="footer-links">
                <li><a href="#">🚚 Delivery Info</a></li>
                <li><a href="#">↩️ Returns Policy</a></li>
                <li><a href="#">📞 Contact Us</a></li>
                <li><a href="#">❓ FAQs</a></li>
                <li><a href="/bloom-aura/admin/login.php">Admin Panel</a></li>
            </ul>
        </div>

    </div>

    <!-- Trust bar -->
    <div class="footer-trust-bar">
        <span>✅ 500+ Happy Customers</span>
        <span>🚚 Same-Day Delivery</span>
        <span>🌸 Fresh Flowers Daily</span>
        <span>⭐ 4.8 / 5 Rating</span>
    </div>

    <!-- Bottom bar -->
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Bloom Aura. All rights reserved. Made with 💗 in India.</p>
    </div>

</footer>

<!-- ── Back to top + mobile nav JS ── -->
<script>
(function () {
    // Flash auto-dismiss (backup — header.php also does this)
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

</body>
</html>