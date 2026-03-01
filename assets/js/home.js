/**
 * bloom-aura-1/assets/js/home.js
 * Homepage-specific JS.
 * – Newsletter form client-side validation
 * – Hero pill hover micro-interaction
 *
 * Rules: no global vars, DOMContentLoaded wrapper, no inline JS.
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  /* ─────────────────────────────────────────────
     NEWSLETTER FORM — validation + success state
  ───────────────────────────────────────────── */
  const newsletterForm  = document.getElementById('newsletter-form');
  const newsletterError = document.getElementById('newsletter-error');

  if (newsletterForm) {
    newsletterForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const input  = newsletterForm.querySelector('input[type="email"]');
      const btn    = newsletterForm.querySelector('button[type="submit"]');
      const val    = (input ? input.value : '').trim();
      const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      /* Clear previous error */
      if (newsletterError) {
        newsletterError.textContent = '';
        newsletterError.style.display = 'none';
      }

      /* Validate */
      if (!val || !emailRe.test(val)) {
        if (newsletterError) {
          newsletterError.textContent = 'Please enter a valid email address.';
          newsletterError.style.display = 'block';
        }
        if (input) input.focus();
        return;
      }

      /* Visual success feedback (replace with real fetch when backend ready) */
      if (btn) {
        const origText = btn.textContent;
        btn.textContent = '✅ Subscribed!';
        btn.disabled    = true;
        setTimeout(function () {
          btn.textContent = origText;
          btn.disabled    = false;
          if (input) input.value = '';
        }, 3000);
      }
    });
  }

  /* ─────────────────────────────────────────────
     HERO PILLS — hover micro-interaction
  ───────────────────────────────────────────── */
  document.querySelectorAll('.hero-pill').forEach(function (pill) {
    pill.addEventListener('mouseenter', function () {
      pill.style.transform = 'translateY(-2px) scale(1.04)';
    });
    pill.addEventListener('mouseleave', function () {
      pill.style.transform = '';
    });
  });

});