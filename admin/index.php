<?php
/**
 * bloom-aura/admin/index.php
 * Default handler for /admin directory — redirects to login or dashboard
 */

session_start();

// If already logged in as admin, go to dashboard
if (!empty($_SESSION['admin_id']) && $_SESSION['admin_role'] === 'admin') {
    header('Location: /bloom-aura/admin/dashboard.php');
    exit;
}

// Otherwise, redirect to login
header('Location: /bloom-aura/admin/login.php');
exit;
