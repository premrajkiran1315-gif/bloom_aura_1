<?php
/**
 * bloom-aura/includes/auth_check.php
 * Session guard for customer-facing protected pages.
 * Include at the top of any page that requires login.
 * Session must already be started before including this file.
 *
 * Usage:
 *   session_start();
 *   require_once __DIR__ . '/../includes/auth_check.php';
 */

if (empty($_SESSION['user_id'])) {
    // Store the URL they were trying to reach so we can redirect back after login
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: /pages/login.php');
    exit;
}

// Also block deactivated accounts
if (isset($_SESSION['user_active']) && $_SESSION['user_active'] === 0) {
    session_destroy();
    header('Location: /pages/login.php?reason=deactivated');
    exit;
}
