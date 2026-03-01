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

        <?php /* Social buttons — Google + Apple (UI only, no real OAuth) */ ?>
        <div class="login-social-row">
          <button class="social-btn" type="button" onclick="socialToast('Google')">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Continue with Google
          </button>
          <button class="social-btn" type="button" onclick="socialToast('Apple')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="white" aria-hidden="true">
              <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
            </svg>
            Apple
          </button>
        </div>

        <div class="login-divider"><span>or sign in with email</span></div>

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