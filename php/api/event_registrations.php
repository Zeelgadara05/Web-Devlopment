<?php
/**
 * /php/api/event_registrations.php
 * --------------------------------
 * GET  - list registrations (by event, by student/roll number, or by code)
 * POST - register a student for an event
 * POST - cancel a registration (action=cancel)
 *
 * Registration payload (JSON or form):
 *   event_id, roll_no | student_id | email, team_name, notes, members[]
 *
 * Cancel payload:
 *   action=cancel, registration_code, roll_no | student_id
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../serializers.php';

/**
 * Build the next unique registration code, e.g. REG-2026-0008.
 */
function next_registration_code(): string
{
    $year = date('Y');
    $candidate = (int) db_value('SELECT COALESCE(MAX(id), 0) + 1 FROM event_registrations');

    for ($attempt = 0; $attempt < 1000; $attempt++) {
        $code = sprintf('REG-%s-%04d', $year, $candidate);
        $exists = db_value(
            'SELECT id FROM event_registrations WHERE registration_code = ? LIMIT 1',
            's',
            [$code]
        );
        if ($exists === null) {
            return $code;
        }
        $candidate++;
    }

    return 'REG-' . $year . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Resolve the student from the request.
 *
 * A signed-in student is always pinned to their own record, whatever the
 * request body says. Only Admin / Faculty may act on behalf of another student.
 */
function resolve_student(array $data): ?array
{
    $user = require_login();

    if (($user['type'] ?? '') === 'student') {
        return db_one('SELECT * FROM students WHERE id = ? LIMIT 1', 'i', [(int) $user['id']]);
    }

    $studentId = input_int($data, 'student_id');
    if ($studentId > 0) {
        return db_one('SELECT * FROM students WHERE id = ? LIMIT 1', 'i', [$studentId]);
    }

    $rollNo = strtoupper(input_string($data, 'roll_no'));
    if ($rollNo !== '') {
        return db_one('SELECT * FROM students WHERE roll_no = ? LIMIT 1', 's', [$rollNo]);
    }

    $email = input_string($data, 'email');
    if ($email !== '') {
        return db_one('SELECT * FROM students WHERE email = ? LIMIT 1', 's', [$email]);
    }

    return null;
}

/**
 * GET requests.
 */
function handle_get(): void
{
    $user = require_login();
    [$page, $perPage, $offset] = pagination(10, 200);

    $code = query_string('code');
    if ($code !== '') {
        $row = db_one(
            'SELECT * FROM vw_event_registration_details WHERE registration_code = ? LIMIT 1',
            's',
            [$code]
        );
        if ($row === null) {
            json_error('Registration not found.', 404);
        }

        if (($user['type'] ?? '') === 'student' && (int) $row['student_id'] !== (int) $user['id']) {
            json_error('You can only view your own registrations.', 403);
        }

        json_success(registration_to_api($row), 'Registration loaded.');
    }

    $where = [];
    $types = '';
    $params = [];

    /* Students can never list another student's registrations. */
    if (($user['type'] ?? '') === 'student') {
        $where[] = 'student_id = ?';
        $types .= 'i';
        $params[] = (int) $user['id'];
    } else {
        $eventId = query_int('event_id');
        if ($eventId > 0) {
            $where[] = 'event_id = ?';
            $types .= 'i';
            $params[] = $eventId;
        }

        $studentId = query_int('student_id');
        if ($studentId > 0) {
            $where[] = 'student_id = ?';
            $types .= 'i';
            $params[] = $studentId;
        }

        $rollNo = strtoupper(query_string('roll_no'));
        if ($rollNo !== '') {
            $where[] = 'roll_no = ?';
            $types .= 's';
            $params[] = $rollNo;
        }
    }

    $status = query_string('status');
    if ($status !== '') {
        $where[] = 'registration_status = ?';
        $types .= 's';
        $params[] = $status;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $total = (int) db_value(
        'SELECT COUNT(*) FROM vw_event_registration_details' . $whereSql,
        $types,
        $params
    );

    $rows = db_query(
        'SELECT * FROM vw_event_registration_details' . $whereSql . '
         ORDER BY registered_at DESC, id DESC
         LIMIT ? OFFSET ?',
        $types . 'ii',
        array_merge($params, [$perPage, $offset])
    );

    $registrations = array_map('registration_to_api', $rows);

    json_success($registrations, 'Registrations loaded.', pagination_meta($page, $perPage, $total));
}

/**
 * Register a student for an event.
 */
function handle_register(array $data): void
{
    $eventId = input_int($data, 'event_id');
    if ($eventId <= 0) {
        json_error('Please choose an event to register for.', 422, ['event_id' => 'Event is required.']);
    }

    $teamName = input_string($data, 'team_name');
    if (strlen($teamName) > 100) {
        json_error('Team name is too long (maximum 100 characters).', 422);
    }

    $notes = input_string($data, 'notes');
    if (strlen($notes) > 1000) {
        json_error('Notes are too long (maximum 1000 characters).', 422);
    }

    $members = $data['members'] ?? [];
    if (!is_array($members)) {
        $members = [];
    }
    $members = array_slice($members, 0, 10);

    $result = db_transaction(function () use ($eventId, $teamName, $notes, $members, $data): array {
        /* Lock the event row while checking capacity. */
        $event = db_one('SELECT * FROM events WHERE id = ? FOR UPDATE', 'i', [$eventId]);
        if ($event === null) {
            json_error('Event not found.', 404);
        }

        if ($event['status'] !== 'published') {
            json_error('Registration is not open for this event.', 409);
        }

        $now = time();
        if (!empty($event['event_date']) && strtotime($event['event_date'] . ' 23:59:59') < $now) {
            json_error('This event has already taken place.', 409);
        }

        if (!empty($event['registration_deadline']) && strtotime($event['registration_deadline']) < $now) {
            json_error('The registration deadline for this event has passed.', 409);
        }

        $activeCount = (int) db_value(
            "SELECT COUNT(*) FROM event_registrations
              WHERE event_id = ? AND status IN ('pending','confirmed','attended')",
            'i',
            [$eventId]
        );

        if ($event['max_participants'] !== null && $activeCount >= (int) $event['max_participants']) {
            json_error('This event is full. Please contact the organiser.', 409);
        }

        $student = resolve_student($data);
        if ($student === null) {
            json_error(
                'No student account was found for that roll number. Please register on the portal first.',
                404
            );
        }

        if ($student['status'] !== 'active') {
            json_error('Your student account is not active. Please contact the administration.', 403);
        }

        $existing = db_one(
            'SELECT id, registration_code, status FROM event_registrations
              WHERE event_id = ? AND student_id = ? LIMIT 1',
            'ii',
            [$eventId, (int) $student['id']]
        );

        if ($existing !== null && $existing['status'] !== 'cancelled') {
            json_error(
                'You are already registered for this event. Registration code: ' . $existing['registration_code'],
                409
            );
        }

        /* Re-activate a previously cancelled registration. */
        if ($existing !== null) {
            db_execute(
                "UPDATE event_registrations
                    SET status = 'confirmed', cancelled_at = NULL, team_name = ?, notes = ?, registered_at = NOW()
                  WHERE id = ?",
                'ssi',
                [$teamName !== '' ? $teamName : null, $notes !== '' ? $notes : null, (int) $existing['id']]
            );
            $registrationId = (int) $existing['id'];
            $registrationCode = (string) $existing['registration_code'];
        } else {
            $registrationCode = next_registration_code();

            $insert = db_execute(
                "INSERT INTO event_registrations
                    (registration_code, event_id, student_id, status, team_name, notes)
                 VALUES (?, ?, ?, 'confirmed', ?, ?)",
                'siiss',
                [
                    $registrationCode,
                    $eventId,
                    (int) $student['id'],
                    $teamName !== '' ? $teamName : null,
                    $notes !== '' ? $notes : null,
                ]
            );
            $registrationId = (int) $insert['insert_id'];
        }

        /* Replace team members. */
        db_execute('DELETE FROM event_registration_members WHERE registration_id = ?', 'i', [$registrationId]);

        foreach ($members as $member) {
            if (is_string($member)) {
                $memberName = trim($member);
                $memberRoll = null;
                $memberEmail = null;
            } elseif (is_array($member)) {
                $memberName = trim((string) ($member['name'] ?? ''));
                $memberRoll = trim((string) ($member['roll_no'] ?? '')) ?: null;
                $memberEmail = trim((string) ($member['email'] ?? '')) ?: null;
            } else {
                continue;
            }

            if ($memberName === '') {
                continue;
            }

            db_execute(
                'INSERT INTO event_registration_members (registration_id, member_name, roll_no, email)
                 VALUES (?, ?, ?, ?)',
                'isss',
                [$registrationId, substr($memberName, 0, 100), $memberRoll, $memberEmail]
            );
        }

        log_activity(
            'event_registration',
            'event_registration',
            $registrationId,
            'Registered for event #' . $eventId,
            null,
            (int) $student['id']
        );

        return ['registration_id' => $registrationId, 'registration_code' => $registrationCode];
    });

    $row = db_one(
        'SELECT * FROM vw_event_registration_details WHERE id = ? LIMIT 1',
        'i',
        [$result['registration_id']]
    );

    if ($row === null) {
        json_error('Registration was saved but could not be loaded.', 500);
    }

    $membersRows = db_query(
        'SELECT member_name, roll_no, email, is_leader
           FROM event_registration_members WHERE registration_id = ? ORDER BY id ASC',
        'i',
        [$result['registration_id']]
    );

    $registration = registration_to_api($row);
    $registration['members'] = array_map(static function (array $member): array {
        return [
            'name'     => (string) $member['member_name'],
            'rollNo'   => $member['roll_no'],
            'email'    => $member['email'],
            'isLeader' => (int) $member['is_leader'] === 1,
        ];
    }, $membersRows);

    json_success($registration, 'Registration successful! Your code is ' . $result['registration_code'] . '.');
}

/**
 * Cancel a registration.
 */
function handle_cancel(array $data): void
{
    $code = input_string($data, 'registration_code');
    if ($code === '') {
        json_error('Registration code is required.', 422);
    }

    $registration = db_one(
        'SELECT er.*, e.registration_deadline, e.event_date, e.title
           FROM event_registrations er
           JOIN events e ON e.id = er.event_id
          WHERE er.registration_code = ? LIMIT 1',
        's',
        [$code]
    );

    if ($registration === null) {
        json_error('Registration not found.', 404);
    }

    $student = resolve_student($data);
    if ($student === null || (int) $student['id'] !== (int) $registration['student_id']) {
        json_error('You can only cancel your own registration.', 403);
    }

    if ($registration['status'] === 'cancelled') {
        json_error('This registration is already cancelled.', 409);
    }

    if (!empty($registration['registration_deadline']) && strtotime($registration['registration_deadline']) < time()) {
        json_error('The registration deadline has passed, so this registration can no longer be cancelled.', 409);
    }

    db_execute(
        "UPDATE event_registrations
            SET status = 'cancelled', cancelled_at = NOW()
          WHERE id = ?",
        'i',
        [(int) $registration['id']]
    );

    log_activity(
        'event_registration_cancelled',
        'event_registration',
        (int) $registration['id'],
        'Cancelled registration for event #' . (int) $registration['event_id'],
        null,
        (int) $registration['student_id']
    );

    json_success(
        ['registrationCode' => $code, 'status' => 'cancelled'],
        'Registration cancelled successfully.'
    );
}

/**
 * Router.
 */
try {
    /* Every registration action requires a signed-in user. */
    require_login();

    if (request_method() === 'GET') {
        handle_get();
    }

    if (request_method() === 'POST') {
        $data = request_data();
        $action = strtolower(input_string($data, 'action'));

        if ($action === 'cancel') {
            handle_cancel($data);
        }

        handle_register($data);
    }

    json_error('Method not allowed.', 405);
} catch (Throwable $error) {
    if (db_is_duplicate($error)) {
        json_error(db_duplicate_message($error), 409);
    }
    json_handle_exception($error);
}
