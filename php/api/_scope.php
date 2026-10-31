<?php
/**
 * StudentHub Portal - shared read scope for personal records
 * ----------------------------------------------------------
 * A student is always pinned to their own row. Admin / Faculty must name a
 * student with ?student_id= so they can review any student's profile.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

function scoped_student(): array
{
    $user = require_login();

    if (($user['type'] ?? '') === 'student') {
        $studentId = (int) $user['id'];
    } else {
        $studentId = query_int('student_id');
        if ($studentId <= 0) {
            json_error('Choose a student to view (student_id is required for staff).', 422);
        }
    }

    $student = db_one('SELECT * FROM vw_student_details WHERE id = ? LIMIT 1', 'i', [$studentId]);

    if ($student === null) {
        json_error('Student not found.', 404);
    }

    return $student;
}
