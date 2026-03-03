/**
 * bloom-aura/assets/js/chatbot.js
 * ─────────────────────────────────────────────────────────────
 * Enhanced Chatbot Widget — v2.0
 *
 * FEATURES:
 *  1. Conversation Memory        — tracks topics discussed to avoid repeating
 *  2. Context-Aware by Page      — detects current page via window.location
 *  3. Login-Aware Greetings      — reads data-user / meta[name=user-name]
 *  4. Dynamic Quick Buttons      — change after each topic is answered
 *  5. Occasion-Based Recommender — multi-turn guided recommendation flow
 *  6. Price Range Filter via Chat — detects ₹/price patterns, links to shop
 *  7. Multi-Turn Conversation    — guided flows with state machine
 *  8. Proactive Trigger Messages — page-aware auto-open nudges
 * 10. Emoji Reactions on Bot Msgs— 👍 / 👎 per message, logged to sessionStorage
 * 11. Chat History Persistence   — last 10 messages survive page navigation
 * 14. Light/Dark Mode Ready      — watches <html data-theme="light|dark">
 *     (future-proof: bot auto-adapts when theme toggle is added)
 *
 * Rules: Vanilla JS only · No frameworks · No external APIs · IIFE wrapped
 * ─────────────────────────────────────────────────────────────
 */

'use strict';

