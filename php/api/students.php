<?php
/**
 * /php/api/students.php
 * ---------------------
 * Staff-only student management API.
 *
 * GET  (list)
 *   q             search name / roll no / email / mobile / department / course
 *   department    exact department name, or "Not specified"
 *   department_id exact department id
 *   course        exact course name
 *   course_id     exact course id
 *   year          1-6
 *   status        active | inactive | graduated | suspended
 *   sort          newest | name | rollNo | year
 *   page          1..
 *   per_page      1-200 (default 10)
 *
 * GET  ?action=lookups        department / course / status / gender options
 * GET  ?id=123                one student record
 *
 * POST action=create          add a student
 * POST action=update          edit a student (id required)
 * POST action=delete          permanently remove a student (id required)
 *
 * Every mutation uses the MySQLi prepared statements in db.php through
 * php/student_records.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../serializers.php';
require_once __DIR__ . '/../student_records.php';

/* The student management page is staff-only. Students must never list each other. */
$actor = require_admin();

try {
    $method = request_method();

    if ($method === 'POST') {
        $input = request_data();
        $action = input_string($input, 'action', query_string('action'));

        if ($action === 'create') {
            $data = student_record_input($input);
            $errors = student_record_validate($data, true);

            if ($errors !== []) {
                json_error((string) reset($errors), 422, $errors);
            }

            $student = student_record_create($data, $actor);
            json_success($student, 'Student "' . $student['name'] . '" was added successfully.');
        }

        if ($action === 'update') {
            $id = input_int($input, 'id');
            if ($id <= 0) {
                json_error('Choose the student record to update.', 422);
            }

            $data = student_record_input($input);
            $errors = student_record_validate($data, false, $id);

            if ($errors !== []) {
                json_error((string) reset($errors), 422, $errors);
            }

            $student = student_record_update($id, $data, $actor);
            json_success($student, 'Student "' . $student['name'] . '" was updated successfully.');
        }

        if ($action === 'delete') {
            $id = input_int($input, 'id');
            if ($id <= 0) {
                json_error('Choose the student record to delete.', 422);
            }

            $student = student_record_delete($id, $actor);
            json_success(
                $student,
                'Student "' . $student['name'] . '" (' . $student['rollNo'] . ') was deleted.'
            );
        }

        json_error('Unknown student action.', 400);
    }

    if ($method !== 'GET') {
        json_error('Method not allowed.', 405);
    }

    if (query_string('action') === 'lookups') {
        json_success(student_lookup_data(), 'Student form options loaded.');
    }

    $id = query_int('id');
    if ($id > 0) {
        $student = student_record_fetch($id);
        if ($student === null) {
            json_error('Student not found.', 404);
        }
        json_success($student, 'Student loaded successfully.');
    }

    /* ----------------------------- list ----------------------------- */
    [$page, $perPage, $offset] = pagination(10, 200);

    $where = [];
    $types = '';
    $params = [];

    $q = query_string('q');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(full_name LIKE ? OR roll_no LIKE ? OR email LIKE ? OR mobile LIKE ? OR department LIKE ? OR course LIKE ?)';
        $types .= 'ssssss';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    $department = query_string('department');
    if ($department !== '') {
        if (strcasecmp($department, 'Not specified') === 0) {
            $where[] = '(department IS NULL OR department = "")';
        } else {
            $where[] = 'department = ?';
            $types .= 's';
            $params[] = $department;
        }
    }

    $departmentId = query_int('department_id');
    if ($departmentId > 0) {
        $where[] = 'department_id = ?';
        $types .= 'i';
        $params[] = $departmentId;
    }

    $year = query_int('year');
    if ($year > 0) {
        $where[] = 'current_year = ?';
        $types .= 'i';
        $params[] = $year;
    }

    $course = query_string('course');
    if ($course !== '') {
        $where[] = 'course = ?';
        $types .= 's';
        $params[] = $course;
    }

    $courseId = query_int('course_id');
    if ($courseId > 0) {
        $where[] = 'course_id = ?';
        $types .= 'i';
        $params[] = $courseId;
    }

    $status = query_string('status');
    if ($status !== '') {
        $where[] = 'status = ?';
        $types .= 's';
        $params[] = $status;
    }

    $sortMap = [
        'newest' => 'id DESC',
        'name'   => 'full_name ASC',
        'rollNo' => 'roll_no ASC',
        'year'   => 'current_year ASC, full_name ASC',
    ];
    $sort = query_string('sort', 'newest');
    $orderBy = $sortMap[$sort] ?? $sortMap['newest'];

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $total = (int) db_value(
        'SELECT COUNT(*) FROM vw_student_details' . $whereSql,
        $types,
        $params
    );

    $rows = db_query(
        'SELECT id, roll_no, full_name, email, mobile, gender, current_year, current_semester,
                status, profile_photo, date_of_birth, address, city, state, pincode,
                admission_date, last_login_at, created_at, department_id, department_code, department,
                course_id, course_code, course, degree_type,
                (SELECT COUNT(*) FROM event_registrations er
                  WHERE er.student_id = vw_student_details.id
                    AND er.status IN (\'pending\',\'confirmed\',\'attended\')) AS total_events
         FROM vw_student_details' . $whereSql . '
         ORDER BY ' . $orderBy . '
         LIMIT ? OFFSET ?',
        $types . 'ii',
        array_merge($params, [$perPage, $offset])
    );

    $students = array_map('student_to_api', $rows);

    json_success($students, 'Students loaded successfully.', pagination_meta($page, $perPage, $total));
} catch (StudentRecordNotFound $error) {
    json_error($error->getMessage(), 404);
} catch (StudentRecordConflict $error) {
    json_error($error->getMessage(), 409);
} catch (Throwable $error) {
    if (db_is_duplicate($error)) {
        json_error(db_duplicate_message($error), 409);
    }
    json_handle_exception($error);
}
