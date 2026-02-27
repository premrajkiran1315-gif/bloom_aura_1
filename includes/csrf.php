<?php
/**
 * bloom-aura/includes/csrf.php
 *
 * Lightweight CSRF token generation and validation.
 * Include this file anywhere you render or process a POST form.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate (or retrieve existing) CSRF token for the current session.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field — call inside every <form>.
 */
function csrf_field(): void {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from a POST request.
 * Exits with 403 if the token is missing or invalid.
 */
function csrf_validate(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        exit('Invalid or missing security token. Please go back and try again.');
    }
}
