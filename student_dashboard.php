<?php
/**
 * StudentHub Portal - student dashboard
 * -------------------------------------
 * Protected page: only a signed-in student may open it. Administrators are
 * sent to their own dashboard. current_user() enforces idle/absolute timeouts.
 */

declare(strict_types=1);

require_once __DIR__ . '/php/bootstrap.php';

$user = require_login();

if (($user['type'] ?? 'student') === 'admin') {
    redirect(app_url(dashboard_path('admin')));
}

$student = db_one('SELECT * FROM vw_student_details WHERE id = ? LIMIT 1', 'i', [(int) $user['id']]);

if ($student === null) {
    logout_user();
    redirect(app_url('login.html?error=' . urlencode('Your student record is no longer available.')));
}

$registrationCount = (int) db_value(
    "SELECT COUNT(*) FROM event_registrations
      WHERE student_id = ? AND status IN ('pending','confirmed','attended')",
    'i',
    [(int) $user['id']]
);

$upcomingEventCount = (int) db_value(
    "SELECT COUNT(*) FROM events
      WHERE status = 'upcoming' AND event_date >= CURDATE()"
);

$noticeCount = (int) db_value('SELECT COUNT(*) FROM notices WHERE is_published = 1');

$flashForbidden = (($_GET['error'] ?? '') === 'forbidden');
$flashLogin = (($_GET['login'] ?? '') === 'success');

$nameParts = preg_split('/\s+/', trim((string) $student['full_name'])) ?: [];
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
if ($initials === '') {
    $initials = 'ST';
}

$idleMinutes = max(1, (int) round(SESSION_IDLE_TIMEOUT / 60));
$absoluteHours = max(1, (int) round(SESSION_ABSOLUTE_TIMEOUT / 3600));
$lastLogin = !empty($student['last_login_at'])
    ? date('d M Y, h:i A', strtotime((string) $student['last_login_at']))
    : 'First sign-in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudentHub Portal</title>
    <link rel="stylesheet" href="CSS/dashboard.css?v=1">
</head>
<body>
    <header class="dash-header">
        <div class="dash-header-inner">
            <a class="dash-brand" href="home.html">StudentHub<span>Portal</span></a>
            <div class="dash-user">
                <span class="dash-user-name"><?= e($student['full_name']) ?></span>
                <a class="dash-logout" href="php/logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="dash-main">
        <?php if ($flashLogin): ?>
            <div class="alert alert-success" role="status">
                Signed in successfully. Your session is protected by a regenerated session ID.
            </div>
        <?php endif; ?>

        <?php if ($flashForbidden): ?>
            <div class="alert alert-warning" role="alert">
                You do not have permission to open that page, so you were returned to your own dashboard.
            </div>
        <?php endif; ?>

        <section class="profile-banner">
            <div class="profile-avatar"><?= e($initials) ?></div>
            <div class="profile-copy">
                <p class="eyebrow">Student dashboard</p>
                <h1>Welcome back, <?= e($nameParts[0] ?? $student['full_name']) ?></h1>
                <p class="profile-meta">
                    <span><?= e($student['roll_no']) ?></span>
                    <span><?= e($student['course'] ?? 'Course not set') ?></span>
                    <span><?= e($student['department'] ?? 'Department not set') ?></span>
                </p>
            </div>
            <span class="badge badge-<?= e($student['status']) ?>"><?= e(ucfirst((string) $student['status'])) ?></span>
        </section>

        <section class="stat-grid" aria-label="Overview">
            <article class="stat-card">
                <span class="stat-label">Event registrations</span>
                <strong class="stat-value"><?= $registrationCount ?></strong>
                <a class="stat-link" href="EventsList.html">Browse events</a>
            </article>
            <article class="stat-card">
                <span class="stat-label">Upcoming events</span>
                <strong class="stat-value"><?= $upcomingEventCount ?></strong>
                <a class="stat-link" href="Events.html">View schedule</a>
            </article>
            <article class="stat-card">
                <span class="stat-label">Published notices</span>
                <strong class="stat-value"><?= $noticeCount ?></strong>
                <a class="stat-link" href="Notices.html">Read notices</a>
            </article>
            <article class="stat-card">
                <span class="stat-label">Current semester</span>
                <strong class="stat-value"><?= (int) $student['current_semester'] ?></strong>
                <span class="stat-link muted">Year <?= (int) $student['current_year'] ?></span>
            </article>
        </section>

        <div class="dash-columns">
            <section class="panel">
                <h2>Account details</h2>
                <dl class="detail-list">
                    <div><dt>Full name</dt><dd><?= e($student['full_name']) ?></dd></div>
                    <div><dt>Roll number</dt><dd><?= e($student['roll_no']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($student['email']) ?></dd></div>
                    <div><dt>Mobile</dt><dd><?= e($student['mobile']) ?></dd></div>
                    <div><dt>Course</dt><dd><?= e($student['course'] ?? '—') ?></dd></div>
                    <div><dt>Department</dt><dd><?= e($student['department'] ?? '—') ?></dd></div>
                    <div><dt>Last sign-in</dt><dd><?= e($lastLogin) ?></dd></div>
                </dl>
            </section>

            <section class="panel">
                <h2>Quick links</h2>
                <nav class="link-grid">
                    <a href="Profile.html">Profile</a>
                    <a href="Course.html">Courses</a>
                    <a href="Attendance.html">Attendance</a>
                    <a href="Result.html">Results</a>
                    <a href="TT.html">Time Table</a>
                    <a href="Notice.html">Notice</a>
                    <a href="Events.html">Events</a>
                    <a href="FAQs.html">FAQs</a>
                    <a href="Contact.html">Contact</a>
                </nav>

                <h2 class="spaced">Session security</h2>
                <dl class="detail-list compact">
                    <div><dt>Session started</dt><dd><?= e($user['login_time'] ?? '—') ?></dd></div>
                    <div><dt>Idle timeout</dt><dd><?= $idleMinutes ?> minutes</dd></div>
                    <div><dt>Maximum lifetime</dt><dd><?= $absoluteHours ?> hours</dd></div>
                    <div><dt>Session ID</dt><dd class="mono"><?= e(substr(session_id(), 0, 12)) ?>…</dd></div>
                </dl>
                <p class="panel-note">
                    Your session ID was regenerated when you signed in. You will be signed out
                    automatically after <?= $idleMinutes ?> minutes of inactivity or <?= $absoluteHours ?> hours
                    after login.
                </p>
                <a class="btn-danger" href="php/logout.php">Log out securely</a>
            </section>
        </div>
    </main>

    <footer class="dash-footer">
        <p>&copy; <?= date('Y') ?> StudentHub Portal · CHARUSAT University</p>
    </footer>
</body>
</html>
