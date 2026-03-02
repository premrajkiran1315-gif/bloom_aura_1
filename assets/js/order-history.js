/**
 * bloom-aura/assets/js/order-history.js
 * ─────────────────────────────────────────────────────────────
 * Order History page JS:
 *   • Order accordion toggle (expand / collapse items)
 *   • Inline review form toggle (Rate this / Cancel)
 *   • Star picker interaction for inline review forms
 * All code inside DOMContentLoaded. No global pollution.
 * ─────────────────────────────────────────────────────────────
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  /* ══════════════════════════════════════════════
     1. ORDER ACCORDION — toggle order details
  ══════════════════════════════════════════════ */
  document.querySelectorAll('.order-toggle-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('aria-controls');
      var target   = document.getElementById(targetId);
      var expanded = btn.getAttribute('aria-expanded') === 'true';

      btn.setAttribute('aria-expanded', String(!expanded));
      target.hidden = expanded;

      var icon = btn.querySelector('i');
      if (icon) {
        icon.style.transform = expanded ? '' : 'rotate(180deg)';
      }
    });
  });

  /* ══════════════════════════════════════════════
     2. INLINE STAR PICKER
        Each form has its own .rating-input hidden field.
        Stars light up on hover and click.
  ══════════════════════════════════════════════ */
  document.querySelectorAll('.inline-review-form').forEach(function (form) {
    var stars      = form.querySelectorAll('.inline-star-btn');
    var ratingInput = form.querySelector('.rating-input');

    stars.forEach(function (star) {
      var val = parseInt(star.dataset.val, 10);

      /* Hover highlight */
      star.addEventListener('mouseenter', function () {
        stars.forEach(function (s) {
          s.classList.toggle('lit', parseInt(s.dataset.val, 10) <= val);
        });
      });

      star.addEventListener('mouseleave', function () {
        var current = parseInt(ratingInput.value, 10);
        stars.forEach(function (s) {
          s.classList.toggle('lit', parseInt(s.dataset.val, 10) <= current);
        });
      });

      /* Click — set rating */
      star.addEventListener('click', function () {
        ratingInput.value = val;
        stars.forEach(function (s) {
          s.classList.toggle('lit', parseInt(s.dataset.val, 10) <= val);
        });
      });
    });

    /* Prevent submitting without a rating */
    var submitBtn = form.querySelector('.inline-submit-btn');
    if (submitBtn) {
      form.querySelector('form').addEventListener('submit', function (e) {
        if (parseInt(ratingInput.value, 10) < 1) {
          e.preventDefault();
          /* Flash the stars */
          stars.forEach(function (s) { s.style.color = '#ef4444'; });
          setTimeout(function () {
            stars.forEach(function (s) { s.style.color = ''; });
          }, 600);
        }
      });
    }
  });

  /* ══════════════════════════════════════════════
     3. "Rate this" BUTTON — show / hide review form
  ══════════════════════════════════════════════ */
  document.querySelectorAll('.rate-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.dataset.target;
      var form     = document.getElementById(targetId);
      if (!form) return;

      var isHidden = form.hidden;
      form.hidden  = !isHidden;
      btn.setAttribute('aria-expanded', String(isHidden));
      btn.style.display = isHidden ? 'none' : '';
    });
  });

  /* ══════════════════════════════════════════════
     4. CANCEL BUTTON — hide form, show Rate btn
  ══════════════════════════════════════════════ */
  document.querySelectorAll('.inline-cancel-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.dataset.target;
      var form     = document.getElementById(targetId);
      if (!form) return;

      form.hidden = true;

      /* Re-show the Rate this button */
      var rateBtn = document.querySelector('.rate-btn[data-target="' + targetId + '"]');
      if (rateBtn) {
        rateBtn.style.display = '';
        rateBtn.setAttribute('aria-expanded', 'false');
      }

      /* Reset the form inputs */
      var ratingInput = form.querySelector('.rating-input');
      if (ratingInput) ratingInput.value = 0;
      form.querySelectorAll('.inline-star-btn').forEach(function (s) {
        s.classList.remove('lit');
        s.style.color = '';
      });
      var textarea = form.querySelector('textarea');
      if (textarea) textarea.value = '';
    });
  });

  /* ══════════════════════════════════════════════
     5. AUTO-DISMISS flash alerts after 4 seconds
  ══════════════════════════════════════════════ */
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s ease, transform .4s ease';
      el.style.opacity    = '0';
      el.style.transform  = 'translateY(-6px)';
      setTimeout(function () { el.remove(); }, 420);
    }, 4000);
  });

});