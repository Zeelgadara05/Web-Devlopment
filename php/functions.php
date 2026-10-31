<?php
/* Escape a value for safe HTML output */
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10}$/', $phone);
}

function validateName($name) {
    return preg_match('/^[a-zA-Z\s]{2,50}$/', $name);
}

/* Read a JSON file and always return an array */
function readJsonFile($path) {
    if (!file_exists($path)) {
        return array();
    }

    $content = file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return array();
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : array();
}

/* Write an array to a JSON file (pretty printed, locked while writing) */
function writeJsonFile($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

/* Next numeric id for a list of records */
function nextNumericId($items) {
    $max = 0;
    foreach ($items as $item) {
        if (isset($item['id']) && is_numeric($item['id'])) {
            $max = max($max, (int) $item['id']);
        }
    }
    return $max + 1;
}

/* Check whether a roll number is already used by another student */
function rollNumberExists($students, $rollNo) {
    foreach ($students as $student) {
        if (isset($student['rollNo']) && strtoupper($student['rollNo']) === strtoupper($rollNo)) {
            return true;
        }
    }
    return false;
}
?>
