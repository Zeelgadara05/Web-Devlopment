<?php
/**
 * StudentHub Portal - email / username availability check
 * -------------------------------------------------------
 * GET php/api/check_availability.php?email=...
 * GET php/api/check_availability.php?username=...
 *
 * Used by JS/Registration.js for the live "already taken" hint.
 * The registration handler always repeats these checks on submit,
 * so the client-side result is only a convenience.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (request_method() !== 'GET') {
    json_error('Only GET requests are allowed.', 405);
}

$email = strtolower(query_string('email'));
$username = strtoupper(query_string('username'));

if ($email === '' && $username === '') {
    json_error('Provide an email or a username to check.', 400);
}

$errors = [];

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Please provide a valid email address.';
}

if ($username !== '' && !preg_match('/^[A-Z0-9]{4,15}$/', $username)) {
    $errors[] = 'Username must be 4-15 letters or digits.';
}

if ($errors !== []) {
    json_error('Invalid input.', 422, $errors);
}

$data = [];

try {
    if ($email !== '') {
        $existingEmail = db_value(
            'SELECT id FROM students WHERE email = ? LIMIT 1',
            's',
            [$email]
        );

        $data['email'] = $email;
        $data['email_available'] = $existingEmail === null;
    }

    if ($username !== '') {
        $existingUsername = db_value(
            'SELECT id FROM students WHERE roll_no = ? LIMIT 1',
            's',
            [$username]
        );

        $data['username'] = $username;
        $data['username_available'] = $existingUsername === null;

        if (!$data['username_available']) {
            $data['suggestions'] = username_suggestions($username);
        }
    }

    $taken = [];
    if (isset($data['email_available']) && $data['email_available'] === false) {
        $taken[] = 'email';
    }
    if (isset($data['username_available']) && $data['username_available'] === false) {
        $taken[] = 'username';
    }

    $message = $taken === []
        ? 'The value is available.'
        : 'The ' . implode(' and ', $taken) . ' is already registered.';

    json_success($data, $message);
} catch (Throwable $error) {
    json_handle_exception($error);
}

/**
 * Build a few nearby unused usernames, e.g. CS21009 -> CS21010, CS21009X.
 *
 * @return array<int,string>
 */
function username_suggestions(string $username): array
{
    $suggestions = [];
    $base = preg_replace('/[0-9]+$/', '', $username);
    $number = (int) preg_replace('/^[^0-9]*/', '', $username);

    if ($base === '') {
        $base = 'STU';
    }

    for ($offset = 1; $offset <= 5 && count($suggestions) < 3; $offset++) {
        $candidate = $base . str_pad((string) ($number + $offset), max(4, strlen((string) ($number + $offset))), '0', STR_PAD_LEFT);
        $candidate = substr($candidate, 0, 15);

        $exists = db_value('SELECT id FROM students WHERE roll_no = ? LIMIT 1', 's', [$candidate]);
        if ($exists === null && !in_array($candidate, $suggestions, true)) {
            $suggestions[] = $candidate;
        }
    }

    return $suggestions;
}
