<?php
/**
 * GET /php/api/me.php
 * -------------------
 * Return the currently logged-in student or admin.
 * Always responds 200; use data.authenticated to check the session.
 *
 * current_user() applies the idle and absolute timeouts, so this endpoint
 * also reports why a session ended and when the current one will expire.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../serializers.php';

/**
 * Session timing metadata for the browser.
 *
 * @return array<string,mixed>
 */
function session_metadata(array $user): array
{
    $started = (int) ($user['login_ts'] ?? time());
    $last = (int) ($user['last_activity'] ?? $started);

    return [
        'loginAt'         => $user['login_time'] ?? date('Y-m-d H:i:s', $started),
        'lastActivity'    => date('Y-m-d H:i:s', $last),
        'idleTimeout'     => SESSION_IDLE_TIMEOUT,
        'absoluteTimeout' => SESSION_ABSOLUTE_TIMEOUT,
        'idleExpiresAt'   => date('c', $last + SESSION_IDLE_TIMEOUT),
        'expiresAt'       => date('c', $started + SESSION_ABSOLUTE_TIMEOUT),
        'remembered'      => isset($_COOKIE[REMEMBER_COOKIE]),
    ];
}

try {
    $user = current_user();

    if ($user === null) {
        $reason = $_SESSION['auth_expired'] ?? null;
        if ($reason !== null) {
            unset($_SESSION['auth_expired']);
        }

        json_success([
            'authenticated' => false,
            'user'          => null,
            'session'       => null,
            'reason'        => $reason,
            'message'       => $reason !== null
                ? session_timeout_message((string) $reason)
                : 'Not logged in.',
        ], 'Not logged in.');
    }

    $session = session_metadata($user);

    if ($user['type'] === 'student') {
        $row = db_one('SELECT * FROM vw_student_details WHERE id = ? LIMIT 1', 'i', [(int) $user['id']]);

        if ($row === null) {
            logout_user();
            json_success([
                'authenticated' => false,
                'user'          => null,
                'session'       => null,
                'reason'        => 'missing',
                'message'       => 'Your account is no longer available.',
            ], 'Session expired.');
        }

        $student = student_to_api($row);
        $student['type'] = 'student';
        $student['role'] = 'student';
        $student['totalEvents'] = (int) db_value(
            "SELECT COUNT(*) FROM event_registrations
              WHERE student_id = ? AND status IN ('pending','confirmed','attended')",
            'i',
            [(int) $user['id']]
        );

        json_success([
            'authenticated' => true,
            'user'          => $student,
            'session'       => $session,
        ], 'Logged in.');
    }

    $admin = db_one(
        'SELECT id, full_name, email, role, status, last_login_at FROM admins WHERE id = ? LIMIT 1',
        'i',
        [(int) $user['id']]
    );

    if ($admin === null || $admin['status'] !== 'active') {
        logout_user();
        json_success([
            'authenticated' => false,
            'user'          => null,
            'session'       => null,
            'reason'        => 'inactive',
            'message'       => 'This administrator account is not active.',
        ], 'Session expired.');
    }

    json_success([
        'authenticated' => true,
        'user' => [
            'type'      => 'admin',
            'id'        => (int) $admin['id'],
            'name'      => $admin['full_name'],
            'email'     => $admin['email'],
            'role'      => $admin['role'],
            'lastLogin' => $admin['last_login_at'],
        ],
        'session' => $session,
    ], 'Logged in.');
} catch (Throwable $error) {
    json_handle_exception($error);
}
