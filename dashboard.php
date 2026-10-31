<?php
/**
 * StudentHub Portal - dashboard router
 * ------------------------------------
 * Every login lands on dashboard.php or a specific dashboard. This router
 * sends the visitor to the correct protected page for their role.
 */

declare(strict_types=1);

require_once __DIR__ . '/php/bootstrap.php';

$user = current_user();

if ($user === null) {
    /* Not signed in: require_login() redirects to the login page. */
    require_login();
}

$type = (string) ($user['type'] ?? 'student');
$target = dashboard_path($type);

$query = [];
if (($_GET['error'] ?? '') === 'forbidden') {
    $query['error'] = 'forbidden';
}

$location = $target . ($query !== [] ? '?' . http_build_query($query) : '');
redirect(app_url($location));
