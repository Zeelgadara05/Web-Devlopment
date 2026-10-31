<?php
/**
 * StudentHub Portal - secure student registration handler
 * ------------------------------------------------------
 * 1. Validates every field on the server (the same rules run in JS/Registration.js).
 * 2. Rejects duplicate email addresses and usernames (roll numbers).
 * 3. Hashes the password with password_hash() before storing it.
 * 4. Inserts the student with MySQLi prepared statements.
 * 5. Shows a clear success or error message, re-filling the form on error.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lookups.php';

$message = '';
$messageType = '';
$successLoginUrl = '../login.html';

$allowedDegrees = ['B.Tech', 'M.Tech', 'BCA', 'MCA'];
$allowedYears = ['1', '2', '3', '4'];
$allowedGenders = ['Male', 'Female', 'Other'];

/* Keep entered values so the form can be re-filled after an error. */
$fullName = '';
$rollNo = '';
$email = '';
$mobile = '';
$course = '';
$department = '';
$year = '';
$gender = '';
$terms = false;

$errors = [];

if (request_method() === 'POST') {
    $fullName = input_string($_POST, 'fullname');
    $rollNo = strtoupper(input_string($_POST, 'rollNo'));
    $email = strtolower(input_string($_POST, 'email'));
    $mobile = input_string($_POST, 'mobile');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirmpassword'] ?? '');
    $course = input_string($_POST, 'course');
    $department = input_string($_POST, 'department');
    $year = input_string($_POST, 'year');
    $gender = input_string($_POST, 'gender');
    $terms = isset($_POST['terms']);

    /* --------------------------------------------------------------
     * 1. Server-side validation
     * -------------------------------------------------------------- */
    if ($fullName === '') {
        $errors['fullname'] = 'Full name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s]{2,50}$/', $fullName)) {
        $errors['fullname'] = 'Name must contain only letters and spaces (2-50 characters).';
    }

    if ($rollNo !== '' && !preg_match('/^[A-Z0-9]{4,15}$/', $rollNo)) {
        $errors['rollNo'] = 'Username must be 4-15 letters or digits (example: CS21009).';
    }

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (validateEmail($email) === false) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($mobile === '') {
        $errors['mobile'] = 'Mobile number is required.';
    } elseif (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        $errors['mobile'] = 'Mobile number must be 10 digits starting with 6-9.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $password)) {
        $errors['password'] = 'Password must be at least 8 characters and include a letter, a number and a special character.';
    }

    if ($confirmPassword === '') {
        $errors['confirmpassword'] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirmpassword'] = 'Passwords do not match.';
    }

    if ($course === '') {
        $errors['course'] = 'Please select a course.';
    } elseif (!in_array($course, $allowedDegrees, true)) {
        $errors['course'] = 'Please select a valid course.';
    }

    if ($department !== '' && find_department($department) === null) {
        $errors['department'] = 'Please select a valid department.';
    }

    if ($year === '') {
        $errors['year'] = 'Please select a year.';
    } elseif (!in_array($year, $allowedYears, true)) {
        $errors['year'] = 'Please select a valid year.';
    }

    if ($gender === '') {
        $errors['gender'] = 'Please select a gender.';
    } elseif (!in_array($gender, $allowedGenders, true)) {
        $errors['gender'] = 'Please select a valid gender.';
    }

    if (!$terms) {
        $errors['terms'] = 'You must accept the Terms and Conditions.';
    }

    if (setting('registration_open', '1') === '0') {
        $errors['form'] = 'New student registrations are currently closed.';
    }

    /* --------------------------------------------------------------
     * 2. Duplicate email / username / mobile check (MySQLi)
     * -------------------------------------------------------------- */
    if ($errors === []) {
        try {
            $emailOwner = db_value('SELECT id FROM students WHERE email = ? LIMIT 1', 's', [$email]);
            if ($emailOwner !== null) {
                $errors['email'] = 'This email address is already registered. Please log in or use another email.';
            }

            if ($rollNo !== '') {
                $usernameOwner = db_value('SELECT id FROM students WHERE roll_no = ? LIMIT 1', 's', [$rollNo]);
                if ($usernameOwner !== null) {
                    $errors['rollNo'] = 'This username / roll number is already taken. Please choose another.';
                }
            }

            $mobileOwner = db_value('SELECT id FROM students WHERE mobile = ? LIMIT 1', 's', [$mobile]);
            if ($mobileOwner !== null) {
                $errors['mobile'] = 'This mobile number is already registered.';
            }
        } catch (Throwable $error) {
            $errors['form'] = APP_DEBUG
                ? 'Database error: ' . $error->getMessage()
                : 'We could not check your details right now. Please try again.';
        }
    }

    /* --------------------------------------------------------------
     * 3. Insert the new student
     * -------------------------------------------------------------- */
    if ($errors === []) {
        try {
            $result = db_transaction(static function () use (
                $fullName,
                $rollNo,
                $email,
                $mobile,
                $password,
                $course,
                $department,
                $year,
                $gender
            ): array {
                $departmentRow = $department !== '' ? find_department($department) : null;
                $courseRow = resolve_course_for_registration($course, $departmentRow);

                if ($courseRow === null) {
                    throw new DomainException('We could not match that course to the available programmes.');
                }

                /* The chosen course defines the department, so keep both consistent. */
                if ($courseRow['department_id'] !== null) {
                    $departmentRow = db_one(
                        'SELECT * FROM departments WHERE id = ? LIMIT 1',
                        'i',
                        [(int) $courseRow['department_id']]
                    );
                }

                $yearInt = max(1, min(6, (int) $year));
                $semester = semester_for_year($yearInt);

                /* Password is never stored in plain text. */
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                /* Insert with a temporary unique roll number, then replace it
                   with the generated one after MySQL gives us the new id. */
                $temporaryRoll = 'TMP' . strtoupper(bin2hex(random_bytes(5)));

                $insert = db_execute(
                    "INSERT INTO students
                        (roll_no, full_name, email, mobile, password_hash, department_id, course_id,
                         current_year, current_semester, gender, admission_date, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active')",
                    'sssssiiiis',
                    [
                        $temporaryRoll,
                        $fullName,
                        $email,
                        $mobile,
                        $passwordHash,
                        $departmentRow !== null ? (int) $departmentRow['id'] : null,
                        (int) $courseRow['id'],
                        $yearInt,
                        $semester,
                        $gender,
                    ]
                );

                $newId = (int) $insert['insert_id'];
                $finalRoll = $rollNo !== '' ? $rollNo : generate_roll_number($newId);

                db_execute(
                    'UPDATE students SET roll_no = ? WHERE id = ?',
                    'si',
                    [$finalRoll, $newId]
                );

                return [
                    'id'      => $newId,
                    'roll_no' => $finalRoll,
                ];
            });

            /* Audit trail (best effort, never blocks registration). */
            log_activity(
                'student_registered',
                'student',
                (int) $result['id'],
                'New student account created: ' . $result['roll_no'],
                null,
                (int) $result['id']
            );

            $loginUrl = '../login.html?' . http_build_query([
                'message'      => 'Registration complete. Please sign in with your new student account.',
                'email'        => $email,
                'login_type'   => 'student',
            ]);

            $message = 'Registration successful! Your account has been created with the roll number '
                . '<strong>' . e($result['roll_no']) . '</strong>. '
                . 'You can now <a href="' . e($loginUrl) . '">sign in to the portal</a>.';
            $messageType = 'success';
            $successLoginUrl = $loginUrl;
            $rollNo = (string) $result['roll_no'];
            $fullName = '';
            $email = '';
            $mobile = '';
            $course = '';
            $department = '';
            $year = '';
            $gender = '';
            $terms = false;
        } catch (DomainException $error) {
            $message = e($error->getMessage());
            $messageType = 'error';
        } catch (Throwable $error) {
            if (db_is_duplicate($error)) {
                $duplicate = db_duplicate_message($error);

                /* Attach the duplicate error to the matching field. */
                if (stripos($duplicate, 'email') !== false) {
                    $errors['email'] = $duplicate;
                } elseif (stripos($duplicate, 'mobile') !== false) {
                    $errors['mobile'] = $duplicate;
                } elseif (stripos($duplicate, 'roll') !== false) {
                    $errors['rollNo'] = $duplicate;
                }

                $message = e($duplicate);
            } else {
                $message = APP_DEBUG
                    ? 'Database error: ' . e($error->getMessage())
                    : 'Registration could not be saved. Please try again.';
            }
            $messageType = 'error';
        }
    }

    if ($errors !== [] && $message === '') {
        if (isset($errors['form'])) {
            $message = e($errors['form']);
            unset($errors['form']);
        } else {
            $message = 'Please fix the highlighted fields and try again.';
        }
        $messageType = 'error';
    }
}

