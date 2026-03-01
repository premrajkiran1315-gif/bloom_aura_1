<?php
/**
 * bloom-aura/pages/login.php
 * Combined Login + Register page — dark UI matching reference design.
 * Handles both sign-in and sign-up POST actions via ?tab= param.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /bloom-aura/pages/shop.php');
    exit;
}

const MAX_ATTEMPTS = 5;
const LOCKOUT_MINS = 15;

function isRateLimited(PDO $pdo, string $email, string $ip): bool {
    $window = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_MINS . ' minutes'));
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE (email = ? OR ip_address = ?) AND attempted_at > ?'
    );
    $stmt->execute([$email, $ip, $window]);
    return (int)$stmt->fetchColumn() >= MAX_ATTEMPTS;
}

function recordFailedAttempt(PDO $pdo, string $email, string $ip): void {
    $pdo->prepare(
        'INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())'
    )->execute([$email, $ip]);
}

// ── Which tab to show ────────────────────────────────────────
$activeTab   = $_GET['tab'] ?? 'signin';
$loginErrors = [];
$signupErrors= [];
$oldLogin    = ['email' => ''];
$oldSignup   = ['name' => '', 'email' => ''];

// ── Handle SIGN IN ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'login') {
    csrf_validate();
    $activeTab = 'signin';

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $oldLogin = ['email' => $email];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loginErrors['email'] = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $loginErrors['password'] = 'Please enter your password.';
    } else {
        try {
            $pdo = getPDO();
            if (isRateLimited($pdo, $email, $ip)) {
                $loginErrors['general'] = 'Too many failed attempts. Please wait ' . LOCKOUT_MINS . ' minutes.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT id, name, email, password_hash, role, is_active
                     FROM users WHERE email = ? AND role = "customer" LIMIT 1'
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    recordFailedAttempt($pdo, $email, $ip);
                    $loginErrors['general'] = 'Incorrect email or password.';
                } elseif (!$user['is_active']) {
                    $loginErrors['general'] = 'Your account has been deactivated. Please contact support.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $user['role'];
                    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
                    $redirect = $_SESSION['redirect_after_login'] ?? '/bloom-aura/pages/shop.php';
                    unset($_SESSION['redirect_after_login']);
                    flash('Welcome back, ' . $user['name'] . '! 🌸', 'success');
                    header('Location: ' . $redirect);
                    exit;
                }
            }
        } catch (RuntimeException $e) {
            $loginErrors['general'] = 'A server error occurred. Please try again.';
        }
    }
}

// ── Handle SIGN UP ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'register') {
    csrf_validate();
    $activeTab = 'signup';

    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $oldSignup = compact('name', 'email');

    if ($name === '' || mb_strlen($name) < 2)
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

$pageTitle = 'Login — Bloom Aura';
$pageCss = 'auth';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container narrow-page">
    <div class="auth-card">

    <!-- Logo -->
    <a href="/bloom-aura/" class="auth-logo">
        🌸 <em>Bloom</em>&thinsp;Aura
    </a>

    <!-- Tab bar -->
    <div class="auth-tab-bar">
        <button class="auth-tab <?= $activeTab === 'signin' ? 'active' : '' ?>"
                id="ltab-signin"
                onclick="switchTab('signin')">Sign In</button>
        <button class="auth-tab <?= $activeTab === 'signup' ? 'active' : '' ?>"
                id="ltab-signup"
                onclick="switchTab('signup')">Create Account</button>
    </div>

    <!-- ══ SIGN IN PANEL ══ -->
    <div class="auth-panel <?= $activeTab === 'signin' ? 'active' : '' ?>" id="panel-signin">

        <div class="auth-header">
            <div class="auth-icon">🌸</div>
            <h2 class="auth-title">Welcome Back</h2>
            <p class="auth-subtitle">Sign in to your Bloom Aura account</p>
        </div>

        <!-- Social login (UI only) -->
        <div class=\"social-login\">
            <button class="social-btn" type="button" onclick="socialToast('Google')">
                <svg width="18" height="18" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </button>
        </div>

        <div class="form-divider"><span>or sign in with email</span></div>

        <!-- Error -->
        <?php if (!empty($loginErrors['general'])): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($loginErrors['general'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="/bloom-aura/pages/login.php" method="POST" novalidate>
            <?php csrf_field(); ?>
            <input type="hidden" name="form_action" value="login">

            <!-- Email -->
            <div class="form-group">
                <label class="field-label" for="login-email">Email Address</label>
                <div class="field-icon-wrap">
                    <span class="field-icon">📧</span>
                    <input type="email" id="login-email" name="email"
                           value="<?= htmlspecialchars($oldLogin['email'], ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="you@example.com"
                           autocomplete="email" required>
                    <span class="field-icon">🌸</span>
                </div>
                <?php if (!empty($loginErrors['email'])): ?>
                    <span class="field-error">❌ <?= htmlspecialchars($loginErrors['email'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="field-label" for="login-pass">Password</label>
                <div class="field-icon-wrap">
                    <span class="field-icon">🔒</span>
                    <input type="password" id="login-pass" name="password"
                           placeholder="Your password"
                           autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" onclick="togglePass('login-pass', this)"
                            aria-label="Show password">👁</button>
                </div>
                <?php if (!empty($loginErrors['password'])): ?>
                    <span class="field-error">❌ <?= htmlspecialchars($loginErrors['password'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <div class="form-forgot-row">
                <a href="#">Forgot password? 💌</a>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Sign In →</button>
        </form>

        <div class="form-footer-text">
            No account? <a href="#" onclick="switchTab('signup');return false;">Create one free →</a>
        </div>
        <div class="form-bottom-links">
            <button onclick="window.location='/bloom-aura/'">← Back to home</button>
            <button onclick="window.location='/bloom-aura/pages/shop.php'">Browse as guest</button>
        </div>
    </div>

    <!-- ══ SIGN UP PANEL ══ -->
    <div class="auth-panel <?= $activeTab === 'signup' ? 'active' : '' ?>" id="panel-signup">

        <div class="auth-header">
            <div class="auth-icon">🌷</div>
            <h2 class="auth-title">Create Account</h2>
            <p class="auth-subtitle">Join Bloom Aura and start gifting</p>
        </div>

        <!-- DB / general error -->
        <?php if (!empty($signupErrors['db'])): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($signupErrors['db'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="/bloom-aura/pages/login.php?tab=signup" method="POST" novalidate>
            <?php csrf_field(); ?>
            <input type="hidden" name="form_action" value="register">

            <!-- Name -->
            <div class="form-group">
                <label class="field-label" for="signup-name">Full Name</label>
                <div class="field-icon-wrap">
                    <span class="field-icon">👤</span>
                    <input type="text" id="signup-name" name="name"
                           value="<?= htmlspecialchars($oldSignup['name'], ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Your full name"
                           autocomplete="name" required>
                    <span class="field-icon">🌸</span>
                </div>
                <?php if (!empty($signupErrors['name'])): ?>
                    <span class="field-error">❌ <?= htmlspecialchars($signupErrors['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label class="field-label" for="signup-email">Email Address</label>
                <div class="field-icon-wrap">
                    <span class="field-icon">📧</span>
                    <input type="email" id="signup-email" name="email"
                           value="<?= htmlspecialchars($oldSignup['email'], ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="you@example.com"
                           autocomplete="email" required>
                </div>
                <?php if (!empty($signupErrors['email'])): ?>
                    <span class="field-error">❌ <?= htmlspecialchars($signupErrors['email'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="field-label" for="signup-pass">Password</label>
                <div class="field-icon-wrap">
                    <span class="field-icon">🔒</span>
                    <input type="password" id="signup-pass" name="password"
                           placeholder="Create a password (min 8 chars)"
                           autocomplete="new-password" required minlength="8"
                           oninput="passHint(this)">
                    <button type="button" class="pw-toggle" onclick="togglePass('signup-pass', this)"
                            aria-label="Show password">👁</button>
                </div>
                <div class="field-hint" id="signup-pass-hint">8+ characters</div>
                <?php if (!empty($signupErrors['password'])): ?>
                    <span class="field-error">❌ <?= htmlspecialchars($signupErrors['password'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <!-- Confirm password -->
            <div class="form-group">
                <label class="field-label" for="signup-confirm">Confirm Password</label>
                <div class="field-icon-wrap">
                    <span class="field-icon">🔒</span>
                    <input type="password" id="signup-confirm" name="confirm"
                           placeholder="Repeat your password"
                           autocomplete="new-password" required>
                    <button type="button" class="pw-toggle" onclick="togglePass('signup-confirm', this)"
                            aria-label="Show password">👁</button>
                </div>
                <?php if (!empty($signupErrors['confirm'])): ?>
                    <span class="field-error">❌ <?= htmlspecialchars($signupErrors['confirm'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Create Account 🌸</button>
        </form>

        <div class="form-footer-text">
            Already have an account? <a href="#" onclick="switchTab('signin');return false;">Sign in →</a>
        </div>
        <div class="form-bottom-links">
            <button onclick="window.location='/bloom-aura/'">← Back to home</button>
        </div>
    </div>

</div><!-- /.auth-card -->
</div><!-- /.page-container -->

<script>
function switchTab(tab) {
    document.getElementById('panel-signin').classList.toggle('active', tab === 'signin');
    document.getElementById('panel-signup').classList.toggle('active', tab === 'signup');
    document.getElementById('ltab-signin').classList.toggle('active', tab === 'signin');
    document.getElementById('ltab-signup').classList.toggle('active', tab === 'signup');
}

function togglePass(id, btn) {
    var inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.textContent = '🙈';
    } else {
        inp.type = 'password';
        btn.textContent = '👁';
    }
}

function passHint(inp) {
    var hint = document.getElementById('signup-pass-hint');
    var len = inp.value.length;
    if (len === 0) { hint.textContent = '8+ characters'; hint.style.opacity = '.4'; }
    else if (len < 8) { hint.textContent = (8 - len) + ' more character' + ((8-len)===1?'':'s') + ' needed'; hint.style.color = '#fca5a5'; }
    else { hint.textContent = '✅ Looks good!'; hint.style.color = '#86efac'; }
}

function socialToast(provider) {
    alert('Social login is not connected yet.\nPlease use the email form below.');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>