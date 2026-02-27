<?php
/**
 * bloom-aura/pages/login.php
 * Customer login with:
 *  - bcrypt password verification
 *  - Rate limiting (5 attempts → 15-minute lockout, tracked in DB)
 *  - Session regeneration after login
 *  - CSRF protection
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /pages/shop.php');
    exit;
}

$errors = [];
$oldEmail = '';

// ── Constants ──────────────────────────────────────────────
const MAX_ATTEMPTS   = 5;
const LOCKOUT_MINS   = 15;

/**
 * Check if an IP/email combination is currently rate-limited.
 * Returns true if the user is locked out.
 */
function isRateLimited(PDO $pdo, string $email, string $ip): bool {
    $window = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_MINS . ' minutes'));
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE (email = ? OR ip_address = ?)
           AND attempted_at > ?'
    );
    $stmt->execute([$email, $ip, $window]);
    return (int) $stmt->fetchColumn() >= MAX_ATTEMPTS;
}

/**
 * Record a failed login attempt.
 */
function recordFailedAttempt(PDO $pdo, string $email, string $ip): void {
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())'
    );
    $stmt->execute([$email, $ip]);
}

// ── Handle POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $oldEmail = $email;
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $errors['password'] = 'Please enter your password.';
    } else {
        try {
            $pdo = getPDO();

            // Check rate limit before touching passwords
            if (isRateLimited($pdo, $email, $ip)) {
                $errors['general'] = 'Too many failed attempts. Please wait ' . LOCKOUT_MINS . ' minutes and try again.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT id, name, email, password_hash, role, is_active
                     FROM users WHERE email = ? AND role = "customer" LIMIT 1'
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    // Record failure — same generic message for both cases (prevents user enumeration)
                    recordFailedAttempt($pdo, $email, $ip);
                    $errors['general'] = 'Incorrect email or password.';
                } elseif (!$user['is_active']) {
                    $errors['general'] = 'Your account has been deactivated. Please contact support.';
                } else {
                    // ✅ Successful login
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email']= $user['email'];
                    $_SESSION['user_role'] = $user['role'];

                    // Clean up old login attempts for this email
                    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);

                    // Redirect to intended page or default shop
                    $redirect = $_SESSION['redirect_after_login'] ?? '/pages/shop.php';
                    unset($_SESSION['redirect_after_login']);

                    flash('Welcome back, ' . $user['name'] . '! 🌸', 'success');
                    header('Location: ' . $redirect);
                    exit;
                }
            }
        } catch (RuntimeException $e) {
            $errors['general'] = 'A server error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Login — Bloom Aura';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container narrow-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Welcome Back 🌸</h1>
            <p class="auth-subtitle">Log in to your Bloom Aura account</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form action="/pages/login.php" method="POST" class="auth-form" novalidate>
            <?php csrf_field(); ?>

            <!-- Email -->
            <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="email"
                    required
                    aria-describedby="email-error"
                >
                <?php if (isset($errors['email'])): ?>
                    <span class="field-error" id="email-error" role="alert">
                        <?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group <?= isset($errors['password']) ? 'has-error' : '' ?>">
                <label for="password">Password</label>
                <div class="input-with-toggle">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        aria-describedby="password-error"
                    >
                    <button type="button" class="toggle-pass" aria-label="Show password" data-target="password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error" id="password-error" role="alert">
                        <?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Log In</button>
        </form>

        <p class="auth-switch">Don't have an account? <a href="/pages/register.php">Sign up free</a></p>
    </div>
</div>

<script src="/assets/js/validate.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
