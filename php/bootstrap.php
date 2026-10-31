<?php
/**
 * StudentHub Portal - shared bootstrap
 * ------------------------------------
 * Include this at the top of every PHP page / API endpoint.
 * It loads configuration, the database layer, validation helpers,
 * starts a secure session and provides JSON + request helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/remember.php';

/* ------------------------------------------------------------------
 * Session
 * ------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    $secure = is_https_request();

    /* Reject uninitialised session ids and keep sessions server-side only. */
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_ABSOLUTE_TIMEOUT);

    session_name('STUDENTHUBSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------------------------------------------------------
 * JSON responses
 * ------------------------------------------------------------------ */
function json_response(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success($data = null, string $message = 'OK', array $meta = []): void
{
    $payload = [
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ];

    if ($meta !== []) {
        $payload['meta'] = $meta;
    }

    json_response($payload);
}

function json_error(string $message, int $status = 400, array $errors = []): void
{
    $payload = [
        'success' => false,
        'message' => $message,
    ];

    if ($errors !== []) {
        /* Keep field names for form highlighting, but re-index sparse lists. */
        $payload['errors'] = array_is_list($errors) ? array_values($errors) : $errors;
    }

    json_response($payload, $status);
}

/**
 * Convert any exception into a safe JSON error for API endpoints.
 */
function json_handle_exception(Throwable $error, int $status = 500): void
{
    $message = APP_DEBUG ? $error->getMessage() : 'Something went wrong. Please try again.';
    json_error($message, $status);
}

/* ------------------------------------------------------------------
 * Request helpers
 * ------------------------------------------------------------------ */

/**
 * Accept both form-encoded and JSON request bodies.
 */
function request_data(): array
{
    $data = $_POST;

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    return $data;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function request_wants_json(): bool
{
    return stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || isset($_GET['format']) && $_GET['format'] === 'json';
}

function input_string(array $source, string $key, string $default = ''): string
{
    $value = $source[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string) $value);
}

function input_int(array $source, string $key, int $default = 0): int
{
    $value = $source[$key] ?? null;
    if ($value === null || $value === '' || is_array($value)) {
        return $default;
    }
    return (int) $value;
}

function input_bool(array $source, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $source)) {
        return $default;
    }
    $value = $source[$key];
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function query_string(string $key, string $default = ''): string
{
    return input_string($_GET, $key, $default);
}

function query_int(string $key, int $default = 0): int
{
    return input_int($_GET, $key, $default);
}

function query_bool(string $key, bool $default = false): bool
{
    return input_bool($_GET, $key, $default);
}

/**
 * Validated page / per-page / offset values.
 *
 * @return array{0:int,1:int,2:int} [page, perPage, offset]
 */
function pagination(int $defaultPerPage = 10, int $maxPerPage = 200): array
{
    $page = max(1, query_int('page', 1));
    $perPage = query_int('per_page', $defaultPerPage);

    if ($perPage < 1) {
        $perPage = $defaultPerPage;
    }
    if ($perPage > $maxPerPage) {
        $perPage = $maxPerPage;
    }

    return [$page, $perPage, ($page - 1) * $perPage];
}

function pagination_meta(int $page, int $perPage, int $total): array
{
    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => max(1, (int) ceil($total / max(1, $perPage))),
    ];
}

/* ------------------------------------------------------------------
 * Auth / session helpers
 * ------------------------------------------------------------------ */
function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/**
 * End a session because it timed out.
 *
 * The remember-me cookie is revoked as well: a timeout must force a fresh
 * password login instead of being silently restored by a stored token.
 */
function auth_expire(string $reason): void
{
    unset($_SESSION['auth']);
    $_SESSION['auth_expired'] = $reason;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    try {
        revoke_remember_token_by_cookie();
    } catch (Throwable $error) {
        /* The timeout must still work if the token table is unavailable. */
    }
}

function session_timeout_message(?string $reason): string
{
    if ($reason === 'absolute') {
        return 'Your session has reached its maximum lifetime. Please log in again.';
    }

    $minutes = max(1, (int) round(SESSION_IDLE_TIMEOUT / 60));
    return 'Your session expired after ' . $minutes . ' minutes of inactivity. Please log in again.';
}

