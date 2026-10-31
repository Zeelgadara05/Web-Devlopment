<?php
/**
 * StudentHub Portal - student management records
 * -----------------------------------------------
 * Shared validation and CRUD helpers for the staff-only student module.
 * Every database call in this file goes through the MySQLi prepared-statement
 * helpers in db.php, so no value is ever concatenated into SQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/serializers.php';

class StudentRecordNotFound extends RuntimeException
{
}

class StudentRecordConflict extends RuntimeException
{
}

function student_statuses(): array
{
    return ['active', 'inactive', 'graduated', 'suspended'];
}

function student_genders(): array
{
    return ['Male', 'Female', 'Other'];
}

/**
 * Options used by the add / edit form and the toolbar filters.
 */
function student_lookup_data(): array
{
    $departments = db_query(
        'SELECT id, code, name FROM departments WHERE is_active = 1 ORDER BY name ASC'
    );
    $courses = db_query(
        'SELECT id, department_id, code, name, degree_type
           FROM courses
          WHERE is_active = 1
          ORDER BY name ASC'
    );

    return [
        'departments' => array_map(static function (array $row): array {
            return [
                'id'   => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
            ];
        }, $departments),
        'courses' => array_map(static function (array $row): array {
            return [
                'id'           => (int) $row['id'],
                'departmentId' => (int) $row['department_id'],
                'code'         => (string) $row['code'],
                'name'         => (string) $row['name'],
                'degreeType'   => (string) $row['degree_type'],
            ];
        }, $courses),
        'statuses'  => student_statuses(),
        'genders'   => student_genders(),
        'years'     => range(1, 6),
        'semesters' => range(1, 12),
    ];
}

function student_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function student_optional_text($value): ?string
{
    $text = trim((string) ($value ?? ''));
    return $text === '' ? null : $text;
}

/**
 * Normalise a raw request body into the student field map.
 *
 * @param array<string,mixed> $source
 * @return array<string,mixed>
 */
function student_record_input(array $source): array
{
    return [
        'roll_no'          => strtoupper(input_string($source, 'roll_no')),
        'full_name'        => input_string($source, 'full_name'),
        'email'            => strtolower(input_string($source, 'email')),
        'mobile'           => input_string($source, 'mobile'),
        'password'         => (string) ($source['password'] ?? ''),
        'department_id'    => input_int($source, 'department_id'),
        'course_id'        => input_int($source, 'course_id'),
        'current_year'     => input_int($source, 'current_year', 1),
        'current_semester' => input_int($source, 'current_semester', 1),
        'gender'           => input_string($source, 'gender'),
        'date_of_birth'    => student_optional_text($source['date_of_birth'] ?? ''),
        'address'          => student_optional_text($source['address'] ?? ''),
        'city'             => student_optional_text($source['city'] ?? ''),
        'state'            => student_optional_text($source['state'] ?? ''),
        'pincode'          => student_optional_text($source['pincode'] ?? ''),
        'admission_date'   => student_optional_text($source['admission_date'] ?? ''),
        'status'           => input_string($source, 'status', 'active'),
    ];
}

function student_date_is_valid(string $value): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
        return false;
    }

    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
}

/**
 * Validate the normalised student fields.
 *
 * @param array<string,mixed> $data
 * @return array<string,string> field => message
 */
