<?php
/**
 * GET /php/api/health.php
 * -----------------------
 * Small diagnostics endpoint: connection status, schema version and row counts.
 * Handy for checking that the database import worked.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

try {
    $counts = [
        'students'             => (int) db_value('SELECT COUNT(*) FROM students'),
        'departments'          => (int) db_value('SELECT COUNT(*) FROM departments'),
        'courses'              => (int) db_value('SELECT COUNT(*) FROM courses'),
        'events'               => (int) db_value('SELECT COUNT(*) FROM events'),
        'event_registrations'  => (int) db_value('SELECT COUNT(*) FROM event_registrations'),
        'notices'              => (int) db_value('SELECT COUNT(*) FROM notices'),
        'faqs'                 => (int) db_value('SELECT COUNT(*) FROM faqs'),
        'contact_messages'     => (int) db_value('SELECT COUNT(*) FROM contact_messages'),
        'admins'               => (int) db_value('SELECT COUNT(*) FROM admins'),
        'remember_tokens'      => (int) db_value('SELECT COUNT(*) FROM remember_tokens'),
    ];

    json_success([
        'status'       => 'ok',
        'database'     => DB_NAME,
        'server'       => db_value('SELECT VERSION()'),
        'php_version'  => PHP_VERSION,
        'server_time'  => date('Y-m-d H:i:s'),
        'tables'       => $counts,
    ], 'Database connection is healthy.');
} catch (Throwable $error) {
    json_error(
        APP_DEBUG ? $error->getMessage() : 'Database connection failed.',
        503
    );
}
