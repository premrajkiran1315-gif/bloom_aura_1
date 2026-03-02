/**
 * bloom-aura/assets/js/admin_customers.js
 * ─────────────────────────────────────────────────────────────
 * Customers page JS — auto-dismiss flash alerts.
 * All code wrapped inside DOMContentLoaded. No global pollution.
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

});