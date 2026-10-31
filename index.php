<?php
/**
 * StudentHub Portal - project entry point
 * ---------------------------------------
 * Opening http://localhost/practical/ must never reveal portal data before a
 * sign-in, so the root simply routes to the login page or the user's dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/php/bootstrap.php';

$user = current_user();

if ($user === null) {
    redirect(app_url('login.html'));
}

redirect(app_url(dashboard_path((string) $user['type'])));
