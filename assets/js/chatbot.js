/**
 * bloom-aura/assets/js/chatbot.js
 * Rule-based chatbot widget for Bloom Aura.
 * No external APIs. Pure Vanilla JS.
 */

'use strict';

(function () {

  /* ── Knowledge base — add/expand freely ── */
  var KB = [
    {
      patterns: ['hello', 'hi', 'hey', 'good morning', 'good evening'],
      response: '🌸 Hello! Welcome to Bloom Aura! How can I help you today? You can ask me about our bouquets, delivery, orders, or anything else!'
    },
    {
      patterns: ['bouquet', 'flower', 'product', 'what do you sell', 'what do you have'],
      response: '💐 We offer a beautiful range of bouquets, gift hampers, chocolate gifts, perfumes, and plants! <a href="/bloom-aura/pages/shop.php">Browse our full shop →</a>'
    },
    {
      patterns: ['price', 'cost', 'how much', 'cheap', 'expensive', 'budget'],
      response: '💰 Our bouquets start from as low as ₹299! We have options for every budget. <a href="/bloom-aura/pages/shop.php">See all prices →</a>'
    },
    {
      patterns: ['delivery', 'shipping', 'how long', 'when will i get'],
      response: '🚚 We offer <strong>same-day delivery</strong> for orders placed before 2 PM. Standard delivery takes 1–2 days. Delivery charges vary by location.'
    },
    {
      patterns: ['order', 'track', 'my order', 'order status'],
      response: '📦 You can track your orders in your <a href="/bloom-aura/pages/order-history.php">Order History</a> page. Make sure you\'re logged in!'
    },
    {
      patterns: ['rose', 'roses'],
      response: '🌹 Yes! We have gorgeous rose bouquets — red, pink, white and mixed. <a href="/bloom-aura/pages/shop.php?q=rose">See rose bouquets →</a>'
    },
    {
      patterns: ['hamper', 'gift', 'gift box', 'gift set'],
      response: '🎁 Our gift hampers are perfect for birthdays, anniversaries, and special occasions! <a href="/bloom-aura/pages/shop.php?cat=hampers">View hampers →</a>'
    },
    {
      patterns: ['chocolate', 'choco'],
      response: '🍫 We have beautiful chocolate gift sets paired with flowers! <a href="/bloom-aura/pages/shop.php?cat=chocolates">View chocolate gifts →</a>'
    },
    {
      patterns: ['wedding', 'anniversary', 'birthday', 'occasion'],
      response: '💍 We create custom arrangements for weddings, anniversaries, and birthdays! Contact us via WhatsApp or browse our occasion-based collections.'
    },
    {
      patterns: ['custom', 'customise', 'customize', 'personalise', 'personalize'],
      response: '✍️ Yes! We offer custom bouquets and calligraphy gifts. <a href="/bloom-aura/pages/customize.php">Start customising →</a>'
    },
    {
      patterns: ['cancel', 'cancellation', 'return', 'refund'],
      response: '↩️ Orders can be cancelled within 1 hour of placing. For refunds or returns, please contact our support team via WhatsApp.'
    },
    {
      patterns: ['login', 'sign in', 'account', 'register', 'sign up'],
      response: '👤 You can <a href="/bloom-aura/pages/login.php">log in here</a> or <a href="/bloom-aura/pages/register.php">create a new account</a>.'
    },
    {
      patterns: ['wishlist', 'save', 'favourite', 'favorite'],
      response: '❤️ You can save bouquets to your wishlist when you\'re logged in! <a href="/bloom-aura/pages/wishlist.php">View your wishlist →</a>'
    },
    {
      patterns: ['cart', 'basket', 'add to cart'],
      response: '🛒 You can add any bouquet to your cart from the shop page. <a href="/bloom-aura/pages/cart.php">View your cart →</a>'
    },
    {
      patterns: ['payment', 'pay', 'how to pay', 'cod', 'cash'],
      response: '💳 We accept Cash on Delivery (COD), online bank transfer, and UPI. Select your preferred method at checkout.'
    },
    {
      patterns: ['contact', 'whatsapp', 'phone', 'call', 'email', 'support'],
      response: '📞 You can reach us on <strong>WhatsApp</strong> or email us at <strong>hello@bloomaura.com</strong>. We typically respond within 1 hour!'
    },
    {
      patterns: ['thank', 'thanks', 'bye', 'goodbye'],
      response: '🌺 You\'re welcome! Have a wonderful day and happy gifting! 🌸'
    }
  ];

  var DEFAULT_RESPONSE = "🤔 I'm not sure about that, but our team can help! You can <a href='/bloom-aura/pages/shop.php'>browse our shop</a> or contact us on WhatsApp for personalized assistance. 💐";

  /* ── Match user input to a KB entry ── */
  function getResponse(input) {
    var lower = input.toLowerCase().trim();
    for (var i = 0; i < KB.length; i++) {
      for (var j = 0; j < KB[i].patterns.length; j++) {
        if (lower.indexOf(KB[i].patterns[j]) !== -1) {
          return KB[i].response;
        }
      }
    }
    return DEFAULT_RESPONSE;
  }

  /* ── Build widget HTML ── */
  function buildWidget() {
    var wrap = document.createElement('div');
    wrap.id  = 'ba-chat-widget';
    wrap.innerHTML = [
      '<button id="ba-chat-toggle" aria-label="Open chat" aria-expanded="false">',
      '  <span class="ba-chat-icon">💬</span>',
      '  <span class="ba-chat-close-icon" aria-hidden="true">✕</span>',
      '  <span class="ba-chat-badge" id="ba-chat-badge" aria-live="polite"></span>',
      '</button>',
      '<div id="ba-chat-box" role="dialog" aria-label="Bloom Aura Chat" aria-hidden="true">',
      '  <div class="ba-chat-header">',
      '    <div class="ba-chat-header-info">',
      '      <span class="ba-chat-avatar">🌸</span>',
      '      <div>',
      '        <strong>Bloom Aura Assistant</strong>',
      '        <small>Typically replies instantly</small>',
      '      </div>',
      '    </div>',
      '    <button class="ba-chat-header-close" id="ba-chat-header-close" aria-label="Close chat">✕</button>',
      '  </div>',
      '  <div class="ba-chat-messages" id="ba-chat-messages" role="log" aria-live="polite">',
      '    <!-- Messages injected here -->',
      '  </div>',
      '  <div class="ba-chat-quick-btns" id="ba-quick-btns">',
      '    <button class="ba-quick-btn" data-msg="What bouquets do you have?">💐 Bouquets</button>',
      '    <button class="ba-quick-btn" data-msg="How does delivery work?">🚚 Delivery</button>',
      '    <button class="ba-quick-btn" data-msg="What are your prices?">💰 Prices</button>',
      '    <button class="ba-quick-btn" data-msg="I want a custom bouquet">✍️ Custom</button>',
      '  </div>',
      '  <div class="ba-chat-input-wrap">',
      '    <input type="text" id="ba-chat-input" placeholder="Type your message..." autocomplete="off" maxlength="200" aria-label="Chat message">',
      '    <button id="ba-chat-send" aria-label="Send message">',
      '      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
      '    </button>',
      '  </div>',
      '</div>'
    ].join('');
    document.body.appendChild(wrap);
  }

  /* ── Append a message bubble ── */
  function addMessage(text, sender) {
    var messages = document.getElementById('ba-chat-messages');
    if (!messages) return;

    var bubble = document.createElement('div');
    bubble.className = 'ba-msg ' + (sender === 'user' ? 'ba-msg--user' : 'ba-msg--bot');

    var inner = document.createElement('div');
    inner.className = 'ba-msg-bubble';

    if (sender === 'bot') {
      inner.innerHTML = text; // safe — all bot strings are hardcoded above
    } else {
      // XSS-safe: user input rendered as text only
      inner.textContent = text;
    }

    bubble.appendChild(inner);
    messages.appendChild(bubble);
    messages.scrollTop = messages.scrollHeight;
  }

  /* ── Typing indicator ── */
  function showTyping() {
    var messages = document.getElementById('ba-chat-messages');
    if (!messages) return;
    var typing = document.createElement('div');
    typing.className = 'ba-msg ba-msg--bot ba-typing-indicator';
    typing.id = 'ba-typing';
    typing.innerHTML = '<div class="ba-msg-bubble"><span></span><span></span><span></span></div>';
    messages.appendChild(typing);
    messages.scrollTop = messages.scrollHeight;
  }

  function hideTyping() {
    var t = document.getElementById('ba-typing');
    if (t) t.remove();
  }

  /* ── Send logic ── */
  function sendMessage(text) {
    text = text.trim();
    if (!text) return;

    // Hide quick buttons after first user message
    var quickBtns = document.getElementById('ba-quick-btns');
    if (quickBtns) quickBtns.style.display = 'none';

    addMessage(text, 'user');

    var input = document.getElementById('ba-chat-input');
    if (input) input.value = '';

    showTyping();

    // Simulate a 800ms "thinking" delay for natural feel
    setTimeout(function () {
      hideTyping();
      addMessage(getResponse(text), 'bot');
    }, 800);
  }

  /* ── Toggle open/close ── */
  function toggleChat(open) {
    var box    = document.getElementById('ba-chat-box');
    var toggle = document.getElementById('ba-chat-toggle');
    var badge  = document.getElementById('ba-chat-badge');

    if (!box || !toggle) return;

    if (open === undefined) open = box.getAttribute('aria-hidden') === 'true';

    if (open) {
      box.setAttribute('aria-hidden', 'false');
      box.classList.add('ba-chat-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.classList.add('is-open');
      if (badge) badge.textContent = '';
      // Focus input
      var input = document.getElementById('ba-chat-input');
      if (input) setTimeout(function() { input.focus(); }, 300);
    } else {
      box.setAttribute('aria-hidden', 'true');
      box.classList.remove('ba-chat-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.classList.remove('is-open');
    }
  }

  /* ── Init ── */
  function init() {
    buildWidget();

    // Show badge with notification dot after 3s to grab attention
    setTimeout(function () {
      var badge = document.getElementById('ba-chat-badge');
      var box   = document.getElementById('ba-chat-box');
      if (badge && box && box.getAttribute('aria-hidden') === 'true') {
        badge.textContent = '1';
      }
    }, 3000);

    // Welcome message on first open
    var greeted = false;

    document.getElementById('ba-chat-toggle').addEventListener('click', function () {
      toggleChat();
      if (!greeted) {
        greeted = true;
        setTimeout(function () {
          addMessage('🌸 Hi there! I\'m the Bloom Aura assistant. Ask me about our bouquets, delivery, pricing, or anything else!', 'bot');
        }, 400);
      }
    });

    document.getElementById('ba-chat-header-close').addEventListener('click', function () {
      toggleChat(false);
    });

    document.getElementById('ba-chat-send').addEventListener('click', function () {
      var input = document.getElementById('ba-chat-input');
      if (input) sendMessage(input.value);
    });

    document.getElementById('ba-chat-input').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        sendMessage(this.value);
      }
    });

    // Quick buttons
    document.getElementById('ba-quick-btns').addEventListener('click', function (e) {
      var btn = e.target.closest('.ba-quick-btn');
      if (btn) sendMessage(btn.getAttribute('data-msg'));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();