function student_record_validate(array $data, bool $creating, int $ignoreId = 0): array
{
    $errors = [];

    if ($data['roll_no'] === '') {
        $errors['roll_no'] = 'Roll number is required.';
    } elseif (!preg_match('/^[A-Z0-9-]{4,20}$/', $data['roll_no'])) {
        $errors['roll_no'] = 'Roll number must be 4-20 letters, digits or hyphens (example: CS21010).';
    }

    if ($data['full_name'] === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (student_text_length($data['full_name']) < 2 || student_text_length($data['full_name']) > 100) {
        $errors['full_name'] = 'Full name must be between 2 and 100 characters.';
    } elseif (!preg_match("/^[A-Za-z][A-Za-z .'-]*$/", $data['full_name'])) {
        $errors['full_name'] = 'Full name may only contain letters, spaces, dots, apostrophes and hyphens.';
    }

    if ($data['email'] === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (strlen($data['email']) > 150 || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($data['mobile'] === '') {
        $errors['mobile'] = 'Mobile number is required.';
    } elseif (!preg_match('/^[6-9][0-9]{9}$/', $data['mobile'])) {
        $errors['mobile'] = 'Mobile number must be 10 digits starting with 6-9.';
    }

    if ($creating && $data['password'] === '') {
        $errors['password'] = 'A password is required for a new student.';
    } elseif (strlen($data['password']) > 72) {
        $errors['password'] = 'Password may not exceed 72 characters.';
    } elseif ($data['password'] !== ''
        && !preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $data['password'])
    ) {
        $errors['password'] = 'Password must be at least 8 characters and include a letter, a number and a special character.';
    }

    if ($data['department_id'] <= 0) {
        $errors['department_id'] = 'Please select a department.';
    } elseif (db_value('SELECT id FROM departments WHERE id = ? LIMIT 1', 'i', [$data['department_id']]) === null) {
        $errors['department_id'] = 'The selected department no longer exists.';
    }

    $course = null;
    if ($data['course_id'] <= 0) {
        $errors['course_id'] = 'Please select a course.';
    } else {
        $course = db_one(
            'SELECT id, department_id FROM courses WHERE id = ? LIMIT 1',
            'i',
            [$data['course_id']]
        );

        if ($course === null) {
            $errors['course_id'] = 'The selected course no longer exists.';
        } elseif ($data['department_id'] > 0
            && (int) $course['department_id'] !== $data['department_id']
        ) {
            $errors['course_id'] = 'Select a course that belongs to the chosen department.';
        }
    }

    if ($data['current_year'] < 1 || $data['current_year'] > 6) {
        $errors['current_year'] = 'Year must be between 1 and 6.';
    }

    if ($data['current_semester'] < 1 || $data['current_semester'] > 12) {
        $errors['current_semester'] = 'Semester must be between 1 and 12.';
    }

    if (!in_array($data['gender'], student_genders(), true)) {
        $errors['gender'] = 'Please select a valid gender.';
    }

    if (!in_array($data['status'], student_statuses(), true)) {
        $errors['status'] = 'Please select a valid status.';
    }

    if ($data['date_of_birth'] !== null) {
        if (!student_date_is_valid($data['date_of_birth'])) {
            $errors['date_of_birth'] = 'Date of birth must be a valid date.';
        } elseif ($data['date_of_birth'] > date('Y-m-d')) {
            $errors['date_of_birth'] = 'Date of birth cannot be in the future.';
        }
    }

    if ($data['admission_date'] !== null && !student_date_is_valid($data['admission_date'])) {
        $errors['admission_date'] = 'Admission date must be a valid date.';
    }

    if ($data['address'] !== null && student_text_length($data['address']) > 255) {
        $errors['address'] = 'Address may not exceed 255 characters.';
    }
    if ($data['city'] !== null && student_text_length($data['city']) > 100) {
        $errors['city'] = 'City may not exceed 100 characters.';
    }
    if ($data['state'] !== null && student_text_length($data['state']) > 100) {
        $errors['state'] = 'State may not exceed 100 characters.';
    }
    if ($data['pincode'] !== null && !preg_match('/^[0-9]{6}$/', $data['pincode'])) {
        $errors['pincode'] = 'Pincode must contain exactly 6 digits.';
    }

    /* Friendly duplicate messages before MySQL raises its unique-key error. */
    if ($errors === [] && ($data['roll_no'] !== '' || $data['email'] !== '' || $data['mobile'] !== '')) {
        $duplicates = db_query(
            'SELECT id, roll_no, email, mobile
               FROM students
              WHERE (roll_no = ? OR email = ? OR mobile = ?)
                AND id <> ?
              LIMIT 3',
            'sssi',
            [$data['roll_no'], $data['email'], $data['mobile'], $ignoreId]
        );

        foreach ($duplicates as $row) {
            if ($data['roll_no'] !== '' && strcasecmp((string) $row['roll_no'], $data['roll_no']) === 0) {
                $errors['roll_no'] = 'This roll number is already registered.';
            }
            if ($data['email'] !== '' && strcasecmp((string) $row['email'], $data['email']) === 0) {
                $errors['email'] = 'This email address is already registered.';
            }
            if ($data['mobile'] !== '' && (string) $row['mobile'] === $data['mobile']) {
                $errors['mobile'] = 'This mobile number is already registered.';
            }
        }
    }

    return $errors;
}

/**
 * Load one student in the API shape, including event participation count.
 */
function student_record_fetch(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $row = db_one(
        'SELECT id, roll_no, full_name, email, mobile, gender, current_year, current_semester,
                status, profile_photo, date_of_birth, address, city, state, pincode,
                admission_date, last_login_at, created_at, department_id, department_code, department,
                course_id, course_code, course, degree_type,
                (SELECT COUNT(*) FROM event_registrations er
                  WHERE er.student_id = vw_student_details.id
                    AND er.status IN (\'pending\',\'confirmed\',\'attended\')) AS total_events
           FROM vw_student_details
          WHERE id = ?
          LIMIT 1',
        'i',
        [$id]
    );

    return $row === null ? null : student_to_api($row);
}

/**
 * @param array<string,mixed> $data  Validated student fields.
 * @param array<string,mixed> $actor Signed-in staff member.
 */
function student_record_create(array $data, array $actor): array
{
    try {
        $result = db_execute(
            'INSERT INTO students
                (roll_no, full_name, email, mobile, password_hash, department_id, course_id,
                 current_year, current_semester, gender, date_of_birth, address, city, state,
                 pincode, admission_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'sssssiiiissssssss',
            [
                $data['roll_no'],
                $data['full_name'],
                $data['email'],
                $data['mobile'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['department_id'],
                $data['course_id'],
                $data['current_year'],
                $data['current_semester'],
                $data['gender'],
                $data['date_of_birth'],
                $data['address'],
                $data['city'],
                $data['state'],
                $data['pincode'],
                $data['admission_date'],
                $data['status'],
            ]
        );
    } catch (Throwable $error) {
        if (db_is_duplicate($error)) {
            throw new StudentRecordConflict(db_duplicate_message($error));
        }
        throw $error;
    }

    $id = (int) $result['insert_id'];

    log_activity(
        'student_created',
        'student',
        $id,
        'Student record created: ' . $data['roll_no'] . ' (' . $data['full_name'] . ').',
        (int) ($actor['id'] ?? 0)
    );

    $student = student_record_fetch($id);
    if ($student === null) {
        throw new RuntimeException('The student was saved but could not be loaded again.');
    }

    return $student;
}

/**
 * @param array<string,mixed> $data  Validated student fields.
 * @param array<string,mixed> $actor Signed-in staff member.
 */
function student_record_update(int $id, array $data, array $actor): array
{
    $existing = db_one(
        'SELECT id, roll_no, status, password_hash FROM students WHERE id = ? LIMIT 1',
        'i',
        [$id]
    );

    if ($existing === null) {
        throw new StudentRecordNotFound('Student not found.');
    }

    $sets = [
        'roll_no = ?',
        'full_name = ?',
        'email = ?',
        'mobile = ?',
        'department_id = ?',
        'course_id = ?',
        'current_year = ?',
        'current_semester = ?',
        'gender = ?',
        'date_of_birth = ?',
        'address = ?',
        'city = ?',
        'state = ?',
        'pincode = ?',
        'admission_date = ?',
        'status = ?',
    ];
    $types = 'ssssiiiissssssss';
    $params = [
        $data['roll_no'],
        $data['full_name'],
        $data['email'],
        $data['mobile'],
        $data['department_id'],
        $data['course_id'],
        $data['current_year'],
        $data['current_semester'],
        $data['gender'],
        $data['date_of_birth'],
        $data['address'],
        $data['city'],
        $data['state'],
        $data['pincode'],
        $data['admission_date'],
        $data['status'],
    ];

    $passwordChanged = $data['password'] !== '';
    if ($passwordChanged) {
        $sets[] = 'password_hash = ?';
        $types .= 's';
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    $types .= 'i';
    $params[] = $id;

    try {
        db_execute('UPDATE students SET ' . implode(', ', $sets) . ' WHERE id = ?', $types, $params);
    } catch (Throwable $error) {
        if (db_is_duplicate($error)) {
            throw new StudentRecordConflict(db_duplicate_message($error));
        }
        throw $error;
    }

    /* A changed password or a deactivated account must not keep old sessions. */
    if ($passwordChanged || $data['status'] !== 'active') {
        revoke_user_remember_tokens('student', $id);
    }

    log_activity(
        'student_updated',
        'student',
        $id,
        'Student record updated: ' . $data['roll_no'] . ' (' . $data['full_name'] . ').'
            . ($passwordChanged ? ' Password was reset.' : ''),
        (int) ($actor['id'] ?? 0)
    );

    $student = student_record_fetch($id);
    if ($student === null) {
        throw new RuntimeException('The student was updated but could not be loaded again.');
    }

    return $student;
}

/**
 * Permanently delete a student and every related record that cascades.
 *
 * @param array<string,mixed> $actor Signed-in staff member.
 * @return array<string,mixed> The removed student.
 */
function student_record_delete(int $id, array $actor): array
{
    $student = student_record_fetch($id);
    if ($student === null) {
        throw new StudentRecordNotFound('Student not found.');
    }

    /* Remove remember-me tokens explicitly before the student row disappears. */
    revoke_user_remember_tokens('student', $id);

    $result = db_execute('DELETE FROM students WHERE id = ?', 'i', [$id]);
    if ((int) $result['affected_rows'] !== 1) {
        throw new StudentRecordNotFound('Student not found.');
    }

    log_activity(
        'student_deleted',
        'student',
        $id,
        'Student record deleted: ' . $student['rollNo'] . ' (' . $student['name'] . ').',
        (int) ($actor['id'] ?? 0)
    );

    return $student;
}