function current_user(): ?array
{
    $user = $_SESSION['auth'] ?? null;
    if (!is_array($user)) {
        return null;
    }

    $now = time();
    $started = (int) ($user['login_ts'] ?? 0);
    $last = (int) ($user['last_activity'] ?? 0);

    if ($started <= 0) {
        $started = $now;
        $_SESSION['auth']['login_ts'] = $started;
    }

    if ($last <= 0) {
        $last = $started;
        $_SESSION['auth']['last_activity'] = $last;
    }

    if (($now - $last) > SESSION_IDLE_TIMEOUT) {
        auth_expire('idle');
        return null;
    }

    if (($now - $started) > SESSION_ABSOLUTE_TIMEOUT) {
        auth_expire('absolute');
        return null;
    }

    $_SESSION['auth']['last_activity'] = $now;
    return $_SESSION['auth'];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(string $type, array $profile): void
{
    /* Prevent session fixation: the old id is destroyed and a new one issued. */
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $now = time();
    unset($_SESSION['auth_expired']);

    $_SESSION['auth'] = [
        'type'          => $type,
        'id'            => (int) $profile['id'],
        'name'          => $profile['name'] ?? '',
        'email'         => $profile['email'] ?? '',
        'role'          => $profile['role'] ?? $type,
        'login_time'    => date('Y-m-d H:i:s'),
        'login_ts'      => $now,
        'last_activity' => $now,
    ];
}

function logout_user(): void
{
    /* Revoke the browser's remember-me token before destroying the session. */
    try {
        revoke_remember_token_by_cookie();
    } catch (Throwable $error) {
        /* Logout must still work if the token table is unavailable. */
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        $reason = $_SESSION['auth_expired'] ?? null;
        if ($reason !== null) {
            unset($_SESSION['auth_expired']);
        }

        $message = $reason !== null
            ? session_timeout_message((string) $reason)
            : 'Please log in to continue.';

        if (request_wants_json()) {
            json_error($message, 401);
        }

        $query = ['error' => $message];
        if ($reason !== null) {
            $query['timeout'] = 1;
        }

        redirect(app_url('login.html?' . http_build_query($query)));
    }

    /* Protected pages and API replies must never be cached by the browser. */
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    return $user;
}

/**
 * Require one of the supplied roles. Admins may be matched by role name or
 * by the generic "admin" type, so require_role('admin') accepts every admin
 * role (super_admin, admin, event_manager, faculty).
 *
 * @param string|array<int,string> $roles
 */
function require_role($roles): array
{
    $user = require_login();
    $roles = is_array($roles) ? $roles : [$roles];

    $type = (string) ($user['type'] ?? '');
    $role = (string) ($user['role'] ?? $type);

    $allowed = in_array($role, $roles, true) || in_array($type, $roles, true);
    if (!$allowed) {
        if (request_wants_json()) {
            json_error('You do not have permission to access this page.', 403);
        }

        redirect(app_url('dashboard.php?error=forbidden'));
    }

    return $user;
}

function require_admin(): array
{
    return require_role(['admin', 'super_admin', 'event_manager', 'faculty']);
}

/**
 * Dashboard URL for a user type (relative to the project root).
 */
function dashboard_path(string $type): string
{
    return $type === 'admin' ? 'admin_dashboard.php' : 'student_dashboard.php';
}

/* ------------------------------------------------------------------
 * Misc helpers
 * ------------------------------------------------------------------ */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

/**
 * Web path of the project root, e.g. "/practical".
 *
 * Scripts may live in the project root or in php/ (and php/api/), so the
 * base is everything before the first "/php/" segment of the script name.
 */
function app_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $position = strpos($script, '/php/');

    if ($position !== false) {
        return rtrim(substr($script, 0, $position), '/');
    }

    return rtrim(str_replace('\\', '/', dirname($script)), '/');
}

/**
 * Build a root-relative URL that works from any script depth.
 */
function app_url(string $path = ''): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

/**
 * Record a login attempt in the audit table.
 */
function log_login_attempt(string $identifier, bool $success): void
{
    try {
        db_execute(
            'INSERT INTO login_attempts (email, ip_address, user_agent, success) VALUES (?, ?, ?, ?)',
            'sssi',
            [substr($identifier, 0, 150), client_ip(), user_agent(), $success ? 1 : 0]
        );
    } catch (Throwable $error) {
        /* Auditing must never block a login. */
    }
}

/**
 * Remove recent failed attempts for an identifier after a successful sign-in.
 */
function clear_login_failures(string $identifier): void
{
    try {
        db_execute(
            "DELETE FROM login_attempts
              WHERE email = ? AND success = 0
                AND attempted_at > (NOW() - INTERVAL 15 MINUTE)",
            's',
            [strtolower($identifier)]
        );
    } catch (Throwable $error) {
        /* Auditing must never block a valid sign-in. */
    }
}

/**
 * Re-hash a password when PHP's default algorithm or cost has changed.
 *
 * @param string $table Either "students" or "admins" (whitelisted).
 */
function upgrade_password_hash(string $table, int $id, string $password, string $currentHash): void
{
    if (!in_array($table, ['students', 'admins'], true)) {
        return;
    }

    if (!password_needs_rehash($currentHash, PASSWORD_DEFAULT)) {
        return;
    }

    try {
        db_execute(
            'UPDATE ' . $table . ' SET password_hash = ? WHERE id = ?',
            'si',
            [password_hash($password, PASSWORD_DEFAULT), $id]
        );
    } catch (Throwable $error) {
        /* A re-hash failure must never block a valid sign-in. */
    }
}

/**
 * Write an activity log row.
 */
function log_activity(
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $details = null,
    ?int $adminId = null,
    ?int $studentId = null
): void {
    try {
        db_execute(
            'INSERT INTO activity_logs (admin_id, student_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            'iississ',
            [$adminId, $studentId, substr($action, 0, 100), $entityType, $entityId, $details, client_ip()]
        );
    } catch (Throwable $error) {
        /* Logging is best-effort. */
    }
}

/**
 * Read a value from the settings table.
 */
function setting(string $key, ?string $default = null): ?string
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $value = db_value('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1', 's', [$key]);
        $cache[$key] = $value !== null ? (string) $value : $default;
    } catch (Throwable $error) {
        $cache[$key] = $default;
    }

    return $cache[$key];
}

/* ------------------------------------------------------------------
 * Optional remember-me sign-in
 * ------------------------------------------------------------------
 * The login and logout scripts define SH_SKIP_REMEMBER_AUTOLOGIN so a
 * new sign-in is never mixed with an old cookie.
 */
if (!defined('SH_SKIP_REMEMBER_AUTOLOGIN')
    && !is_logged_in()
    && isset($_COOKIE[REMEMBER_COOKIE])) {
    attempt_remember_login();
}
