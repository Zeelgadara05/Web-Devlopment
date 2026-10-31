<?php
/**
 * StudentHub Portal - contact form handler
 * ----------------------------------------
 * Validates the contact form and stores the message in MySQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$message = '';
$messageType = '';

$name = '';
$email = '';
$phone = '';
$subject = '';
$msg = '';

if (request_method() === 'POST') {
    $name = input_string($_POST, 'name');
    $email = input_string($_POST, 'email');
    $phone = input_string($_POST, 'phone');
    $subject = input_string($_POST, 'subject');
    $msg = input_string($_POST, 'message');

    $errors = [];

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (!validateName($name)) {
        $errors[] = 'Name must contain only letters and spaces (2-50 characters).';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Invalid email format.';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!validatePhone($phone)) {
        $errors[] = 'Phone number must be 10 digits.';
    }

    if ($subject === '') {
        $errors[] = 'Subject is required.';
    } elseif (strlen($subject) > 200) {
        $errors[] = 'Subject is too long (maximum 200 characters).';
    }

    if ($msg === '') {
        $errors[] = 'Message is required.';
    } elseif (strlen($msg) < 10) {
        $errors[] = 'Message must be at least 10 characters.';
    } elseif (strlen($msg) > 5000) {
        $errors[] = 'Message is too long (maximum 5000 characters).';
    }

    if ($errors !== []) {
        $message = 'Please fix the following errors:<br>' . implode('<br>', array_map('e', $errors));
        $messageType = 'error';
    } else {
        try {
            $insert = db_execute(
                "INSERT INTO contact_messages (name, email, phone, subject, message, status)
                 VALUES (?, ?, ?, ?, ?, 'new')",
                'sssss',
                [$name, $email, $phone, $subject, $msg]
            );

            log_activity(
                'contact_message',
                'contact_message',
                (int) $insert['insert_id'],
                'New contact message: ' . $subject
            );

            $message = 'Thank you! Your message has been sent successfully. Our team will get back to you soon.';
            $messageType = 'success';
            $name = $email = $phone = $subject = $msg = '';
        } catch (Throwable $error) {
            $message = APP_DEBUG
                ? 'Database error: ' . e($error->getMessage())
                : 'Your message could not be saved right now. Please try again.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - StudentHub Portal</title>
    <link rel="stylesheet" href="../CSS/contact_new.css">
    <style>
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Contact Us</h1>
        </header>

        <div class="contact-grid">
            <div class="contact-card">
                <div class="card-header">
                    <h2>Get In Touch</h2>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <h3>Address</h3>
                        <p>CHARUSAT University<br>Changa, Anand<br>Gujarat, India</p>
                    </div>
                    <div class="info-item">
                        <h3>Phone</h3>
                        <p>+91 9998158131</p>
                    </div>
                    <div class="info-item">
                        <h3>Email</h3>
                        <p>zeelgadara05@gmail.com</p>
                    </div>
                </div>
            </div>

            <div class="contact-card">
                <div class="card-header">
                    <h2>Send Message</h2>
                </div>
                <div class="card-body">
                    <?php if ($message !== ''): ?>
                        <div class="message <?php echo e($messageType); ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="contact_process.php">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" value="<?php echo e($name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo e($email); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" maxlength="10" value="<?php echo e($phone); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" maxlength="200" value="<?php echo e($subject); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea name="message" rows="5" required><?php echo e($msg); ?></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Send Message</button>
                    </form>

                    <p style="margin-top: 15px;"><a href="../Contact.html">Back to Contact</a> | <a href="../home.html">Home</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
