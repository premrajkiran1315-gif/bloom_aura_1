<?php
/**
 * bloom-aura/admin/logout.php
 * Destroys the admin session and redirects to admin login.
 */

session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// ✅ Admin logout → admin login (NOT pages/login.php)
header('Location: /bloom-aura/admin/login.php');
exit;