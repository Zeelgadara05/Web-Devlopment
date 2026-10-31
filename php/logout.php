<?php
/**
 * StudentHub Portal - logout
 * --------------------------
 * Deletes the session cookie, destroys the server-side session and revokes
 * the remember-me token that belongs to this browser.
 */

declare(strict_types=1);

/* Do not restore the session from the cookie we are about to remove. */
define('SH_SKIP_REMEMBER_AUTOLOGIN', true);

require_once __DIR__ . '/bootstrap.php';

logout_user();

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Clear-Site-Data: "cache", "storage"');
}

redirect(app_url('login.html?message=' . urlencode('You have been logged out successfully.')));
