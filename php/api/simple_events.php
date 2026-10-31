<?php
/**
 * Simple Events JSON API
 * ----------------------
 * A compact CRUD endpoint used by the Fetch API demo in SimpleEvents.html.
 *
 * GET  /php/api/simple_events.php          -> list events
 * POST /php/api/simple_events.php          -> action=create | update | delete
 *
 * The request and response bodies are JSON. Every query uses a MySQLi
 * prepared statement. The page and endpoint are staff-only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$actor = require_admin();

/**
 * Shape one database row for the browser.
 */
function simple_event_row(array $row): array
{
    $date = (string) ($row['event_date'] ?? '');

    return [
        'id'          => (int) ($row['id'] ?? 0),
        'title'       => (string) ($row['title'] ?? ''),
        'venue'       => (string) ($row['venue'] ?? ''),
        'eventDate'   => $date,
        'displayDate' => $date !== '' ? date('d M Y', strtotime($date)) : '',
        'status'      => (string) ($row['status'] ?? 'scheduled'),
        'description' => (string) ($row['description'] ?? ''),
        'createdAt'   => $row['created_at'] ?? null,
        'updatedAt'   => $row['updated_at'] ?? null,
    ];
}

function simple_event_fetch(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $row = db_one(
        'SELECT id, title, venue, event_date, status, description, created_at, updated_at
         FROM simple_events WHERE id = ? LIMIT 1',
        'i',
        [$id]
    );

    return $row === null ? null : simple_event_row($row);
}

/**
 * Read and trim the submitted fields.
 */
function simple_event_input(array $source): array
{
    $description = trim((string) ($source['description'] ?? ''));

    return [
        'title'       => input_string($source, 'title'),
        'venue'       => input_string($source, 'venue'),
        'event_date'  => input_string($source, 'event_date'),
        'status'      => input_string($source, 'status', 'scheduled'),
        'description' => $description === '' ? null : $description,
    ];
}

/**
 * @return array<string,string> field => message
 */
function simple_event_validate(array $data): array
{
    $errors = [];

    if ($data['title'] === '') {
        $errors['title'] = 'Event title is required.';
    } elseif (strlen($data['title']) < 3 || strlen($data['title']) > 150) {
        $errors['title'] = 'Event title must be between 3 and 150 characters.';
    }

    if ($data['venue'] === '') {
        $errors['venue'] = 'Venue is required.';
    } elseif (strlen($data['venue']) > 150) {
        $errors['venue'] = 'Venue may not exceed 150 characters.';
    }

    if ($data['event_date'] === '') {
        $errors['event_date'] = 'Event date is required.';
    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data['event_date'], $parts) !== 1
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
    ) {
        $errors['event_date'] = 'Event date must be a valid date.';
    }

    if (!in_array($data['status'], ['scheduled', 'completed', 'cancelled'], true)) {
        $errors['status'] = 'Please select a valid status.';
    }

    if ($data['description'] !== null && strlen($data['description']) > 1000) {
        $errors['description'] = 'Description may not exceed 1000 characters.';
    }

    return $errors;
}

try {
    $method = request_method();

    /* ----------------------------- list ----------------------------- */
    if ($method === 'GET') {
        $rows = db_query(
            'SELECT id, title, venue, event_date, status, description, created_at, updated_at
             FROM simple_events
             ORDER BY event_date ASC, id ASC'
        );

        json_success(
            array_map('simple_event_row', $rows),
            'Events loaded successfully.'
        );
    }

    if ($method !== 'POST') {
        json_error('Method not allowed.', 405);
    }

    $input = request_data();
    $action = input_string($input, 'action', 'create');

    /* ---------------------------- create ---------------------------- */
    if ($action === 'create') {
        $data = simple_event_input($input);
        $errors = simple_event_validate($data);

        if ($errors !== []) {
            json_error((string) reset($errors), 422, $errors);
        }

        $result = db_execute(
            'INSERT INTO simple_events (title, venue, event_date, status, description)
             VALUES (?, ?, ?, ?, ?)',
            'sssss',
            [
                $data['title'],
                $data['venue'],
                $data['event_date'],
                $data['status'],
                $data['description'],
            ]
        );

        $id = (int) $result['insert_id'];
        $event = simple_event_fetch($id);

        if ($event === null) {
            json_error('The event was saved but could not be loaded.', 500);
        }

        log_activity(
            'simple_event_created',
            'simple_event',
            $id,
            'Simple event created: ' . $data['title'] . '.',
            (int) ($actor['id'] ?? 0)
        );

        json_success($event, 'Event "' . $event['title'] . '" was added successfully.');
    }

    /* ---------------------------- update ---------------------------- */
    if ($action === 'update') {
        $id = input_int($input, 'id');
        if ($id <= 0) {
            json_error('Choose the event you want to update.', 422);
        }

        $existing = simple_event_fetch($id);
        if ($existing === null) {
            json_error('Event not found.', 404);
        }

        $data = simple_event_input($input);
        $errors = simple_event_validate($data);

        if ($errors !== []) {
            json_error((string) reset($errors), 422, $errors);
        }

        db_execute(
            'UPDATE simple_events
             SET title = ?, venue = ?, event_date = ?, status = ?, description = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?',
            'sssssi',
            [
                $data['title'],
                $data['venue'],
                $data['event_date'],
                $data['status'],
                $data['description'],
                $id,
            ]
        );

        $event = simple_event_fetch($id);
        if ($event === null) {
            json_error('The event was updated but could not be loaded.', 500);
        }

        log_activity(
            'simple_event_updated',
            'simple_event',
            $id,
            'Simple event updated: ' . $data['title'] . '.',
            (int) ($actor['id'] ?? 0)
        );

        json_success($event, 'Event "' . $event['title'] . '" was updated successfully.');
    }

    /* ---------------------------- delete ---------------------------- */
    if ($action === 'delete') {
        $id = input_int($input, 'id');
        if ($id <= 0) {
            json_error('Choose the event you want to delete.', 422);
        }

        $existing = simple_event_fetch($id);
        if ($existing === null) {
            json_error('Event not found.', 404);
        }

        $result = db_execute('DELETE FROM simple_events WHERE id = ?', 'i', [$id]);
        if ((int) $result['affected_rows'] !== 1) {
            json_error('Event not found.', 404);
        }

        log_activity(
            'simple_event_deleted',
            'simple_event',
            $id,
            'Simple event deleted: ' . $existing['title'] . '.',
            (int) ($actor['id'] ?? 0)
        );

        json_success($existing, 'Event "' . $existing['title'] . '" was deleted successfully.');
    }

    json_error('Unknown action.', 400);
} catch (Throwable $error) {
    json_handle_exception($error);
}
