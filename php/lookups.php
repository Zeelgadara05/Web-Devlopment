<?php
/**
 * StudentHub Portal - lookup helpers
 * ----------------------------------
 * Maps the values coming from the registration form (degree names and
 * department names) onto the departments / courses tables.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Find a department by name or code (case-insensitive).
 */
function find_department(string $nameOrCode): ?array
{
    $value = trim($nameOrCode);
    if ($value === '') {
        return null;
    }

    return db_one(
        'SELECT * FROM departments WHERE (name = ? OR code = ?) AND is_active = 1 LIMIT 1',
        'ss',
        [$value, strtoupper($value)]
    );
}

/**
 * Find a course by code, id or exact name.
 */
function find_course(string $codeOrName): ?array
{
    $value = trim($codeOrName);
    if ($value === '') {
        return null;
    }

    return db_one(
        'SELECT * FROM courses WHERE (code = ? OR name = ?) AND is_active = 1 LIMIT 1',
        'ss',
        [strtoupper($value), $value]
    );
}

/**
 * Map the registration form's degree choice + department onto a course row.
 *
 * Form values: B.Tech, M.Tech, BCA, MCA
 * Department: Computer Science, Electronics, Mechanical, Civil (+ IT, Management, ...)
 */
function resolve_course_for_registration(string $degree, ?array $department): ?array
{
    $degree = trim($degree);
    if ($degree === '') {
        return null;
    }

    $deptCode = $department['code'] ?? null;
    $courseCode = null;

    switch (strtoupper($degree)) {
        case 'B.TECH':
        case 'BTECH':
            $map = [
                'CS' => 'BTECH-CSE',
                'IT' => 'BTECH-IT',
                'EC' => 'BTECH-EC',
                'ME' => 'BTECH-ME',
                'CE' => 'BTECH-CE',
            ];
            $courseCode = $deptCode !== null ? ($map[$deptCode] ?? null) : 'BTECH-CSE';
            break;

        case 'M.TECH':
        case 'MTECH':
            $courseCode = 'MTECH-CSE';
            break;

        case 'BCA':
            $courseCode = 'BCA';
            break;

        case 'MCA':
            $courseCode = 'MCA';
            break;
    }

    if ($courseCode !== null) {
        $course = find_course($courseCode);
        if ($course !== null) {
            return $course;
        }
    }

    /* Fallback: first active course of the degree type. */
    $degreeType = in_array(strtoupper($degree), ['BCA', 'MCA'], true) ? 'Bachelor' : null;
    if (strtoupper($degree) === 'MCA') {
        $degreeType = 'Master';
    }
    if (strtoupper($degree) === 'M.TECH' || strtoupper($degree) === 'MTECH') {
        $degreeType = 'Master';
    }
    if (strtoupper($degree) === 'B.TECH' || strtoupper($degree) === 'BTECH') {
        $degreeType = 'Bachelor';
    }

    if ($department !== null) {
        $course = db_one(
            'SELECT * FROM courses
              WHERE department_id = ? AND is_active = 1' . ($degreeType !== null ? ' AND degree_type = ?' : '') . '
              ORDER BY id ASC LIMIT 1',
            $degreeType !== null ? 'is' : 'i',
            $degreeType !== null
                ? [(int) $department['id'], $degreeType]
                : [(int) $department['id']]
        );
        if ($course !== null) {
            return $course;
        }
    }

    if ($degreeType !== null) {
        return db_one(
            'SELECT * FROM courses WHERE degree_type = ? AND is_active = 1 ORDER BY id ASC LIMIT 1',
            's',
            [$degreeType]
        );
    }

    return null;
}

/**
 * Generate a roll number such as STU0010 that is not already used.
 */
function generate_roll_number(int $seed): string
{
    $candidate = max(1, $seed);

    for ($attempt = 0; $attempt < 1000; $attempt++) {
        $rollNo = 'STU' . str_pad((string) $candidate, 4, '0', STR_PAD_LEFT);
        $exists = db_value('SELECT id FROM students WHERE roll_no = ? LIMIT 1', 's', [$rollNo]);
        if ($exists === null) {
            return $rollNo;
        }
        $candidate++;
    }

    return 'STU' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Semester number for a given year (odd semester by default).
 */
function semester_for_year(int $year): int
{
    return max(1, min(12, ($year * 2) - 1));
}
