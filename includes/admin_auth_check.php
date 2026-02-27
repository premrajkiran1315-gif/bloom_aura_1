<?php
/**
 * bloom-aura/includes/admin_auth_check.php
 *
 * Include at the TOP of every admin page.
 * Checks that the user is logged in AND has the 'admin' role.
 * Admin session is stored under a separate key to prevent privilege escalation.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin session is tracked separately from customer session
if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: /admin/login.php');
    exit;
}
