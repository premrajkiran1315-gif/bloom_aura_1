<?php
/**
 * bloom-aura-1/pages/login.php
 *
 * Combined Sign In + Create Account page.
 * UI exactly matches bloom_aura reference HTML (dark card, lfield-* classes,
 * Apple social button, bloom icon, pass hint, login-main-btn gradient, etc.)
 *
 * Security: CSRF, bcrypt verify, session_regenerate_id, brute-force lockout.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// Already logged in → redirect
if (!empty($_SESSION['user_id'])) {
    header('Location: /bloom-aura/pages/shop.php');
    exit;
}

/* ── which tab to show on page load ── */
$activeTab    = ($_GET['tab'] ?? 'signin') === 'signup' ? 'signup' : 'signin';

/* ── error/old-value bags ── */
$loginErrors  = [];
$signupErrors = [];
$oldLogin     = ['email' => ''];
$oldSignup    = ['name' => '', 'email' => ''];

/* ════════════════════════════════════════════════════
   HANDLE SIGN-IN
════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'login') {
    csrf_validate();
    $activeTab = 'signin';

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']       ?? '';
    $oldLogin = ['email' => $email];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loginErrors['email'] = 'Please enter a valid email address.';
    }
    if ($password === '') {
        $loginErrors['password'] = 'Password is required.';
    }

    if (empty($loginErrors)) {
        try {
            $pdo = getPDO();
            $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            /* ── brute-force: max 5 attempts in 15 min ── */
            $window = date('Y-m-d H:i:s', strtotime('-15 minutes'));
            $stmt   = $pdo->prepare(
                'SELECT COUNT(*) FROM login_attempts
                  WHERE email = ? AND ip_address = ? AND attempted_at > ?'
            );
            $stmt->execute([$email, $ip, $window]);

            if ((int)$stmt->fetchColumn() >= 5) {
                $loginErrors['general'] = 'Too many failed attempts. Please wait 15 minutes and try again.';
            } else {
                /* ── fetch user ── */
                $stmt = $pdo->prepare(
                    'SELECT id, name, password_hash, is_active
                       FROM users WHERE email = ? AND role = "customer" LIMIT 1'
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    /* log failed attempt */
                    $pdo->prepare(
                        'INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())'
                    )->execute([$email, $ip]);
                    $loginErrors['general'] = 'Incorrect email or password.';

                } elseif (!$user['is_active']) {
                    $loginErrors['general'] = 'Your account has been deactivated. Please contact support.';

                } else {
                    /* ── success ── */
                    session_regenerate_id(true);
                    $_SESSION['user_id']     = $user['id'];
                    $_SESSION['user_name']   = $user['name'];
                    $_SESSION['user_active'] = 1;

                    /* clear attempts */
                    $pdo->prepare(
                        'DELETE FROM login_attempts WHERE email = ?'
                    )->execute([$email]);

                    $redirect = $_SESSION['login_redirect'] ?? '/bloom-aura/pages/shop.php';
                    unset($_SESSION['login_redirect']);
                    header('Location: ' . $redirect);
                    exit;
                }
            }
        } catch (RuntimeException $e) {
            $loginErrors['general'] = 'A server error occurred. Please try again.';
        }
    }
}

/* ════════════════════════════════════════════════════
   HANDLE SIGN-UP
════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'register') {
    csrf_validate();
    $activeTab = 'signup';

    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']       ?? '';
    $confirm  = $_POST['confirm']        ?? '';
    $oldSignup = compact('name', 'email');

    if (mb_strlen($name) < 2)
        $signupErrors['name'] = 'Please enter your full name (at least 2 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $signupErrors['email'] = 'Please enter a valid email address.';
    if (strlen($password) < 8)
        $signupErrors['password'] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)
        $signupErrors['confirm'] = 'Passwords do not match.';

    if (empty($signupErrors['email'])) {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch())
                $signupErrors['email'] = 'An account with this email already exists.';
        } catch (RuntimeException $e) {
            $signupErrors['db'] = 'A server error occurred.';
        }
    }

    if (empty($signupErrors)) {
        try {
            $pdo  = getPDO();
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, is_active, created_at)
                 VALUES (?, ?, ?, "customer", 1, NOW())'
            )->execute([$name, $email, $hash]);

            flash('Account created! Welcome to Bloom Aura 🌸', 'success');
            header('Location: /bloom-aura/pages/login.php?tab=signin');
            exit;
        } catch (RuntimeException $e) {
            $signupErrors['db'] = 'A server error occurred. Please try again.';
        }
    }
}

/* ════════════════════════════════════════════════════
   RENDER
════════════════════════════════════════════════════ */
$pageTitle = 'Login — Bloom Aura';
$pageCss   = 'auth';
require_once __DIR__ . '/../includes/header.php';
?>

