/**
 * bloom-aura/assets/js/admin_reviews.js
 * ─────────────────────────────────────────────────────────────
 * Reviews page JS.
 * • Auto-dismiss flash alert messages after 4 seconds.
 * • Animates rating bar fills on page load.
 * All code inside DOMContentLoaded. No global pollution.
 * ─────────────────────────────────────────────────────────────
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  /* ── Auto-dismiss alert messages after 4 seconds ── */
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s ease, transform .4s ease';
      el.style.opacity    = '0';
      el.style.transform  = 'translateY(-6px)';
      setTimeout(function () { el.remove(); }, 420);
    }, 4000);
  });

  /* ── Animate rating bar fills on load ── */
  document.querySelectorAll('.adm-rb-fill').forEach(function (bar) {
    var targetWidth = bar.style.width;
    bar.style.width = '0%';
    setTimeout(function () {
      bar.style.width = targetWidth;
    }, 120);
  });

});