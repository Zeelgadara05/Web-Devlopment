<?php
/**
 * StudentHub Portal - page access rules
 * -------------------------------------
 * Shared by the .html gate (php/page_gate.php) and the login handler so a
 * post-login redirect can never send a user to a page their role may not open.
 *
 * Levels:
 *   public        - login and student self-registration
 *   authenticated - any signed-in student or staff member
 *   student       - students only (personal profile, attendance, results, timetable)
 *   staff         - Admin / Faculty only (student and event management)
 */

declare(strict_types=1);

function page_access_level(string $page): string
{
    $page = strtolower(basename($page));

    $public = ['login.html', 'registration.html'];
    if (in_array($page, $public, true)) {
        return 'public';
    }

    $staff = ['students.html', 'manageevents.html', 'simpleevents.html'];
    if (in_array($page, $staff, true)) {
        return 'staff';
    }

    $student = ['profile.html', 'attendance.html', 'result.html', 'tt.html'];
    if (in_array($page, $student, true)) {
        return 'student';
    }

    return 'authenticated';
}

/**
 * @param array<string,mixed> $user Session user from current_user().
 */
function page_allowed_for_user(string $page, array $user): bool
{
    $level = page_access_level($page);

    if ($level === 'public' || $level === 'authenticated') {
        return true;
    }

    $type = (string) ($user['type'] ?? '');

    if ($level === 'student') {
        return $type === 'student';
    }

    if ($level === 'staff') {
        return $type === 'admin';
    }

    return false;
}

function page_allowed_for_type(string $page, string $type): bool
{
    return page_allowed_for_user($page, ['type' => $type]);
}

/**
 * Normalise a ?next= value to a safe root-level .html page.
 */
function safe_next_page(string $next): ?string
{
    $next = trim($next);

    if ($next === '' || strlen($next) > 80) {
        return null;
    }

    if (str_contains($next, '..') || str_contains($next, '/') || str_contains($next, '\\')) {
        return null;
    }

    if (!preg_match('/^[A-Za-z0-9_-]+\.html$/', $next)) {
        return null;
    }

    /* A login page is never a useful post-login destination. */
    if (page_access_level($next) === 'public') {
        return null;
    }

    return $next;
}
