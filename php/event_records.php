<?php
/**
 * StudentHub Portal - event management records
 * --------------------------------------------
 * Shared validation, CRUD and poster-upload helpers for the staff-only
 * event management screen. Every database call uses the MySQLi
 * prepared-statement helpers in db.php, so no value is concatenated into SQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/serializers.php';

class EventRecordNotFound extends RuntimeException
{
}

class EventRecordConflict extends RuntimeException
{
}

class EventPosterException extends RuntimeException
{
}

function event_statuses(): array
{
    return ['draft', 'published', 'cancelled', 'completed'];
}

function event_poster_max_bytes(): int
{
    return 2 * 1024 * 1024;
}

function event_poster_max_label(): string
{
    return '2 MB';
}

/**
 * MIME type => canonical stored extension.
 *
 * @return array<string,string>
 */
function event_poster_allowed_types(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
}

/**
 * @return array<int,string>
 */
function event_poster_allowed_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp'];
}

function event_poster_directory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'events';
}

function event_poster_public_prefix(): string
{
    return 'uploads/events/';
}

function event_lookup_data(): array
{
    $categories = db_query(
        'SELECT id, name, color, icon FROM event_categories WHERE is_active = 1 ORDER BY name ASC'
    );

    return [
        'categories' => array_map(static function (array $row): array {
            return [
                'id'    => (int) $row['id'],
                'name'  => (string) $row['name'],
                'color' => (string) $row['color'],
                'icon'  => $row['icon'],
            ];
        }, $categories),
        'statuses' => event_statuses(),
        'poster'   => [
            'maxBytes'          => event_poster_max_bytes(),
            'maxLabel'          => event_poster_max_label(),
            'allowedExtensions' => event_poster_allowed_extensions(),
            'accept'            => 'image/jpeg,image/png,image/webp',
        ],
    ];
}

function event_optional_text($value): ?string
{
    $text = trim((string) ($value ?? ''));
    return $text === '' ? null : $text;
}

function event_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function event_normalize_time(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
        return $value . ':00';
    }

    return $value;
}

function event_normalize_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
        return $value . ':00';
    }

    return $value;
}

function event_optional_int($value): ?int
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return null;
    }

    return preg_match('/^\d+$/', $text) === 1 ? (int) $text : -1;
}

function event_date_is_valid(string $value): bool
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
        return false;
    }

    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
}

function event_time_is_valid(string $value): bool
{
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value) === 1;
}

function event_datetime_is_valid(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    return $date !== false && $date->format('Y-m-d H:i:s') === $value;
}

/**
 * Normalise a raw request body into the event field map.
 *
 * @param array<string,mixed> $source
 * @return array<string,mixed>
 */
function event_record_input(array $source): array
{
    $fee = trim((string) ($source['registration_fee'] ?? ''));

    return [
        'title'                 => input_string($source, 'title'),
        'description'           => event_optional_text($source['description'] ?? ''),
        'category_id'           => input_int($source, 'category_id'),
        'venue'                 => input_string($source, 'venue'),
        'event_date'            => input_string($source, 'event_date'),
        'start_time'            => event_normalize_time(input_string($source, 'start_time')),
        'end_time'              => event_normalize_time(input_string($source, 'end_time')),
        'registration_deadline' => event_normalize_datetime(input_string($source, 'registration_deadline')),
        'max_participants'      => event_optional_int($source['max_participants'] ?? ''),
        'registration_fee'      => $fee === '' ? '0.00' : $fee,
        'organizer_name'        => event_optional_text($source['organizer_name'] ?? ''),
        'contact_email'         => event_optional_text($source['contact_email'] ?? ''),
        'contact_phone'         => event_optional_text($source['contact_phone'] ?? ''),
        'is_featured'           => input_bool($source, 'is_featured'),
        'status'                => input_string($source, 'status', 'published'),
        'remove_poster'         => input_bool($source, 'remove_poster'),
    ];
}

/**
 * Validate the normalised event fields.
 *
 * @param array<string,mixed> $data
 * @return array<string,string> field => message
 */
