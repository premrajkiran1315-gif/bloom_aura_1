<?php
/**
 * bloom-aura/includes/auth_check.php
 *
 * Include at the TOP of every page that requires a logged-in customer.
 * Usage:  require_once __DIR__ . '/../includes/auth_check.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If not logged in as a customer, redirect to login page
if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    // Store intended destination so login can redirect back
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /pages/login.php');
    exit;
}
