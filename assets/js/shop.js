/**
 * bloom-aura-1/assets/js/shop.js
 * ─────────────────────────────────────────────────────────────
 * Shop page JavaScript.
 *   – Toast notification on "Add to Cart" form submit
 *   – Sort-select auto-submit on change
 *   – Sidebar search focus ring enhancement
 *
 * Rules:
 *   ✔ No global variable pollution
 *   ✔ All code inside DOMContentLoaded
 *   ✔ No inline JS in PHP/HTML
 *   ✔ No framework dependencies
 *   ✔ CSRF handled server-side via hidden input in PHP forms
 * ─────────────────────────────────────────────────────────────
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  /* ─────────────────────────────────────────────────────────
     1. TOAST — shown when Add to Cart form is submitted.
        Intercepts the submit, shows toast, then lets the
        form POST naturally (full-page, no AJAX needed).
        PHP handles the actual cart update.
  ───────────────────────────────────────────────────────── */
  var toast     = document.getElementById('shopToast');
  var toastTitle = document.getElementById('toastTitle');
  var toastSub   = document.getElementById('toastSub');
  var toastPrice = document.getElementById('toastPrice');
  var toastTimer = null;

  function showToast(name, price) {
    if (!toast) return;
    if (toastTitle) toastTitle.textContent = 'Added to Cart!';
    if (toastSub)   toastSub.textContent   = name;
    if (toastPrice) toastPrice.textContent  = price;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toast.classList.remove('show');
    }, 3000);
  }

  /* Attach to every "Add" submit button on the grid */
  document.querySelectorAll('.add-btn[data-name]').forEach(function (btn) {
    /* The button sits inside a <form>; intercept the form's submit */
    var form = btn.closest('form');
    if (!form) return;

    form.addEventListener('submit', function () {
      var name  = btn.getAttribute('data-name')  || 'Item';
      var price = btn.getAttribute('data-price') || '';
      showToast(name, price);
      /* Let the form submit normally — PHP processes cart update */
    });
  });

  /* ─────────────────────────────────────────────────────────
     2. SORT-SELECT — auto-submit on change (replaces the
        onchange="this.form.submit()" inline attribute).
  ───────────────────────────────────────────────────────── */
  var sortSelect = document.getElementById('sort-select');
  var sortForm   = document.getElementById('sort-form');

  if (sortSelect && sortForm) {
    sortSelect.addEventListener('change', function () {
      sortForm.submit();
    });
  }

  /* ─────────────────────────────────────────────────────────
     3. SIDEBAR SEARCH — subtle focus ring colour via JS
        (CSS :focus-visible handles the ring; this adds
         a matching box-shadow for older browsers).
  ───────────────────────────────────────────────────────── */
  var searchInput = document.getElementById('sidebar-search');
  if (searchInput) {
    searchInput.addEventListener('focus', function () {
      searchInput.style.borderColor  = '#d63384';
      searchInput.style.boxShadow    = '0 0 0 3px rgba(214,51,132,.10)';
    });
    searchInput.addEventListener('blur', function () {
      searchInput.style.borderColor  = '';
      searchInput.style.boxShadow    = '';
    });
  }

  /* ─────────────────────────────────────────────────────────
     4. WISHLIST HEART — optimistic UI feedback.
        The form POSTs to wishlist.php; this just flips the
        emoji instantly so the user gets immediate feedback
        before the page reloads.
  ───────────────────────────────────────────────────────── */
  document.querySelectorAll('.wishlist-form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('.card-wishlist-btn');
      if (!btn) return;
      var isWishlisted = btn.classList.contains('wishlisted');
      btn.textContent = isWishlisted ? '🤍' : '❤️';
      btn.classList.toggle('wishlisted');
    });
  });

});