<?php
/**
 * StudentHub Portal - administrator dashboard
 * -------------------------------------------
 * Protected page: only active staff accounts from the admins table may open
 * it. require_admin() rejects students, and the role/status check below also
 * turns away deactivated or unknown accounts. The sidebar menu is filtered by
 * the signed-in user's role, so every staff member sees only their options.
 */

declare(strict_types=1);

require_once __DIR__ . '/php/bootstrap.php';

$user = require_admin();

$admin = db_one(
    'SELECT id, full_name, email, role, status, last_login_at FROM admins WHERE id = ? LIMIT 1',
    'i',
    [(int) $user['id']]
);

$allowedRoles = ['super_admin', 'admin', 'event_manager', 'faculty'];
if (
    $admin === null
    || !in_array((string) $admin['role'], $allowedRoles, true)
    || (string) $admin['status'] !== 'active'
) {
    logout_user();
    redirect(app_url('login.html?error=' . urlencode('This administrator account is no longer allowed to open the dashboard.')));
}

$role = (string) $admin['role'];

$roleLabels = [
    'super_admin'   => 'Super Administrator',
    'admin'         => 'Administrator',
    'event_manager' => 'Event Manager',
    'faculty'       => 'Faculty',
];
$roleLabel = $roleLabels[$role] ?? ucfirst($role);

/* ----------------------------- User statistics ---------------------------- */
$studentStatusLabels = [
    'active'    => 'Active',
    'inactive'  => 'Inactive',
    'graduated' => 'Graduated',
    'suspended' => 'Suspended',
];
$studentStatusCounts = array_fill_keys(array_keys($studentStatusLabels), 0);
foreach (db_query('SELECT status, COUNT(*) AS total FROM students GROUP BY status') as $row) {
    $key = (string) $row['status'];
    if (array_key_exists($key, $studentStatusCounts)) {
        $studentStatusCounts[$key] = (int) $row['total'];
    }
}
$studentTotal = array_sum($studentStatusCounts);
$newStudentsThisMonth = (int) db_value(
    "SELECT COUNT(*) FROM students WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
);

$staffRoleLabels = [
    'super_admin'   => 'Super admins',
    'admin'         => 'Administrators',
    'event_manager' => 'Event managers',
    'faculty'       => 'Faculty',
];
$staffRoleCounts = array_fill_keys(array_keys($staffRoleLabels), 0);
foreach (db_query('SELECT role, COUNT(*) AS total FROM admins GROUP BY role') as $row) {
    $key = (string) $row['role'];
    if (array_key_exists($key, $staffRoleCounts)) {
        $staffRoleCounts[$key] = (int) $row['total'];
    }
}
$staffTotal = array_sum($staffRoleCounts);
$newStaffThisMonth = (int) db_value(
    "SELECT COUNT(*) FROM admins WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
);

