<?php
/**
 * StudentHub Portal - login handler
 * ---------------------------------
 * The login page sends an explicit login_type:
 *   student -> only the students table (email or roll number)
 *   staff   -> only the admins table (email; Admin / Faculty / Event Manager)
 *
 * Security notes
 *  - Passwords are checked with password_verify() against the stored hash.
 *  - login_user() calls session_regenerate_id(true) to stop session fixation.
 *  - The session carries an idle and an absolute timeout (see config.php).
 *  - "Remember me" stores a selector + validator in an HttpOnly cookie and
 *    only a sha256(validator) row in MySQL (see remember.php).
 *  - Each account type is redirected to its own protected dashboard.
 */

declare(strict_types=1);

/* Never auto-login from an old cookie while a new sign-in is being processed. */
define('SH_SKIP_REMEMBER_AUTOLOGIN', true);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/page_rules.php';

if (request_method() !== 'POST') {
    redirect(app_url('login.html'));
}

$identifier = input_string($_POST, 'identifier');
$password = (string) ($_POST['password'] ?? '');
$remember = input_bool($_POST, 'remember');
$loginType = strtolower(input_string($_POST, 'login_type', 'student'));
$next = safe_next_page(input_string($_POST, 'next'));

if (!in_array($loginType, ['student', 'staff'], true)) {
    $loginType = 'student';
}

/* Email addresses are compared lower-case; roll numbers are upper-case. */
$emailLookup = strtolower($identifier);
$rollLookup = strtoupper($identifier);

$fail = static function (string $message, string $identifier = '') use ($loginType, $next) {
    $query = [
        'error'      => $message,
        'email'      => $identifier,
        'login_type' => $loginType,
    ];

    if ($next !== null) {
        $query['next'] = $next;
    }

    redirect(app_url('login.html?' . http_build_query($query)));
};

if ($identifier === '' || $password === '') {
    $fail('Please enter both your email / roll number and password.', $identifier);
}

/* Brute-force protection: 5 failures per identifier or 20 per IP in 15 minutes. */
try {
    $identifierFailures = (int) db_value(
        "SELECT COUNT(*) FROM login_attempts
          WHERE email = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE)",
        's',
        [$emailLookup]
    );

    $ipFailures = (int) db_value(
        "SELECT COUNT(*) FROM login_attempts
          WHERE ip_address = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE)",
        's',
        [client_ip()]
    );

    if ($identifierFailures >= 5 || $ipFailures >= 20) {
        $fail('Too many failed login attempts. Please wait 15 minutes and try again.', $identifier);
    }
} catch (Throwable $error) {
    /* If the audit table is unavailable, do not block sign-in. */
}

try {
    if ($loginType === 'student') {
        /* ---------- Student sign-in ---------- */
        $student = db_one(
            'SELECT * FROM students WHERE email = ? OR roll_no = ? LIMIT 1',
            'ss',
            [$emailLookup, $rollLookup]
        );

        if ($student === null || !password_verify($password, (string) $student['password_hash'])) {
            log_login_attempt($emailLookup, false);
            $fail('Invalid student email / roll number or password.', $identifier);
        }

        if ($student['status'] !== 'active') {
            log_login_attempt($emailLookup, false);
            $fail('Your student account is not active. Please contact the administration.', $identifier);
        }

        login_user('student', [
            'id'    => (int) $student['id'],
            'name'  => $student['full_name'],
            'email' => $student['email'],
            'role'  => 'student',
        ]);

        /* Upgrade older hashes and clear the failures for this identifier. */
        upgrade_password_hash('students', (int) $student['id'], $password, (string) $student['password_hash']);
        clear_login_failures($emailLookup);

        /* Replace any older token for this browser, or clear it when unchecked. */
        revoke_remember_token_by_cookie();
        if ($remember) {
            issue_remember_token('student', (int) $student['id']);
        }

        db_execute('UPDATE students SET last_login_at = NOW() WHERE id = ?', 'i', [(int) $student['id']]);
        log_login_attempt($emailLookup, true);
        log_activity('student_login', 'student', (int) $student['id'], 'Student signed in.', null, (int) $student['id']);

        $target = ($next !== null && page_allowed_for_type($next, 'student'))
            ? $next
            : dashboard_path('student');

        redirect(app_url($target . (str_contains($target, '?') ? '&' : '?') . 'login=success'));
    }

    /* ---------- Admin / Faculty sign-in ---------- */
    $admin = db_one('SELECT * FROM admins WHERE email = ? LIMIT 1', 's', [$emailLookup]);

    if ($admin === null || !password_verify($password, (string) $admin['password_hash'])) {
        log_login_attempt($emailLookup, false);
        $fail('Invalid staff email or password. Student accounts must use the Student tab.', $identifier);
    }

    if ($admin['status'] !== 'active') {
        log_login_attempt($emailLookup, false);
        $fail('This staff account is inactive. Please contact the administrator.', $identifier);
    }

    login_user('admin', [
        'id'    => (int) $admin['id'],
        'name'  => $admin['full_name'],
        'email' => $admin['email'],
        'role'  => $admin['role'],
    ]);

    upgrade_password_hash('admins', (int) $admin['id'], $password, (string) $admin['password_hash']);
    clear_login_failures($emailLookup);

    revoke_remember_token_by_cookie();
    if ($remember) {
        issue_remember_token('admin', (int) $admin['id']);
    }

    db_execute('UPDATE admins SET last_login_at = NOW() WHERE id = ?', 'i', [(int) $admin['id']]);
    log_login_attempt($emailLookup, true);
    log_activity('admin_login', 'admin', (int) $admin['id'], 'Administrator signed in.', (int) $admin['id']);

    $target = ($next !== null && page_allowed_for_type($next, 'admin'))
        ? $next
        : dashboard_path('admin');

    redirect(app_url($target . (str_contains($target, '?') ? '&' : '?') . 'login=success'));
} catch (Throwable $error) {
    $message = APP_DEBUG ? $error->getMessage() : 'Login is temporarily unavailable. Please try again.';
    $fail($message, $identifier);
}
