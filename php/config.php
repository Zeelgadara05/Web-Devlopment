<?php
/**
 * StudentHub Portal - Database & application configuration
 * ---------------------------------------------------------
 * Works out of the box with a default XAMPP install:
 *   host: 127.0.0.1   port: 3306
 *   user: root        password: (empty)
 *   db  : studenthub
 *
 * You may override any value with an environment variable, for example:
 *   set SH_DB_PASS=mysecret
 */

declare(strict_types=1);

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('SH_DB_HOST') !== false ? (string) getenv('SH_DB_HOST') : '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('SH_DB_PORT') !== false ? (int) getenv('SH_DB_PORT') : 3306);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('SH_DB_NAME') !== false ? (string) getenv('SH_DB_NAME') : 'studenthub');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('SH_DB_USER') !== false ? (string) getenv('SH_DB_USER') : 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('SH_DB_PASS') !== false ? (string) getenv('SH_DB_PASS') : '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

/* Application */
if (!defined('APP_NAME')) {
    define('APP_NAME', 'StudentHub Portal');
}
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Kolkata');
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', getenv('SH_DEBUG') === '1');
}

/* Authentication / session policy */
if (!defined('SESSION_IDLE_TIMEOUT')) {
    $idle = getenv('SH_SESSION_IDLE');
    define('SESSION_IDLE_TIMEOUT', $idle !== false ? max(60, (int) $idle) : 1800); // 30 minutes
}
if (!defined('SESSION_ABSOLUTE_TIMEOUT')) {
    $absolute = getenv('SH_SESSION_ABSOLUTE');
    define('SESSION_ABSOLUTE_TIMEOUT', $absolute !== false ? max(300, (int) $absolute) : 28800); // 8 hours
}
if (!defined('REMEMBER_ME_DAYS')) {
    $days = getenv('SH_REMEMBER_DAYS');
    define('REMEMBER_ME_DAYS', $days !== false ? max(1, (int) $days) : 30);
}
if (!defined('REMEMBER_COOKIE')) {
    define('REMEMBER_COOKIE', 'STUDENTHUB_REMEMBER');
}

date_default_timezone_set(APP_TIMEZONE);

/* Show errors only while developing. APIs always return JSON. */
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
