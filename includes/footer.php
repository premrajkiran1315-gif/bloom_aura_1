<?php
/**
 * bloom-aura-1/includes/footer.php
 *
 * Global site footer.
 * Chatbot is suppressed on auth pages (login, register) — no widget
 * should appear when the user is not yet in the shopping experience.
 */
?>

</main><!-- /.site-main -->

<footer class="site-footer" role="contentinfo">
    <div class="footer-inner">
        <p class="footer-copy">
            &copy; <?= date('Y') ?> Bloom Aura. All rights reserved. Made with 💗 in India.
        </p>
    </div>
</footer>

<!-- ── Back to top + mobile nav JS ── -->
<script>
(function () {
    // Flash auto-dismiss
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

<?php
/*
 * ── CHATBOT WIDGET ──────────────────────────────────────────
 * Only load on pages where the chatbot adds value.
 * Excluded: login.php, register.php — the user hasn't entered the
 * shopping experience yet, and showing a support widget on an
 * auth/dark-card page looks out of place and adds visual noise.
 *
 * $currentPage is set by header.php via basename($_SERVER['PHP_SELF'])
 * ─────────────────────────────────────────────────────────────
 */
$_chatbotExcluded = ['login.php', 'register.php'];
if (!in_array($currentPage ?? '', $_chatbotExcluded, true)):
?>
<!-- ✅ CHATBOT — CSS loads here, JS loads after all page content -->
<link rel="stylesheet" href="/bloom-aura/assets/css/chatbot.css">
<script src="/bloom-aura/assets/js/chatbot.js" defer></script>
<?php endif; ?>

</body>
</html>