/**
 * bloom-aura/assets/js/cart.js
 * Handles the "Add to Cart" button on shop/product pages.
 * Sends a POST request to cart.php and updates the cart badge count.
 * The CSRF token is read from a <meta> tag injected by PHP.
 *
 * To use: add <meta name="csrf-token" content="<?= csrf_token() ?>"> in header.php
 */

'use strict';

/**
 * Read CSRF token from the meta tag added by PHP header.
 */
function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Update the cart badge count in the header.
 */
function updateCartBadge(count) {
  const badge = document.querySelector('.cart-badge');

  if (count > 0) {
    if (badge) {
      badge.textContent = count;
    } else {
      // Create badge if it doesn't exist
      const cartBtn = document.querySelector('.cart-btn');
      if (cartBtn) {
        const newBadge = document.createElement('span');
        newBadge.className = 'cart-badge';
        newBadge.setAttribute('aria-live', 'polite');
        newBadge.textContent = count;
        cartBtn.appendChild(newBadge);
      }
    }
  } else if (badge) {
    badge.remove();
  }
}

/**
 * Show a brief toast notification.
 */
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `flash-msg flash-${type}`;
  toast.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:999;max-width:320px;animation:fadeIn .2s ease';
  toast.textContent = message;

  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {

  document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const productId = btn.dataset.id;
      const productName = btn.dataset.name ?? 'Item';

      if (!productId) return;

      // Visual feedback
      const origText  = btn.innerHTML;
      btn.disabled    = true;
      btn.innerHTML   = '<i class="fa-solid fa-spinner fa-spin"></i> Adding…';

      try {
        const res = await fetch('/pages/cart.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action:     'add',
            product_id: productId,
            qty:        '1',
            csrf_token: getCsrfToken(),
          }),
          redirect: 'follow',
        });

        if (res.ok) {
          // Read updated cart count from response header (set by PHP)
          const cartCount = parseInt(res.headers.get('X-Cart-Count') ?? '-1', 10);
          if (cartCount >= 0) updateCartBadge(cartCount);

          btn.innerHTML = '<i class="fa-solid fa-check"></i> Added!';
          btn.classList.add('btn-added');
          showToast(`${productName} added to cart! 🛒`);

          setTimeout(() => {
            btn.innerHTML = origText;
            btn.disabled  = false;
            btn.classList.remove('btn-added');
          }, 1800);
        } else {
          throw new Error('Request failed');
        }
      } catch {
        btn.innerHTML = origText;
        btn.disabled  = false;
        showToast('Could not add to cart. Please try again.', 'error');
      }
    });
  });

});
