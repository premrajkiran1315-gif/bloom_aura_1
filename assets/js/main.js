/**
 * bloom-aura/assets/js/main.js
 * Global JS — search toggle, mobile nav, flash message dismissal, user dropdown.
 * NO inline event handlers — all listeners are attached here via addEventListener.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

  // ── Flash message dismissal ──────────────────────────────────
  document.querySelectorAll('.flash-close').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.closest('.flash-msg')?.remove();
    });
  });

  // Auto-dismiss flash after 5s
  document.querySelectorAll('.flash-msg').forEach(msg => {
    setTimeout(() => msg.remove(), 5000);
  });

  // ── Search bar toggle ────────────────────────────────────────
  const searchToggleBtn = document.getElementById('search-toggle-btn');
  const searchBar       = document.getElementById('search-bar');

  if (searchToggleBtn && searchBar) {
    searchToggleBtn.addEventListener('click', () => {
      const isHidden = searchBar.hidden;
      searchBar.hidden = !isHidden;
      searchToggleBtn.setAttribute('aria-expanded', String(isHidden));

      if (isHidden) {
        searchBar.querySelector('input')?.focus();
      }
    });
  }

  // ── Mobile nav ───────────────────────────────────────────────
  const hamburger = document.getElementById('hamburger-btn');
  const mobileNav = document.getElementById('mobile-nav-overlay');

  if (hamburger && mobileNav) {
    hamburger.addEventListener('click', () => {
      const isOpen = !mobileNav.hidden;
      mobileNav.hidden = isOpen;
      hamburger.setAttribute('aria-expanded', String(!isOpen));
      document.body.style.overflow = isOpen ? '' : 'hidden';
    });

    // Close on overlay click
    mobileNav.addEventListener('click', e => {
      if (e.target === mobileNav) {
        mobileNav.hidden = true;
        hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });
  }

  // ── Password show/hide toggles ───────────────────────────────
  document.querySelectorAll('.toggle-pass').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.dataset.target;
      const input    = document.getElementById(targetId);
      if (!input) return;

      const isPass = input.type === 'password';
      input.type   = isPass ? 'text' : 'password';
      btn.querySelector('i')?.classList.toggle('fa-eye',      !isPass);
      btn.querySelector('i')?.classList.toggle('fa-eye-slash', isPass);
      btn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
    });
  });

  // ── Payment option highlight ─────────────────────────────────
  document.querySelectorAll('.payment-option input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
      radio.closest('.payment-option')?.classList.add('selected');
    });
  });

});
