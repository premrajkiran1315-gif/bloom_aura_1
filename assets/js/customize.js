/**
 * bloom-aura-1/assets/js/customize.js
 * ─────────────────────────────────────────────────────────────
 * Custom Bouquet Builder — all client-side interactions.
 * Matches bloom_aura reference behaviour exactly.
 *
 * Sections:
 *   1. State object
 *   2. Flower selection
 *   3. Size selection
 *   4. Wrap selection
 *   5. Chocolate selection
 *   6. Extras (checkboxes)
 *   7. Price + preview update
 *   8. Preview tags
 *   9. Form submit validation
 *  10. Toast notification
 *  11. Flash auto-dismiss
 * ─────────────────────────────────────────────────────────────
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  /* ─────────────────────────────────────────────
     1. SHARED STATE
  ───────────────────────────────────────────── */
  var state = {
    flower:      '',
    flowerPrice: 0,
    flowerEmoji: '💐',
    size:        '',
    sizePrice:   0,
    wrap:        '',
    wrapPrice:   0,
    choc:        'None',
    chocPrice:   0,
    extras:      [],
    extrasPrice: 0
  };

  /* Flower → emoji map (matches reference exactly) */
  var flowerEmoji = {
    'Red Roses':     '🌹',
    'White Lilies':  '🌷',
    'Pink Tulips':   '🌸',
    'Sunflowers':    '🌻',
    'Mixed Seasonal':'💐',
    'Orchids':       '🪻'
  };

  /* ─────────────────────────────────────────────
     2. FLOWER SELECTION
  ───────────────────────────────────────────── */
  document.querySelectorAll('.flower-option').forEach(function (el) {
    el.addEventListener('click', function () {
      document.querySelectorAll('.flower-option').forEach(function (f) {
        f.classList.remove('selected');
      });
      this.classList.add('selected');

      state.flower      = this.dataset.name;
      state.flowerPrice = parseInt(this.dataset.price, 10) || 0;
      state.flowerEmoji = flowerEmoji[state.flower] || '💐';

      /* hidden inputs */
      setVal('inp-flower',       state.flower);
      setVal('inp-flower-price', state.flowerPrice);

      /* preview */
      setText('preview-name',    state.flower);
      setText('preview-emoji',   state.flowerEmoji);
      setText('breakdown-base',  fmt(state.flowerPrice));

      updatePrice();
    });
  });

  /* ─────────────────────────────────────────────
     3. SIZE SELECTION
  ───────────────────────────────────────────── */
  document.querySelectorAll('.size-option').forEach(function (el) {
    el.addEventListener('click', function () {
      document.querySelectorAll('.size-option').forEach(function (s) {
        s.classList.remove('selected');
      });
      this.classList.add('selected');

      state.size      = this.dataset.name;
      state.sizePrice = parseInt(this.dataset.price, 10) || 0;

      setVal('inp-size',       state.size);
      setVal('inp-size-price', state.sizePrice);

      setText('preview-size',   '📦 ' + state.size);
      setText('breakdown-size', fmt(state.sizePrice));

      updatePrice();
    });
  });

  /* ─────────────────────────────────────────────
     4. WRAP SELECTION
  ───────────────────────────────────────────── */
  document.querySelectorAll('.wrap-option').forEach(function (el) {
    el.addEventListener('click', function () {
      document.querySelectorAll('.wrap-option').forEach(function (w) {
        w.classList.remove('selected');
      });
      this.classList.add('selected');

      state.wrap      = this.dataset.name;
      state.wrapPrice = parseInt(this.dataset.price, 10) || 0;

      setVal('inp-wrap',       state.wrap);
      setVal('inp-wrap-price', state.wrapPrice);

      setText('preview-wrap',   '🎁 ' + state.wrap + ' wrapping');
      setText('breakdown-wrap', fmt(state.wrapPrice));

      updatePrice();
    });
  });

  /* ─────────────────────────────────────────────
     5. CHOCOLATE SELECTION
  ───────────────────────────────────────────── */
  document.querySelectorAll('.choc-option').forEach(function (el) {
    el.addEventListener('click', function () {
      document.querySelectorAll('.choc-option').forEach(function (c) {
        c.classList.remove('selected');
      });
      this.classList.add('selected');

      state.choc      = this.dataset.name;
      state.chocPrice = parseInt(this.dataset.price, 10) || 0;

      setVal('inp-choc',       state.choc);
      setVal('inp-choc-price', state.chocPrice);

      setText('breakdown-choc', fmt(state.chocPrice));

      updatePrice();
    });
  });

  /* ─────────────────────────────────────────────
     6. EXTRAS CHECKBOXES
  ───────────────────────────────────────────── */
  document.querySelectorAll('.extra-cb').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var label = this.closest('.extra-option');
      if (label) label.classList.toggle('checked', this.checked);
      recalcExtras();
      updatePrice();
    });
  });

  function recalcExtras() {
    var total  = 0;
    var labels = [];
    if (cbChecked('ribbon'))  { total += 50;  labels.push('Ribbon'); }
    if (cbChecked('glitter')) { total += 30;  labels.push('Glitter'); }
    if (cbChecked('scent'))   { total += 20;  labels.push('Scent'); }
    if (cbChecked('gcard'))   { total += 40;  labels.push('Card'); }
    state.extrasPrice = total;
    state.extras      = labels;
    setText('breakdown-extras', fmt(total));
  }

  function cbChecked(id) {
    var el = document.getElementById(id);
    return el && el.checked;
  }

  /* ─────────────────────────────────────────────
     7. PRICE + PREVIEW UPDATE
  ───────────────────────────────────────────── */
  function updatePrice() {
    var total = state.flowerPrice + state.sizePrice + state.wrapPrice
              + state.extrasPrice + state.chocPrice;

    var display = document.getElementById('custom-price-display');
    if (display) {
      display.textContent = total > 0 ? fmt(total) : '₹0';
      display.classList.remove('bump');
      void display.offsetWidth; /* force reflow */
      display.classList.add('bump');
      setTimeout(function () { display.classList.remove('bump'); }, 300);
    }

    renderTags();
  }

  /* ─────────────────────────────────────────────
     8. PREVIEW TAGS
  ───────────────────────────────────────────── */
  function renderTags() {
    var container = document.getElementById('preview-tags');
    if (!container) return;

    var tags = [];
    if (state.flower) tags.push(state.flower);
    if (state.size)   tags.push(state.size);
    if (state.wrap)   tags.push(state.wrap);
    if (state.choc && state.choc !== 'None') tags.push('🍫 ' + state.choc);
    state.extras.forEach(function (e) { tags.push(e); });

    container.innerHTML = tags.map(function (t) {
      return '<span class="preview-tag">' + escHtml(t) + '</span>';
    }).join('');
  }

  /* ─────────────────────────────────────────────
     9. FORM SUBMIT — client validation + toast
  ───────────────────────────────────────────── */
  var form = document.getElementById('customizeForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      if (!state.flower) {
        e.preventDefault();
        showWarnToast('Please select base flowers! 🌸');
        return;
      }
      if (!state.size) {
        e.preventDefault();
        showWarnToast('Please select a bouquet size! 📦');
        return;
      }
      if (!state.wrap) {
        e.preventDefault();
        showWarnToast('Please choose a wrapping style! 🎁');
        return;
      }
      /* valid — allow submit */
    });
  }

  /* ─────────────────────────────────────────────
     10. TOAST
  ───────────────────────────────────────────── */
  var toastTimer = null;

  function showWarnToast(msg) {
    var toast = document.getElementById('custToast');
    var title = document.getElementById('custToastTitle');
    var sub   = document.getElementById('custToastSub');
    if (!toast) return;

    if (title) title.textContent = '⚠️ ' + msg;
    if (sub)   sub.textContent   = 'Please complete your selection';

    toast.classList.add('show', 'warn');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toast.classList.remove('show', 'warn');
    }, 2800);
  }

  /* ─────────────────────────────────────────────
     11. FLASH AUTO-DISMISS
  ───────────────────────────────────────────── */
  document.querySelectorAll('.alert').forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = 'opacity .4s, transform .4s';
      alert.style.opacity    = '0';
      alert.style.transform  = 'translateX(30px)';
      setTimeout(function () { alert.remove(); }, 400);
    }, 4000);
  });

  /* ─────────────────────────────────────────────
     HELPERS
  ───────────────────────────────────────────── */
  function fmt(n) {
    return '₹' + (n || 0).toLocaleString('en-IN');
  }
  function setText(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
  }
  function setVal(id, val) {
    var el = document.getElementById(id);
    if (el) el.value = val;
  }
  function escHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

});