<?php
/**
 * StudentHub Portal - secure remember-me tokens
 * ---------------------------------------------
 * The browser stores  selector:validator  in an HttpOnly cookie.
 * MySQL stores only the selector and sha256(validator), never the
 * validator itself. Every successful use deletes the old row and
 * rotates in a new token, so a copied cookie cannot be replayed.
 */

declare(strict_types=1);

/**
 * Cookie options shared by the set and clear operations.
 *
 * @return array{expires:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string}
 */
function remember_cookie_options(int $expires): array
{
    return [
        'expires'  => $expires,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function set_remember_cookie(string $selector, string $validator): void
{
    $value = $selector . ':' . $validator;
    setcookie(REMEMBER_COOKIE, $value, remember_cookie_options(time() + (REMEMBER_ME_DAYS * 86400)));

    /* Make the value visible to the rest of this request. */
    $_COOKIE[REMEMBER_COOKIE] = $value;
}

function clear_remember_cookie(): void
{
    unset($_COOKIE[REMEMBER_COOKIE]);
    setcookie(REMEMBER_COOKIE, '', remember_cookie_options(time() - 3600));
}

/**
 * Split and validate the cookie value.
 *
 * @return array{0:string,1:string}|null [selector, validator]
 */
function remember_cookie_parts(?string $cookie = null): ?array
{
    $cookie = $cookie ?? (string) ($_COOKIE[REMEMBER_COOKIE] ?? '');
    if ($cookie === '') {
        return null;
    }

    $decoded = rawurldecode($cookie);
    if (substr_count($decoded, ':') !== 1) {
        return null;
    }

    [$selector, $validator] = explode(':', $decoded, 2);

    if (!preg_match('/^[a-f0-9]{24}$/', $selector)) {
        return null;
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $validator)) {
        return null;
    }

    return [$selector, $validator];
}

/**
 * Delete expired tokens. Called whenever a new token is issued.
 */
function purge_expired_remember_tokens(): void
{
    try {
        db_execute('DELETE FROM remember_tokens WHERE expires_at <= NOW()');
    } catch (Throwable $error) {
        /* The table may not exist yet; never block sign-in because of cleanup. */
    }
}

/**
 * Issue and persist a rotated remember-me token for a user.
 */
function issue_remember_token(string $userType, int $userId): bool
{
    if (!in_array($userType, ['student', 'admin'], true) || $userId <= 0) {
        return false;
    }

    try {
        purge_expired_remember_tokens();

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + (REMEMBER_ME_DAYS * 86400));

        db_execute(
            'INSERT INTO remember_tokens
                (selector, token_hash, user_type, user_id, expires_at, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            'sssisss',
            [
                $selector,
                $tokenHash,
                $userType,
                $userId,
                $expiresAt,
                client_ip(),
                user_agent(),
            ]
        );

        set_remember_cookie($selector, $validator);
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

/**
 * Revoke the token referenced by the browser cookie, then clear the cookie.
 */
function revoke_remember_token_by_cookie(?string $cookie = null): void
{
    $parts = remember_cookie_parts($cookie);

    if ($parts !== null) {
        try {
            db_execute(
                'DELETE FROM remember_tokens WHERE selector = ? AND token_hash = ?',
                'ss',
                [$parts[0], hash('sha256', $parts[1])]
            );
        } catch (Throwable $error) {
            /* Logout and timeout must still work if the table is unavailable. */
        }
    }

    clear_remember_cookie();
}

/**
 * Revoke every token for one account (useful after a password change).
 */
function revoke_user_remember_tokens(string $userType, int $userId): void
{
    try {
        db_execute(
            'DELETE FROM remember_tokens WHERE user_type = ? AND user_id = ?',
            'si',
            [$userType, $userId]
        );
    } catch (Throwable $error) {
        /* Best effort. */
    }
}

/**
 * Try to restore a session from a valid remember-me cookie.
 * Returns true when the user was signed in.
 */
function attempt_remember_login(): bool
{
    if (isset($_SESSION['auth']) || isset($_SESSION['auth_expired'])) {
        return false;
    }

    $parts = remember_cookie_parts();
    if ($parts === null) {
        if (isset($_COOKIE[REMEMBER_COOKIE])) {
            clear_remember_cookie();
        }
        return false;
    }

    [$selector, $validator] = $parts;
    $tokenHash = hash('sha256', $validator);

    try {
        $row = db_one(
            'SELECT * FROM remember_tokens
              WHERE selector = ? AND expires_at > NOW()
              LIMIT 1',
            's',
            [$selector]
        );

        if ($row === null || !hash_equals((string) $row['token_hash'], $tokenHash)) {
            if ($row !== null) {
                db_execute('DELETE FROM remember_tokens WHERE id = ?', 'i', [(int) $row['id']]);
            }
            clear_remember_cookie();
            return false;
        }

        $type = (string) $row['user_type'];
        $userId = (int) $row['user_id'];
        $profile = null;

        if ($type === 'student') {
            $student = db_one(
                "SELECT id, full_name, email FROM students WHERE id = ? AND status = 'active' LIMIT 1",
                'i',
                [$userId]
            );

            if ($student !== null) {
                $profile = [
                    'id'    => (int) $student['id'],
                    'name'  => $student['full_name'],
                    'email' => $student['email'],
                    'role'  => 'student',
                ];
            }
        } elseif ($type === 'admin') {
            $admin = db_one(
                "SELECT id, full_name, email, role FROM admins WHERE id = ? AND status = 'active' LIMIT 1",
                'i',
                [$userId]
            );

            if ($admin !== null) {
                $profile = [
                    'id'    => (int) $admin['id'],
                    'name'  => $admin['full_name'],
                    'email' => $admin['email'],
                    'role'  => $admin['role'],
                ];
            }
        }

        if ($profile === null) {
            db_execute('DELETE FROM remember_tokens WHERE id = ?', 'i', [(int) $row['id']]);
            clear_remember_cookie();
            return false;
        }

        /* Atomically consume the token before creating its replacement. */
        $consumed = db_execute(
            'DELETE FROM remember_tokens WHERE id = ? AND token_hash = ?',
            'is',
            [(int) $row['id'], $tokenHash]
        );

        if ((int) $consumed['affected_rows'] !== 1) {
            clear_remember_cookie();
            return false;
        }

        login_user($type === 'admin' ? 'admin' : 'student', $profile);

        if (!issue_remember_token($type, $userId)) {
            clear_remember_cookie();
        }

        if ($type === 'student') {
            db_execute('UPDATE students SET last_login_at = NOW() WHERE id = ?', 'i', [$userId]);
            log_activity(
                'student_remember_login',
                'student',
                $userId,
                'Student signed in with a remember-me token.',
                null,
                $userId
            );
        } else {
            db_execute('UPDATE admins SET last_login_at = NOW() WHERE id = ?', 'i', [$userId]);
            log_activity(
                'admin_remember_login',
                'admin',
                $userId,
                'Administrator signed in with a remember-me token.',
                $userId
            );
        }

        log_login_attempt((string) $profile['email'], true);
        return true;
    } catch (Throwable $error) {
        /* A broken token table must not break the whole site. */
        return false;
    }
}
