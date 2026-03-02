/**
 * bloom-aura/assets/js/profile.js
 * Client-side form handling for profile page
 * Features:
 *   - Real-time password strength indicator
 *   - Confirm password matching validation
 *   - Email validation feedback
 *   - Form submission feedback
 *   - Progressive enhancement (server-side is source of truth)
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

  // ── Password Strength Indicator ──────────────────────────────────
  const newPasswordInput = document.getElementById('new_password');
  const currentPasswordInput = document.getElementById('current_password');
  const confirmPasswordInput = document.getElementById('confirm_password');
  const emailInput = document.getElementById('email');
  const nameInput = document.getElementById('name');

  // ── Password Strength Meter ──────────────────────────────────────
  function calculatePasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength++;
    return strength;
  }

  function getPasswordStrengthLabel(strength) {
    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    return labels[Math.min(strength, 5)];
  }

  function getPasswordStrengthColor(strength) {
    const colors = ['#dc2626', '#f97316', '#eab308', '#84cc16', '#22c55e', '#16a34a'];
    return colors[Math.min(strength, 5)];
  }

  // Create and append password strength indicator
  if (newPasswordInput) {
    const strengthContainer = document.createElement('div');
    strengthContainer.style.cssText = 'margin-top: 0.5rem; display: flex; align-items: center; gap: 0.75rem;';
    strengthContainer.innerHTML = `
      <div style="flex: 1;">
        <div style="height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden;">
          <div id="strengthBar" style="height: 100%; width: 0%; background: #dc2626; transition: width 0.3s, background-color 0.3s; border-radius: 2px;"></div>
        </div>
      </div>
      <span id="strengthLabel" style="font-size: 0.75rem; font-weight: 600; color: #6b7280; min-width: 80px;">Minimum 8 chars</span>
    `;
    newPasswordInput.closest('.form-group')?.appendChild(strengthContainer);

    newPasswordInput.addEventListener('input', () => {
      const strength = calculatePasswordStrength(newPasswordInput.value);
      const label = getPasswordStrengthLabel(strength);
      const color = getPasswordStrengthColor(strength);
      const barWidth = (strength / 6) * 100;

      const strengthBar = document.getElementById('strengthBar');
      const strengthLabel = document.getElementById('strengthLabel');

      strengthBar.style.width = barWidth + '%';
      strengthBar.style.backgroundColor = color;
      strengthLabel.textContent = newPasswordInput.value ? label : 'Minimum 8 chars';
      strengthLabel.style.color = newPasswordInput.value ? color : '#6b7280';
    });
  }

  // ── Confirm Password Matching ────────────────────────────────────
  if (newPasswordInput && confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', () => {
      const formGroup = confirmPasswordInput.closest('.form-group');
      if (!formGroup) return;

      if (confirmPasswordInput.value === '' || newPasswordInput.value === '') {
        clearPasswordMatchFeedback(formGroup);
      } else if (newPasswordInput.value === confirmPasswordInput.value) {
        showPasswordMatchSuccess(formGroup);
      } else {
        showPasswordMatchError(formGroup);
      }
    });

    // Also validate when new password changes
    newPasswordInput.addEventListener('input', () => {
      if (confirmPasswordInput.value) {
        const formGroup = confirmPasswordInput.closest('.form-group');
        if (!formGroup) return;

        if (newPasswordInput.value === confirmPasswordInput.value) {
          showPasswordMatchSuccess(formGroup);
        } else {
          showPasswordMatchError(formGroup);
        }
      }
    });
  }

  function showPasswordMatchSuccess(formGroup) {
    let feedback = formGroup.querySelector('.password-match-feedback');
    if (!feedback) {
      feedback = document.createElement('span');
      feedback.className = 'password-match-feedback';
      feedback.style.cssText = 'display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #22c55e; margin-top: 0.35rem; font-weight: 500;';
      formGroup.appendChild(feedback);
    }
    feedback.innerHTML = '<i class="fa-solid fa-check"></i> Passwords match';
    feedback.style.color = '#22c55e';
  }

  function showPasswordMatchError(formGroup) {
    let feedback = formGroup.querySelector('.password-match-feedback');
    if (!feedback) {
      feedback = document.createElement('span');
      feedback.className = 'password-match-feedback';
      feedback.style.cssText = 'display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #dc2626; margin-top: 0.35rem; font-weight: 500;';
      formGroup.appendChild(feedback);
    }
    feedback.innerHTML = '<i class="fa-solid fa-xmark"></i> Passwords do not match';
    feedback.style.color = '#dc2626';
  }

  function clearPasswordMatchFeedback(formGroup) {
    const feedback = formGroup.querySelector('.password-match-feedback');
    if (feedback) {
      feedback.remove();
    }
  }

  // ── Email Validation ─────────────────────────────────────────────
  if (emailInput) {
    emailInput.addEventListener('blur', () => {
      const formGroup = emailInput.closest('.form-group');
      if (!formGroup) return;

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      const isValid = emailRegex.test(emailInput.value);

      if (emailInput.value === '') {
        clearEmailFeedback(formGroup);
      } else if (isValid) {
        showEmailSuccess(formGroup);
      } else {
        showEmailError(formGroup);
      }
    });
  }

  function showEmailSuccess(formGroup) {
    let feedback = formGroup.querySelector('.email-feedback');
    if (!feedback) {
      feedback = document.createElement('span');
      feedback.className = 'email-feedback';
      feedback.style.cssText = 'display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #22c55e; margin-top: 0.35rem;';
      formGroup.appendChild(feedback);
    }
    feedback.innerHTML = '<i class="fa-solid fa-check"></i> Valid email';
    feedback.style.color = '#22c55e';
  }

  function showEmailError(formGroup) {
    let feedback = formGroup.querySelector('.email-feedback');
    if (!feedback) {
      feedback = document.createElement('span');
      feedback.className = 'email-feedback';
      feedback.style.cssText = 'display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #dc2626; margin-top: 0.35rem;';
      formGroup.appendChild(feedback);
    }
    feedback.innerHTML = '<i class="fa-solid fa-xmark"></i> Invalid email format';
    feedback.style.color = '#dc2626';
  }

  function clearEmailFeedback(formGroup) {
    const feedback = formGroup.querySelector('.email-feedback');
    if (feedback) {
      feedback.remove();
    }
  }

  // ── Form Submission Feedback ─────────────────────────────────────
  const profileForm = document.querySelector('form[action="/pages/profile.php"]');
  const updateProfileForm = document.querySelector('input[name="action"][value="update_profile"]')?.closest('form');
  const changePasswordForm = document.querySelector('input[name="action"][value="change_password"]')?.closest('form');

  if (updateProfileForm) {
    updateProfileForm.addEventListener('submit', (e) => {
      const nameValue = nameInput?.value.trim() || '';
      const emailValue = emailInput?.value.trim() || '';

      if (nameValue.length < 2) {
        e.preventDefault();
        showFormError(nameInput?.closest('.form-group'), 'Name must be at least 2 characters');
      }

      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
        e.preventDefault();
        showFormError(emailInput?.closest('.form-group'), 'Please enter a valid email address');
      }
    });
  }

  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', (e) => {
      const currentPass = currentPasswordInput?.value || '';
      const newPass = newPasswordInput?.value || '';
      const confirmPass = confirmPasswordInput?.value || '';

      let hasError = false;

      if (currentPass === '') {
        e.preventDefault();
        showFormError(currentPasswordInput?.closest('.form-group'), 'Enter your current password');
        hasError = true;
      }

      if (newPass.length < 8) {
        e.preventDefault();
        showFormError(newPasswordInput?.closest('.form-group'), 'New password must be at least 8 characters');
        hasError = true;
      }

      if (newPass !== confirmPass) {
        e.preventDefault();
        showFormError(confirmPasswordInput?.closest('.form-group'), 'Passwords do not match');
        hasError = true;
      }
    });
  }

  function showFormError(formGroup, message) {
    if (!formGroup) return;
    formGroup.classList.add('has-error');
    let errorEl = formGroup.querySelector('.field-error');
    if (!errorEl) {
      errorEl = document.createElement('span');
      errorEl.className = 'field-error';
      formGroup.appendChild(errorEl);
    }
    errorEl.textContent = message;
  }

  // ── Real-time name validation ────────────────────────────────────
  if (nameInput) {
    nameInput.addEventListener('blur', () => {
      const formGroup = nameInput.closest('.form-group');
      if (!formGroup) return;

      const nameValue = nameInput.value.trim();
      if (nameValue.length < 2 && nameValue.length > 0) {
        formGroup.classList.add('has-error');
        let errorEl = formGroup.querySelector('.field-error');
        if (!errorEl) {
          errorEl = document.createElement('span');
          errorEl.className = 'field-error';
          formGroup.appendChild(errorEl);
        }
        errorEl.textContent = 'Name must be at least 2 characters';
      } else {
        formGroup.classList.remove('has-error');
        const errorEl = formGroup.querySelector('.field-error');
        if (errorEl && !errorEl.textContent.includes('is already in use')) {
          errorEl.remove();
        }
      }
    });
  }

});
