<?php
/**
 * bloom-aura/includes/flash.php
 * Helpers for setting one-time flash messages via the session.
 * Messages are displayed in header.php and then cleared.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Queue a flash message.
 *
 * @param string $msg  The message text (will be HTML-escaped on output)
 * @param string $type 'success' | 'error' | 'info' | 'warning'
 */
function flash(string $msg, string $type = 'info'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}