<?php /* ── Dark full-page wrapper — replaces normal page-container on this page ── */ ?>
<div class="login-page-wrap">

  <div class="login-page-bg"></div><?php /* radial glow orbs — styled in auth.css */ ?>

  <div class="login-page-inner">

    <?php /* ── Logo ── */ ?>
    <a href="/bloom-aura/" class="login-logo">🌸 <em>Bloom</em>&thinsp;Aura</a>

    <?php /* ── Card ── */ ?>
    <div class="login-card">

      <?php /* Flash messages (e.g. "Account created!") */ ?>
      <?php foreach ($flashMessages as $fm): ?>
        <div class="flash-msg flash-<?= htmlspecialchars($fm['type'], ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars($fm['msg'], ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endforeach; ?>

      <?php /* ── Tab bar ── */ ?>
      <div class="login-tab-bar">
        <button class="ltab <?= $activeTab === 'signin' ? 'active' : '' ?>"
                id="ltab-signin"
                onclick="switchLoginTab('signin')">Sign In</button>
        <button class="ltab <?= $activeTab === 'signup' ? 'active' : '' ?>"
                id="ltab-signup"
                onclick="switchLoginTab('signup')">Create Account</button>
      </div>

      <?php /* ════════ SIGN-IN PANEL ════════ */ ?>
      <div id="login-panel-signin" <?= $activeTab !== 'signin' ? 'style="display:none"' : '' ?>>

        <div class="login-panel-header">
          <div class="login-panel-icon">🌸</div>
          <h2 class="login-panel-title">Welcome Back</h2>
          <p class="login-panel-sub">Sign in to your Bloom Aura account</p>
        </div>

       

        <?php /* General / brute-force error */ ?>
        <?php if (!empty($loginErrors['general'])): ?>
          <div class="login-error-dark">❌ <?= htmlspecialchars($loginErrors['general'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="/bloom-aura/pages/login.php" method="POST" novalidate>
          <?php csrf_field(); ?>
          <input type="hidden" name="form_action" value="login">

          <?php /* Email field */ ?>
          <div class="lfield-wrap">
            <label class="lfield-label" for="login-email">Email Address</label>
            <div class="lfield">
              <span class="lfield-icon">📧</span>
              <input type="email" id="login-email" name="email"
                     value="<?= htmlspecialchars($oldLogin['email'], ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="you@example.com"
                     autocomplete="email" required>
              <span class="lfield-bloom">🌸</span>
            </div>
            <?php if (!empty($loginErrors['email'])): ?>
              <div class="field-error-dark">❌ <?= htmlspecialchars($loginErrors['email'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <?php /* Password field */ ?>
          <div class="lfield-wrap">
            <label class="lfield-label" for="login-pass">Password</label>
            <div class="lfield">
              <span class="lfield-icon">🔒</span>
              <input type="password" id="login-pass" name="password"
                     placeholder="Your password"
                     autocomplete="current-password" required>
              <button type="button" class="lfield-end" onclick="toggleLoginPass()" aria-label="Toggle password visibility">👁</button>
            </div>
            <?php if (!empty($loginErrors['password'])): ?>
              <div class="field-error-dark">❌ <?= htmlspecialchars($loginErrors['password'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <div class="forgot-row">
            <a href="#">Forgot password? 💌</a>
          </div>

          <button type="submit" class="login-main-btn">Sign In →</button>
        </form>

        <div class="login-footer-text">
          No account? <a href="#" onclick="switchLoginTab('signup');return false;">Create one free →</a>
        </div>
        <div class="login-bottom-links">
          <button type="button" onclick="window.location='/bloom-aura/'">← Back to home</button>
          <span>·</span>
          <button type="button" onclick="window.location='/bloom-aura/pages/shop.php'">Browse as guest</button>
        </div>

      </div><?php /* /login-panel-signin */ ?>

      <?php /* ════════ SIGN-UP PANEL ════════ */ ?>
      <div id="login-panel-signup" <?= $activeTab !== 'signup' ? 'style="display:none"' : '' ?>>

        <div class="login-panel-header">
          <div class="login-panel-icon">🌷</div>
          <h2 class="login-panel-title">Create Account</h2>
          <p class="login-panel-sub">Join Bloom Aura and start gifting</p>
        </div>

        <?php if (!empty($signupErrors['db'])): ?>
          <div class="login-error-dark">❌ <?= htmlspecialchars($signupErrors['db'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="/bloom-aura/pages/login.php?tab=signup" method="POST" novalidate>
          <?php csrf_field(); ?>
          <input type="hidden" name="form_action" value="register">

          <?php /* Full Name */ ?>
          <div class="lfield-wrap">
            <label class="lfield-label" for="signup-name">Full Name</label>
            <div class="lfield">
              <span class="lfield-icon">👤</span>
              <input type="text" id="signup-name" name="name"
                     value="<?= htmlspecialchars($oldSignup['name'], ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="Your full name"
                     autocomplete="name" required>
              <span class="lfield-bloom">🌸</span>
            </div>
            <?php if (!empty($signupErrors['name'])): ?>
              <div class="field-error-dark">❌ <?= htmlspecialchars($signupErrors['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <?php /* Email */ ?>
          <div class="lfield-wrap">
            <label class="lfield-label" for="signup-email">Email Address</label>
            <div class="lfield">
              <span class="lfield-icon">📧</span>
              <input type="email" id="signup-email" name="email"
                     value="<?= htmlspecialchars($oldSignup['email'], ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="you@example.com"
                     autocomplete="email" required>
            </div>
            <?php if (!empty($signupErrors['email'])): ?>
              <div class="field-error-dark">❌ <?= htmlspecialchars($signupErrors['email'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <?php /* Password */ ?>
          <div class="lfield-wrap">
            <label class="lfield-label" for="signup-pass">Password</label>
            <div class="lfield">
              <span class="lfield-icon">🔒</span>
              <input type="password" id="signup-pass" name="password"
                     placeholder="Create a password (8+ chars)"
                     oninput="signupPassHint(this)"
                     autocomplete="new-password" required>
              <button type="button" class="lfield-end" onclick="toggleSignupPass()" aria-label="Toggle password visibility">👁</button>
            </div>
            <div id="signup-pass-hint" class="pass-hint-new">8+ characters</div>
            <?php if (!empty($signupErrors['password'])): ?>
              <div class="field-error-dark">❌ <?= htmlspecialchars($signupErrors['password'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <?php /* Confirm Password */ ?>
          <div class="lfield-wrap">
            <label class="lfield-label" for="signup-confirm">Confirm Password</label>
            <div class="lfield">
              <span class="lfield-icon">🔒</span>
              <input type="password" id="signup-confirm" name="confirm"
                     placeholder="Repeat your password"
                     autocomplete="new-password" required>
            </div>
            <?php if (!empty($signupErrors['confirm'])): ?>
              <div class="field-error-dark">❌ <?= htmlspecialchars($signupErrors['confirm'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <button type="submit" class="login-main-btn">Create Account 🌸</button>
        </form>

        <div class="login-footer-text">
          Already have an account? <a href="#" onclick="switchLoginTab('signin');return false;">Sign in →</a>
        </div>
        <div class="login-bottom-links">
          <button type="button" onclick="window.location='/bloom-aura/'">← Back to home</button>
        </div>

      </div><?php /* /login-panel-signup */ ?>

    </div><?php /* /.login-card */ ?>
  </div><?php /* /.login-page-inner */ ?>
</div><?php /* /.login-page-wrap */ ?>

<script src="/bloom-aura/assets/js/login.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>