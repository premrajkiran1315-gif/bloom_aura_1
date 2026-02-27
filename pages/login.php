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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Bloom Aura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #120818;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Radial blob decorations — from reference */
        .login-bg::before {
            content: '';
            position: fixed;
            top: -10%; left: -10%;
            width: 60%; height: 60%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(214,51,132,.22) 0%, transparent 65%);
            pointer-events: none;
        }
        .login-bg::after {
            content: '';
            position: fixed;
            bottom: -10%; right: -10%;
            width: 55%; height: 55%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(173,20,87,.18) 0%, transparent 65%);
            pointer-events: none;
        }
        .login-bg { position: fixed; inset: 0; pointer-events: none; }

        /* Card */
        .login-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
            padding: 0 10px;
        }

        /* Top logo link */
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #e8a4b8;
            text-decoration: none;
            margin-bottom: 2rem;
        }
        .login-logo:hover { color: #ff79b0; text-decoration: none; }

        /* Tab bar */
        .login-tab-bar {
            background: rgba(255,255,255,.06);
            border-radius: 14px;
            padding: 5px;
            display: flex;
            gap: 4px;
            margin-bottom: 28px;
        }
        .ltab {
            flex: 1;
            padding: 11px;
            border: none;
            border-radius: 10px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .25s;
            font-family: inherit;
            background: transparent;
            color: rgba(255,255,255,.45);
        }
        .ltab.active {
            background: linear-gradient(135deg, #d63384, #e91e8c);
            color: white;
            box-shadow: 0 4px 16px rgba(214,51,132,.4);
        }

        /* Panel header */
        .panel-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .panel-icon { font-size: 2.8rem; margin-bottom: 10px; }
        .panel-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            margin-bottom: 6px;
        }
        .panel-sub { color: rgba(255,255,255,.38); font-size: .88rem; }

        /* Social buttons */
        .social-row { display: flex; gap: 10px; margin-bottom: 4px; }
        .social-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 13px 16px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 12px;
            color: white;
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            font-family: inherit;
        }
        .social-btn:hover {
            background: rgba(255,255,255,.13);
            border-color: rgba(255,255,255,.22);
            transform: translateY(-2px);
        }

        /* Divider */
        .login-divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 20px 0;
        }
        .login-divider::before, .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,.08);
        }
        .login-divider span {
            color: rgba(255,255,255,.28);
            font-size: .75rem;
            white-space: nowrap;
        }

        /* Field */
        .lfield-wrap { margin-bottom: 16px; }
        .lfield-label {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: rgba(255,255,255,.35);
            display: block;
            margin-bottom: 7px;
        }
        .lfield { position: relative; }
        .lfield-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            opacity: .45;
            color: white;
            pointer-events: none;
        }
        .lfield-end {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: .95rem;
            opacity: .3;
            color: white;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        .lfield-end:hover { opacity: .7; }
        .lfield input {
            width: 100%;
            background: rgba(255,255,255,.06);
            border: 1.5px solid rgba(255,255,255,.1);
            border-radius: 12px;
            padding: 13px 42px;
            color: white;
            font-size: .92rem;
            outline: none;
            font-family: inherit;
            transition: border-color .2s, background .2s;
        }
        .lfield input::placeholder { color: rgba(255,255,255,.22); }
        .lfield input:focus {
            border-color: #d63384;
            background: rgba(214,51,132,.07);
        }
        /* autofill dark */
        .lfield input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 30px #1e0c28 inset !important;
            -webkit-text-fill-color: white !important;
        }
        .field-hint-dark {
            font-size: .7rem;
            color: rgba(255,255,255,.28);
            margin-top: 5px;
            padding-left: 2px;
            min-height: 16px;
        }

        /* Error box */
        .error-box {
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.25);
            color: #fca5a5;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: .83rem;
            margin-bottom: 16px;
            font-weight: 600;
        }
        .field-error-dark {
            color: #fca5a5;
            font-size: .75rem;
            margin-top: 4px;
            padding-left: 2px;
        }

        /* Forgot link */
        .forgot-row {
            text-align: right;
            margin-bottom: 18px;
        }
        .forgot-row a {
            color: rgba(255,255,255,.38);
            font-size: .8rem;
            text-decoration: none;
            transition: color .2s;
        }
        .forgot-row a:hover { color: #ff79b0; }

        /* Main button */
        .login-main-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #d63384, #ff4d94);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 24px rgba(214,51,132,.4);
            transition: all .25s;
            font-family: inherit;
            letter-spacing: .3px;
        }
        .login-main-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(214,51,132,.55);
        }

        /* Footer text */
        .login-footer-text {
            text-align: center;
            color: rgba(255,255,255,.28);
            font-size: .82rem;
            margin-top: 20px;
        }
        .login-footer-text a {
            color: #ff79b0;
            font-weight: 700;
            text-decoration: none;
        }
        .login-footer-text a:hover { text-decoration: underline; }

        /* Back/guest links */
        .bottom-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 14px;
        }
        .bottom-links button {
            background: none;
            border: none;
            color: rgba(255,255,255,.22);
            font-size: .78rem;
            cursor: pointer;
            font-family: inherit;
            transition: color .2s;
        }
        .bottom-links button:hover { color: rgba(255,255,255,.5); }

        /* Hidden panel */
        .login-panel { display: none; }
        .login-panel.active { display: block; }
    </style>
