<?php
/**
 * GET /php/api/timetable.php
 * --------------------------
 * Weekly timetable for the signed-in student's department and semester.
 * Admin / Faculty may pass student_id (or department_id + semester) to inspect
 * any class group.
 */

declare(strict_types=1);

require_once __DIR__ . '/../serializers.php';
require_once __DIR__ . '/_scope.php';

try {
    $student = scoped_student();

    $departmentId = (int) ($student['department_id'] ?? 0);
    $courseId = (int) ($student['course_id'] ?? 0);
    $semester = (int) ($student['current_semester'] ?? 0);

    if ($departmentId <= 0 || $semester <= 0) {
        json_success([
            'student' => [
                'id'       => (int) $student['id'],
                'rollNo'   => (string) $student['roll_no'],
                'name'     => (string) $student['full_name'],
                'course'   => $student['course'],
                'semester' => $semester,
            ],
            'days' => [],
        ], 'No timetable is configured for this student yet.');
    }

    $where = 't.is_active = 1 AND t.department_id = ? AND t.semester = ?';
    $types = 'ii';
    $params = [$departmentId, $semester];

    if ($courseId > 0) {
        $where .= ' AND (t.course_id IS NULL OR t.course_id = ?)';
        $types .= 'i';
        $params[] = $courseId;
    }

    $rows = db_query(
        "SELECT t.id, t.day_of_week, t.start_time, t.end_time, t.title AS slot_title,
                t.room, t.faculty_name,
                s.code AS subject_code, s.name AS subject_name
           FROM timetable t
           LEFT JOIN subjects s ON s.id = t.subject_id
          WHERE $where
          ORDER BY t.day_of_week ASC, t.start_time ASC",
        $types,
        $params
    );

    $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $days = [];

    foreach ($rows as $row) {
        $day = (int) $row['day_of_week'];
        if (!isset($days[$day])) {
            $days[$day] = [
                'dayOfWeek' => $day,
                'dayName'   => $dayNames[$day] ?? ('Day ' . $day),
                'slots'     => [],
            ];
        }

        $days[$day]['slots'][] = [
            'id'          => (int) $row['id'],
            'startTime'   => format_time_value((string) $row['start_time']),
            'endTime'     => format_time_value((string) $row['end_time']),
            'title'       => (string) ($row['subject_name'] ?? $row['slot_title'] ?? 'Class'),
            'subjectCode' => $row['subject_code'],
            'subjectName' => $row['subject_name'],
            'room'        => $row['room'],
            'faculty'     => $row['faculty_name'],
        ];
    }

    json_success([
        'student' => [
            'id'         => (int) $student['id'],
            'rollNo'     => (string) $student['roll_no'],
            'name'       => (string) $student['full_name'],
            'course'     => $student['course'],
            'department' => $student['department'],
            'semester'   => $semester,
        ],
        'days' => array_values($days),
    ], 'Timetable loaded.');
} catch (Throwable $error) {
    json_handle_exception($error);
}
