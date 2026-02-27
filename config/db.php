<?php
/**
 * bloom-aura/config/db.php
 * Central PDO database connection.
 * This file must NEVER be accessible via the browser — protect with .htaccess.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'bloom_aura');
define('DB_USER', 'root');       // Change for production
define('DB_PASS', '');           // Change for production
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO instance.
 * Throws a generic exception on failure (never exposes credentials).
 */
function getPDO(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Use real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log real error to server, never expose to browser
            error_log('[BloomAura DB Error] ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please try again later.');
        }
    }

    return $pdo;
}