function event_record_validate(array $data): array
{
    $errors = [];

    if ($data['title'] === '') {
        $errors['title'] = 'Event title is required.';
    } elseif (event_text_length($data['title']) < 3 || event_text_length($data['title']) > 150) {
        $errors['title'] = 'Event title must be between 3 and 150 characters.';
    }

    if ($data['description'] !== null && event_text_length($data['description']) > 5000) {
        $errors['description'] = 'Description may not exceed 5000 characters.';
    }

    if ($data['category_id'] <= 0) {
        $errors['category_id'] = 'Please select an event category.';
    } elseif (db_value('SELECT id FROM event_categories WHERE id = ? LIMIT 1', 'i', [$data['category_id']]) === null) {
        $errors['category_id'] = 'The selected event category no longer exists.';
    }

    if ($data['venue'] === '') {
        $errors['venue'] = 'Venue is required.';
    } elseif (event_text_length($data['venue']) > 150) {
        $errors['venue'] = 'Venue may not exceed 150 characters.';
    }

    if ($data['event_date'] === '') {
        $errors['event_date'] = 'Event date is required.';
    } elseif (!event_date_is_valid($data['event_date'])) {
        $errors['event_date'] = 'Event date must be a valid date.';
    }

    if ($data['start_time'] !== null && !event_time_is_valid($data['start_time'])) {
        $errors['start_time'] = 'Start time must be a valid time (HH:MM).';
    }

    if ($data['end_time'] !== null && !event_time_is_valid($data['end_time'])) {
        $errors['end_time'] = 'End time must be a valid time (HH:MM).';
    }

    if (!isset($errors['start_time']) && !isset($errors['end_time'])
        && $data['start_time'] !== null && $data['end_time'] !== null
        && $data['end_time'] <= $data['start_time']
    ) {
        $errors['end_time'] = 'End time must be later than the start time.';
    }

    if ($data['registration_deadline'] !== null) {
        if (!event_datetime_is_valid($data['registration_deadline'])) {
            $errors['registration_deadline'] = 'Registration deadline must be a valid date and time.';
        } elseif ($data['event_date'] !== '' && event_date_is_valid($data['event_date'])
            && substr($data['registration_deadline'], 0, 10) > $data['event_date']
        ) {
            $errors['registration_deadline'] = 'Registration deadline cannot be after the event date.';
        }
    }

    if ($data['max_participants'] !== null
        && ($data['max_participants'] < 1 || $data['max_participants'] > 100000)
    ) {
        $errors['max_participants'] = 'Maximum participants must be a whole number between 1 and 100000.';
    }

    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $data['registration_fee'])) {
        $errors['registration_fee'] = 'Registration fee must be a valid amount with up to 2 decimal places.';
    } elseif ((float) $data['registration_fee'] > 99999999.99) {
        $errors['registration_fee'] = 'Registration fee is too large.';
    }

    if ($data['organizer_name'] !== null && event_text_length($data['organizer_name']) > 100) {
        $errors['organizer_name'] = 'Organizer name may not exceed 100 characters.';
    }

    if ($data['contact_email'] !== null
        && (strlen($data['contact_email']) > 150 || filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL) === false)
    ) {
        $errors['contact_email'] = 'Please enter a valid contact email address.';
    }

    if ($data['contact_phone'] !== null && preg_match('/^[6-9][0-9]{9}$/', $data['contact_phone']) !== 1) {
        $errors['contact_phone'] = 'Contact phone must be 10 digits starting with 6-9.';
    }

    if (!in_array($data['status'], event_statuses(), true)) {
        $errors['status'] = 'Please select a valid event status.';
    }

    return $errors;
}

function event_slugify(string $title): string
{
    $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
    if ($slug === '') {
        $slug = 'event';
    }

    return substr($slug, 0, 160);
}

function event_unique_slug(string $title, int $ignoreId = 0): string
{
    $base = event_slugify($title);
    $candidate = $base;
    $suffix = 2;

    while (db_value(
        'SELECT id FROM events WHERE slug = ? AND id <> ? LIMIT 1',
        'si',
        [$candidate, $ignoreId]
    ) !== null) {
        $ending = '-' . $suffix;
        $candidate = substr($base, 0, 180 - strlen($ending)) . $ending;
        $suffix++;
    }

    return $candidate;
}

function event_record_fetch(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $row = db_one('SELECT * FROM vw_event_details WHERE id = ? LIMIT 1', 'i', [$id]);
    return $row === null ? null : event_to_api($row);
}

/**
 * Store one uploaded poster after checking its error code, size, extension,
 * MIME type and real image dimensions. Returns null when no file was sent.
 *
 * @param array<string,mixed>|null $file One entry from $_FILES.
 * @return array<string,mixed>|null
 */
