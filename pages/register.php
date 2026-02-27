<?php
/**
 * bloom-aura/pages/register.php
 * Customer registration with server-side validation and bcrypt hashing.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// Already logged in — redirect away
if (!empty($_SESSION['user_id'])) {
    header('Location: /pages/shop.php');
    exit;
}

$errors = [];
$old    = []; // Repopulate form fields on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validate CSRF
    csrf_validate();

    // 2. Sanitise and collect inputs
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    $old = compact('name', 'email');

    // 3. Validate
    if ($name === '' || mb_strlen($name) < 2) {
        $errors['name'] = 'Please enter your full name (at least 2 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'Passwords do not match.';
    }

    // 4. Check for duplicate email (only if email is valid)
    if (empty($errors['email'])) {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'An account with this email already exists. Try logging in.';
            }
        } catch (RuntimeException $e) {
            $errors['db'] = 'A server error occurred. Please try again.';
        }
    }

    // 5. Insert if no errors
    if (empty($errors)) {
        try {
            $pdo  = getPDO();
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, is_active, created_at)
                 VALUES (?, ?, ?, "customer", 1, NOW())'
            );
            $stmt->execute([$name, $email, $hash]);

            flash('Account created! Please log in.', 'success');
            header('Location: /pages/login.php');
            exit;
        } catch (RuntimeException $e) {
            $errors['db'] = 'A server error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Create Account — Bloom Aura';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container narrow-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Create Your Account</h1>
            <p class="auth-subtitle">Join Bloom Aura and start gifting beauty 🌸</p>
        </div>

        <?php if (!empty($errors['db'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors['db'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="/pages/register.php" method="POST" class="auth-form" novalidate>
            <?php csrf_field(); ?>

            <!-- Full Name -->
            <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="name"
                    required
                    aria-describedby="name-error"
                >
                <?php if (isset($errors['name'])): ?>
                    <span class="field-error" id="name-error" role="alert">
                        <?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
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
                        autocomplete="new-password"
                        required
                        minlength="8"
                        aria-describedby="password-hint password-error"
                    >
                    <button type="button" class="toggle-pass" aria-label="Show password" data-target="password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <span class="field-hint" id="password-hint">Minimum 8 characters.</span>
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error" id="password-error" role="alert">
                        <?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Confirm Password -->
            <div class="form-group <?= isset($errors['confirm']) ? 'has-error' : '' ?>">
                <label for="confirm">Confirm Password</label>
                <div class="input-with-toggle">
                    <input
                        type="password"
                        id="confirm"
                        name="confirm"
                        autocomplete="new-password"
                        required
                        aria-describedby="confirm-error"
                    >
                    <button type="button" class="toggle-pass" aria-label="Show confirm password" data-target="confirm">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <?php if (isset($errors['confirm'])): ?>
                    <span class="field-error" id="confirm-error" role="alert">
                        <?= htmlspecialchars($errors['confirm'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Create Account</button>
        </form>

        <p class="auth-switch">Already have an account? <a href="/pages/login.php">Log in</a></p>
    </div>
</div>

<script src="/assets/js/validate.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
