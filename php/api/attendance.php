<?php
/**
 * GET /php/api/attendance.php
 * ---------------------------
 * Attendance for the signed-in student. Admin / Faculty may pass student_id.
 *
 * Response data:
 *   student    roll number, name, course, department, semester
 *   overall    total / present / absent / late / excused / percentage
 *   subjects   subject-wise totals and percentage
 *   records    the latest attendance entries
 */

declare(strict_types=1);

require_once __DIR__ . '/../serializers.php';
require_once __DIR__ . '/_scope.php';

try {
    $student = scoped_student();
    $studentId = (int) $student['id'];

    $subjects = db_query(
        "SELECT s.id, s.code, s.name,
                COUNT(a.id) AS total,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
                SUM(CASE WHEN a.status = 'absent'  THEN 1 ELSE 0 END) AS absent,
                SUM(CASE WHEN a.status = 'late'    THEN 1 ELSE 0 END) AS late,
                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) AS excused
           FROM attendance a
           JOIN subjects s ON s.id = a.subject_id
          WHERE a.student_id = ?
          GROUP BY s.id, s.code, s.name
          ORDER BY s.name ASC",
        'i',
        [$studentId]
    );

    $subjectSummary = [];
    $totals = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

    foreach ($subjects as $row) {
        $total = (int) $row['total'];
        $present = (int) $row['present'];

        foreach (['total', 'present', 'absent', 'late', 'excused'] as $key) {
            $totals[$key] += $key === 'total' ? $total : (int) $row[$key];
        }

        $subjectSummary[] = [
            'subjectId'  => (int) $row['id'],
            'code'       => (string) $row['code'],
            'name'       => (string) $row['name'],
            'total'      => $total,
            'present'    => $present,
            'absent'     => (int) $row['absent'],
            'late'       => (int) $row['late'],
            'excused'    => (int) $row['excused'],
            'percentage' => $total > 0 ? round($present / $total * 100, 2) : 0.0,
        ];
    }

    $records = db_query(
        "SELECT a.id, a.attendance_date, a.status, a.remarks,
                s.code AS subject_code, s.name AS subject_name
           FROM attendance a
           JOIN subjects s ON s.id = a.subject_id
          WHERE a.student_id = ?
          ORDER BY a.attendance_date DESC, a.id DESC
          LIMIT 200",
        'i',
        [$studentId]
    );

    $recordList = array_map(static function (array $row): array {
        return [
            'id'          => (int) $row['id'],
            'date'        => (string) $row['attendance_date'],
            'displayDate' => format_date_value((string) $row['attendance_date']),
            'status'      => (string) $row['status'],
            'remarks'     => $row['remarks'],
            'subjectCode' => (string) $row['subject_code'],
            'subjectName' => (string) $row['subject_name'],
        ];
    }, $records);

    json_success([
        'student' => [
            'id'         => $studentId,
            'rollNo'     => (string) $student['roll_no'],
            'name'       => (string) $student['full_name'],
            'course'     => $student['course'],
            'department' => $student['department'],
            'semester'   => (int) $student['current_semester'],
        ],
        'overall' => [
            'total'      => $totals['total'],
            'present'    => $totals['present'],
            'absent'     => $totals['absent'],
            'late'       => $totals['late'],
            'excused'    => $totals['excused'],
            'percentage' => $totals['total'] > 0
                ? round($totals['present'] / $totals['total'] * 100, 2)
                : 0.0,
        ],
        'subjects' => $subjectSummary,
        'records'  => $recordList,
    ], 'Attendance loaded.');
} catch (Throwable $error) {
    json_handle_exception($error);
}
