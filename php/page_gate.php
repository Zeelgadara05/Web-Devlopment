<?php
/**
 * StudentHub Portal - site-wide .html gate
 * ----------------------------------------
 * .htaccess sends every root-level *.html request here (except login.html and
 * Registration.html). The gate checks the session and the page's role rules,
 * then streams the original static page. Because the browser URL never changes,
 * relative CSS/JS/image paths keep working.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/page_rules.php';

$page = basename((string) ($_GET['page'] ?? ''));
if ($page === '' || !preg_match('/^[A-Za-z0-9_-]+\.html$/', $page)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Page not found.';
    exit;
}

/* Only files that really exist in the project root may be streamed. */
$root = dirname(__DIR__);
$path = $root . DIRECTORY_SEPARATOR . $page;
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Page not found.';
    exit;
}

$level = page_access_level($page);

/* Public pages are streamed without a session requirement. */
if ($level !== 'public') {
    $user = current_user();

    if ($user === null) {
        $reason = $_SESSION['auth_expired'] ?? null;
        if ($reason !== null) {
            unset($_SESSION['auth_expired']);
        }

        $query = [
            'error' => $reason !== null
                ? session_timeout_message((string) $reason)
                : 'Please log in to open the student portal.',
            'next'  => $page,
        ];

        if ($reason !== null) {
            $query['timeout'] = 1;
        }

        redirect(app_url('login.html?' . http_build_query($query)));
    }

    if (!page_allowed_for_user($page, $user)) {
        redirect(app_url(dashboard_path((string) $user['type']) . '?error=forbidden'));
    }
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

readfile($path);
