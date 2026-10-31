<?php
/**
 * StudentHub Portal - MySQL / MariaDB connection + query helpers
 * ---------------------------------------------------------------
 * All database access in the portal goes through this file.
 * It uses PHP's mysqli extension with prepared statements.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Return a single shared mysqli connection (lazy singleton).
 */
function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    $connection = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($connection->connect_errno) {
        throw new RuntimeException(
            'Database connection failed (' . $connection->connect_errno . '): ' . $connection->connect_error
        );
    }

    if (!$connection->set_charset(DB_CHARSET)) {
        throw new RuntimeException('Could not set database charset to ' . DB_CHARSET);
    }

    /* Keep MySQL's NOW() in the same timezone as PHP. */
    $connection->query("SET time_zone = '+05:30'");

    return $connection;
}

/**
 * Prepare a statement and optionally bind parameters.
 *
 * @param string $sql    SQL with ? placeholders
 * @param string $types  mysqli type string, e.g. "ssi"
 * @param array  $params Values in the same order as the placeholders
 */
function db_prepare(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $connection = db();
    $stmt = $connection->prepare($sql);

    if ($stmt === false) {
        throw new RuntimeException('SQL prepare failed: ' . $connection->error);
    }

    if ($types !== '') {
        if (strlen($types) !== count($params)) {
            $stmt->close();
            throw new InvalidArgumentException('Bind types do not match the number of parameters.');
        }

        $bindArguments = [$types];
        $values = array_values($params);
        foreach ($values as $index => $value) {
            $bindArguments[] = &$values[$index];
        }

        call_user_func_array([$stmt, 'bind_param'], $bindArguments);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('SQL execute failed: ' . $error);
    }

    return $stmt;
}

/**
 * Run a SELECT and return all rows as an associative array.
 */
function db_query(string $sql, string $types = '', array $params = []): array
{
    $stmt = db_prepare($sql, $types, $params);

    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
        $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } else {
        $rows = [];
        $meta = $stmt->result_metadata();
        if ($meta !== false) {
            $fields = [];
            $row = [];
            while ($field = $meta->fetch_field()) {
                $fields[] = $field->name;
                $row[$field->name] = null;
            }
            $bind = [];
            foreach ($fields as $name) {
                $bind[] = &$row[$name];
            }
            call_user_func_array([$stmt, 'bind_result'], $bind);
            while ($stmt->fetch()) {
                $copy = [];
                foreach ($row as $key => $value) {
                    $copy[$key] = $value;
                }
                $rows[] = $copy;
            }
            $meta->free();
        }
    }

    $stmt->close();
    return $rows;
}

/**
 * Run a SELECT and return the first row or null.
 */
function db_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_query($sql, $types, $params);
    return $rows[0] ?? null;
}

/**
 * Run a SELECT and return the first column of the first row.
 */
function db_value(string $sql, string $types = '', array $params = [])
{
    $row = db_one($sql, $types, $params);
    if ($row === null) {
        return null;
    }
    return reset($row);
}

/**
 * Run INSERT / UPDATE / DELETE.
 *
 * @return array{affected_rows:int, insert_id:int}
 */
function db_execute(string $sql, string $types = '', array $params = []): array
{
    $stmt = db_prepare($sql, $types, $params);
    $info = [
        'affected_rows' => $stmt->affected_rows,
        'insert_id'     => $stmt->insert_id,
    ];
    $stmt->close();
    return $info;
}

/**
 * Run a callback inside a transaction. Rolls back on any exception.
 */
function db_transaction(callable $callback)
{
    $connection = db();
    $connection->begin_transaction();

    try {
        $result = $callback($connection);
        $connection->commit();
        return $result;
    } catch (Throwable $error) {
        $connection->rollback();
        throw $error;
    }
}

/**
 * True when a duplicate-key error (1062) was raised.
 */
function db_is_duplicate(Throwable $error): bool
{
    return strpos($error->getMessage(), '1062') !== false
        || stripos($error->getMessage(), 'Duplicate entry') !== false;
}

/**
 * True when the caller tried to insert a duplicate value.
 */
function db_duplicate_message(Throwable $error): string
{
    $message = $error->getMessage();

    if (stripos($message, 'uq_students_email') !== false || stripos($message, 'students.email') !== false) {
        return 'This email address is already registered.';
    }
    if (stripos($message, 'uq_students_mobile') !== false || stripos($message, 'students.mobile') !== false) {
        return 'This mobile number is already registered.';
    }
    if (stripos($message, 'uq_students_roll_no') !== false || stripos($message, 'students.roll_no') !== false) {
        return 'This roll number is already registered.';
    }
    if (stripos($message, 'uq_event_registrations_event_student') !== false) {
        return 'You are already registered for this event.';
    }

    return 'A record with the same unique value already exists.';
}
