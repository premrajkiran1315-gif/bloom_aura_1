/**
 * bloom-aura-1/assets/js/home.js
 * ─────────────────────────────────────────────────────────────
 * Homepage JavaScript.
 *   1. Newsletter form — client-side email validation + feedback
 *   2. Category pill active state on click
 *   3. Hero CTA button hover lift (CSS handles this, JS only for
 *      focus accessibility polish)
 *   4. Flash message auto-dismiss (backup)
 *
 * Rules:
 *   ✔ All code inside DOMContentLoaded
 *   ✔ No global variable pollution
 *   ✔ No inline JS in PHP/HTML
 *   ✔ No framework dependencies
 * ─────────────────────────────────────────────────────────────
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  /* ─────────────────────────────────────────────────────────
     1. NEWSLETTER FORM — client-side validation
        The form is decorative (no server-side endpoint needed
        unless you add one later). Shows success/error inline.
  ───────────────────────────────────────────────────────── */
  var newsletterForm  = document.getElementById('newsletter-form');
  var newsletterEmail = document.getElementById('newsletter-email');
  var newsletterError = document.getElementById('newsletter-error');

  if (newsletterForm && newsletterEmail && newsletterError) {
    newsletterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var email = newsletterEmail.value.trim();

      /* Simple email pattern check */
      var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

      if (!emailOk) {
        newsletterError.textContent = 'Please enter a valid email address.';
        newsletterError.classList.add('show');
        newsletterEmail.focus();
        return;
      }

      /* Success state */
      newsletterError.classList.remove('show');
      var btn = newsletterForm.querySelector('button[type="submit"]');
      var origText = btn ? btn.textContent : 'Subscribe';
      if (btn) {
        btn.textContent = '✅ You\'re in!';
        btn.disabled    = true;
        btn.style.background = 'rgba(255,255,255,.9)';
      }
      newsletterEmail.value   = '';
      newsletterEmail.disabled = true;

      /* Reset after 5 s */
      setTimeout(function () {
        if (btn) {
          btn.textContent          = origText;
          btn.disabled             = false;
          btn.style.background     = '';
        }
        newsletterEmail.disabled = false;
      }, 5000);
    });
  }

  /* ─────────────────────────────────────────────────────────
     2. CATEGORY PILL active class on click
        Gives instant visual feedback before the page reloads.
  ───────────────────────────────────────────────────────── */
  document.querySelectorAll('.category-strip .cat-pill').forEach(function (pill) {
    pill.addEventListener('click', function () {
      document.querySelectorAll('.category-strip .cat-pill').forEach(function (p) {
        p.classList.remove('active');
      });
      this.classList.add('active');
    });
  });

  /* ─────────────────────────────────────────────────────────
     3. FLASH MESSAGE AUTO-DISMISS
        Fades out alert messages after 4 seconds.
  ───────────────────────────────────────────────────────── */
  document.querySelectorAll('.alert').forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = 'opacity .4s, transform .4s';
      alert.style.opacity    = '0';
      alert.style.transform  = 'translateX(30px)';
      setTimeout(function () { alert.remove(); }, 400);
    }, 4000);
  });

  /* ─────────────────────────────────────────────────────────
     4. HERO CTA BUTTON — keyboard focus ring enhancement
        Ensures focus is clearly visible on the pill buttons
        for keyboard users (CSS :focus-visible handles most cases).
  ───────────────────────────────────────────────────────── */
  ['.hero-btn-primary', '.hero-btn-ghost'].forEach(function (sel) {
    var el = document.querySelector(sel);
    if (!el) return;
    el.addEventListener('focus', function () {
      this.style.outline       = '3px solid rgba(255,255,255,.9)';
      this.style.outlineOffset = '3px';
    });
    el.addEventListener('blur', function () {
      this.style.outline       = '';
      this.style.outlineOffset = '';
    });
  });

});