function event_poster_store(?array $file): ?array
{
    if ($file === null || !array_key_exists('error', $file)) {
        return null;
    }

    if (is_array($file['error'])) {
        throw new EventPosterException('Please upload a single poster image.');
    }

    $error = (int) $file['error'];
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new EventPosterException(event_poster_upload_error($error));
    }

    $temporary = (string) ($file['tmp_name'] ?? '');
    $originalName = (string) ($file['name'] ?? 'poster');
    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0) {
        throw new EventPosterException('The selected poster file is empty.');
    }

    if ($size > event_poster_max_bytes()) {
        throw new EventPosterException(
            'Poster is too large. Maximum file size is ' . event_poster_max_label() . '.'
        );
    }

    if ($temporary === '' || !is_uploaded_file($temporary)) {
        throw new EventPosterException('The poster upload could not be verified. Please try again.');
    }

    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, event_poster_allowed_extensions(), true)) {
        throw new EventPosterException('Poster must be a JPG, PNG or WebP image.');
    }

    $detectedType = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $allowedTypes = event_poster_allowed_types();

    if ($detectedType === false || !isset($allowedTypes[$detectedType])) {
        throw new EventPosterException('Poster must be a JPG, PNG or WebP image.');
    }

    $matchingExtensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    if (!in_array($extension, $matchingExtensions[$detectedType], true)) {
        throw new EventPosterException('The poster file type does not match its file extension.');
    }

    $imageInfo = @getimagesize($temporary);
    $expectedImageType = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png'  => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    if ($imageInfo === false || (int) $imageInfo[2] !== $expectedImageType[$detectedType]) {
        throw new EventPosterException('The uploaded file is not a valid image.');
    }

    $directory = event_poster_directory();
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new EventPosterException('The poster upload folder could not be created.');
    }

    $filename = 'event-' . date('Ymd-His') . '-' . bin2hex(random_bytes(6))
        . '.' . $allowedTypes[$detectedType];
    $target = $directory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($temporary, $target)) {
        throw new EventPosterException('The poster could not be saved. Please try again.');
    }

    return [
        'path'         => event_poster_public_prefix() . $filename,
        'originalName' => basename($originalName),
        'size'         => $size,
        'mime'         => $detectedType,
        'width'        => (int) $imageInfo[0],
        'height'       => (int) $imageInfo[1],
    ];
}

function event_poster_upload_error(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Poster is too large. Maximum file size is ' . event_poster_max_label() . '.';
        case UPLOAD_ERR_PARTIAL:
            return 'The poster upload was interrupted. Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'The server could not store the poster. Please try again later.';
        default:
            return 'The poster could not be uploaded.';
    }
}

/**
 * Delete a poster file only when it lives inside uploads/events.
 */
function event_poster_delete_file(?string $path): void
{
    if ($path === null || trim($path) === '') {
        return;
    }

    $normalized = str_replace('\\', '/', $path);
    if (str_starts_with($normalized, event_poster_public_prefix()) === false) {
        return;
    }

    $base = realpath(event_poster_directory());
    if ($base === false) {
        return;
    }

    $target = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));
    if ($target === false || !is_file($target)) {
        return;
    }

    $basePrefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with($target, $basePrefix)) {
        @unlink($target);
    }
}

/**
 * @param array<string,mixed> $data Validated event fields.
 * @param array<string,mixed>|null $poster Stored poster metadata.
 * @param array<string,mixed> $actor Signed-in staff member.
 */
