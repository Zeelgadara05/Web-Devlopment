<?php
/**
 * StudentHub Portal - row serializers
 * -----------------------------------
 * Convert database rows into the JSON shape used by the front-end.
 * Keeping the mapping here means every API returns consistent fields.
 */

declare(strict_types=1);

function format_date_value(?string $value, string $format = 'd M Y'): ?string
{
    if ($value === null || $value === '' || $value === '0000-00-00') {
        return null;
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? null : date($format, $timestamp);
}

function format_time_value(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('h:i A', $timestamp);
}

function student_to_api(array $row): array
{
    $department = $row['department'] ?? null;
    if ($department === null || $department === '') {
        $department = 'Not specified';
    }

    $year = isset($row['current_year']) ? (int) $row['current_year'] : 1;
    $semester = isset($row['current_semester']) ? (int) $row['current_semester'] : 1;

    return [
        'id'             => (int) ($row['id'] ?? 0),
        'rollNo'         => (string) ($row['roll_no'] ?? ''),
        'roll_no'        => (string) ($row['roll_no'] ?? ''),
        'name'           => (string) ($row['full_name'] ?? ''),
        'fullName'       => (string) ($row['full_name'] ?? ''),
        'email'          => (string) ($row['email'] ?? ''),
        'mobile'         => (string) ($row['mobile'] ?? ''),
        'gender'         => (string) ($row['gender'] ?? ''),
        'department'     => $department,
        'departmentId'   => isset($row['department_id']) ? (int) $row['department_id'] : null,
        'departmentCode' => $row['department_code'] ?? null,
        'courseId'       => isset($row['course_id']) ? (int) $row['course_id'] : null,
        'course'         => $row['course'] ?? null,
        'courseCode'     => $row['course_code'] ?? null,
        'degreeType'     => $row['degree_type'] ?? null,
        'year'           => $year,
        'semester'       => $semester,
        'status'         => (string) ($row['status'] ?? 'active'),
        'photo'          => $row['profile_photo'] ?? null,
        'dateOfBirth'    => $row['date_of_birth'] ?? null,
        'address'        => $row['address'] ?? null,
        'city'           => $row['city'] ?? null,
        'state'          => $row['state'] ?? null,
        'pincode'        => $row['pincode'] ?? null,
        'admissionDate'  => $row['admission_date'] ?? null,
        'lastLoginAt'    => $row['last_login_at'] ?? null,
        'joinedAt'       => $row['created_at'] ?? null,
        'joinedDate'     => format_date_value($row['created_at'] ?? null),
        'totalEvents'    => (int) ($row['total_events'] ?? 0),
    ];
}

function event_to_api(array $row): array
{
    $status = (string) ($row['status'] ?? 'published');
    $seatsLeft = $row['seats_left'] ?? null;
    $deadline = $row['registration_deadline'] ?? null;
    $eventDate = $row['event_date'] ?? null;

    $deadlinePassed = false;
    if ($deadline !== null && $deadline !== '') {
        $deadlinePassed = strtotime((string) $deadline) < time();
    }

    $eventPassed = false;
    if ($eventDate !== null && $eventDate !== '') {
        $eventPassed = strtotime((string) $eventDate . ' 23:59:59') < time();
    }

    $seatsAvailable = ($seatsLeft === null || (int) $seatsLeft > 0);

    return [
        'id'                    => (int) ($row['id'] ?? 0),
        'name'                  => (string) ($row['title'] ?? ''),
        'title'                 => (string) ($row['title'] ?? ''),
        'slug'                  => (string) ($row['slug'] ?? ''),
        'description'           => (string) ($row['description'] ?? ''),
        'category'              => (string) ($row['category'] ?? 'General'),
        'categoryId'            => isset($row['category_id']) ? (int) $row['category_id'] : null,
        'categoryColor'         => (string) ($row['category_color'] ?? '#0d47a1'),
        'date'                  => $eventDate,
        'displayDate'           => format_date_value($eventDate),
        'day'                   => $eventDate ? date('l', strtotime((string) $eventDate)) : null,
        'startTime'             => format_time_value($row['start_time'] ?? null),
        'endTime'               => format_time_value($row['end_time'] ?? null),
        'startTimeRaw'          => !empty($row['start_time']) ? substr((string) $row['start_time'], 0, 5) : null,
        'endTimeRaw'            => !empty($row['end_time']) ? substr((string) $row['end_time'], 0, 5) : null,
        'venue'                 => (string) ($row['venue'] ?? ''),
        'registrationDeadline'  => $deadline,
        'deadlineLabel'         => format_date_value($deadline, 'd M Y, h:i A'),
        'registrationDeadlineRaw' => !empty($deadline) ? date('Y-m-d\TH:i', strtotime((string) $deadline)) : null,
        'maxParticipants'       => isset($row['max_participants']) && $row['max_participants'] !== null
            ? (int) $row['max_participants']
            : null,
        'registeredCount'       => (int) ($row['registered_count'] ?? 0),
        'seatsLeft'             => $seatsLeft !== null ? (int) $seatsLeft : null,
        'registrationFee'       => (float) ($row['registration_fee'] ?? 0),
        'fee'                   => (float) ($row['registration_fee'] ?? 0),
        'organizer'             => $row['organizer_name'] ?? null,
        'contactEmail'          => $row['contact_email'] ?? null,
        'contactPhone'          => $row['contact_phone'] ?? null,
        'banner'                => $row['banner_image'] ?? null,
        'poster'                => $row['banner_image'] ?? null,
        'isFeatured'            => (int) ($row['is_featured'] ?? 0) === 1,
        'status'                => $status,
        'isPast'                => $eventPassed,
        'deadlinePassed'        => $deadlinePassed,
        'isFull'                => $seatsLeft !== null && (int) $seatsLeft <= 0,
        'canRegister'           => $status === 'published' && !$deadlinePassed && !$eventPassed && $seatsAvailable,
        'createdBy'             => isset($row['created_by']) ? (int) $row['created_by'] : null,
        'createdAt'             => $row['created_at'] ?? null,
        'updatedAt'             => $row['updated_at'] ?? null,
    ];
}

function notice_to_api(array $row): array
{
    $publishedAt = $row['published_at'] ?? null;

    return [
        'id'          => (int) ($row['id'] ?? 0),
        'title'       => (string) ($row['title'] ?? ''),
        'content'     => (string) ($row['content'] ?? ''),
        'category'    => (string) ($row['category'] ?? 'General'),
        'categoryColor' => (string) ($row['category_color'] ?? '#455a64'),
        'date'        => $publishedAt ? substr((string) $publishedAt, 0, 10) : null,
        'displayDate' => format_date_value($publishedAt),
        'publishedAt' => $publishedAt,
        'isPinned'    => (int) ($row['is_pinned'] ?? 0) === 1,
        'expiresAt'   => $row['expires_at'] ?? null,
        'isExpired'   => !empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time(),
    ];
}

function faq_to_api(array $row): array
{
    return [
        'id'            => (int) ($row['id'] ?? 0),
        'question'      => (string) ($row['question'] ?? ''),
        'answer'        => (string) ($row['answer'] ?? ''),
        'category'      => (string) ($row['category'] ?? 'General'),
        'displayOrder'  => (int) ($row['display_order'] ?? 0),
    ];
}

function registration_to_api(array $row): array
{
    return [
        'id'                => (int) ($row['id'] ?? 0),
        'registrationCode'  => (string) ($row['registration_code'] ?? ''),
        'status'            => (string) ($row['registration_status'] ?? 'confirmed'),
        'teamName'          => $row['team_name'] ?? null,
        'notes'             => $row['notes'] ?? null,
        'registeredAt'      => $row['registered_at'] ?? null,
        'registeredDate'    => format_date_value($row['registered_at'] ?? null, 'd M Y, h:i A'),
        'cancelledAt'       => $row['cancelled_at'] ?? null,
        'eventId'           => (int) ($row['event_id'] ?? 0),
        'eventTitle'        => (string) ($row['event_title'] ?? ''),
        'eventDate'         => $row['event_date'] ?? null,
        'eventDateLabel'    => format_date_value($row['event_date'] ?? null),
        'startTime'         => format_time_value($row['start_time'] ?? null),
        'venue'             => (string) ($row['venue'] ?? ''),
        'eventCategory'     => (string) ($row['event_category'] ?? ''),
        'studentId'         => (int) ($row['student_id'] ?? 0),
        'rollNo'            => (string) ($row['roll_no'] ?? ''),
        'studentName'       => (string) ($row['student_name'] ?? ''),
        'studentEmail'      => (string) ($row['student_email'] ?? ''),
        'studentMobile'     => (string) ($row['student_mobile'] ?? ''),
        'department'        => $row['department'] ?? null,
    ];
}
