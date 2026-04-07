/**
 * bloom-aura-1/assets/js/validate.js
 * Client-side form validation — progressive enhancement only.
 * Server-side validation in PHP is the source of truth.
 * This file only adds UX improvements (inline hints as the user types).
 */

'use strict';

/* ── Regex: only letters (including accented/Unicode), spaces, hyphens, apostrophes ── */
var NAME_RE = /^[A-Za-zÀ-ÖØ-öø-ÿ' -]+$/;

document.addEventListener('DOMContentLoaded', () => {

  // ── Name live validation ──────────────────────────────────────────────────
  document.querySelectorAll('input[name="name"]').forEach(input => {

    /* Block digit keys in real time */
    input.addEventListener('keypress', function (e) {
      var char = e.key;
      // Allow control keys (Backspace, arrows, etc.) and valid name chars
      if (char.length === 1 && !NAME_RE.test(char)) {
        e.preventDefault();
      }
    });

    /* Strip any pasted digits or invalid chars */
    input.addEventListener('input', function () {
      var cleaned = this.value.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ' -]/g, '');
      if (cleaned !== this.value) this.value = cleaned;
    });

    /* On blur — show inline error */
    input.addEventListener('blur', () => {
      var group = input.closest('.form-group') || input.closest('.lfield-wrap');
      if (!group) return;
      var val = input.value.trim();

      if (!val) {
        markError(group, 'Please enter your full name.');
      } else if (val.length < 2) {
        markError(group, 'Name must be at least 2 characters.');
      } else if (!NAME_RE.test(val)) {
        markError(group, 'Name can only contain letters, spaces, hyphens and apostrophes.');
      } else {
        clearError(group);
      }
    });

    /* Clear error while the user is typing again */
    input.addEventListener('input', function () {
      var group = input.closest('.form-group') || input.closest('.lfield-wrap');
      if (group) clearError(group);
    });
  });

  // ── Email live validation ─────────────────────────────────────────────────
  document.querySelectorAll('input[type="email"]').forEach(input => {
    input.addEventListener('blur', () => {
      const group = input.closest('.form-group') || input.closest('.lfield-wrap');
      if (!group) return;
      const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (input.value && !emailRe.test(input.value)) {
        markError(group, 'Please enter a valid email address.');
      } else {
        clearError(group);
      }
    });
  });

  // ── Password strength hint ────────────────────────────────────────────────
  const passwordInput = document.getElementById('password');
  const hintEl        = document.getElementById('password-hint');

  if (passwordInput && hintEl) {
    passwordInput.addEventListener('input', () => {
      const len = passwordInput.value.length;
      if (len === 0) {
        hintEl.textContent = 'Minimum 8 characters.';
        hintEl.style.color = '';
      } else if (len < 8) {
        hintEl.textContent = `${8 - len} more character${8 - len > 1 ? 's' : ''} needed.`;
        hintEl.style.color = 'var(--color-error)';
      } else {
        hintEl.textContent = '✓ Good length.';
        hintEl.style.color = 'var(--color-success)';
      }
    });
  }

  // ── Confirm password match ────────────────────────────────────────────────
  const confirmInput = document.getElementById('confirm');
  if (passwordInput && confirmInput) {
    confirmInput.addEventListener('blur', () => {
      const group = confirmInput.closest('.form-group');
      if (!group) return;
      if (confirmInput.value && confirmInput.value !== passwordInput.value) {
        markError(group, 'Passwords do not match.');
      } else {
        clearError(group);
      }
    });
  }

  // ── Phone: digits only ────────────────────────────────────────────────────
  document.querySelectorAll('input[type="tel"]').forEach(input => {
    input.addEventListener('input', () => {
      input.value = input.value.replace(/\D/g, '').slice(0, 10);
    });
  });

  // ── PIN code: digits only, max 6 ─────────────────────────────────────────
  const pincodeInput = document.getElementById('pincode');
  if (pincodeInput) {
    pincodeInput.addEventListener('input', () => {
      pincodeInput.value = pincodeInput.value.replace(/\D/g, '').slice(0, 6);
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  function markError(group, message) {
    group.classList.add('has-error');
    let err = group.querySelector('.field-error-live');
    if (!err) {
      err = document.createElement('span');
      err.className = 'field-error field-error-live';
      err.setAttribute('role', 'alert');
      group.appendChild(err);
    }
    err.textContent = message;
  }

  function clearError(group) {
    group.classList.remove('has-error');
    group.querySelector('.field-error-live')?.remove();
  }

});