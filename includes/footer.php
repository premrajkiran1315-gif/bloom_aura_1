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

<style>
/* ── Footer styles ── */
.site-footer {
    background: linear-gradient(160deg, #130d1a 0%, #1e0c28 60%, #120818 100%);
    color: rgba(255,255,255,.65);
    padding: 3.5rem 1.25rem 0;
    margin-top: auto;
}

.footer-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 2.5rem;
    padding-bottom: 2.5rem;
    border-bottom: 1px solid rgba(255,255,255,.07);
}

/* Brand */
.footer-logo {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #e8a4b8;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    margin-bottom: .85rem;
}
.footer-logo:hover { color: #ff79b0; text-decoration: none; }
.footer-logo em { font-style: italic; }

.footer-tagline {
    font-size: .84rem;
    color: rgba(255,255,255,.38);
    line-height: 1.65;
    margin-bottom: 1.25rem;
}

/* Social icons */
.footer-socials {
    display: flex;
    gap: .6rem;
}
.social-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1.5px solid rgba(255,255,255,.12);
    background: rgba(255,255,255,.05);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,.5);
    font-size: .9rem;
    text-decoration: none;
    transition: all .25s;
}
.social-icon:hover {
    background: #d63384;
    border-color: #d63384;
    color: #fff;
    transform: translateY(-2px);
    text-decoration: none;
}

/* Columns */
.footer-col { }
.footer-heading {
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    margin-bottom: 1rem;
    font-family: 'Inter', sans-serif;
}
.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: .55rem;
}
.footer-links a {
    color: rgba(255,255,255,.48);
    font-size: .84rem;
    text-decoration: none;
    transition: color .2s, padding-left .2s;
    display: inline-block;
}
.footer-links a:hover {
    color: #e8a4b8;
    padding-left: 4px;
    text-decoration: none;
}

/* Trust bar */
.footer-trust-bar {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.25rem 0;
    display: flex;
    justify-content: center;
    gap: 2.5rem;
    flex-wrap: wrap;
    font-size: .8rem;
    font-weight: 600;
    color: rgba(255,255,255,.35);
    border-bottom: 1px solid rgba(255,255,255,.07);
}
.footer-trust-bar span {
    display: flex;
    align-items: center;
    gap: .3rem;
}

/* Bottom bar */
.footer-bottom {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.1rem 0 1.4rem;
    text-align: center;
    font-size: .78rem;
    color: rgba(255,255,255,.22);
}

/* Responsive */
@media (max-width: 900px) {
    .footer-inner {
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }
    .footer-brand { grid-column: 1 / -1; }
}
@media (max-width: 500px) {
    .footer-inner {
        grid-template-columns: 1fr;
    }
    .footer-trust-bar { gap: 1rem; }
}
</style>

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