<?php
/**
 * bloom-aura/pages/profile.php
 * Customer profile: view details, update name/email/password.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/auth_check.php';

$userId = (int)$_SESSION['user_id'];
$errors = [];
$old    = [];

// ── Handle POST updates ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    // Update profile info
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $old   = ['name' => $name, 'email' => $email];

        if ($name === '' || strlen($name) < 2)  $errors['name']  = 'Name must be at least 2 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';

        if (empty($errors)) {
            try {
                $pdo = getPDO();
                // Check email uniqueness (excluding own account)
                $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
                $check->execute([$email, $userId]);
                if ($check->fetch()) {
                    $errors['email'] = 'This email is already in use by another account.';
                } else {
                    $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                        ->execute([$name, $email, $userId]);
                    $_SESSION['user_name']  = $name;
                    $_SESSION['user_email'] = $email;
                    flash('Profile updated successfully! ✅', 'success');
                    header('Location: /pages/profile.php');
                    exit;
                }
            } catch (RuntimeException $e) {
                $errors['db'] = 'Could not update profile. Please try again.';
            }
        }
    }

    // Change password
    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $newPass  = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($current === '')          $errors['current_password'] = 'Enter your current password.';
        if (strlen($newPass) < 8)     $errors['new_password']     = 'New password must be at least 8 characters.';
        if ($newPass !== $confirm)    $errors['confirm_password'] = 'Passwords do not match.';

        if (empty($errors)) {
            try {
                $pdo  = getPDO();
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($current, $user['password_hash'])) {
                    $errors['current_password'] = 'Current password is incorrect.';
                } else {
                    $hash = password_hash($newPass, PASSWORD_BCRYPT);
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
                    flash('Password changed successfully! 🔐', 'success');
                    header('Location: /pages/profile.php');
                    exit;
                }
            } catch (RuntimeException $e) {
                $errors['db'] = 'Could not update password. Please try again.';
            }
        }
    }
}

// ── Fetch current user ────────────────────────────────────────────────────────
try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT name, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Order stats
    $statsStmt = $pdo->prepare(
        "SELECT COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS total_spent
         FROM orders WHERE user_id = ?"
    );
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch();

    // Wishlist count
    $wlStmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE user_id = ?');
    $wlStmt->execute([$userId]);
    $wishlistCount = (int)$wlStmt->fetchColumn();

} catch (RuntimeException $e) {
    $user  = ['name' => '', 'email' => '', 'created_at' => date('Y-m-d')];
    $stats = ['order_count' => 0, 'total_spent' => 0];
    $wishlistCount = 0;
}

$pageTitle = 'My Profile — Bloom Aura';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <li><a href="/">Home</a></li>
        <li aria-current="page">My Profile</li>
    </ol>
</nav>

<div class="page-container profile-page">

    <!-- Stats header -->
    <div class="profile-hero">
        <div class="profile-avatar" aria-hidden="true">
            <?= strtoupper(mb_substr($user['name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="profile-hero-info">
            <h1 class="profile-name"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="profile-email"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></p>
            <p class="profile-since">Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
        </div>
        <div class="profile-stats">
            <div class="profile-stat">
                <span class="stat-val"><?= (int)$stats['order_count'] ?></span>
                <span class="stat-label">Orders</span>
            </div>
            <div class="profile-stat">
                <span class="stat-val">₹<?= number_format($stats['total_spent'], 0) ?></span>
                <span class="stat-label">Spent</span>
            </div>
            <div class="profile-stat">
                <span class="stat-val"><?= $wishlistCount ?></span>
                <span class="stat-label">Wishlisted</span>
            </div>
        </div>
    </div>

    <!-- Quick links -->
    <div class="profile-quick-links">
        <a href="/pages/order-history.php" class="quick-link">
            <i class="fa-solid fa-clock-rotate-left"></i> Order History
        </a>
        <a href="/pages/wishlist.php" class="quick-link">
            <i class="fa-solid fa-heart"></i> My Wishlist
        </a>
        <a href="/pages/shop.php" class="quick-link">
            <i class="fa-solid fa-basket-shopping"></i> Shop Now
        </a>
    </div>

    <div class="profile-grid">

        <!-- ── Update Profile Form ── -->
        <div class="profile-card">
            <h2 class="profile-card-title">Edit Profile</h2>

            <?php if (!empty($errors['db'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($errors['db'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form action="/pages/profile.php" method="POST" novalidate>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name"
                           value="<?= htmlspecialchars($old['name'] ?? $user['name'], ENT_QUOTES, 'UTF-8') ?>"
                           required autocomplete="name">
                    <?php if (isset($errors['name'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($old['email'] ?? $user['email'], ENT_QUOTES, 'UTF-8') ?>"
                           required autocomplete="email">
                    <?php if (isset($errors['email'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>

        <!-- ── Change Password Form ── -->
        <div class="profile-card">
            <h2 class="profile-card-title">Change Password</h2>

            <form action="/pages/profile.php" method="POST" novalidate>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group <?= isset($errors['current_password']) ? 'has-error' : '' ?>">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password"
                           required autocomplete="current-password">
                    <?php if (isset($errors['current_password'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['current_password'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['new_password']) ? 'has-error' : '' ?>">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password"
                           required autocomplete="new-password" minlength="8">
                    <?php if (isset($errors['new_password'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['new_password'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           required autocomplete="new-password">
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        </div>

    </div><!-- /.profile-grid -->

</div><!-- /.page-container -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