/**
 * Render the inline message for one field.
 */
function field_error(array $errors, string $name): string
{
    if (!isset($errors[$name])) {
        return '';
    }

    return '<span class="error-message" id="' . e($name) . 'Error" role="alert">'
        . e($errors[$name]) . '</span>';
}

/**
 * Add class="is-invalid" when a field has an error.
 */
function field_class(array $errors, string $name, string $base = ''): string
{
    $classes = trim($base);
    if (isset($errors[$name])) {
        $classes .= ($classes !== '' ? ' ' : '') . 'is-invalid';
    }

    return $classes !== '' ? ' class="' . e($classes) . '"' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - StudentHub Portal</title>
    <link rel="stylesheet" href="../CSS/registration.css">
    <?php if ($messageType === 'success'): ?>
    <meta http-equiv="refresh" content="8;url=<?php echo e($successLoginUrl); ?>">
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Student Registration</h1>
        </header>

        <div class="form-container">
            <div class="form-header">
                <h2><?php echo $messageType === 'success' ? 'Registration Complete' : 'Create Your Account'; ?></h2>
            </div>
            <div class="form-body">
                <?php if ($message !== ''): ?>
                    <div class="message <?php echo e($messageType); ?>" role="alert" aria-live="polite">
                        <?php echo $message; ?>
                        <?php if ($messageType === 'success'): ?>
                            <br><small>Taking you to the login page in 8 seconds&hellip;</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($messageType === 'success'): ?>
                    <p style="text-align:center;">
                        <a class="btn-link" href="<?php echo e($successLoginUrl); ?>">Go to Login</a>
                    </p>
                <?php else: ?>
                    <form method="POST" action="register_process.php" novalidate>
                        <div class="form-group">
                            <label for="fullname">Full Name *</label>
                            <input type="text" id="fullname" name="fullname" maxlength="50"
                                   value="<?php echo e($fullName); ?>"<?php echo field_class($errors, 'fullname'); ?>
                                   autocomplete="name" required>
                            <?php echo field_error($errors, 'fullname'); ?>
                        </div>

                        <div class="form-group">
                            <label for="rollNo">Username / Roll Number <small>(optional &mdash; auto-generated if blank)</small></label>
                            <input type="text" id="rollNo" name="rollNo" maxlength="15"
                                   value="<?php echo e($rollNo); ?>"<?php echo field_class($errors, 'rollNo'); ?>
                                   autocomplete="username" placeholder="e.g. CS21009">
                            <span class="field-hint">This is your login username. Leave blank to generate one automatically.</span>
                            <?php echo field_error($errors, 'rollNo'); ?>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" maxlength="150"
                                   value="<?php echo e($email); ?>"<?php echo field_class($errors, 'email'); ?>
                                   autocomplete="email" required>
                            <?php echo field_error($errors, 'email'); ?>
                        </div>

                        <div class="form-group">
                            <label for="mobile">Mobile Number *</label>
                            <input type="tel" id="mobile" name="mobile" maxlength="10" inputmode="numeric"
                                   value="<?php echo e($mobile); ?>"<?php echo field_class($errors, 'mobile'); ?>
                                   autocomplete="tel" required>
                            <?php echo field_error($errors, 'mobile'); ?>
                        </div>

                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" minlength="8"
                                   <?php echo field_class($errors, 'password'); ?>
                                   autocomplete="new-password" required>
                            <span class="field-hint">At least 8 characters with a letter, a number and a special character (@$!%*#?&amp;).</span>
                            <?php echo field_error($errors, 'password'); ?>
                        </div>

                        <div class="form-group">
                            <label for="confirmpassword">Confirm Password *</label>
                            <input type="password" id="confirmpassword" name="confirmpassword" minlength="8"
                                   <?php echo field_class($errors, 'confirmpassword'); ?>
                                   autocomplete="new-password" required>
                            <?php echo field_error($errors, 'confirmpassword'); ?>
                        </div>

                        <div class="form-group">
                            <label for="course">Course *</label>
                            <select id="course" name="course"<?php echo field_class($errors, 'course'); ?> required>
                                <option value="">Select Course</option>
                                <?php foreach ($allowedDegrees as $degree): ?>
                                    <option value="<?php echo e($degree); ?>" <?php echo $course === $degree ? 'selected' : ''; ?>><?php echo e($degree); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo field_error($errors, 'course'); ?>
                        </div>

                        <div class="form-group">
                            <label for="department">Department <small>(optional)</small></label>
                            <select id="department" name="department"<?php echo field_class($errors, 'department'); ?>>
                                <option value="">Select Department</option>
                                <?php foreach (['Computer Science', 'Electronics', 'Mechanical', 'Civil', 'Information Technology'] as $dept): ?>
                                    <option value="<?php echo e($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>><?php echo e($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo field_error($errors, 'department'); ?>
                        </div>

                        <div class="form-group">
                            <label for="year">Year *</label>
                            <select id="year" name="year"<?php echo field_class($errors, 'year'); ?> required>
                                <option value="">Select Year</option>
                                <?php foreach (['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'] as $value => $label): ?>
                                    <option value="<?php echo e($value); ?>" <?php echo $year === (string) $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo field_error($errors, 'year'); ?>
                        </div>

                        <div class="form-group">
                            <label>Gender *</label>
                            <div class="radio-group">
                                <?php foreach ($allowedGenders as $option): ?>
                                    <label class="radio-option">
                                        <input type="radio" name="gender" value="<?php echo e($option); ?>"
                                            <?php echo $gender === $option ? 'checked' : ''; ?> required>
                                        <?php echo e($option); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php echo field_error($errors, 'gender'); ?>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="terms" name="terms" <?php echo $terms ? 'checked' : ''; ?> required>
                                <label for="terms">I accept the Terms and Conditions *</label>
                            </div>
                            <?php echo field_error($errors, 'terms'); ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="registerButton">Register</button>
                        </div>
                    </form>

                    <p style="margin-top: 20px; text-align: center;">
                        <a href="../login.html">Already have an account? Login</a> |
                        <a href="../home.html">Back to Home</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
