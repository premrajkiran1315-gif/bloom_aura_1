<?php
/**
 * bloom-aura/includes/admin_header.php
 * Shared HTML head + open admin layout wrapper for all admin pages.
 * Must be included AFTER: session_start(), admin_auth_check, $pageTitle is set.
 * Close it with: require_once __DIR__ . '/../includes/admin_footer.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

// Flash messages
if (session_status() !== PHP_SESSION_NONE && !empty($_SESSION['flash'])) {
    $flashMessages = $_SESSION['flash'];
    unset($_SESSION['flash']);
} else {
    $flashMessages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin — Bloom Aura', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/bloom-aura/assets/css/admin.css">
</head>
<body class="admin-body">

<div class="admin-layout">
    <?php require_once __DIR__ . '/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <h1 class="admin-page-title"><?= htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="admin-topbar-right">
                <span class="admin-greeting">Hello, <?= $adminName ?> 👑</span>
                <a href="/admin/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </div>

        <!-- Flash messages -->
        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
                <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>

        <!-- Page content starts here -->
