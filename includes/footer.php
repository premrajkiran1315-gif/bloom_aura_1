<?php
/**
 * bloom-aura/includes/footer.php
 * Site-wide footer. Closes <main> and <body> opened in header.php.
 */
?>
</main><!-- /#main-content -->

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <span class="logo-icon">🌸</span>
            <span class="logo-text">Bloom <em>Aura</em></span>
            <p>Fresh flowers, delivered with love.</p>
        </div>
        <div class="footer-links">
            <h4>Shop</h4>
            <ul>
                <li><a href="/pages/shop.php">All Products</a></li>
                <li><a href="/pages/shop.php?cat=bouquets">Bouquets</a></li>
                <li><a href="/pages/shop.php?cat=hampers">Hampers</a></li>
                <li><a href="/pages/shop.php?cat=chocolates">Chocolates</a></li>
            </ul>
        </div>
        <div class="footer-links">
            <h4>Account</h4>
            <ul>
                <li><a href="/pages/profile.php">My Profile</a></li>
                <li><a href="/pages/order-history.php">Order History</a></li>
                <li><a href="/pages/wishlist.php">Wishlist</a></li>
            </ul>
        </div>
        <div class="footer-links">
            <h4>Help</h4>
            <ul>
                <li><a href="#">Delivery Info</a></li>
                <li><a href="#">Returns Policy</a></li>
                <li><a href="#">Contact Us</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Bloom Aura. All rights reserved.</p>
    </div>
</footer>

<!-- Global JS -->
<script src="/assets/js/main.js"></script>
</body>
</html>
