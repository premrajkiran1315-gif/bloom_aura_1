<?php
/**
 * bloom-aura/admin/login.php
 * Admin login — uses a SEPARATE session namespace from customer login.
 * Admin credentials are stored in the database (role = 'admin'), not hardcoded.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

// Already logged in as admin
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

const ADMIN_MAX_ATTEMPTS = 999;
const ADMIN_LOCKOUT_MINS = 1;

$errors   = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $oldEmail = $email;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $errors['password'] = 'Please enter your password.';
    } else {
        try {
            $pdo = getPDO();

            // Rate limit check
            $window = date('Y-m-d H:i:s', strtotime('-' . ADMIN_LOCKOUT_MINS . ' minutes'));
            $stmt   = $pdo->prepare(
                'SELECT COUNT(*) FROM login_attempts
                 WHERE (email = ? OR ip_address = ?) AND attempted_at > ?'
            );
            $stmt->execute([$email, $ip, $window]);
            $attempts = (int) $stmt->fetchColumn();

            if ($attempts >= ADMIN_MAX_ATTEMPTS) {
                $errors['general'] = 'Too many failed attempts. Please wait ' . ADMIN_LOCKOUT_MINS . ' minutes.';
            } else {
                // Only look for admin-role users
                $stmt = $pdo->prepare(
                    'SELECT id, name, email, password_hash, role, is_active
                     FROM users WHERE email = ? AND role = "admin" LIMIT 1'
                );
                $stmt->execute([$email]);
                $admin = $stmt->fetch();

                if (!$admin || !password_verify($password, $admin['password_hash'])) {
                    // Record attempt
                    $pdo->prepare(
                        'INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())'
                    )->execute([$email, $ip]);
                    $errors['general'] = 'Incorrect email or password.';
                } elseif (!$admin['is_active']) {
                    $errors['general'] = 'This admin account has been deactivated.';
                } else {
                    // ✅ Login successful — use SEPARATE session keys for admin
                    session_regenerate_id(true);

                    $_SESSION['admin_id']   = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['admin_role'] = $admin['role']; // must === 'admin'

                    // Clean login attempts
                    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);

                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                    exit;
                }
            }
        } catch (RuntimeException $e) {
            $errors['general'] = 'A server error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Bloom Aura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin.css">
</head>
<body class="admin-login-page">

<div class="admin-login-wrap">
    <div class="admin-login-card">
        <div class="admin-login-header">
            <span class="admin-logo-icon">🌸</span>
            <h1>Bloom Aura</h1>
            <p>Admin Panel</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/admin/login.php" method="POST" class="admin-login-form" novalidate>
            <?php csrf_field(); ?>

            <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="email" required>
                <?php if (isset($errors['email'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['password']) ? 'has-error' : '' ?>">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-full">
                <i class="fa-solid fa-lock"></i> Log In
            </button>
        </form>

        <p class="back-link"><a href="/">&larr; Back to store</a></p>
    </div>
</div>

<script src="/assets/js/validate.js"></script>
</body>
</html>
