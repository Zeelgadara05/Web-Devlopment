<?php
/**
 * /php/api/events.php
 * -------------------
 * Event records for the portal.
 *
 * GET (any signed-in user)
 *   q           search title / description / venue / organizer
 *   category    category name (Technical, Cultural, ...)
 *   category_id category id
 *   status      draft | published | cancelled | completed
 *   upcoming    1 = only future published events
 *   featured    1 = only featured events
 *   sort        date | name | newest | deadline
 *   page        1..
 *   per_page    1-200 (default 10)
 *   id          one event record
 *   action      categories -> list event categories
 *               lookups    -> staff form options (categories, statuses, poster rules)
 *
 * POST (Admin / Faculty staff only)
 *   action=create   add an event (multipart/form-data for the poster)
 *   action=update   edit an event (id + optional poster / remove_poster)
 *   action=delete   permanently remove an event (id required)
 *
 * Every mutation uses the MySQLi prepared statements in db.php through
 * php/event_records.php. Poster uploads are checked server-side for size,
 * extension, MIME type and real image data before they are stored.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../serializers.php';
require_once __DIR__ . '/../event_records.php';

/* Portal content is only available after sign-in (also when the API is called directly). */
require_login();

try {
    $method = request_method();

    if ($method === 'POST') {
        /* Only staff may change events. */
        $actor = require_admin();

        $input = request_data();
        $action = input_string($input, 'action', query_string('action', 'create'));

        if ($action === 'delete') {
            $id = input_int($input, 'id');
            if ($id <= 0) {
                json_error('Choose the event you want to delete.', 422);
            }

            $event = event_record_delete($id, $actor);
            json_success(
                $event,
                'Event "' . $event['title'] . '" and its registrations were deleted.'
            );
        }

        if ($action !== 'create' && $action !== 'update') {
            json_error('Unknown event action.', 400);
        }

        $id = input_int($input, 'id');
        if ($action === 'update' && $id <= 0) {
            json_error('Choose the event you want to update.', 422);
        }

        $data = event_record_input($input);
        $errors = event_record_validate($data);

        /* Store the poster only after the text fields are known to be valid,
         * but still report a bad upload together with any field errors. */
        $poster = null;
        try {
            $poster = event_poster_store($_FILES['poster'] ?? $_FILES['banner'] ?? null);
        } catch (EventPosterException $error) {
            $errors['poster'] = $error->getMessage();
        }

        if ($errors !== []) {
            if ($poster !== null) {
                event_poster_delete_file((string) $poster['path']);
            }
            json_error((string) reset($errors), 422, $errors);
        }

        if ($action === 'create') {
            $event = event_record_create($data, $poster, $actor);
            json_success($event, 'Event "' . $event['title'] . '" was added successfully.');
        }

        $event = event_record_update($id, $data, $poster, $actor);
        json_success($event, 'Event "' . $event['title'] . '" was updated successfully.');
    }

    if ($method !== 'GET') {
        json_error('Method not allowed.', 405);
    }

    $action = query_string('action');

    if ($action === 'categories') {
        $categories = db_query(
            'SELECT id, name, color, icon FROM event_categories WHERE is_active = 1 ORDER BY name ASC'
        );

        json_success(array_map(static function (array $row): array {
            return [
                'id'    => (int) $row['id'],
                'name'  => (string) $row['name'],
                'color' => (string) $row['color'],
                'icon'  => $row['icon'],
            ];
        }, $categories), 'Event categories loaded.');
    }

    if ($action === 'lookups') {
        require_admin();
        json_success(event_lookup_data(), 'Event form options loaded.');
    }

    $id = query_int('id');
    if ($id > 0) {
        $event = event_record_fetch($id);
        if ($event === null) {
            json_error('Event not found.', 404);
        }
        json_success($event, 'Event loaded successfully.');
    }

    /* ----------------------------- list ----------------------------- */
    [$page, $perPage, $offset] = pagination(10, 200);

    $where = [];
    $types = '';
    $params = [];

    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(title LIKE ? OR description LIKE ? OR venue LIKE ? OR organizer_name LIKE ? OR category LIKE ?)';
        $types .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $category = query_string('category');
    if ($category !== '' && strcasecmp($category, 'All') !== 0) {
        $where[] = 'category = ?';
        $types .= 's';
        $params[] = $category;
    }

    $categoryId = query_int('category_id');
    if ($categoryId > 0) {
        $where[] = 'category_id = ?';
        $types .= 'i';
        $params[] = $categoryId;
    }

    $status = query_string('status');
    if ($status !== '' && strcasecmp($status, 'All') !== 0) {
        $where[] = 'status = ?';
        $types .= 's';
        $params[] = $status;
    }

    if (query_bool('upcoming')) {
        $where[] = "status = 'published' AND event_date >= CURDATE()";
    }

    if (query_bool('featured')) {
        $where[] = 'is_featured = 1';
    }

    $sortMap = [
        'date'     => 'event_date ASC, start_time ASC',
        'name'     => 'title ASC',
        'newest'   => 'id DESC',
        'deadline' => 'registration_deadline ASC',
        'updated'  => 'updated_at DESC',
    ];
    $sort = query_string('sort', 'date');
    $orderBy = $sortMap[$sort] ?? $sortMap['date'];

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $total = (int) db_value('SELECT COUNT(*) FROM vw_event_details' . $whereSql, $types, $params);

    $rows = db_query(
        'SELECT * FROM vw_event_details' . $whereSql . '
         ORDER BY ' . $orderBy . '
         LIMIT ? OFFSET ?',
        $types . 'ii',
        array_merge($params, [$perPage, $offset])
    );

    json_success(
        array_map('event_to_api', $rows),
        'Events loaded successfully.',
        pagination_meta($page, $perPage, $total)
    );
} catch (EventRecordNotFound $error) {
    json_error($error->getMessage(), 404);
} catch (EventRecordConflict $error) {
    json_error($error->getMessage(), 409);
} catch (EventPosterException $error) {
    json_error($error->getMessage(), 422, ['poster' => $error->getMessage()]);
} catch (Throwable $error) {
    if (db_is_duplicate($error)) {
        json_error(db_duplicate_message($error), 409);
    }
    json_handle_exception($error);
}
