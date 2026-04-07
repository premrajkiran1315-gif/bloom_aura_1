/**
 * bloom-aura/assets/js/profile.js
 * Client-side form handling for profile page.
 * Name validation: letters only (no digits).
 */

'use strict';

/* ── Only letters, spaces, hyphens, apostrophes ── */
var NAME_RE = /^[A-Za-zÀ-ÖØ-öø-ÿ' -]+$/;

document.addEventListener('DOMContentLoaded', () => {

  const newPasswordInput  = document.getElementById('new_password');
  const currentPasswordInput = document.getElementById('current_password');
  const confirmPasswordInput = document.getElementById('confirm_password');
  const emailInput  = document.getElementById('email');
  const nameInput   = document.getElementById('name');

  // ── Name validation — block digits, show error ────────────────────────────
  if (nameInput) {

    /* Block digit key presses in real time */
    nameInput.addEventListener('keypress', function (e) {
      if (e.key.length === 1 && !NAME_RE.test(e.key)) {
        e.preventDefault();
      }
    });

    /* Strip pasted digits / invalid chars */
    nameInput.addEventListener('input', function () {
      var cleaned = this.value.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ' -]/g, '');
      if (cleaned !== this.value) this.value = cleaned;
    });

    /* On blur — full validation */
    nameInput.addEventListener('blur', () => {
      const group = nameInput.closest('.form-group');
      if (!group) return;
      const val = nameInput.value.trim();

      if (!val) {
        showFormError(group, 'Name is required.');
      } else if (val.length < 2) {
        showFormError(group, 'Name must be at least 2 characters.');
      } else if (!NAME_RE.test(val)) {
        showFormError(group, 'Name can only contain letters, spaces, hyphens and apostrophes.');
      } else {
        clearFieldError(group);
      }
    });
  }

  // ── Password Strength Meter ───────────────────────────────────────────────
  function calculatePasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8)  strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength++;
    return strength;
  }

  function getPasswordStrengthLabel(s) {
    return ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'][Math.min(s, 5)];
  }

  function getPasswordStrengthColor(s) {
    return ['#dc2626','#f97316','#eab308','#84cc16','#22c55e','#16a34a'][Math.min(s, 5)];
  }

  if (newPasswordInput) {
    const strengthContainer = document.createElement('div');
    strengthContainer.style.cssText = 'margin-top:.5rem;display:flex;align-items:center;gap:.75rem;';
    strengthContainer.innerHTML = `
      <div style="flex:1;">
        <div style="height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden;">
          <div id="strengthBar" style="height:100%;width:0%;background:#dc2626;transition:width .3s,background-color .3s;border-radius:2px;"></div>
        </div>
      </div>
      <span id="strengthLabel" style="font-size:.75rem;font-weight:600;color:#6b7280;min-width:80px;">Minimum 8 chars</span>`;
    newPasswordInput.closest('.form-group')?.appendChild(strengthContainer);

    newPasswordInput.addEventListener('input', () => {
      const s     = calculatePasswordStrength(newPasswordInput.value);
      const color = getPasswordStrengthColor(s);
      const bar   = document.getElementById('strengthBar');
      const lbl   = document.getElementById('strengthLabel');
      if (bar) { bar.style.width = (s / 6 * 100) + '%'; bar.style.backgroundColor = color; }
      if (lbl) {
        lbl.textContent = newPasswordInput.value ? getPasswordStrengthLabel(s) : 'Minimum 8 chars';
        lbl.style.color = newPasswordInput.value ? color : '#6b7280';
      }
    });
  }

  // ── Confirm password matching ─────────────────────────────────────────────
  if (newPasswordInput && confirmPasswordInput) {
    const checkMatch = () => {
      const group = confirmPasswordInput.closest('.form-group');
      if (!group) return;
      if (!confirmPasswordInput.value || !newPasswordInput.value) { clearFieldError(group); return; }
      if (newPasswordInput.value === confirmPasswordInput.value) {
        showPasswordMatchSuccess(group);
      } else {
        showPasswordMatchError(group);
      }
    };
    confirmPasswordInput.addEventListener('blur', checkMatch);
    newPasswordInput.addEventListener('input', () => { if (confirmPasswordInput.value) checkMatch(); });
  }

  // ── Email validation ──────────────────────────────────────────────────────
  if (emailInput) {
    emailInput.addEventListener('blur', () => {
      const group = emailInput.closest('.form-group');
      if (!group) return;
      const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value);
      if (emailInput.value && !ok) {
        showEmailError(group);
      } else {
        clearEmailFeedback(group);
      }
    });
  }

  // ── Form submit validation ────────────────────────────────────────────────
  const updateProfileForm   = document.querySelector('input[name="action"][value="update_profile"]')?.closest('form');
  const changePasswordForm  = document.querySelector('input[name="action"][value="change_password"]')?.closest('form');

  if (updateProfileForm) {
    updateProfileForm.addEventListener('submit', e => {
      const nameVal  = nameInput?.value.trim() || '';
      const emailVal = emailInput?.value.trim() || '';
      let hasError   = false;

      if (nameVal.length < 2 || !NAME_RE.test(nameVal)) {
        e.preventDefault();
        showFormError(nameInput?.closest('.form-group'), 'Please enter a valid name (letters only, at least 2 characters).');
        hasError = true;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
        e.preventDefault();
        showFormError(emailInput?.closest('.form-group'), 'Please enter a valid email address.');
        hasError = true;
      }
      return !hasError;
    });
  }

  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', e => {
      const cur     = currentPasswordInput?.value || '';
      const newP    = newPasswordInput?.value || '';
      const confirm = confirmPasswordInput?.value || '';
      let hasError  = false;

      if (!cur) { e.preventDefault(); showFormError(currentPasswordInput?.closest('.form-group'), 'Enter your current password.'); hasError = true; }
      if (newP.length < 8) { e.preventDefault(); showFormError(newPasswordInput?.closest('.form-group'), 'New password must be at least 8 characters.'); hasError = true; }
      if (newP !== confirm) { e.preventDefault(); showFormError(confirmPasswordInput?.closest('.form-group'), 'Passwords do not match.'); hasError = true; }
      return !hasError;
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  function showFormError(group, message) {
    if (!group) return;
    group.classList.add('has-error');
    let err = group.querySelector('.field-error');
    if (!err) { err = document.createElement('span'); err.className = 'field-error'; group.appendChild(err); }
    err.textContent = message;
  }

  function clearFieldError(group) {
    if (!group) return;
    group.classList.remove('has-error');
    group.querySelector('.field-error')?.remove();
  }

  function showPasswordMatchSuccess(group) {
    let fb = group.querySelector('.password-match-feedback');
    if (!fb) { fb = document.createElement('span'); fb.className = 'password-match-feedback'; fb.style.cssText = 'display:flex;align-items:center;gap:.35rem;font-size:.8rem;margin-top:.35rem;font-weight:500;'; group.appendChild(fb); }
    fb.innerHTML = '<i class="fa-solid fa-check"></i> Passwords match'; fb.style.color = '#22c55e';
  }

  function showPasswordMatchError(group) {
    let fb = group.querySelector('.password-match-feedback');
    if (!fb) { fb = document.createElement('span'); fb.className = 'password-match-feedback'; fb.style.cssText = 'display:flex;align-items:center;gap:.35rem;font-size:.8rem;margin-top:.35rem;font-weight:500;'; group.appendChild(fb); }
    fb.innerHTML = '<i class="fa-solid fa-xmark"></i> Passwords do not match'; fb.style.color = '#dc2626';
  }

  function showEmailSuccess(group) {
    let fb = group.querySelector('.email-feedback');
    if (!fb) { fb = document.createElement('span'); fb.className = 'email-feedback'; fb.style.cssText = 'display:flex;align-items:center;gap:.35rem;font-size:.8rem;color:#22c55e;margin-top:.35rem;'; group.appendChild(fb); }
    fb.innerHTML = '<i class="fa-solid fa-check"></i> Valid email'; fb.style.color = '#22c55e';
  }

  function showEmailError(group) {
    let fb = group.querySelector('.email-feedback');
    if (!fb) { fb = document.createElement('span'); fb.className = 'email-feedback'; fb.style.cssText = 'display:flex;align-items:center;gap:.35rem;font-size:.8rem;color:#dc2626;margin-top:.35rem;'; group.appendChild(fb); }
    fb.innerHTML = '<i class="fa-solid fa-xmark"></i> Invalid email format'; fb.style.color = '#dc2626';
  }

  function clearEmailFeedback(group) { group.querySelector('.email-feedback')?.remove(); }

});