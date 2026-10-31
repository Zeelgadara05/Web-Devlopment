<?php
/**
 * GET /php/api/results.php
 * ------------------------
 * Examination results for the signed-in student.
 * Admin / Faculty may pass student_id to inspect another student's results.
 */

declare(strict_types=1);

require_once __DIR__ . '/../serializers.php';
require_once __DIR__ . '/_scope.php';

try {
    $student = scoped_student();
    $studentId = (int) $student['id'];

    $rows = db_query(
        "SELECT r.id, r.exam_type, r.marks_obtained, r.max_marks, r.grade,
                r.exam_date, r.published_at,
                s.id AS subject_id, s.code AS subject_code, s.name AS subject_name
           FROM results r
           JOIN subjects s ON s.id = r.subject_id
          WHERE r.student_id = ?
          ORDER BY s.name ASC, FIELD(r.exam_type, 'internal','midterm','final','practical','assignment'), r.id ASC",
        'i',
        [$studentId]
    );

    $results = [];
    $percentageTotal = 0.0;
    $subjectIds = [];

    foreach ($rows as $row) {
        $marks = (float) $row['marks_obtained'];
        $max = (float) $row['max_marks'];
        $percentage = $max > 0 ? round($marks / $max * 100, 2) : 0.0;

        $percentageTotal += $percentage;
        $subjectIds[(int) $row['subject_id']] = true;

        $results[] = [
            'id'          => (int) $row['id'],
            'subjectId'   => (int) $row['subject_id'],
            'subjectCode' => (string) $row['subject_code'],
            'subjectName' => (string) $row['subject_name'],
            'examType'    => (string) $row['exam_type'],
            'examLabel'   => ucfirst((string) $row['exam_type']),
            'marks'       => $marks,
            'maxMarks'    => $max,
            'percentage'  => $percentage,
            'grade'       => $row['grade'],
            'examDate'    => $row['exam_date'],
            'displayDate' => format_date_value($row['exam_date']),
            'publishedAt' => $row['published_at'],
        ];
    }

    json_success([
        'student' => [
            'id'       => $studentId,
            'rollNo'   => (string) $student['roll_no'],
            'name'     => (string) $student['full_name'],
            'course'   => $student['course'],
            'semester' => (int) $student['current_semester'],
        ],
        'summary' => [
            'exams'     => count($results),
            'subjects'  => count($subjectIds),
            'average'   => $results === [] ? 0.0 : round($percentageTotal / count($results), 2),
        ],
        'results' => $results,
    ], 'Results loaded.');
} catch (Throwable $error) {
    json_handle_exception($error);
}