function event_record_create(array $data, ?array $poster, array $actor): array
{
    $slug = event_unique_slug($data['title']);
    $posterPath = $poster['path'] ?? null;

    try {
        $result = db_execute(
            'INSERT INTO events
                (title, slug, description, category_id, venue, event_date, start_time, end_time,
                 registration_deadline, max_participants, registration_fee, organizer_name,
                 contact_email, contact_phone, banner_image, is_featured, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'sssisssssisssssisi',
            [
                $data['title'],
                $slug,
                $data['description'],
                $data['category_id'],
                $data['venue'],
                $data['event_date'],
                $data['start_time'],
                $data['end_time'],
                $data['registration_deadline'],
                $data['max_participants'],
                $data['registration_fee'],
                $data['organizer_name'],
                $data['contact_email'],
                $data['contact_phone'],
                $posterPath,
                $data['is_featured'] ? 1 : 0,
                $data['status'],
                (int) ($actor['id'] ?? 0),
            ]
        );
    } catch (Throwable $error) {
        if ($posterPath !== null) {
            event_poster_delete_file($posterPath);
        }
        throw $error;
    }

    $id = (int) $result['insert_id'];

    log_activity(
        'event_created',
        'event',
        $id,
        'Event created: ' . $data['title'] . ' (' . $slug . ').',
        (int) ($actor['id'] ?? 0)
    );

    $event = event_record_fetch($id);
    if ($event === null) {
        throw new RuntimeException('The event was saved but could not be loaded again.');
    }

    return $event;
}

/**
 * @param array<string,mixed> $data Validated event fields.
 * @param array<string,mixed>|null $poster New poster metadata, if any.
 * @param array<string,mixed> $actor Signed-in staff member.
 */
function event_record_update(int $id, array $data, ?array $poster, array $actor): array
{
    $existing = db_one(
        'SELECT id, title, banner_image FROM events WHERE id = ? LIMIT 1',
        'i',
        [$id]
    );

    if ($existing === null) {
        if ($poster !== null) {
            event_poster_delete_file((string) $poster['path']);
        }
        throw new EventRecordNotFound('Event not found.');
    }

    $newPoster = $existing['banner_image'] ?? null;
    $oldPoster = null;

    if ($poster !== null) {
        $newPoster = (string) $poster['path'];
        $oldPoster = $existing['banner_image'] ?? null;
    } elseif ($data['remove_poster']) {
        $newPoster = null;
        $oldPoster = $existing['banner_image'] ?? null;
    }

    $slug = event_unique_slug($data['title'], $id);

    try {
        db_execute(
            'UPDATE events SET
                title = ?, slug = ?, description = ?, category_id = ?, venue = ?, event_date = ?,
                start_time = ?, end_time = ?, registration_deadline = ?, max_participants = ?,
                registration_fee = ?, organizer_name = ?, contact_email = ?, contact_phone = ?,
                banner_image = ?, is_featured = ?, status = ?
              WHERE id = ?',
            'sssisssssisssssisi',
            [
                $data['title'],
                $slug,
                $data['description'],
                $data['category_id'],
                $data['venue'],
                $data['event_date'],
                $data['start_time'],
                $data['end_time'],
                $data['registration_deadline'],
                $data['max_participants'],
                $data['registration_fee'],
                $data['organizer_name'],
                $data['contact_email'],
                $data['contact_phone'],
                $newPoster,
                $data['is_featured'] ? 1 : 0,
                $data['status'],
                $id,
            ]
        );
    } catch (Throwable $error) {
        if ($poster !== null) {
            event_poster_delete_file((string) $poster['path']);
        }
        throw $error;
    }

    if ($oldPoster !== null && $oldPoster !== $newPoster) {
        event_poster_delete_file((string) $oldPoster);
    }

    $posterNote = '';
    if ($poster !== null) {
        $posterNote = ' Poster was replaced.';
    } elseif ($data['remove_poster']) {
        $posterNote = ' Poster was removed.';
    }

    log_activity(
        'event_updated',
        'event',
        $id,
        'Event updated: ' . $data['title'] . ' (' . $slug . ').' . $posterNote,
        (int) ($actor['id'] ?? 0)
    );

    $event = event_record_fetch($id);
    if ($event === null) {
        throw new RuntimeException('The event was updated but could not be loaded again.');
    }

    return $event;
}

/**
 * Permanently delete an event, its poster and all cascaded registrations.
 *
 * @param array<string,mixed> $actor Signed-in staff member.
 */
function event_record_delete(int $id, array $actor): array
{
    $event = event_record_fetch($id);
    if ($event === null) {
        throw new EventRecordNotFound('Event not found.');
    }

    $result = db_execute('DELETE FROM events WHERE id = ?', 'i', [$id]);
    if ((int) $result['affected_rows'] !== 1) {
        throw new EventRecordNotFound('Event not found.');
    }

    event_poster_delete_file($event['banner'] ?? null);

    log_activity(
        'event_deleted',
        'event',
        $id,
        'Event deleted: ' . $event['title'] . ' (' . $event['slug'] . ').',
        (int) ($actor['id'] ?? 0)
    );

    return $event;
}