/* ----------------------------- Portal totals ------------------------------ */
$counts = [
    'users'             => $studentTotal + $staffTotal,
    'students'          => $studentTotal,
    'active_students'   => $studentStatusCounts['active'],
    'staff'             => $staffTotal,
    'active_staff'      => (int) db_value("SELECT COUNT(*) FROM admins WHERE status = 'active'"),
    'events'            => (int) db_value('SELECT COUNT(*) FROM events'),
    'upcoming_events'   => (int) db_value("SELECT COUNT(*) FROM events WHERE status = 'published' AND event_date >= CURDATE()"),
    'registrations'     => (int) db_value("SELECT COUNT(*) FROM event_registrations WHERE status IN ('pending','confirmed','attended')"),
    'new_registrations' => (int) db_value("SELECT COUNT(*) FROM event_registrations WHERE status IN ('pending','confirmed','attended') AND registered_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'notices'           => (int) db_value('SELECT COUNT(*) FROM notices WHERE is_published = 1'),
    'faqs'              => (int) db_value('SELECT COUNT(*) FROM faqs'),
    'contacts'          => (int) db_value('SELECT COUNT(*) FROM contact_messages'),
];

$percent = static function (int $value, int $total): int {
    return $total > 0 ? (int) round(($value / $total) * 100) : 0;
};

/* --------------------------- Role-based menu ------------------------------ */
$allStaffRoles = ['super_admin', 'admin', 'event_manager', 'faculty'];
$canManageStudents = in_array($role, ['super_admin', 'admin', 'faculty'], true);
$menuItems = [
    ['key' => 'dashboard',     'label' => 'Dashboard',      'href' => 'admin_dashboard.php', 'icon' => '▦', 'roles' => $allStaffRoles],
    ['key' => 'students',      'label' => 'Students',       'href' => 'Students.html',       'icon' => '◍', 'roles' => ['super_admin', 'admin', 'faculty'], 'count' => $counts['students']],
    ['key' => 'events',        'label' => 'Manage events',  'href' => 'ManageEvents.html',   'icon' => '▤', 'roles' => $allStaffRoles, 'count' => $counts['events']],
    ['key' => 'registrations', 'label' => 'Registrations',  'href' => 'EventsList.html',     'icon' => '✎', 'roles' => $allStaffRoles, 'count' => $counts['registrations']],
    ['key' => 'simple',        'label' => 'Fetch API demo', 'href' => 'SimpleEvents.html',   'icon' => '⚡', 'roles' => $allStaffRoles],
    ['key' => 'notices',       'label' => 'Notices',        'href' => 'Notices.html',        'icon' => '◈', 'roles' => $allStaffRoles, 'count' => $counts['notices']],
    ['key' => 'faqs',          'label' => 'FAQs',           'href' => 'FAQs.html',           'icon' => '?', 'roles' => $allStaffRoles],
    ['key' => 'messages',      'label' => 'Messages',       'href' => 'Contact.html',        'icon' => '✉', 'roles' => ['super_admin', 'admin'], 'count' => $counts['contacts']],
];
$menuItems = array_values(array_filter(
    $menuItems,
    static fn (array $item): bool => in_array($role, $item['roles'], true)
));

$recentRegistrations = db_query(
    "SELECT registration_code, student_name, roll_no, event_title, registration_status, registered_at
       FROM vw_event_registration_details
      ORDER BY registered_at DESC
      LIMIT 8"
);

$recentActivity = db_query(
    "SELECT action, details, created_at
       FROM activity_logs
      ORDER BY created_at DESC, id DESC
      LIMIT 6"
);

$eventParticipation = db_query(
    "SELECT e.id, e.title, e.event_date, e.status, e.max_participants,
            ec.name AS category,
            COUNT(er.id) AS active_count,
            SUM(CASE WHEN er.status = 'attended' THEN 1 ELSE 0 END) AS attended_count
       FROM events e
       LEFT JOIN event_categories ec ON ec.id = e.category_id
       LEFT JOIN event_registrations er
              ON er.event_id = e.id
             AND er.status IN ('pending','confirmed','attended')
      GROUP BY e.id, e.title, e.event_date, e.status, e.max_participants, ec.name
      ORDER BY active_count DESC, e.event_date ASC"
);

$flashLogin = (($_GET['login'] ?? '') === 'success');
$flashForbidden = (($_GET['error'] ?? '') === 'forbidden');

$nameParts = preg_split('/\s+/', trim((string) $admin['full_name'])) ?: [];
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
if ($initials === '') {
    $initials = 'AD';
}

$idleMinutes = max(1, (int) round(SESSION_IDLE_TIMEOUT / 60));
$absoluteHours = max(1, (int) round(SESSION_ABSOLUTE_TIMEOUT / 3600));
$lastLogin = !empty($admin['last_login_at'])
    ? date('d M Y, h:i A', strtotime((string) $admin['last_login_at']))
    : 'First sign-in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudentHub Portal</title>
    <link rel="stylesheet" href="CSS/dashboard.css?v=2">
</head>
<body>
    <header class="dash-header dash-header-admin">
        <div class="dash-header-inner">
            <a class="dash-brand" href="home.html">StudentHub<span>Admin</span></a>
            <div class="dash-user">
                <span class="dash-user-name"><?= e($admin['full_name']) ?></span>
                <a class="dash-logout" href="php/logout.php">Logout</a>
            </div>
        </div>
    </header>

    <div class="dash-shell">
        <aside class="dash-sidebar" aria-label="Administration menu">
            <div class="side-profile">
                <div class="side-avatar"><?= e($initials) ?></div>
                <div class="side-identity">
                    <strong><?= e($admin['full_name']) ?></strong>
                    <span><?= e($roleLabel) ?></span>
                </div>
            </div>

            <nav class="side-menu" aria-label="Role-based menu">
                <?php foreach ($menuItems as $item): ?>
                    <?php $isActive = $item['key'] === 'dashboard'; ?>
                    <a class="side-link<?= $isActive ? ' is-active' : '' ?>"
                       href="<?= e($item['href']) ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <span class="side-icon" aria-hidden="true"><?= e($item['icon']) ?></span>
                        <span class="side-label"><?= e($item['label']) ?></span>
                        <?php if (isset($item['count'])): ?>
                            <span class="side-badge"><?= (int) $item['count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <p class="side-note">
                Menu options shown for <strong><?= e($roleLabel) ?></strong>.
            </p>

            <a class="side-logout" href="php/logout.php">Log out securely</a>
        </aside>

        <main class="dash-main">
        <?php if ($flashLogin): ?>
            <div class="alert alert-success" role="status">
                Administrator sign-in successful. The session ID was regenerated for this session.
            </div>
        <?php endif; ?>

        <?php if ($flashForbidden): ?>
            <div class="alert alert-warning" role="alert">
                That page is not available for your account role.
            </div>
        <?php endif; ?>

        <section class="profile-banner profile-banner-admin">
            <div class="profile-avatar"><?= e($initials) ?></div>
            <div class="profile-copy">
                <p class="eyebrow">Administration dashboard</p>
                <h1>Welcome, <?= e($nameParts[0] ?? $admin['full_name']) ?></h1>
                <p class="profile-meta">
                    <span><?= e($roleLabel) ?></span>
                    <span><?= e($admin['email']) ?></span>
                </p>
            </div>
            <span class="badge badge-role"><?= e($roleLabel) ?></span>
        </section>

        <section class="stat-grid" aria-label="Portal statistics">
            <article class="stat-card stat-card-accent">
                <span class="stat-label">Total users</span>
                <strong class="stat-value"><?= $counts['users'] ?></strong>
                <span class="stat-foot"><?= $counts['students'] ?> students · <?= $counts['staff'] ?> staff</span>
                <a class="stat-link" href="#user-statistics">See user breakdown</a>
            </article>
            <article class="stat-card">
                <span class="stat-label">Students</span>
                <strong class="stat-value"><?= $counts['students'] ?></strong>
                <span class="stat-foot"><?= $counts['active_students'] ?> active · <?= $newStudentsThisMonth ?> new this month</span>
                <?php if ($canManageStudents): ?>
                    <a class="stat-link" href="Students.html">Manage students</a>
                <?php else: ?>
                    <span class="stat-link muted">Student overview only</span>
                <?php endif; ?>
            </article>
            <article class="stat-card">
                <span class="stat-label">Staff accounts</span>
                <strong class="stat-value"><?= $counts['staff'] ?></strong>
                <span class="stat-foot"><?= $counts['active_staff'] ?> active · <?= $newStaffThisMonth ?> new this month</span>
                <span class="stat-link muted">Admins, faculty &amp; event managers</span>
            </article>
            <article class="stat-card">
                <span class="stat-label">Events</span>
                <strong class="stat-value"><?= $counts['events'] ?></strong>
                <span class="stat-foot"><?= $counts['upcoming_events'] ?> upcoming published</span>
                <a class="stat-link" href="ManageEvents.html">Manage events</a>
            </article>
            <article class="stat-card">
                <span class="stat-label">Active registrations</span>
                <strong class="stat-value"><?= $counts['registrations'] ?></strong>
                <span class="stat-foot"><?= $counts['new_registrations'] ?> added this month</span>
                <a class="stat-link" href="EventsList.html">View registrations</a>
            </article>
            <article class="stat-card">
                <span class="stat-label">Published notices</span>
                <strong class="stat-value"><?= $counts['notices'] ?></strong>
                <span class="stat-foot"><?= $counts['faqs'] ?> FAQs · <?= $counts['contacts'] ?> messages</span>
                <a class="stat-link" href="Notices.html">Open notices</a>
            </article>
        </section>

        <section class="panel panel-block" id="user-statistics">
            <div class="panel-head">
                <div>
                    <h2>User statistics</h2>
                    <p class="panel-sub">Accounts in the portal, grouped by status and staff role.</p>
                </div>
                <span class="badge badge-role"><?= $counts['users'] ?> total users</span>
            </div>

            <div class="breakdown-grid">
                <div class="breakdown-block">
                    <h3>Students <span class="breakdown-total"><?= $counts['students'] ?></span></h3>
                    <ul class="breakdown-list">
                        <?php foreach ($studentStatusLabels as $statusKey => $statusLabel): ?>
                            <?php $statusValue = (int) $studentStatusCounts[$statusKey]; ?>
                            <li>
                                <span class="breakdown-label"><?= e($statusLabel) ?></span>
                                <span class="breakdown-track" aria-hidden="true">
                                    <span class="breakdown-fill" style="width: <?= $percent($statusValue, $counts['students']) ?>%"></span>
                                </span>
                                <strong><?= $statusValue ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="breakdown-block">
                    <h3>Staff accounts <span class="breakdown-total"><?= $counts['staff'] ?></span></h3>
                    <ul class="breakdown-list">
                        <?php foreach ($staffRoleLabels as $staffKey => $staffLabel): ?>
                            <?php $staffValue = (int) $staffRoleCounts[$staffKey]; ?>
                            <li>
                                <span class="breakdown-label"><?= e($staffLabel) ?></span>
                                <span class="breakdown-track" aria-hidden="true">
                                    <span class="breakdown-fill breakdown-fill-staff" style="width: <?= $percent($staffValue, $counts['staff']) ?>%"></span>
                                </span>
                                <strong><?= $staffValue ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <p class="panel-note">
                <?= $newStudentsThisMonth ?> student<?= $newStudentsThisMonth === 1 ? '' : 's' ?> and
                <?= $newStaffThisMonth ?> staff account<?= $newStaffThisMonth === 1 ? '' : 's' ?> joined this month.
                The menu on the left changes with the signed-in role.
            </p>
        </section>

        <section class="panel panel-block">
            <h2>Event participation</h2>
            <?php if ($eventParticipation === []): ?>
                <p class="empty-state">No events have been created yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th>Registrations</th>
                                <th>Attended</th>
                                <th>Capacity</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($eventParticipation as $eventRow): ?>
                            <tr>
                                <td>
                                    <strong><?= e($eventRow['title']) ?></strong>
                                    <span class="cell-sub"><?= e(ucfirst((string) $eventRow['status'])) ?></span>
                                </td>
                                <td><?= e($eventRow['category'] ?? 'General') ?></td>
                                <td><?= e(date('d M Y', strtotime((string) $eventRow['event_date']))) ?></td>
                                <td><strong><?= (int) $eventRow['active_count'] ?></strong> students</td>
                                <td><?= (int) $eventRow['attended_count'] ?></td>
                                <td><?= $eventRow['max_participants'] !== null ? (int) $eventRow['max_participants'] : 'Unlimited' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if ($canManageStudents): ?>
                <p class="panel-note">
                    Open <a href="Students.html">student management</a> to add, edit or remove
                    records, review account status and see individual event participation.
                </p>
            <?php else: ?>
                <p class="panel-note">
                    Student records are managed by administrators and faculty. Your role can
                    review event participation here without changing student accounts.
                </p>
            <?php endif; ?>
        </section>

        <div class="dash-columns">
            <section class="panel" id="latest-registrations">
                <div class="panel-head">
                    <div>
                        <h2>Latest registrations</h2>
                        <p class="panel-sub">Newest sign-ups across all events.</p>
                    </div>
                    <a class="panel-link" href="EventsList.html">View all</a>
                </div>
                <?php if ($recentRegistrations === []): ?>
                    <p class="empty-state">No event registrations yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Event</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentRegistrations as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($row['student_name']) ?></strong>
                                        <span class="cell-sub"><?= e($row['roll_no']) ?></span>
                                    </td>
                                    <td>
                                        <?= e($row['event_title']) ?>
                                        <span class="cell-sub"><?= e($row['registration_code']) ?></span>
                                    </td>
                                    <td><span class="status-pill status-<?= e($row['registration_status']) ?>"><?= e(ucfirst((string) $row['registration_status'])) ?></span></td>
                                    <td><?= e(date('d M Y', strtotime((string) $row['registered_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <h2>Account &amp; session</h2>
                <dl class="detail-list compact">
                    <div><dt>Role</dt><dd><?= e($roleLabel) ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($admin['email']) ?></dd></div>
                    <div><dt>Last sign-in</dt><dd><?= e($lastLogin) ?></dd></div>
                    <div><dt>Session started</dt><dd><?= e($user['login_time'] ?? '—') ?></dd></div>
                    <div><dt>Idle timeout</dt><dd><?= $idleMinutes ?> minutes</dd></div>
                    <div><dt>Maximum lifetime</dt><dd><?= $absoluteHours ?> hours</dd></div>
                </dl>

                <h2 class="spaced">Recent activity</h2>
                <?php if ($recentActivity === []): ?>
                    <p class="empty-state">No activity has been recorded yet.</p>
                <?php else: ?>
                    <ul class="activity-list">
                    <?php foreach ($recentActivity as $item): ?>
                        <li>
                            <span class="activity-action"><?= e(str_replace('_', ' ', (string) $item['action'])) ?></span>
                            <span class="activity-time"><?= e(date('d M, H:i', strtotime((string) $item['created_at']))) ?></span>
                            <?php if (!empty($item['details'])): ?>
                                <span class="activity-detail"><?= e($item['details']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </main>
    </div>

    <footer class="dash-footer">
        <p>&copy; <?= date('Y') ?> StudentHub Portal · Administration</p>
    </footer>
</body>
</html>
