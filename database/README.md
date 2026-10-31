# StudentHub Portal — Database & PHP Layer

This folder contains the complete MySQL/MariaDB schema for the StudentHub portal, and the
PHP pages/APIs in `../php/` are the application layer that talks to it.

Everything runs on a normal XAMPP installation (Apache + PHP 8 + MariaDB/MySQL).

---

## 1. Quick start

1. Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
2. Import the database:
   - **phpMyAdmin:** <http://localhost/phpmyadmin> → **Import** → choose `database/studenthub.sql` → **Go**.
   - **Command line (Windows):**
     ```powershell
     cmd /c "C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < E:\SEMESTER 3\web devlopment\practical\database\studenthub.sql"
     ```
3. Open the portal: <http://localhost/practical/> — you will be sent to the login page.
4. Sign in as a student or as Admin / Faculty (see the demo accounts below).
5. Check the database connection: <http://localhost/practical/php/api/health.php>

New students can create their own account from the login page
(<http://localhost/practical/Registration.html>). Staff accounts are seeded and
cannot be self-registered.

If your MySQL user or password is different, edit `php/config.php` (or set the
`SH_DB_HOST`, `SH_DB_PORT`, `SH_DB_NAME`, `SH_DB_USER`, `SH_DB_PASS` environment variables).

> The SQL script drops and recreates the StudentHub tables. The old legacy table
> `student` (singular) is **not** touched.

If the database already exists and you do not want to lose data, run the
non-destructive upgrade instead. It adds the `timetable` table, the Faculty
account and the demo timetable without dropping anything:

```powershell
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < E:\SEMESTER 3\web devlopment\practical\database\portal_upgrade.sql"
```

The Fetch API demo uses its own small table, so it can be added to an existing
database without touching real portal events:

```powershell
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < E:\SEMESTER 3\web devlopment\practical\database\simple_events.sql"
```

It creates `simple_events` when missing and inserts three sample rows only once.

---

## 2. Database at a glance

| Item | Value |
|------|-------|
| Database | `studenthub` |
| Engine | InnoDB |
| Charset | `utf8mb4` / `utf8mb4_unicode_ci` |
| Foreign keys | Enabled on all relationships |
| Time zone | `Asia/Kolkata` (+05:30) |

### Tables

| Table | Purpose |
|-------|---------|
| `departments` | Academic departments (CS, IT, EC, ME, CE, Management, Applied Sciences) |
| `courses` | Degree programmes offered by each department |
| `admins` | Administrator / Faculty / event-manager accounts (staff, no self-registration) |
| `students` | Student portal accounts (hashed passwords, roll number, course, year, status) |
| `event_categories` | Technical, Cultural, Sports, Workshop, Seminar |
| `events` | Event details, dates, venue, deadline, capacity, fee, status |
| `event_registrations` | One row per student per event, with a unique registration code |
| `event_registration_members` | Teammates for group events |
| `notice_categories` | Notice categories with colours |
| `notices` | Published notices with pin/expiry support |
| `faq_categories` | FAQ categories |
| `faqs` | Questions, answers, display order |
| `contact_messages` | Messages submitted through the contact form |
| `subjects` | Subjects mapped to department/course/semester |
| `attendance` | Daily attendance per student per subject |
| `results` | Internal/midterm/final marks with grade |
| `timetable` | Weekly class slots per department + semester (day, time, subject, room, faculty) |
| `login_attempts` | Login audit + brute-force protection |
| `remember_tokens` | Optional "remember me" selector + hashed validator pairs |
| `activity_logs` | Admin/student activity trail |
| `simple_events` | Small standalone table used by the Fetch API CRUD demo (`SimpleEvents.html`) |
| `settings` | Site-wide configuration values |

### Views

| View | Purpose |
|------|---------|
| `vw_student_details` | Student + department + course in one row (used by the APIs) |
| `vw_event_details` | Event + category + registered count + seats left |
| `vw_event_registration_details` | Registration + event + student details |
| `vw_student_dashboard` | Per-student totals: events, attendance, average marks |

### Relationships

```
departments 1 ──< courses 1 ──< students >── 1 departments
events 1 ──< event_registrations >── 1 students
event_registrations 1 ──< event_registration_members
event_categories 1 ──< events
notice_categories 1 ──< notices
faq_categories 1 ──< faqs
admins 1 ──< events / notices / attendance / contact_messages / activity_logs
subjects 1 ──< attendance / results >── 1 students
```

---

## 3. Seeded demo accounts

| Role | Login | Password |
|------|-------|----------|
| Student | `rahul.sharma@charusat.edu` or `CS21001` | `Student@123` |
| Student | `priya.patel@charusat.edu` or `EC21002` | `Student@123` |
| Admin | `admin@studenthub.local` | `Admin@123` |
| Faculty | `faculty@studenthub.local` | `Faculty@123` |
| Event manager | `events@studenthub.local` | `Admin@123` |

Students sign in from the **Student** tab (email or roll number). Admin and
Faculty sign in from the **Admin / Faculty** tab (email only). Using the wrong
tab is rejected, so a student account can never be used to reach staff pages.

All seeded students use `Student@123`. Passwords are stored with
`password_hash(..., PASSWORD_DEFAULT)` and verified with `password_verify()`.

The seed data includes **9 students, 21 events, 7 registrations, 8 notices,
7 FAQs, 10 subjects, 68 timetable slots, attendance, results, 3 staff accounts
and 9 settings**.

---

## 4. PHP files

| File | What it does |
|------|--------------|
| `php/config.php` | Database credentials, timezone, debug flag |
| `php/db.php` | `mysqli` singleton + prepared-statement helpers (`db_query`, `db_one`, `db_execute`, `db_transaction`) |
| `php/bootstrap.php` | Session, JSON responses, request/pagination/auth helpers, timeouts, role guards, audit logging |
| `php/remember.php` | Remember-me tokens: issue, rotate, validate, revoke and expire |
| `php/functions.php` | Validation helpers, `e()` escaping |
| `php/serializers.php` | Converts DB rows to the JSON shape used by the front-end |
| `php/lookups.php` | Maps form degree/department values to `courses` / `departments` rows |
| `php/register_process.php` | Secure registration handler: server-side validation, duplicate checks, `password_hash()`, MySQLi insert, auto roll number |
| `php/contact_process.php` | Contact form handler (inserts into `contact_messages`) |
| `php/login_process.php` / `php/logout.php` | Student/staff login, session regeneration, remember-me issue/revoke, role redirect, logout |
| `php/page_rules.php` | Page access levels (`public`, `student`, `staff`, `authenticated`) and safe `?next=` validation |
| `php/page_gate.php` | Streams a protected `.html` page only after the session and role check (wired in `.htaccess`) |
| `index.php` | Project entry point: sends visitors to login or to their dashboard |
| `dashboard.php` | Routes a signed-in user to the dashboard for their role |
| `student_dashboard.php` / `admin_dashboard.php` | Protected dashboard pages (`require_login()` / `require_admin()`) |
| `php/api/*.php` | JSON APIs used by the list pages and event registration UI |

Every query uses **prepared statements** — no user input is concatenated into SQL.

---

## 5. JSON API

All endpoints return:

```json
{
  "success": true,
  "message": "Students loaded successfully.",
  "data": [],
  "meta": { "page": 1, "per_page": 10, "total": 9, "total_pages": 1 }
}
```

Errors return `success: false`, a `message`, an optional `errors` map keyed by
field name and an HTTP status (400/401/403/404/409/422/500/503).

All endpoints except `health.php` and `check_availability.php` require a signed-in
session. `students.php` and `simple_events.php` are **staff-only**, and event
changes in `events.php` require a staff account (`super_admin`, `admin`,
`event_manager` or `faculty`).
`attendance.php`, `results.php` and `timetable.php` always return the signed-in
student's own data.

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `php/api/health.php` | Connection + row-count diagnostics |
| GET | `php/api/students.php` | **Staff only.** Students: `q`, `department`, `department_id`, `year`, `course`, `course_id`, `status`, `sort`, `page`, `per_page` (includes event counts) |
| GET | `php/api/students.php?action=lookups` | **Staff only.** Departments, courses, statuses, genders, years and semesters for the management form |
| GET | `php/api/students.php?id=1` | **Staff only.** One student record |
| POST | `php/api/students.php` (`action=create`) | **Staff only.** Add a student (validated, password hashed) |
| POST | `php/api/students.php` (`action=update`) | **Staff only.** Edit a student; a blank password keeps the current hash |
| POST | `php/api/students.php` (`action=delete`) | **Staff only.** Permanently delete a student and cascaded records |
| GET | `php/api/events.php` | Events: `q`, `category`, `category_id`, `status`, `upcoming=1`, `featured=1`, `sort`, `page`, `per_page` |
| GET | `php/api/events.php?action=categories` | Event category list |
| GET | `php/api/events.php?action=lookups` | **Staff only.** Categories, statuses and poster upload rules for the management form |
| GET | `php/api/events.php?id=1` | One event record |
| POST | `php/api/events.php` (`action=create`) | **Staff only.** Add an event with optional `poster` upload |
| POST | `php/api/events.php` (`action=update`) | **Staff only.** Edit an event, replace its poster or send `remove_poster=1` |
| POST | `php/api/events.php` (`action=delete`) | **Staff only.** Permanently delete an event, its poster and cascaded registrations |
| GET | `php/api/simple_events.php` | **Staff only.** List the `simple_events` demo rows as JSON |
| POST | `php/api/simple_events.php` (`action=create`) | **Staff only.** Add a simple event (`title`, `venue`, `event_date`, `status`, `description`) |
| POST | `php/api/simple_events.php` (`action=update`) | **Staff only.** Update a simple event by `id` |
| POST | `php/api/simple_events.php` (`action=delete`) | **Staff only.** Delete a simple event by `id` |
| GET | `php/api/notices.php` | Notices: `q`, `category`, `active=1`, `sort`, `page`, `per_page` |
| GET | `php/api/faqs.php` | FAQs: `q`, `category`, `sort`, `page`, `per_page` |
| GET | `php/api/event_registrations.php` | Registrations: `event_id`, `student_id`, `roll_no`, `status`, `code`, `page`, `per_page` (students are pinned to their own rows) |
| POST | `php/api/event_registrations.php` | Register for an event |
| POST | `php/api/event_registrations.php` (`action=cancel`) | Cancel a registration |
| GET | `php/api/attendance.php` | **Student:** own attendance. **Staff:** add `student_id` |
| GET | `php/api/results.php` | **Student:** own results. **Staff:** add `student_id` |
| GET | `php/api/timetable.php` | **Student:** own weekly timetable. **Staff:** add `student_id` |
| GET | `php/api/me.php` | Current session user |
| GET | `php/api/check_availability.php` | Live duplicate check: `email=...` and/or `username=...` |

### Check email / username availability

```powershell
Invoke-RestMethod "http://localhost/practical/php/api/check_availability.php?email=rahul.sharma@charusat.edu"
# data.email_available = false

Invoke-RestMethod "http://localhost/practical/php/api/check_availability.php?username=CS21009"
# data.username_available + data.suggestions
```

`JS/Registration.js` calls this endpoint on blur and again on submit. The
registration handler repeats the check, so the database remains authoritative.

### Register for an event

```powershell
$body = @{
  event_id  = 16
  roll_no   = "CS21001"
  team_name = "Code Warriors"
  notes     = "Vegetarian lunch"
} | ConvertTo-Json

Invoke-RestMethod "http://localhost/practical/php/api/event_registrations.php" `
  -Method Post -ContentType "application/json" -Body $body
```

The response contains a unique `registrationCode` such as `REG-2026-0008`.

### Simple events Fetch CRUD

`SimpleEvents.html` uses `fetch()` for every operation, so the page never
reloads. `JS/SimpleEvents.js` sends JSON request bodies and re-renders the table
from the JSON response.

```js
/* List */
const list = await fetch("php/api/simple_events.php", {
  headers: { Accept: "application/json" }
}).then(response => response.json());

/* Add */
await fetch("php/api/simple_events.php", {
  method: "POST",
  headers: { "Content-Type": "application/json", Accept: "application/json" },
  body: JSON.stringify({
    action: "create",
    title: "Coding Workshop",
    venue: "Computer Lab 2",
    event_date: "2026-12-01",
    status: "scheduled",
    description: "Optional note"
  })
});
```

Send `{ "action": "update", "id": 1, ...fields }` to edit a row and
`{ "action": "delete", "id": 1 }` to remove one. Validation failures return
HTTP 422 with an `errors` map keyed by field name; the page highlights those
fields next to the form.

### Cancel a registration

```powershell
$body = @{
  action            = "cancel"
  registration_code = "REG-2026-0008"
  roll_no           = "CS21001"
} | ConvertTo-Json

Invoke-RestMethod "http://localhost/practical/php/api/event_registrations.php" `
  -Method Post -ContentType "application/json" -Body $body
```

---

## 6. Front-end integration

| Page | Data source |
|------|-------------|
| `Students.html` | **Staff only.** `GET php/api/students.php` for the list, lookups and single records; `POST` with `action=create`, `update` or `delete` |
| `ManageEvents.html` | **Staff only.** `GET php/api/events.php` for the list, lookups and single records; `POST` with `action=create`, `update` or `delete` plus the optional poster file |
| `SimpleEvents.html` | **Staff only.** `GET php/api/simple_events.php` for the list; `POST` with `action=create`, `update` or `delete` (Fetch API demo, no page reload) |
| `Profile.html` | **Student only.** `GET php/api/me.php` |
| `Attendance.html` | **Student only.** `GET php/api/attendance.php` |
| `Result.html` | **Student only.** `GET php/api/results.php` |
| `TT.html` | **Student only.** `GET php/api/timetable.php` |
| `Events.html` | `GET php/api/events.php?upcoming=1` |
| `EventsList.html` | `GET php/api/events.php` + registration modal → `POST php/api/event_registrations.php` |
| `p6.html` | `GET php/api/events.php` |
| `Notices.html` | `GET php/api/notices.php` |
| `Notice.html` | `GET php/api/notices.php?active=1` |
| `FAQs.html` | `GET php/api/faqs.php` |
| `Registration.html` | Public. `POST php/register_process.php` |
| `Contact.html` | `POST php/contact_process.php` |
| `login.html` | Public. `POST php/login_process.php` |
| `dashboard.php` | Session cookie → redirects to the student or admin dashboard |
| `student_dashboard.php` / `admin_dashboard.php` | Server-rendered protected pages |

Every other root-level `.html` page is rewritten by `.htaccess` to
`php/page_gate.php`, which checks the session and role before streaming the
original page. The browser URL stays the same, so relative CSS/JS paths keep
working. Only `login.html` and `Registration.html` are public.

The old files in `JSON/` and `data/` are kept as legacy fixtures; the pages above no
longer read them, so MySQL is the single source of truth.

### What staff can do on the student management page

1. `Students.html` lists students from MySQL with server-side search, department,
   course, year and status filters plus pagination.
2. **Add Student** validates every field on the server, checks duplicate roll
   number, email and mobile, hashes the password with `password_hash()` and writes
   the row with a MySQLi prepared statement.
3. **Edit Student** updates the record; leaving the password blank keeps the
   existing hash. Changing the password revokes that student's remember-me tokens.
4. **View** opens a read-only profile with department, course, contact details,
   status and event participation.
5. **Delete** asks for confirmation and permanently removes the student. Related
   attendance, results and event registrations are removed by the schema's
   `ON DELETE CASCADE` rules.
6. Success and failure messages come back as JSON and are shown as a green or red
   banner; validation errors also highlight the affected form fields.

### What staff can do on the event management page

1. `ManageEvents.html` lists events from MySQL with server-side search, category
   and status filters, sorting and pagination. The public `EventsList.html` and
   `Events.html` pages keep showing the same records.
2. **Add Event** validates the title, category, venue, date, time, capacity, fee
   and contact details on the server, then writes the row with a MySQLi prepared
   statement. The optional poster is checked on both the client and the server.
3. **Poster rules:** JPG, PNG or WebP only, maximum **2 MB**. The server also
   checks the MIME type and that `getimagesize()` can read the file. Uploads are
   stored in `uploads/events/` with a random filename, and that folder blocks
   script execution through its own `.htaccess`.
4. **View** opens a read-only summary with the poster, schedule, venue, seat
   counts and contact details.
5. **Edit** updates the event; uploading a new poster deletes the old file, and
   ticking **Remove current poster** deletes it without replacing it.
6. **Delete** asks for confirmation and permanently removes the event and its
   poster. Registrations for that event are removed by the schema's
   `ON DELETE CASCADE` rules.
7. Every create, update and delete is written to `activity_logs`. Students cannot
   open the page or call the write endpoints; anonymous visitors are sent to the
   login page.

### What happens when a student registers

1. The form is validated on the server (name, email, mobile, password strength, year, gender, terms).
2. The email, username and mobile are checked; a duplicate is rejected with a field-level message.
3. A roll number is generated as `STU` + zero-padded id when the field is left blank.
4. The password is hashed and the student is linked to the matching department/course.
5. The new student can sign in immediately and sees only their own profile,
   attendance, results, timetable and event registrations. The account also
   appears in the staff-only student management page for Admin / Faculty.

### What happens when a student registers for an event

1. The API locks the event row and checks status, date, deadline and capacity.
2. The student is looked up by roll number, email or session.
3. Duplicate registrations are rejected (unique `event_id + student_id`).
4. A unique code `REG-YYYY-NNNN` is generated and the registration is stored as `confirmed`.
5. The event card shows updated seat counts and the code is displayed in the modal.

---

## 7. Useful SQL for the practical

```sql
USE studenthub;

-- Students with department and course
SELECT s.roll_no, s.full_name, d.name AS department, c.name AS course,
       s.current_year, s.status
FROM students s
LEFT JOIN departments d ON d.id = s.department_id
LEFT JOIN courses c ON c.id = s.course_id
ORDER BY s.roll_no;

-- Events with registration counts and seats left
SELECT title, event_date, venue, category, registered_count, seats_left, status
FROM vw_event_details
ORDER BY event_date;

-- Registrations for one event
SELECT roll_no, student_name, student_email, registration_status, registered_at
FROM vw_event_registration_details
WHERE event_id = 16
ORDER BY registered_at;

-- Most popular events
SELECT e.title, COUNT(er.id) AS total_registrations
FROM events e
LEFT JOIN event_registrations er
       ON er.event_id = e.id AND er.status IN ('pending','confirmed','attended')
GROUP BY e.id, e.title
ORDER BY total_registrations DESC;

-- Attendance percentage per student
SELECT s.roll_no, s.full_name,
       COUNT(a.id) AS classes,
       SUM(a.status = 'present') AS present,
       ROUND(SUM(a.status = 'present') / NULLIF(COUNT(a.id), 0) * 100, 2) AS percentage
FROM students s
LEFT JOIN attendance a ON a.student_id = s.id
GROUP BY s.id, s.roll_no, s.full_name;

-- Result summary
SELECT s.roll_no, s.full_name, ROUND(AVG(r.marks_obtained / r.max_marks * 100), 2) AS average_percentage
FROM students s
LEFT JOIN results r ON r.student_id = s.id
GROUP BY s.id, s.roll_no, s.full_name;
```

---

## 8. Security & data-integrity features

- Prepared statements for every query (`mysqli::prepare` / `bind_param`).
- `password_hash()` / `password_verify()` for all account passwords; plain-text passwords are never stored.
- Registration validates every field on both the client and the server.
- Duplicate email, username/roll number and mobile checks return clear field-level messages.
- Unique constraints on email, mobile, roll number and event registration code.
- One registration per student per event enforced by `uq_event_registrations_event_student`.
- Foreign keys with `ON UPDATE CASCADE` and sensible delete rules.
- `CHECK` constraints for year (1–6) and semester (1–12).
- Brute-force protection: 5 failed logins in 15 minutes per identifier, plus a 20-failure cap per IP address; the counter is cleared after a successful sign-in.
- Login audit (`login_attempts`) and activity trail (`activity_logs`).
- Session cookies are `HttpOnly` + `SameSite=Lax` (`Secure` on HTTPS); the session id is regenerated with `session_regenerate_id(true)` on login.
- Protected responses are sent with `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN` and a strict `Referrer-Policy`; the direct-served HTML pages get the same baseline headers from `.htaccess`.
- Outdated password hashes are upgraded with `password_needs_rehash()` / `password_hash()` after a successful `password_verify()`.
- Idle timeout: **30 minutes** (`SESSION_IDLE_TIMEOUT = 1800`); absolute timeout: **8 hours** (`SESSION_ABSOLUTE_TIMEOUT = 28800`). Both are enforced by `current_user()` on every protected request.
- Logout destroys the session, expires the session cookie, revokes the remember-me token and sends `Clear-Site-Data` so cached dashboard pages cannot be reopened with the Back button.
- Authenticated pages use `require_login()`, `require_role()` or `require_admin()`; a timed-out session is sent back to the login page with an explanation.
- The site-wide `.htaccess` gate makes every root-level `.html` page private except `login.html` and `Registration.html`.
- Student privacy is enforced on the server, not just by hiding links:
  students can never list other students, read another student's registration,
  or fetch another student's profile/attendance/results/timetable. Staff APIs
  (`students.php`) reject student sessions with HTTP 403.
- Admin / Faculty can review every student's profile, status and event
  participation from `Students.html` and `admin_dashboard.php`.
- The login page has separate **Student** and **Admin / Faculty** tabs; staff
  accounts cannot be self-registered and each tab only queries its own table.
- Role-based redirects: students → `student_dashboard.php`, admins → `admin_dashboard.php`.
- Optional remember-me: `selector:validator` cookie (`HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS) with only `sha256(validator)` stored in `remember_tokens`. The token is rotated on every use and revoked on logout or timeout.
- Output is escaped with `htmlspecialchars()` / the JS `escapeHtml()` helper.

### Session and remember-me configuration

| Constant | Default | Meaning |
|----------|---------|---------|
| `SESSION_IDLE_TIMEOUT` | `1800` | Seconds of inactivity before sign-out (`SH_SESSION_IDLE`) |
| `SESSION_ABSOLUTE_TIMEOUT` | `28800` | Maximum session lifetime in seconds (`SH_SESSION_ABSOLUTE`) |
| `REMEMBER_ME_DAYS` | `30` | Days a remember-me token stays valid (`SH_REMEMBER_DAYS`) |
| `REMEMBER_COOKIE` | `STUDENTHUB_REMEMBER` | Name of the remember-me cookie |

---

## 9. Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Database connection failed` | Start MySQL in XAMPP and confirm port `3306`; check `php/config.php` credentials |
| `Table 'studenthub.students' doesn't exist` | Import `database/studenthub.sql` again |
| Page shows an old list | Hard refresh (`Ctrl+F5`); `.htaccess` disables caching |
| API returns 500 | Set `SH_DEBUG=1` or open `php/api/health.php`; check `C:\xampp\apache\logs\error.log` |
| Registration says email already exists | That email is already registered — log in instead or use another email |
| Registration says username is taken | Choose another roll number or leave the field blank to auto-generate one |
| Event says “registration deadline passed” | The deadline is checked against the server clock (Asia/Kolkata) |
| Cannot cancel a registration | Cancellation is only allowed before the event's registration deadline |
| Logged out with a timeout message | The idle or absolute session limit was reached; sign in again. Adjust `SH_SESSION_IDLE` / `SH_SESSION_ABSOLUTE` for testing |
| Remember-me does not sign in | The token may have expired or been revoked; sign in with the password. Import the `remember_tokens` table if it is missing |
