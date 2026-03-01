/**
 * bloom-aura-1/assets/js/shop.js
 * ─────────────────────────────────────────────────────────────
 * Shop page JavaScript.
 *   1. Toast notification on "Add to Cart" form submit
 *   2. Sort-select auto-submit on change
 *   3. Mobile sidebar filter toggle
 *   4. Sidebar search focus ring enhancement
 *
 * Rules:
 *   ✔ No global variable pollution (all inside IIFE + DOMContentLoaded)
 *   ✔ No inline JS in PHP/HTML
 *   ✔ No framework dependencies
 *   ✔ CSRF handled server-side via hidden input in PHP forms
 * ─────────────────────────────────────────────────────────────
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  /* ─────────────────────────────────────────────────────────
     1. TOAST
     Shows a brief notification when "Add to Cart" is submitted.
     The form still POSTs normally; the toast fires on submit event
     before the page navigates (visible for ~300ms, then PHP redirects).
  ───────────────────────────────────────────────────────── */
  var toast      = document.getElementById('shopToast');
  var toastTitle = document.getElementById('toastTitle');
  var toastSub   = document.getElementById('toastSub');
  var toastPrice = document.getElementById('toastPrice');
  var toastTimer = null;

  function showToast(name, price) {
    if (!toast) return;
    if (toastTitle) toastTitle.textContent = 'Added to Cart!';
    if (toastSub)   toastSub.textContent   = name;
    if (toastPrice) toastPrice.textContent = price;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toast.classList.remove('show');
    }, 3000);
  }

  /* Attach to every "Add to Cart" button that has data-name */
  document.querySelectorAll('.add-btn[data-name]').forEach(function (btn) {
    var form = btn.closest('form');
    if (!form) return;
    form.addEventListener('submit', function () {
      var name  = btn.getAttribute('data-name')  || 'Item';
      var price = btn.getAttribute('data-price') || '';
      showToast(name, price);
      /* Form submits normally — PHP handles cart update + redirect */
    });
  });

  /* ─────────────────────────────────────────────────────────
     2. SORT-SELECT — auto-submit on change
        Removes the need for any onchange inline attribute.
  ───────────────────────────────────────────────────────── */
  var sortSelect = document.getElementById('sort-select');
  var sortForm   = document.getElementById('sort-form');

  if (sortSelect && sortForm) {
    sortSelect.addEventListener('change', function () {
      sortForm.submit();
    });
  }

  /* ─────────────────────────────────────────────────────────
     3. MOBILE SIDEBAR FILTER TOGGLE
        The "☰ Filters" button in the topbar shows/hides the
        sidebar panel on screens ≤ 900px.
  ───────────────────────────────────────────────────────── */
  var filterBtn     = document.getElementById('filterToggleBtn');
  var shopSidebar   = document.getElementById('shopSidebar');

  if (filterBtn && shopSidebar) {
    filterBtn.addEventListener('click', function () {
      var isOpen = shopSidebar.classList.toggle('open');
      filterBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      filterBtn.textContent = isOpen ? '✕ Close' : '☰ Filters';
    });
  }

  /* ─────────────────────────────────────────────────────────
     4. SIDEBAR SEARCH — box-shadow focus ring for older browsers
        (CSS :focus-visible handles modern browsers already)
  ───────────────────────────────────────────────────────── */
  var searchInput = document.getElementById('sidebar-search');
  if (searchInput) {
    searchInput.addEventListener('focus', function () {
      this.style.boxShadow = '0 0 0 3px rgba(214,51,132,.10)';
    });
    searchInput.addEventListener('blur', function () {
      this.style.boxShadow = '';
    });
  }

  /* ─────────────────────────────────────────────────────────
     5. FLASH MESSAGE AUTO-DISMISS
        Dismisses any alert/flash messages after 4 seconds.
  ───────────────────────────────────────────────────────── */
  document.querySelectorAll('.alert').forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = 'opacity .4s';
      alert.style.opacity    = '0';
      setTimeout(function () { alert.remove(); }, 400);
    }, 4000);
  });

});