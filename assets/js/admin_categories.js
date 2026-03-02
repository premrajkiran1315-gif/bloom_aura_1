/**
 * bloom-aura/assets/js/admin_categories.js
 * ─────────────────────────────────────────────────────────────
 * Categories page JS — emoji picker + auto-dismiss alerts.
 * All code wrapped in DOMContentLoaded. No global pollution.
 * ─────────────────────────────────────────────────────────────
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  /* ── Auto-dismiss alert messages after 4 seconds ── */
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s ease, transform .4s ease';
      el.style.opacity    = '0';
      el.style.transform  = 'translateY(-6px)';
      setTimeout(function () { el.remove(); }, 420);
    }, 4000);
  });

  /* ── Emoji picker functionality ── */
  const emojiInput = document.getElementById('cat-emoji');
  const emojiDisplay = document.getElementById('selected-emoji-display');
  const emojiGrid = document.getElementById('cat-emoji-grid');

  if (emojiGrid && emojiInput) {
    // Set initially selected emoji
    const initialEmoji = emojiInput.value;
    if (initialEmoji) {
      document.querySelectorAll('.cat-emoji-btn').forEach(btn => {
        if (btn.dataset.emoji === initialEmoji) {
          btn.classList.add('selected');
        }
      });
      if (emojiDisplay) emojiDisplay.textContent = initialEmoji;
    }

    // Handle emoji button clicks
    document.querySelectorAll('.cat-emoji-btn').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const emoji = this.dataset.emoji;

        // Remove selected class from all buttons
        document.querySelectorAll('.cat-emoji-btn').forEach(b => {
          b.classList.remove('selected');
        });

        // Add selected class to clicked button
        this.classList.add('selected');

        // Update hidden input
        emojiInput.value = emoji;

        // Update display
        if (emojiDisplay) emojiDisplay.textContent = emoji;
      });
    });

    // Clear emoji on middle-click or when double-clicking empty space
    emojiGrid.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      document.querySelectorAll('.cat-emoji-btn').forEach(b => {
        b.classList.remove('selected');
      });
      emojiInput.value = '';
      if (emojiDisplay) emojiDisplay.textContent = '—';
    });
  }

});