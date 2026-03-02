/**
 * bloom-aura/assets/js/product.js
 * ─────────────────────────────────────────────────────────────
 * JavaScript for: pages/product.php
 * Handles: qty stepper, add-to-cart animation, star hover,
 *          image zoom-in-place, smooth review scroll.
 * No globals, no inline JS. Wrapped in DOMContentLoaded.
 * ─────────────────────────────────────────────────────────────
 */

document.addEventListener('DOMContentLoaded', function () {

  /* ── Quantity Stepper ─────────────────────────────────────── */
  const qtyInput = document.getElementById('qty');
  const minusBtn = document.getElementById('qty-minus');
  const plusBtn  = document.getElementById('qty-plus');

  if (qtyInput && minusBtn && plusBtn) {
    const max = parseInt(qtyInput.getAttribute('max'), 10) || 99;
    const min = parseInt(qtyInput.getAttribute('min'), 10) || 1;

    minusBtn.addEventListener('click', function () {
      const current = parseInt(qtyInput.value, 10) || 1;
      if (current > min) {
        qtyInput.value = current - 1;
        triggerInputAnim(qtyInput);
      }
    });

    plusBtn.addEventListener('click', function () {
      const current = parseInt(qtyInput.value, 10) || 1;
      if (current < max) {
        qtyInput.value = current + 1;
        triggerInputAnim(qtyInput);
      }
    });

    qtyInput.addEventListener('change', function () {
      let val = parseInt(this.value, 10);
      if (isNaN(val) || val < min) val = min;
      if (val > max) val = max;
      this.value = val;
    });

    function triggerInputAnim(input) {
      input.style.transition = 'transform .1s';
      input.style.transform  = 'scale(1.15)';
      setTimeout(function () { input.style.transform = 'scale(1)'; }, 120);
    }
  }

  /* ── Add to Cart button — flash animation ─────────────────── */
  const addForm = document.getElementById('add-to-cart-form');
  const addBtn  = document.getElementById('add-cart-btn');

  if (addForm && addBtn) {
    addForm.addEventListener('submit', function () {
      addBtn.classList.add('added');
      addBtn.innerHTML = '<span class="btn-icon">✓</span> Added!';
      // The form will navigate, but give visual feedback before
    });
  }

  /* ── Star rating input — hover + click highlight ─────────── */
  const starLabels = document.querySelectorAll('.star-rating-input label');
  const starInputs = document.querySelectorAll('.star-rating-input input');

  starLabels.forEach(function (label) {
    label.addEventListener('mouseenter', function () {
      const forId = this.getAttribute('for');
      const val   = parseInt(document.getElementById(forId)?.value, 10);
      highlightStars(val);
    });

    label.addEventListener('mouseleave', function () {
      // Reset to selected value
      let selected = 0;
      starInputs.forEach(function (inp) {
        if (inp.checked) selected = parseInt(inp.value, 10);
      });
      if (selected) {
        highlightStars(selected);
      } else {
        clearStars();
      }
    });

    label.addEventListener('click', function () {
      const forId = this.getAttribute('for');
      const val   = parseInt(document.getElementById(forId)?.value, 10);
      highlightStars(val);
    });
  });

  function highlightStars(rating) {
    starLabels.forEach(function (label) {
      const forId  = label.getAttribute('for');
      const labelVal = parseInt(document.getElementById(forId)?.value, 10);
      label.style.color = labelVal <= rating ? '#f59e0b' : '#d1d5db';
    });
  }

  function clearStars() {
    starLabels.forEach(function (label) {
      label.style.color = '';
    });
  }

  /* ── Smooth scroll to reviews ─────────────────────────────── */
  document.querySelectorAll('a[href="#reviews"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.getElementById('reviews');
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Offset for sticky header
        setTimeout(function () {
          window.scrollBy(0, -80);
        }, 450);
      }
    });
  });

  /* ── Flash messages — auto-dismiss after 4s ──────────────── */
  document.querySelectorAll('.flash').forEach(function (flash) {
    // Error messages persist longer (10s), others 5s
    const timeout = flash.classList.contains('flash-error') ? 10000 : 5000;
    setTimeout(function () {
      flash.style.transition = 'opacity .4s, max-height .4s';
      flash.style.opacity    = '0';
      flash.style.maxHeight  = '0';
      flash.style.overflow   = 'hidden';
      setTimeout(function () { flash.remove(); }, 450);
    }, timeout);
  });

  /* ── Wishlist button — optimistic text swap ──────────────── */
  const wishlistForm = document.querySelector('.wishlist-form-inline');
  const wishlistBtn  = document.querySelector('.wishlist-toggle-btn');

  if (wishlistForm && wishlistBtn) {
    wishlistForm.addEventListener('submit', function () {
      const isWishlisted = wishlistBtn.classList.contains('wishlisted');
      if (isWishlisted) {
        wishlistBtn.textContent = '🤍 Save to Wishlist';
      } else {
        wishlistBtn.textContent = '❤️ Saved to Wishlist';
      }
    });
  }

  /* ── Related card image — preload on hover ────────────────── */
  document.querySelectorAll('.rel-card img').forEach(function (img) {
    img.closest('.rel-card')?.addEventListener('mouseenter', function () {
      img.style.willChange = 'transform';
    });
    img.closest('.rel-card')?.addEventListener('mouseleave', function () {
      img.style.willChange = 'auto';
    });
  });

  /* ── Review form — basic UX ──────────────────────────────── */
  const reviewForm = document.getElementById('review-form');
  if (reviewForm) {
    const ratingInputs = reviewForm.querySelectorAll('input[name="rating"]');
    const commentTextarea = reviewForm.querySelector('textarea[name="comment"]');
    const ratingError = document.getElementById('rating-error');

    // Clear rating error when user selects a rating
    ratingInputs.forEach(input => {
      input.addEventListener('change', function () {
        if (ratingError) {
          ratingError.style.display = 'none';
        }
      });
    });

    // Set custom validation message for textarea
    if (commentTextarea) {
      commentTextarea.addEventListener('invalid', function (e) {
        if (!this.value) {
          this.setCustomValidity('Please write a comment');
        } else if (this.value.length < 5) {
          this.setCustomValidity('Comment must be at least 5 characters (you have ' + this.value.length + ')');
        } else {
          this.setCustomValidity('');
        }
      });

      commentTextarea.addEventListener('input', function () {
        this.setCustomValidity('');
      });
    }

    // Show rating error if trying to submit without rating
    reviewForm.addEventListener('submit', function (e) {
      const ratingSelected = Array.from(ratingInputs).some(inp => inp.checked);
      if (!ratingSelected) {
        e.preventDefault();
        if (ratingError) {
          ratingError.style.display = 'block';
        }
      }
    });
  }

});