</head>
<body>

<div class="login-bg"></div>

<div class="login-card">

    <!-- Logo -->
    <a href="/bloom-aura/" class="login-logo">
        🌸 <em>Bloom</em>&thinsp;Aura
    </a>

    <!-- Tab bar -->
    <div class="login-tab-bar">
        <button class="ltab <?= $activeTab === 'signin' ? 'active' : '' ?>"
                id="ltab-signin"
                onclick="switchTab('signin')">Sign In</button>
        <button class="ltab <?= $activeTab === 'signup' ? 'active' : '' ?>"
                id="ltab-signup"
                onclick="switchTab('signup')">Create Account</button>
    </div>

    <!-- ══ SIGN IN PANEL ══ -->
    <div class="login-panel <?= $activeTab === 'signin' ? 'active' : '' ?>" id="panel-signin">

        <div class="panel-header">
            <div class="panel-icon">🌸</div>
            <h2 class="panel-title">Welcome Back</h2>
            <p class="panel-sub">Sign in to your Bloom Aura account</p>
        </div>

        <!-- Social login (UI only) -->
        <div class="social-row">
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

        <div class="login-divider"><span>or sign in with email</span></div>

        <!-- Error -->
        <?php if (!empty($loginErrors['general'])): ?>
            <div class="error-box">❌ <?= htmlspecialchars($loginErrors['general'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="/bloom-aura/pages/login.php" method="POST" novalidate>
            <?php csrf_field(); ?>
            <input type="hidden" name="form_action" value="login">

            <!-- Email -->
            <div class="lfield-wrap">
                <label class="lfield-label" for="login-email">Email Address</label>
                <div class="lfield">
                    <span class="lfield-icon">📧</span>
                    <input type="email" id="login-email" name="email"
                           value="<?= htmlspecialchars($oldLogin['email'], ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="you@example.com"
                           autocomplete="email" required>
                    <span class="lfield-end">🌸</span>
                </div>
                <?php if (!empty($loginErrors['email'])): ?>
                    <div class="field-error-dark">❌ <?= htmlspecialchars($loginErrors['email'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="lfield-wrap">
                <label class="lfield-label" for="login-pass">Password</label>
                <div class="lfield">
                    <span class="lfield-icon">🔒</span>
                    <input type="password" id="login-pass" name="password"
                           placeholder="Your password"
                           autocomplete="current-password" required>
                    <button type="button" class="lfield-end" onclick="togglePass('login-pass', this)"
                            aria-label="Show password">👁</button>
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
            No account? <a href="#" onclick="switchTab('signup');return false;">Create one free →</a>
        </div>
        <div class="bottom-links">
            <button onclick="window.location='/bloom-aura/'">← Back to home</button>
            <button onclick="window.location='/bloom-aura/pages/shop.php'">Browse as guest</button>
        </div>
    </div>

    <!-- ══ SIGN UP PANEL ══ -->
    <div class="login-panel <?= $activeTab === 'signup' ? 'active' : '' ?>" id="panel-signup">

        <div class="panel-header">
            <div class="panel-icon">🌷</div>
            <h2 class="panel-title">Create Account</h2>
            <p class="panel-sub">Join Bloom Aura and start gifting</p>
        </div>

        <!-- DB / general error -->
        <?php if (!empty($signupErrors['db'])): ?>
            <div class="error-box">❌ <?= htmlspecialchars($signupErrors['db'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="/bloom-aura/pages/login.php?tab=signup" method="POST" novalidate>
            <?php csrf_field(); ?>
            <input type="hidden" name="form_action" value="register">

            <!-- Name -->
            <div class="lfield-wrap">
                <label class="lfield-label" for="signup-name">Full Name</label>
                <div class="lfield">
                    <span class="lfield-icon">👤</span>
                    <input type="text" id="signup-name" name="name"
                           value="<?= htmlspecialchars($oldSignup['name'], ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Your full name"
                           autocomplete="name" required>
                    <span class="lfield-end">🌸</span>
                </div>
                <?php if (!empty($signupErrors['name'])): ?>
                    <div class="field-error-dark">❌ <?= htmlspecialchars($signupErrors['name'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>

            <!-- Email -->
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

            <!-- Password -->
            <div class="lfield-wrap">
                <label class="lfield-label" for="signup-pass">Password</label>
                <div class="lfield">
                    <span class="lfield-icon">🔒</span>
                    <input type="password" id="signup-pass" name="password"
                           placeholder="Create a password (min 8 chars)"
                           autocomplete="new-password" required minlength="8"
                           oninput="passHint(this)">
                    <button type="button" class="lfield-end" onclick="togglePass('signup-pass', this)"
                            aria-label="Show password">👁</button>
                </div>
                <div class="field-hint-dark" id="signup-pass-hint">8+ characters</div>
                <?php if (!empty($signupErrors['password'])): ?>
                    <div class="field-error-dark">❌ <?= htmlspecialchars($signupErrors['password'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>

            <!-- Confirm password -->
            <div class="lfield-wrap">
                <label class="lfield-label" for="signup-confirm">Confirm Password</label>
                <div class="lfield">
                    <span class="lfield-icon">🔒</span>
                    <input type="password" id="signup-confirm" name="confirm"
                           placeholder="Repeat your password"
                           autocomplete="new-password" required>
                    <button type="button" class="lfield-end" onclick="togglePass('signup-confirm', this)"
                            aria-label="Show password">👁</button>
                </div>
                <?php if (!empty($signupErrors['confirm'])): ?>
                    <div class="field-error-dark">❌ <?= htmlspecialchars($signupErrors['confirm'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="login-main-btn">Create Account 🌸</button>
        </form>

        <div class="login-footer-text">
            Already have an account? <a href="#" onclick="switchTab('signin');return false;">Sign in →</a>
        </div>
        <div class="bottom-links">
            <button onclick="window.location='/bloom-aura/'">← Back to home</button>
        </div>
    </div>

</div><!-- /.login-card -->

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
    if (len === 0) { hint.textContent = '8+ characters'; hint.style.color = 'rgba(255,255,255,.28)'; }
    else if (len < 8) { hint.textContent = (8 - len) + ' more character' + ((8-len)===1?'':'s') + ' needed'; hint.style.color = '#fca5a5'; }
    else { hint.textContent = '✅ Looks good!'; hint.style.color = '#86efac'; }
}

function socialToast(provider) {
    alert('Social login is not connected yet.\nPlease use the email form below.');
}
</script>
</body>
</html>