(function () {

  /* ══════════════════════════════════════════════════════════
     SECTION 1 — CONFIGURATION & CONSTANTS
  ══════════════════════════════════════════════════════════ */

  var STORAGE_KEY   = 'ba_chat_history';   // sessionStorage key for persistence
  var FEEDBACK_KEY  = 'ba_chat_feedback';  // sessionStorage key for reactions
  var MAX_HISTORY   = 10;                  // max messages to persist
  var TYPING_DELAY  = 750;                 // ms before bot "replies"
  var PROACTIVE_DELAY = {
    home     : 30000,   // 30s on homepage
    shop     : 20000,   // 20s on shop
    cart     : 60000,   // 60s on cart
    product  : 8000,    // 8s on product page
    checkout : 15000    // 15s on checkout
  };

  /* ── Page detection ── */
  var PATH = window.location.pathname;
  var PAGE = (function () {
    if (PATH.indexOf('cart')           !== -1) return 'cart';
    if (PATH.indexOf('checkout')       !== -1) return 'checkout';
    if (PATH.indexOf('product')        !== -1) return 'product';
    if (PATH.indexOf('shop')           !== -1) return 'shop';
    if (PATH.indexOf('order-history')  !== -1) return 'orders';
    if (PATH.indexOf('profile')        !== -1) return 'profile';
    if (PATH.indexOf('wishlist')       !== -1) return 'wishlist';
    if (PATH.indexOf('register')       !== -1) return 'register';
    if (PATH.indexOf('login')          !== -1) return 'login';
    return 'home';
  }());

  /* ── User detection — reads PHP-injected attributes safely ── */
  var USER_NAME = (function () {
    // Try <meta name="user-name" content="Sarah"> (add to header.php)
    var meta = document.querySelector('meta[name="user-name"]');
    if (meta && meta.content) return meta.content;
    // Try <body data-user="Sarah"> (alternative)
    var body = document.body.getAttribute('data-user');
    if (body) return body;
    return null;
  }());
  var IS_LOGGED_IN = !!USER_NAME;

  /* ── Product catalogue for recommendations ── */
  var PRODUCTS = [
    { name: 'Red Rose Bouquet',          price: 599,  cat: 'bouquet',   occasion: ['anniversary','love','romance','valentine'],    url: '/bloom-aura/pages/shop.php?q=rose' },
    { name: 'Pink Tulip Bouquet',        price: 499,  cat: 'bouquet',   occasion: ['birthday','friendship','thank you'],           url: '/bloom-aura/pages/shop.php?q=tulip' },
    { name: 'Sunflower Bunch',           price: 399,  cat: 'bouquet',   occasion: ['birthday','cheer','friendship','get well'],    url: '/bloom-aura/pages/shop.php?q=sunflower' },
    { name: 'Luxury Orchid Arrangement', price: 1299, cat: 'bouquet',   occasion: ['anniversary','luxury','wedding','corporate'], url: '/bloom-aura/pages/shop.php?q=orchid' },
    { name: 'Chocolate Gift Hamper',     price: 899,  cat: 'chocolate', occasion: ['birthday','love','anniversary','thank you'],   url: '/bloom-aura/pages/shop.php?cat=chocolates' },
    { name: 'Perfume & Rose Combo',      price: 1599, cat: 'perfume',   occasion: ['anniversary','luxury','wedding'],              url: '/bloom-aura/pages/shop.php?cat=perfumes' },
    { name: 'Birthday Goodie Hamper',    price: 1349, cat: 'hamper',    occasion: ['birthday','surprise','kids'],                  url: '/bloom-aura/pages/shop.php?cat=hampers' },
    { name: 'Satin Rose Bouquet',        price: 749,  cat: 'bouquet',   occasion: ['anniversary','love','luxury'],                 url: '/bloom-aura/pages/shop.php?q=satin' },
    { name: 'Mixed Seasonal Bouquet',    price: 349,  cat: 'bouquet',   occasion: ['birthday','general','get well','friendship'],  url: '/bloom-aura/pages/shop.php?q=seasonal' },
    { name: 'Love & Bliss Gift Pack',    price: 1199, cat: 'hamper',    occasion: ['anniversary','wedding','romance'],             url: '/bloom-aura/pages/shop.php?cat=hampers' }
  ];

  /* ══════════════════════════════════════════════════════════
     SECTION 2 — CONVERSATION STATE
  ══════════════════════════════════════════════════════════ */

  var state = {
    discussed   : [],          // topics already covered this session
    flowActive  : false,       // are we in a guided multi-turn flow?
    flowStep    : null,        // current step name
    flowData    : {},          // collected data from guided flow
    msgCount    : 0,           // total messages exchanged
    isOpen      : false,       // widget open state
    greeted     : false,       // welcome message shown?
    proactiveDone : false      // proactive nudge already fired?
  };

  /* ══════════════════════════════════════════════════════════
     SECTION 3 — KNOWLEDGE BASE
     Each entry has: patterns[], response (string or fn), 
     followUps[] (quick button labels for next step),
     topic (string — for memory tracking)
  ══════════════════════════════════════════════════════════ */

  var KB = [

    /* ── Greetings ── */
    {
      topic: 'greeting',
      patterns: ['hello', 'hi', 'hey', 'good morning', 'good evening', 'good afternoon', 'howdy'],
      response: function () {
        if (IS_LOGGED_IN) {
          return '🌸 Welcome back, <strong>' + USER_NAME + '</strong>! Great to see you. How can I help you today?';
        }
        return '🌸 Hello! Welcome to Bloom Aura! I\'m here to help you find the perfect bouquet or gift. What can I do for you?';
      },
      followUps: ['💐 Browse bouquets', '🎁 Gift ideas', '🚚 Delivery info', '💰 Pricing']
    },

    /* ── Bouquets / products ── */
    {
      topic: 'products',
      patterns: ['bouquet', 'flower', 'product', 'what do you sell', 'what do you have', 'collection', 'catalogue', 'catalog'],
      response: '💐 We have a gorgeous range! Bouquets, gift hampers, chocolate gifts, perfumes, satin roses, and custom arrangements. Want me to help you find something for a specific <strong>occasion</strong> or <strong>budget</strong>?',
      followUps: ['🎂 For a birthday', '💍 For anniversary', '💰 Under ₹500', '✍️ Custom bouquet']
    },

    /* ── Pricing ── */
    {
      topic: 'pricing',
      patterns: ['price', 'cost', 'how much', 'cheap', 'expensive', 'budget', 'affordable', 'rates', 'starting'],
      response: '💰 Our range starts at just <strong>₹299</strong>! Here\'s a quick overview:<br>• 💐 Bouquets: ₹299–₹1,299<br>• 🎁 Hampers: ₹899–₹1,699<br>• 🍫 Chocolates: ₹899–₹1,499<br>• 🧴 Perfumes: ₹999–₹2,499<br><br><a href="/bloom-aura/pages/shop.php">View full shop →</a>',
      followUps: ['Under ₹500', 'Under ₹1000', '🎁 Best value picks', '🚚 Delivery charges?']
    },

    /* ── Delivery ── */
    {
      topic: 'delivery',
      patterns: ['delivery', 'shipping', 'how long', 'when will i get', 'deliver', 'dispatch', 'arrive', 'time'],
      response: '🚚 We offer <strong>same-day delivery</strong> for orders placed before 2 PM! Standard delivery takes 1–2 days. Charges vary by location — free delivery on orders above ₹999.',
      followUps: ['Same-day available?', 'Delivery charges?', '📦 Track my order', '📍 My area?']
    },

    /* ── Same-day delivery follow-up ── */
    {
      topic: 'same_day',
      patterns: ['same day', 'same-day', 'today', 'urgent', 'express', 'asap', 'immediately'],
      response: '⚡ Yes! Same-day delivery is available for orders placed <strong>before 2 PM</strong> in select pin codes. Place your order early and we\'ll make it happen! 🌸',
      followUps: ['🛒 Shop now', '📞 Contact us', '📦 Track order', '💰 Check prices']
    },

    /* ── Delivery charges ── */
    {
      topic: 'delivery_charges',
      patterns: ['delivery charge', 'delivery fee', 'delivery cost', 'shipping charge', 'shipping fee', 'free delivery', 'free shipping'],
      response: '📦 Delivery charges:<br>• <strong>Free delivery</strong> on orders above ₹999 🎉<br>• Standard delivery: ₹49–₹99 depending on location<br>• Express/same-day: ₹79–₹149',
      followUps: ['🛒 Shop to get free delivery', '🚚 Delivery time?', '📍 Check my area', '💐 Browse bouquets']
    },

    /* ── Order tracking ── */
    {
      topic: 'order_tracking',
      patterns: ['track', 'my order', 'order status', 'where is', 'order history', 'past order'],
      response: function () {
        if (IS_LOGGED_IN) {
          return '📦 You can track all your orders in your <a href="/bloom-aura/pages/order-history.php">Order History</a> page, ' + USER_NAME + '! Click the link to see live status updates.';
        }
        return '📦 To track your order, you\'ll need to be logged in. <a href="/bloom-aura/pages/login.php">Login here →</a> and then visit your Order History.';
      },
      followUps: ['📦 Order history', '📞 Contact support', '🚚 Delivery info', '🌸 Shop more']
    },

    /* ── Specific flowers ── */
    {
      topic: 'roses',
      patterns: ['rose', 'roses', 'red rose', 'pink rose'],
      response: '🌹 We have stunning rose bouquets — red, pink, white, ivory and mixed colours! Prices from <strong>₹599</strong>. Satin/forever roses also available! <a href="/bloom-aura/pages/shop.php?q=rose">See rose collection →</a>',
      followUps: ['💍 For anniversary', '💌 For Valentine\'s', '🎁 Add chocolates?', '💰 What\'s the price?']
    },
    {
      topic: 'tulips',
      patterns: ['tulip', 'tulips'],
      response: '🌷 Our pink tulip bouquets are a bestseller! Perfect for birthdays and special occasions. Starting at <strong>₹499</strong>. <a href="/bloom-aura/pages/shop.php?q=tulip">View tulips →</a>',
      followUps: ['🎂 Birthday bouquets', '💐 Other flowers', '🎁 Add a hamper?', '💰 Check prices']
    },
    {
      topic: 'sunflowers',
      patterns: ['sunflower', 'sunflowers'],
      response: '🌻 Sunflowers bring such joy! We have beautiful sunflower bunches starting at <strong>₹399</strong>. Great for cheering someone up! <a href="/bloom-aura/pages/shop.php?q=sunflower">View sunflowers →</a>',
      followUps: ['🎂 For a birthday', '🌸 Get well soon', '💐 Mixed bouquets', '🎁 Add chocolates?']
    },

    /* ── Gift types ── */
    {
      topic: 'hampers',
      patterns: ['hamper', 'gift box', 'gift set', 'gift basket', 'gift pack'],
      response: '🎁 Our gift hampers are perfect for any occasion! We have Birthday, Anniversary, Eid Special, and General hampers. Starting at <strong>₹1,199</strong>. <a href="/bloom-aura/pages/shop.php?cat=hampers">View hampers →</a>',
      followUps: ['🎂 Birthday hamper', '💍 Anniversary hamper', '💰 Under ₹1500', '🍫 Add chocolates?']
    },
    {
      topic: 'chocolates',
      patterns: ['chocolate', 'choco', 'ferrero', 'cadbury', 'lindt', 'toblerone', 'sweet'],
      response: '🍫 We have amazing chocolate gifts! Options include Cadbury, Ferrero Rocher, Toblerone, and Lindt. Standalone boxes or paired with flowers. From <strong>₹899</strong>. <a href="/bloom-aura/pages/shop.php?cat=chocolates">View chocolates →</a>',
      followUps: ['🌹 Flowers + chocolates?', '🎁 Gift hampers', '💰 Under ₹1000', '💍 For anniversary']
    },
    {
      topic: 'perfumes',
      patterns: ['perfume', 'fragrance', 'scent', 'attar', 'cologne', 'swiss arabian', 'ajmal'],
      response: '🧴 Our perfume collections are luxurious! We carry floral, woody, and fruity scents from Ajmal, Rasasi, Swiss Arabian, and more. From <strong>₹999</strong>. <a href="/bloom-aura/pages/shop.php?cat=perfumes">View perfumes →</a>',
      followUps: ['🌹 Perfume + flowers combo', '💍 For anniversary', '💰 Under ₹1500', '🎁 As a gift?']
    },

    /* ── Occasions ── */
    {
      topic: 'birthday',
      patterns: ['birthday', 'bday', 'birth day', 'happy birthday'],
      response: function () { return buildOccasionResponse('birthday'); },
      followUps: ['💐 More birthday ideas', '🎁 Birthday hampers', '🍫 Add chocolates', '💰 Under ₹500']
    },
    {
      topic: 'anniversary',
      patterns: ['anniversary', 'wedding anniversary'],
      response: function () { return buildOccasionResponse('anniversary'); },
      followUps: ['💍 Luxury options', '🌹 Rose bouquets', '🧴 Perfume combos', '💰 Budget options']
    },
    {
      topic: 'wedding',
      patterns: ['wedding', 'bridal', 'bride', 'groom', 'shaadi', 'nikah', 'reception'],
      response: function () { return buildOccasionResponse('wedding'); },
      followUps: ['💍 View arrangements', '✍️ Custom bouquet', '📞 Contact us', '🌹 Rose options']
    },
    {
      topic: 'valentine',
      patterns: ["valentine", "valentines", "valentine's day", 'february 14', 'v-day'],
      response: function () { return buildOccasionResponse('love'); },
      followUps: ['🌹 Red roses', '💍 Premium gifts', '🍫 Chocolates + flowers', '💌 Add a card?']
    },

    /* ── Custom bouquets ── */
    {
      topic: 'custom',
      patterns: ['custom', 'customise', 'customize', 'personalise', 'personalize', 'build my own', 'create my own', 'own bouquet'],
      response: '✍️ Love it! Our bouquet builder lets you choose your base flowers, size, wrapping style, and add chocolates or extras. <a href="/bloom-aura/pages/customize.php">Start customising →</a>',
      followUps: ['🌸 Start building', '💰 Custom prices?', '📞 Need help?', '💐 View ready bouquets']
    },

    /* ── Cart & checkout ── */
    {
      topic: 'cart',
      patterns: ['cart', 'basket', 'add to cart', 'checkout', 'buying', 'purchase'],
      response: function () {
        if (PAGE === 'cart') {
          return '🛒 I can see you\'re on your cart page! Once you\'re ready, click <strong>Place Order</strong> and fill in your delivery address. Need help with anything specific?';
        }
        return '🛒 You can add any bouquet to your cart from the shop page. <a href="/bloom-aura/pages/cart.php">View your cart →</a>';
      },
      followUps: ['💳 Payment methods?', '🚚 Delivery info', '🎁 Add gift wrap?', '💐 Keep shopping']
    },

    /* ── Payment ── */
    {
      topic: 'payment',
      patterns: ['payment', 'pay', 'how to pay', 'cod', 'cash on delivery', 'upi', 'card', 'online payment', 'gpay', 'phonepe'],
      response: '💳 We accept multiple payment methods:<br>• 💵 Cash on Delivery (COD)<br>• 📱 UPI (GPay, PhonePe, Paytm)<br>• 💳 Debit/Credit Cards<br>• 🏦 Net Banking<br><br>Select your preferred method at checkout.',
      followUps: ['🛒 Go to checkout', '🚚 Delivery info', '📦 Track order', '💐 Browse more']
    },

    /* ── Wishlist ── */
    {
      topic: 'wishlist',
      patterns: ['wishlist', 'wish list', 'save', 'favourite', 'favorite', 'saved items'],
      response: function () {
        if (IS_LOGGED_IN) {
          return '❤️ Great idea! You can save any bouquet to your wishlist by clicking the heart icon. <a href="/bloom-aura/pages/wishlist.php">View your wishlist, ' + USER_NAME + ' →</a>';
        }
        return '❤️ You can save bouquets to your wishlist when you\'re logged in. <a href="/bloom-aura/pages/login.php">Login →</a> or <a href="/bloom-aura/pages/register.php">create a free account</a>!';
      },
      followUps: ['❤️ View wishlist', '🛒 View cart', '💐 Browse shop', '👤 My profile']
    },

    /* ── Account ── */
    {
      topic: 'account',
      patterns: ['login', 'sign in', 'account', 'register', 'sign up', 'create account', 'log in', 'my account'],
      response: function () {
        if (IS_LOGGED_IN) {
          return '👤 You\'re already logged in as <strong>' + USER_NAME + '</strong>! You can view your <a href="/bloom-aura/pages/profile.php">profile →</a> or <a href="/bloom-aura/pages/order-history.php">order history →</a>.';
        }
        return '👤 You can <a href="/bloom-aura/pages/login.php">log in here</a> or <a href="/bloom-aura/pages/register.php">create a free account</a> to track orders, save wishlists, and more!';
      },
      followUps: ['📦 Track orders', '❤️ Wishlist', '👤 My profile', '🌸 Start shopping']
    },

    /* ── Cancellation / refunds ── */
    {
      topic: 'refund',
      patterns: ['cancel', 'cancellation', 'return', 'refund', 'exchange', 'money back'],
      response: '↩️ Orders can be cancelled within <strong>1 hour</strong> of placing. For refunds, returns, or exchanges, please contact us on WhatsApp or email within 24 hours of delivery.',
      followUps: ['📞 Contact support', '💬 WhatsApp us', '📦 Track order', '🌸 Continue shopping']
    },

    /* ── Contact / support ── */
    {
      topic: 'contact',
      patterns: ['contact', 'whatsapp', 'phone', 'call', 'email', 'support', 'help', 'customer care', 'reach'],
      response: '📞 We\'re here to help!<br>• 💬 <strong>WhatsApp</strong>: <a href="https://wa.me/919876543210" target="_blank">Chat with us →</a><br>• 📧 <strong>Email</strong>: hello@bloomaura.com<br>• ⏰ Available 9AM–9PM daily<br><br>We typically reply within <strong>1 hour</strong>!',
      followUps: ['💬 Open WhatsApp', '📦 Track order', '↩️ Returns?', '🌸 Back to shopping']
    },

    /* ── Promo codes ── */
    {
      topic: 'promo',
      patterns: ['promo', 'discount', 'coupon', 'offer', 'deal', 'code', 'voucher', 'bloom10'],
      response: '🎉 Use code <strong>BLOOM10</strong> at checkout for <strong>10% off</strong> your order! We also offer special discounts during festivals and for loyal customers. <a href="/bloom-aura/pages/shop.php">Shop now →</a>',
      followUps: ['🛒 Use code now', '💐 Browse offers', '📧 Subscribe for deals', '💰 Check prices']
    },

    /* ── Reviews / ratings ── */
    {
      topic: 'reviews',
      patterns: ['review', 'rating', 'feedback', 'testimonial', 'experience', 'quality'],
      response: '⭐ We\'re rated <strong>4.8/5</strong> by 500+ happy customers! You can leave a review on any product page after your purchase. We genuinely love your feedback 🌸',
      followUps: ['💐 Browse products', '📦 My orders', '📞 Leave feedback', '🌸 Shop now']
    },

    /* ── Thank you / bye ── */
    {
      topic: 'farewell',
      patterns: ['thank', 'thanks', 'thank you', 'bye', 'goodbye', 'see you', 'cheers', 'awesome'],
      response: function () {
        var name = IS_LOGGED_IN ? ', ' + USER_NAME : '';
        return '🌺 You\'re so welcome' + name + '! Have a wonderful day and happy gifting! Come back anytime 🌸';
      },
      followUps: ['💐 Browse shop', '🎁 Gift ideas', '📞 Contact us']
    }
  ];

  /* ── Occasion-based product recommender ── */
  function buildOccasionResponse(occasion) {
    var matches = PRODUCTS.filter(function (p) {
      return p.occasion.indexOf(occasion) !== -1;
    }).slice(0, 3);

    if (!matches.length) {
      return '🌸 We have beautiful options for that! <a href="/bloom-aura/pages/shop.php">Browse our full collection →</a>';
    }

    var html = '🌸 Here are my top picks for <strong>' + occasion + '</strong>:<br><br>';
    matches.forEach(function (p, i) {
      html += (i + 1) + '. <a href="' + p.url + '">' + p.name + '</a> — <strong>₹' + p.price + '</strong><br>';
    });
    html += '<br><a href="/bloom-aura/pages/shop.php">See all options →</a>';
    return html;
  }

  /* ── Price-range filter detector ── */
  function detectPriceQuery(input) {
    // Matches: "under 500", "below ₹500", "less than 500", "budget 500"
    var m = input.match(/(?:under|below|less than|max|upto|budget|within)\s*[₹rs.]?\s*(\d+)/i);
    if (m) return parseInt(m[1], 10);
    return null;
  }

  function buildPriceResponse(max) {
    var matches = PRODUCTS.filter(function (p) { return p.price <= max; });
    if (!matches.length) {
      return '😔 We don\'t currently have items under ₹' + max + ', but our cheapest bouquets start at ₹299! <a href="/bloom-aura/pages/shop.php">Browse shop →</a>';
    }
    var url = '/bloom-aura/pages/shop.php?max_price=' + max;
    var html = '💰 Here are options <strong>under ₹' + max + '</strong>:<br><br>';
    matches.slice(0, 4).forEach(function (p, i) {
      html += (i + 1) + '. <a href="' + p.url + '">' + p.name + '</a> — ₹' + p.price + '<br>';
    });
    html += '<br><a href="' + url + '">See all under ₹' + max + ' →</a>';
    return html;
  }

  /* ── Multi-turn guided recommendation flow ── */
  var FLOW = {

    /* Step 1: Start — ask occasion */
    start: function () {
      state.flowActive = true;
      state.flowStep   = 'askOccasion';
      return {
        text     : '🌸 I\'d love to help you find the perfect gift! What\'s the <strong>occasion</strong>?',
        buttons  : ['🎂 Birthday', '💍 Anniversary', '💌 Just because', '🎊 Other occasion']
      };
    },

    /* Step 2: Got occasion — ask style/budget */
    askOccasion: function (input) {
      var lower = input.toLowerCase();
      var occasion;
      if (lower.indexOf('birthday') !== -1 || lower.indexOf('bday') !== -1) occasion = 'birthday';
      else if (lower.indexOf('anniversary') !== -1)                          occasion = 'anniversary';
      else if (lower.indexOf('wedding') !== -1)                              occasion = 'wedding';
      else if (lower.indexOf('valentine') !== -1)                            occasion = 'love';
      else                                                                   occasion = 'general';

      state.flowData.occasion = occasion;
      state.flowStep = 'askBudget';

      return {
        text    : '🎁 Perfect! For a <strong>' + occasion + '</strong> — what\'s your <strong>budget</strong>?',
        buttons : ['💸 Under ₹500', '💰 ₹500–₹1000', '✨ ₹1000–₹1500', '👑 No limit!']
      };
    },

    /* Step 3: Got budget — show final recommendations */
    askBudget: function (input) {
      var lower = input.toLowerCase();
      var maxPrice;
      if      (lower.indexOf('500')  !== -1 && lower.indexOf('under')  !== -1) maxPrice = 500;
      else if (lower.indexOf('1000') !== -1)                                    maxPrice = 1000;
      else if (lower.indexOf('1500') !== -1)                                    maxPrice = 1500;
      else                                                                       maxPrice = 99999;

      state.flowData.budget = maxPrice;
      var occasion = state.flowData.occasion || 'general';

      /* Find products matching both occasion and budget */
      var matches = PRODUCTS.filter(function (p) {
        return p.price <= maxPrice && p.occasion.indexOf(occasion) !== -1;
      });
      /* Fallback: just price filter */
      if (!matches.length) {
        matches = PRODUCTS.filter(function (p) { return p.price <= maxPrice; });
      }
      matches = matches.slice(0, 3);

      state.flowActive = false;
      state.flowStep   = null;
      state.flowData   = {};

      var html;
      if (!matches.length) {
        html = '💐 I\'ll show you our full collection — I\'m sure you\'ll find something lovely! <a href="/bloom-aura/pages/shop.php">Browse shop →</a>';
      } else {
        html = '🌟 Here are my <strong>top picks</strong> for you:<br><br>';
        matches.forEach(function (p, i) {
          html += (i + 1) + '. <a href="' + p.url + '">' + p.name + '</a> — <strong>₹' + p.price + '</strong><br>';
        });
        html += '<br><a href="/bloom-aura/pages/shop.php">See all options →</a>';
      }

      return {
        text    : html,
        buttons : ['🛒 Add to cart', '🔄 Start over', '📞 Need help?', '💐 More options']
      };
    }
  };

  /* ── Default response with memory awareness ── */
  var DEFAULT_RESPONSE = function () {
    if (state.discussed.length > 2) {
      return '🤔 I\'m not quite sure about that one! You can <a href="/bloom-aura/pages/shop.php">browse our shop</a> or reach us on <a href="https://wa.me/919876543210" target="_blank">WhatsApp</a> for personalised help. 💐';
    }
    return '🌸 I didn\'t quite catch that! Try asking about our bouquets, delivery, pricing, or a specific occasion. Or type <em>"help"</em> for options!';
  };

  /* ══════════════════════════════════════════════════════════
     SECTION 4 — RESPONSE ENGINE
  ══════════════════════════════════════════════════════════ */

  /**
   * Match user input to a KB entry.
   * @param  {string} input
   * @returns {{ text: string, buttons: string[], topic: string }}
   */
  function getResponse(input) {
    var lower = input.toLowerCase().trim();

    /* ── 1. Check if we're in a guided multi-turn flow ── */
    if (state.flowActive && state.flowStep && FLOW[state.flowStep]) {
      var flowResult = FLOW[state.flowStep](input);
      return { text: flowResult.text, buttons: flowResult.buttons, topic: 'flow' };
    }

    /* ── 2. Trigger guided flow keywords ── */
    if (lower.indexOf('recommend') !== -1 || lower.indexOf('suggest') !== -1 ||
        lower.indexOf('help me choose') !== -1 || lower.indexOf('what should') !== -1) {
      var flowStart = FLOW.start();
      return { text: flowStart.text, buttons: flowStart.buttons, topic: 'flow_start' };
    }

    /* ── 3. Price range detection ── */
    var priceMax = detectPriceQuery(lower);
    if (priceMax) {
      return {
        text    : buildPriceResponse(priceMax),
        buttons : ['🛒 Shop now', '🔄 Different budget', '💐 Browse all', '🎁 Gift ideas'],
        topic   : 'price_filter'
      };
    }

    /* ── 4. Help keyword — show all topics ── */
    if (lower === 'help' || lower === 'options' || lower === 'what can you do') {
      return {
        text    : '🌸 I can help with:<br>• 💐 Bouquet & product info<br>• 🚚 Delivery & shipping<br>• 💰 Pricing & budget filters<br>• 🎁 Occasion-based recommendations<br>• 📦 Order tracking<br>• 💳 Payment methods<br>• 📞 Contact & support<br><br>Just ask!',
        buttons : ['💐 Bouquets', '🚚 Delivery', '🎂 Gift guide', '📦 My orders'],
        topic   : 'help'
      };
    }

    /* ── 5. Memory: check if topic already discussed ── */
    /* ── 6. Knowledge base match ── */
    for (var i = 0; i < KB.length; i++) {
      var entry = KB[i];
      for (var j = 0; j < entry.patterns.length; j++) {
        if (lower.indexOf(entry.patterns[j]) !== -1) {
          var text = typeof entry.response === 'function' ? entry.response() : entry.response;

          /* If topic already mentioned — prefix with memory note */
          if (state.discussed.indexOf(entry.topic) !== -1) {
            var memPrefixes = [
              'As I mentioned earlier — ',
              'Just to recap — ',
              'Circling back — '
            ];
            var prefix = memPrefixes[state.msgCount % memPrefixes.length];
            text = prefix + text;
          }

          /* Track this topic */
          if (state.discussed.indexOf(entry.topic) === -1) {
            state.discussed.push(entry.topic);
          }

          return {
            text    : text,
            buttons : entry.followUps || [],
            topic   : entry.topic
          };
        }
      }
    }

    /* ── 7. No match ── */
    return {
      text    : DEFAULT_RESPONSE(),
      buttons : ['💐 Browse shop', '📞 Contact us', '🎁 Gift guide', '🚚 Delivery info'],
      topic   : 'unknown'
    };
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 5 — SESSION STORAGE (History + Feedback)
  ══════════════════════════════════════════════════════════ */

  function loadHistory() {
    try {
      var raw = sessionStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) { return []; }
  }

  function saveHistory(messages) {
    try {
      /* Keep only last MAX_HISTORY messages */
      var trimmed = messages.slice(-MAX_HISTORY);
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(trimmed));
    } catch (e) { /* storage full or unavailable — silent fail */ }
  }

  function loadFeedback() {
    try {
      var raw = sessionStorage.getItem(FEEDBACK_KEY);
      return raw ? JSON.parse(raw) : {};
    } catch (e) { return {}; }
  }

  function saveFeedback(msgId, value) {
    try {
      var fb = loadFeedback();
      fb[msgId] = value;
      sessionStorage.setItem(FEEDBACK_KEY, JSON.stringify(fb));
    } catch (e) {}
  }

  /* In-memory message log for this page */
  var messageLog = [];

  /* ══════════════════════════════════════════════════════════
     SECTION 6 — DOM BUILDING
  ══════════════════════════════════════════════════════════ */

  function buildWidget() {
    var wrap     = document.createElement('div');
    wrap.id      = 'ba-chat-widget';
    /* data-theme will be set + watched by theme observer */
    wrap.setAttribute('data-theme', getTheme());

    wrap.innerHTML = [
      /* ── Toggle button ── */
      '<button id="ba-chat-toggle" aria-label="Open chat assistant" aria-expanded="false">',
      '  <span class="ba-chat-icon" aria-hidden="true">💬</span>',
      '  <span class="ba-chat-close-icon" aria-hidden="true">✕</span>',
      '  <span class="ba-chat-badge" id="ba-chat-badge" aria-live="polite"></span>',
      '</button>',

      /* ── Chat box ── */
      '<div id="ba-chat-box" role="dialog" aria-label="Bloom Aura Chat Assistant" aria-hidden="true">',

      '  <div class="ba-chat-header">',
      '    <div class="ba-chat-header-info">',
      '      <span class="ba-chat-avatar" aria-hidden="true">🌸</span>',
      '      <div>',
      '        <strong>Bloom Aura Assistant</strong>',
      '        <small>Typically replies instantly ⚡</small>',
      '      </div>',
      '    </div>',
      '    <button class="ba-chat-header-close" id="ba-chat-header-close" aria-label="Close chat">✕</button>',
      '  </div>',

      '  <div class="ba-chat-messages" id="ba-chat-messages" role="log" aria-live="polite" aria-relevant="additions">',
      '  </div>',

      '  <div class="ba-quick-wrap" id="ba-quick-wrap">',
      '    <div class="ba-chat-quick-btns" id="ba-quick-btns"></div>',
      '  </div>',

      '  <div class="ba-chat-input-wrap">',
      '    <input type="text" id="ba-chat-input"',
      '           placeholder="Ask me anything…"',
      '           autocomplete="off" maxlength="200"',
      '           aria-label="Type your message">',
      '    <button id="ba-chat-send" aria-label="Send message">',
      '      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="17" height="17">',
      '        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
      '      </svg>',
      '    </button>',
      '  </div>',

      '</div>'
    ].join('');

    document.body.appendChild(wrap);
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 7 — MESSAGE RENDERING
  ══════════════════════════════════════════════════════════ */

  var msgIdCounter = 0;

  /**
   * Append a message bubble to the chat window.
   * @param {string} text    — HTML (bot) or plain string (user)
   * @param {string} sender  — 'bot' | 'user'
   * @param {boolean} persist — save to sessionStorage
   */
  function addMessage(text, sender, persist) {
    var messages = document.getElementById('ba-chat-messages');
    if (!messages) return;

    msgIdCounter++;
    var msgId = 'ba-msg-' + Date.now() + '-' + msgIdCounter;

    var bubble = document.createElement('div');
    bubble.className  = 'ba-msg ba-msg--' + sender;
    bubble.id         = msgId;
    bubble.setAttribute('data-sender', sender);

    var inner = document.createElement('div');
    inner.className = 'ba-msg-bubble';

    if (sender === 'bot') {
      inner.innerHTML = text; /* bot strings are hardcoded — safe */
    } else {
      inner.textContent = text; /* user input — always textContent (XSS safe) */
    }

    bubble.appendChild(inner);

    /* ── Reaction buttons (bot messages only) ── */
    if (sender === 'bot') {
      var reactions = document.createElement('div');
      reactions.className = 'ba-reactions';
      reactions.setAttribute('aria-label', 'Was this helpful?');

      var fb = loadFeedback();

      var thumbUp   = document.createElement('button');
      thumbUp.className = 'ba-react-btn ba-react-up' + (fb[msgId] === 'up' ? ' active' : '');
      thumbUp.setAttribute('aria-label', 'Helpful');
      thumbUp.setAttribute('title', 'This was helpful');
      thumbUp.textContent = '👍';

      var thumbDown = document.createElement('button');
      thumbDown.className = 'ba-react-btn ba-react-dn' + (fb[msgId] === 'down' ? ' active' : '');
      thumbDown.setAttribute('aria-label', 'Not helpful');
      thumbDown.setAttribute('title', 'Not helpful');
      thumbDown.textContent = '👎';

      /* Reaction click handlers */
      thumbUp.addEventListener('click', function () {
        saveFeedback(msgId, 'up');
        thumbUp.classList.add('active');
        thumbDown.classList.remove('active');
        thumbUp.classList.add('ba-react-animate');
        setTimeout(function () { thumbUp.classList.remove('ba-react-animate'); }, 400);
      });
      thumbDown.addEventListener('click', function () {
        saveFeedback(msgId, 'down');
        thumbDown.classList.add('active');
        thumbUp.classList.remove('active');
        thumbDown.classList.add('ba-react-animate');
        setTimeout(function () { thumbDown.classList.remove('ba-react-animate'); }, 400);
        /* Offer escalation after negative feedback */
        setTimeout(function () {
          addMessage('😔 Sorry that wasn\'t helpful! You can <a href="https://wa.me/919876543210" target="_blank">chat with us on WhatsApp</a> for personalised support. 💬', 'bot', false);
        }, 600);
      });

      reactions.appendChild(thumbUp);
      reactions.appendChild(thumbDown);
      bubble.appendChild(reactions);
    }

    messages.appendChild(bubble);
    messages.scrollTop = messages.scrollHeight;

    /* ── Persist to sessionStorage ── */
    if (persist !== false) {
      messageLog.push({ sender: sender, text: text, id: msgId });
      saveHistory(messageLog);
    }

    state.msgCount++;
    return msgId;
  }

  /* ── Typing indicator ── */
  function showTyping() {
    var messages = document.getElementById('ba-chat-messages');
    if (!messages || document.getElementById('ba-typing')) return;
    var typing = document.createElement('div');
    typing.className = 'ba-msg ba-msg--bot ba-typing-indicator';
    typing.id        = 'ba-typing';
    typing.setAttribute('aria-label', 'Assistant is typing');
    typing.innerHTML = '<div class="ba-msg-bubble"><span></span><span></span><span></span></div>';
    messages.appendChild(typing);
    messages.scrollTop = messages.scrollHeight;
  }

  function hideTyping() {
    var t = document.getElementById('ba-typing');
    if (t) t.parentNode.removeChild(t);
  }

  /* ── Update quick action buttons ── */
  function setQuickButtons(buttons) {
    var wrap = document.getElementById('ba-quick-btns');
    if (!wrap) return;
    wrap.innerHTML = '';

    if (!buttons || !buttons.length) {
      wrap.parentElement.style.display = 'none';
      return;
    }

    wrap.parentElement.style.display = '';
    buttons.forEach(function (label) {
      var btn = document.createElement('button');
      btn.className       = 'ba-quick-btn';
      btn.textContent     = label;
      btn.setAttribute('data-msg', label);
      wrap.appendChild(btn);
    });
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 8 — SEND LOGIC
  ══════════════════════════════════════════════════════════ */

  function sendMessage(text) {
    text = (text || '').trim();
    if (!text) return;

    /* Clear input */
    var input = document.getElementById('ba-chat-input');
    if (input) input.value = '';

    /* Hide quick buttons while processing */
    setQuickButtons([]);

    /* Add user bubble */
    addMessage(text, 'user');

    /* Typing delay for natural feel */
    showTyping();
    setTimeout(function () {
      hideTyping();
      var result = getResponse(text);
      addMessage(result.text, 'bot');
      /* Set contextual follow-up buttons */
      setQuickButtons(result.buttons);
    }, TYPING_DELAY);
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 9 — OPEN / CLOSE WIDGET
  ══════════════════════════════════════════════════════════ */

  function toggleChat(forceOpen) {
    var box    = document.getElementById('ba-chat-box');
    var toggle = document.getElementById('ba-chat-toggle');
    var badge  = document.getElementById('ba-chat-badge');
    if (!box || !toggle) return;

    var shouldOpen = (forceOpen !== undefined) ? forceOpen : !state.isOpen;

    if (shouldOpen) {
      box.setAttribute('aria-hidden', 'false');
      box.classList.add('ba-chat-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.classList.add('is-open');
      if (badge) badge.textContent = '';
      state.isOpen = true;
      /* Focus input */
      var inp = document.getElementById('ba-chat-input');
      if (inp) setTimeout(function () { inp.focus(); }, 320);
    } else {
      box.setAttribute('aria-hidden', 'true');
      box.classList.remove('ba-chat-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.classList.remove('is-open');
      state.isOpen = false;
    }
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 10 — RESTORE HISTORY ON PAGE LOAD
  ══════════════════════════════════════════════════════════ */

  function restoreHistory() {
    var history = loadHistory();
    if (!history.length) return false;

    /* Mark as already greeted if history exists */
    state.greeted = true;

    history.forEach(function (msg) {
      addMessage(msg.text, msg.sender, false /* don't re-save */);
    });

    /* Restore in-memory log */
    messageLog = history.slice();

    /* Show a "session restored" separator */
    var messages = document.getElementById('ba-chat-messages');
    if (messages && history.length) {
      var sep = document.createElement('div');
      sep.className   = 'ba-history-sep';
      sep.textContent = '— Previous conversation —';
      /* Insert before the last few messages */
      messages.insertBefore(sep, messages.firstChild);
    }

    return true;
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 11 — PAGE-AWARE WELCOME MESSAGE
  ══════════════════════════════════════════════════════════ */

  function getWelcomeMessage() {
    var name = IS_LOGGED_IN ? ', <strong>' + USER_NAME + '</strong>' : '';

    switch (PAGE) {
      case 'cart':
        return '🛒 Hi' + name + '! I see you\'re on your cart. Ready to checkout, or need help with anything? I can assist with delivery info, payment methods, or promo codes!';
      case 'checkout':
        return '✅ Hi' + name + '! Almost there! Need help filling in your details, or have a promo code question? I\'m right here!';
      case 'product':
        return '🌸 Hi' + name + '! Want to know more about this bouquet? Ask me about customisation, delivery, or similar options!';
      case 'shop':
        return '💐 Hi' + name + '! Looking for something specific? Tell me the occasion or your budget and I\'ll find the perfect match!';
      case 'orders':
        return '📦 Hi' + name + '! Checking on your orders? I can help you understand order statuses or delivery timelines!';
      case 'profile':
        return '👤 Hi' + name + '! Managing your profile? Let me know if you need help with anything on your account!';
      default: /* home */
        if (IS_LOGGED_IN) {
          return '🌸 Welcome back, <strong>' + USER_NAME + '</strong>! Great to have you. Looking for something special today? 💐';
        }
        return '🌸 Hello! Welcome to Bloom Aura! I can help you find the perfect bouquet or gift. What\'s the occasion? 💐';
    }
  }

  /* ── Page-aware quick buttons on welcome ── */
  function getWelcomeButtons() {
    switch (PAGE) {
      case 'cart':     return ['💳 Payment methods', '🚚 Delivery info', '🎁 Add promo code', '💐 Keep shopping'];
      case 'checkout': return ['💳 Payment methods', '🚚 Delivery time', '🎁 Promo codes', '📞 Need help?'];
      case 'product':  return ['✍️ Customise this', '🚚 Delivery info', '💰 Price match?', '💐 Similar items'];
      case 'shop':     return ['🎂 For birthday', '💍 For anniversary', '💰 Under ₹500', '🎁 Gift guide'];
      default:         return ['💐 Browse bouquets', '🎁 Gift ideas', '🚚 Delivery info', '💰 Pricing'];
    }
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 12 — PROACTIVE TRIGGER MESSAGES
  ══════════════════════════════════════════════════════════ */

  function scheduleProactive() {
    var delay = PROACTIVE_DELAY[PAGE] || PROACTIVE_DELAY.home;

    var proactiveMessages = {
      home     : '🌸 Hey there! Need help choosing the perfect bouquet? Tell me the occasion and I\'ll find something beautiful! 💐',
      shop     : '💐 Looking for something specific? I can filter by occasion, budget, or flower type — just ask!',
      cart     : '🛒 Taking your time? No rush! If you have questions about delivery, payment, or need a promo code — I\'m here!',
      product  : '✨ Loving this bouquet? I can tell you about customisation options, delivery times, or similar picks!',
      checkout : '⚡ Need a hand at checkout? Try promo code <strong>BLOOM10</strong> for 10% off!'
    };

    var msg = proactiveMessages[PAGE] || proactiveMessages.home;

    setTimeout(function () {
      /* Only fire if chat is closed and not yet done */
      if (!state.isOpen && !state.proactiveDone) {
        state.proactiveDone = true;

        /* Show badge on button */
        var badge = document.getElementById('ba-chat-badge');
        if (badge) badge.textContent = '1';

        /* Pulse animation on toggle button */
        var toggle = document.getElementById('ba-chat-toggle');
        if (toggle) {
          toggle.classList.add('ba-pulse');
          setTimeout(function () { toggle.classList.remove('ba-pulse'); }, 3000);
        }

        /* Pre-load message so it appears instantly on open */
        messageLog.push({ sender: 'bot', text: msg, id: 'proactive-1' });
        saveHistory(messageLog);
        state.greeted = true;
      }
    }, delay);
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 13 — THEME AWARENESS (future-proof)
  ══════════════════════════════════════════════════════════ */

  function getTheme() {
    /* Check <html data-theme="light"> — not set yet, but ready */
    var htmlTheme = document.documentElement.getAttribute('data-theme');
    if (htmlTheme) return htmlTheme;
    /* Check prefers-color-scheme as fallback */
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) return 'light';
    return 'dark'; /* Bloom Aura default */
  }

  function applyTheme(theme) {
    var widget = document.getElementById('ba-chat-widget');
    if (widget) widget.setAttribute('data-theme', theme);
  }

  function watchTheme() {
    /* MutationObserver on <html> for when you add theme toggle later */
    if (typeof MutationObserver === 'undefined') return;
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (m) {
        if (m.attributeName === 'data-theme') {
          applyTheme(getTheme());
        }
      });
    });
    observer.observe(document.documentElement, { attributes: true });

    /* Also watch prefers-color-scheme */
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', function () {
        applyTheme(getTheme());
      });
    }
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 14 — EVENT LISTENERS
  ══════════════════════════════════════════════════════════ */

  function bindEvents() {

    /* Toggle open/close */
    document.getElementById('ba-chat-toggle').addEventListener('click', function () {
      toggleChat();

      /* On first open — show welcome or restore history */
      if (state.isOpen && !state.greeted) {
        state.greeted = true;
        var hasHistory = restoreHistory();
        if (!hasHistory) {
          setTimeout(function () {
            var welcome = getWelcomeMessage();
            addMessage(welcome, 'bot');
            setQuickButtons(getWelcomeButtons());
          }, 350);
        } else {
          /* History restored — just show contextual follow-up */
          setTimeout(function () {
            addMessage('👋 Welcome back! Continuing where we left off. How can I help?', 'bot');
            setQuickButtons(getWelcomeButtons());
          }, 350);
        }
      }
    });

    /* Header close button */
    document.getElementById('ba-chat-header-close').addEventListener('click', function () {
      toggleChat(false);
    });

    /* Send button */
    document.getElementById('ba-chat-send').addEventListener('click', function () {
      var input = document.getElementById('ba-chat-input');
      if (input) sendMessage(input.value);
    });

    /* Enter key */
    document.getElementById('ba-chat-input').addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(this.value);
      }
    });

    /* Quick button clicks (event delegation) */
    document.getElementById('ba-quick-btns').addEventListener('click', function (e) {
      var btn = e.target.closest('.ba-quick-btn');
      if (btn) {
        var msg = btn.getAttribute('data-msg') || btn.textContent;
        sendMessage(msg);
      }
    });

    /* Close on outside click */
    document.addEventListener('click', function (e) {
      var widget = document.getElementById('ba-chat-widget');
      if (state.isOpen && widget && !widget.contains(e.target)) {
        toggleChat(false);
      }
    });

    /* Escape key closes */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && state.isOpen) toggleChat(false);
    });
  }

  /* ══════════════════════════════════════════════════════════
     SECTION 15 — INIT
  ══════════════════════════════════════════════════════════ */

  function init() {
    buildWidget();
    bindEvents();
    watchTheme();
    scheduleProactive();

    /* Show notification badge after 3s if chat is closed */
    setTimeout(function () {
      var badge = document.getElementById('ba-chat-badge');
      var box   = document.getElementById('ba-chat-box');
      if (badge && box && !state.isOpen) {
        badge.textContent = '1';
      }
    }, 3000);
  }

  /* ── Bootstrap ── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

}());