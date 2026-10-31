-- =====================================================================
--  StudentHub Portal - Complete MySQL / MariaDB Database
--  Database : studenthub
--  Engine   : InnoDB
--  Charset  : utf8mb4
--
--  Import with:
--    C:\xampp\mysql\bin\mysql.exe -u root < database\studenthub.sql
--  or paste into phpMyAdmin -> SQL
--
--  NOTE: This script drops and recreates the StudentHub tables.
--        The legacy "student" table (if present) is left untouched.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `studenthub`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `studenthub`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW  IF EXISTS `vw_student_dashboard`;
DROP VIEW  IF EXISTS `vw_event_registration_details`;
DROP VIEW  IF EXISTS `vw_event_details`;
DROP VIEW  IF EXISTS `vw_student_details`;

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `remember_tokens`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `timetable`;
DROP TABLE IF EXISTS `results`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `faqs`;
DROP TABLE IF EXISTS `faq_categories`;
DROP TABLE IF EXISTS `notices`;
DROP TABLE IF EXISTS `notice_categories`;
DROP TABLE IF EXISTS `event_registration_members`;
DROP TABLE IF EXISTS `event_registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `event_categories`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- 1. DEPARTMENTS
-- =====================================================================
CREATE TABLE `departments` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(10)  NOT NULL,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_departments_code` (`code`),
    UNIQUE KEY `uq_departments_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. COURSES / PROGRAMMES
-- =====================================================================
CREATE TABLE `courses` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id`  INT UNSIGNED NOT NULL,
    `code`           VARCHAR(20)  NOT NULL,
    `name`           VARCHAR(120) NOT NULL,
    `degree_type`    ENUM('Certificate','Diploma','Bachelor','Master','Doctorate') NOT NULL DEFAULT 'Bachelor',
    `duration_years` TINYINT UNSIGNED NOT NULL DEFAULT 4,
    `total_semesters` TINYINT UNSIGNED NOT NULL DEFAULT 8,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_courses_code` (`code`),
    KEY `idx_courses_department` (`department_id`),
    CONSTRAINT `fk_courses_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. ADMINS
-- =====================================================================
CREATE TABLE `admins` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name`     VARCHAR(100) NOT NULL,
    `email`         VARCHAR(150) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('super_admin','admin','event_manager','faculty') NOT NULL DEFAULT 'admin',
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. STUDENTS  (portal accounts)
-- =====================================================================
CREATE TABLE `students` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `roll_no`          VARCHAR(20)  NOT NULL,
    `full_name`        VARCHAR(100) NOT NULL,
    `email`            VARCHAR(150) NOT NULL,
    `mobile`           VARCHAR(15)  NOT NULL,
    `password_hash`    VARCHAR(255) NOT NULL,
    `department_id`    INT UNSIGNED NULL,
    `course_id`        INT UNSIGNED NULL,
    `current_year`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `current_semester` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `gender`           ENUM('Male','Female','Other') NOT NULL DEFAULT 'Other',
    `date_of_birth`    DATE NULL,
    `address`          VARCHAR(255) NULL,
    `city`             VARCHAR(100) NULL,
    `state`            VARCHAR(100) NULL,
    `pincode`          VARCHAR(10)  NULL,
    `admission_date`   DATE NULL,
    `profile_photo`    VARCHAR(255) NULL,
    `status`           ENUM('active','inactive','graduated','suspended') NOT NULL DEFAULT 'active',
    `email_verified_at` DATETIME NULL,
    `last_login_at`    DATETIME NULL,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_students_roll_no` (`roll_no`),
    UNIQUE KEY `uq_students_email` (`email`),
    UNIQUE KEY `uq_students_mobile` (`mobile`),
    KEY `idx_students_department` (`department_id`),
    KEY `idx_students_course` (`course_id`),
    KEY `idx_students_status` (`status`),
    KEY `idx_students_year` (`current_year`),
    CONSTRAINT `fk_students_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_students_course`
        FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `chk_students_year` CHECK (`current_year` BETWEEN 1 AND 6),
    CONSTRAINT `chk_students_semester` CHECK (`current_semester` BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. EVENT CATEGORIES
-- =====================================================================
CREATE TABLE `event_categories` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`      VARCHAR(60) NOT NULL,
    `color`     VARCHAR(20) NOT NULL DEFAULT '#0d47a1',
    `icon`      VARCHAR(50) NULL,
    `is_active` TINYINT(1)  NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. EVENTS
-- =====================================================================
CREATE TABLE `events` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`                 VARCHAR(150) NOT NULL,
    `slug`                  VARCHAR(180) NOT NULL,
    `description`           TEXT NULL,
    `category_id`           INT UNSIGNED NOT NULL,
    `venue`                 VARCHAR(150) NOT NULL,
    `event_date`            DATE NOT NULL,
    `start_time`            TIME NULL,
    `end_time`              TIME NULL,
    `registration_deadline` DATETIME NULL,
    `max_participants`      INT UNSIGNED NULL COMMENT 'NULL = unlimited',
    `registration_fee`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `organizer_name`        VARCHAR(100) NULL,
    `contact_email`         VARCHAR(150) NULL,
    `contact_phone`         VARCHAR(15)  NULL,
    `banner_image`          VARCHAR(255) NULL,
    `is_featured`           TINYINT(1) NOT NULL DEFAULT 0,
    `status`                ENUM('draft','published','cancelled','completed') NOT NULL DEFAULT 'published',
    `created_by`            INT UNSIGNED NULL,
    `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_events_slug` (`slug`),
    KEY `idx_events_category` (`category_id`),
    KEY `idx_events_date` (`event_date`),
    KEY `idx_events_status` (`status`),
    KEY `idx_events_deadline` (`registration_deadline`),
    CONSTRAINT `fk_events_category`
        FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_events_admin`
        FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. EVENT REGISTRATIONS
-- =====================================================================
CREATE TABLE `event_registrations` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `registration_code` VARCHAR(30) NOT NULL,
    `event_id`          BIGINT UNSIGNED NOT NULL,
    `student_id`        BIGINT UNSIGNED NOT NULL,
    `status`            ENUM('pending','confirmed','waitlisted','cancelled','attended') NOT NULL DEFAULT 'confirmed',
    `team_name`         VARCHAR(100) NULL,
    `notes`             TEXT NULL,
    `registered_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cancelled_at`      DATETIME NULL,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_registrations_code` (`registration_code`),
    UNIQUE KEY `uq_event_registrations_event_student` (`event_id`, `student_id`),
    KEY `idx_event_registrations_student` (`student_id`),
    KEY `idx_event_registrations_event` (`event_id`),
    KEY `idx_event_registrations_status` (`status`),
    CONSTRAINT `fk_event_registrations_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_event_registrations_student`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. TEAM MEMBERS FOR GROUP EVENTS
-- =====================================================================
CREATE TABLE `event_registration_members` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `registration_id` BIGINT UNSIGNED NOT NULL,
    `member_name`     VARCHAR(100) NOT NULL,
    `roll_no`         VARCHAR(20) NULL,
    `email`           VARCHAR(150) NULL,
    `is_leader`       TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_members_registration` (`registration_id`),
    CONSTRAINT `fk_event_members_registration`
        FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 9. NOTICE CATEGORIES / NOTICES
-- =====================================================================
CREATE TABLE `notice_categories` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`      VARCHAR(60) NOT NULL,
    `color`     VARCHAR(20) NOT NULL DEFAULT '#d32f2f',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_notice_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notices` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(200) NOT NULL,
    `content`      TEXT NOT NULL,
    `category_id`  INT UNSIGNED NULL,
    `is_published` TINYINT(1) NOT NULL DEFAULT 1,
    `is_pinned`    TINYINT(1) NOT NULL DEFAULT 0,
    `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`   DATETIME NULL,
    `created_by`   INT UNSIGNED NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notices_category` (`category_id`),
    KEY `idx_notices_published` (`is_published`, `published_at`),
    KEY `idx_notices_pinned` (`is_pinned`),
    CONSTRAINT `fk_notices_category`
        FOREIGN KEY (`category_id`) REFERENCES `notice_categories` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_notices_admin`
        FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 10. FAQ CATEGORIES / FAQS
-- =====================================================================
CREATE TABLE `faq_categories` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`      VARCHAR(60) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_faq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `faqs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question`      VARCHAR(255) NOT NULL,
    `answer`        TEXT NOT NULL,
    `category_id`   INT UNSIGNED NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_faqs_category` (`category_id`),
    KEY `idx_faqs_active_order` (`is_active`, `display_order`),
    CONSTRAINT `fk_faqs_category`
        FOREIGN KEY (`category_id`) REFERENCES `faq_categories` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 11. CONTACT MESSAGES
-- =====================================================================
CREATE TABLE `contact_messages` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `email`       VARCHAR(150) NOT NULL,
    `phone`       VARCHAR(15) NULL,
    `subject`     VARCHAR(200) NOT NULL,
    `message`     TEXT NOT NULL,
    `status`      ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
    `admin_reply` TEXT NULL,
    `replied_by`  INT UNSIGNED NULL,
    `replied_at`  DATETIME NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_status` (`status`),
    KEY `idx_contact_created` (`created_at`),
    CONSTRAINT `fk_contact_admin`
        FOREIGN KEY (`replied_by`) REFERENCES `admins` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 12. SUBJECTS / ATTENDANCE / RESULTS
-- =====================================================================
CREATE TABLE `subjects` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id` INT UNSIGNED NOT NULL,
    `course_id`     INT UNSIGNED NULL,
    `code`          VARCHAR(20) NOT NULL,
    `name`          VARCHAR(120) NOT NULL,
    `semester`      TINYINT UNSIGNED NOT NULL,
    `credits`       TINYINT UNSIGNED NOT NULL DEFAULT 4,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subjects_code` (`code`),
    KEY `idx_subjects_department` (`department_id`),
    KEY `idx_subjects_course` (`course_id`),
    CONSTRAINT `fk_subjects_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_subjects_course`
        FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`      BIGINT UNSIGNED NOT NULL,
    `subject_id`      INT UNSIGNED NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status`          ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    `marked_by`       INT UNSIGNED NULL,
    `remarks`         VARCHAR(255) NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attendance_student_subject_date` (`student_id`, `subject_id`, `attendance_date`),
    KEY `idx_attendance_subject` (`subject_id`),
    KEY `idx_attendance_date` (`attendance_date`),
    CONSTRAINT `fk_attendance_student`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_admin`
        FOREIGN KEY (`marked_by`) REFERENCES `admins` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `results` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`     BIGINT UNSIGNED NOT NULL,
    `subject_id`     INT UNSIGNED NOT NULL,
    `exam_type`      ENUM('internal','midterm','final','practical','assignment') NOT NULL DEFAULT 'final',
    `marks_obtained` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `max_marks`      DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `grade`          VARCHAR(5) NULL,
    `exam_date`      DATE NULL,
    `published_at`   DATETIME NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_results_student_subject_exam` (`student_id`, `subject_id`, `exam_type`),
    KEY `idx_results_subject` (`subject_id`),
    CONSTRAINT `fk_results_student`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_results_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 12b. WEEKLY TIMETABLE
-- =====================================================================
-- One row per class slot. A slot may point at a subject row or carry a
-- free-text title (for labs / non-subject sessions).
CREATE TABLE `timetable` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id` INT UNSIGNED NOT NULL,
    `course_id`     INT UNSIGNED NULL,
    `semester`      TINYINT UNSIGNED NOT NULL,
    `day_of_week`   TINYINT UNSIGNED NOT NULL COMMENT '1=Monday ... 7=Sunday',
    `start_time`    TIME NOT NULL,
    `end_time`      TIME NOT NULL,
    `subject_id`    INT UNSIGNED NULL,
    `title`         VARCHAR(120) NULL,
    `room`          VARCHAR(60) NULL,
    `faculty_name`  VARCHAR(100) NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_timetable_slot` (`department_id`, `semester`, `day_of_week`, `start_time`),
    KEY `idx_timetable_lookup` (`department_id`, `semester`, `day_of_week`, `start_time`),
    KEY `idx_timetable_course` (`course_id`),
    KEY `idx_timetable_subject` (`subject_id`),
    CONSTRAINT `fk_timetable_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_timetable_course`
        FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_timetable_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `chk_timetable_day` CHECK (`day_of_week` BETWEEN 1 AND 7),
    CONSTRAINT `chk_timetable_time` CHECK (`end_time` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 13. SECURITY / AUDIT
-- =====================================================================
CREATE TABLE `login_attempts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`        VARCHAR(150) NOT NULL,
    `ip_address`   VARCHAR(45) NULL,
    `user_agent`   VARCHAR(255) NULL,
    `success`      TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_email` (`email`),
    KEY `idx_login_attempts_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Selector + hashed validator pairs for the optional "remember me" cookie.
-- The raw validator is never stored; only sha256(validator) is kept here.
CREATE TABLE `remember_tokens` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `selector`   CHAR(24) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `user_type`  ENUM('student','admin') NOT NULL,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_remember_selector` (`selector`),
    KEY `idx_remember_user` (`user_type`, `user_id`),
    KEY `idx_remember_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED NULL,
    `student_id`  BIGINT UNSIGNED NULL,
    `action`      VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(60) NULL,
    `entity_id`   BIGINT UNSIGNED NULL,
    `details`     TEXT NULL,
    `ip_address`  VARCHAR(45) NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_admin` (`admin_id`),
    KEY `idx_activity_student` (`student_id`),
    KEY `idx_activity_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 14. SETTINGS
-- =====================================================================
CREATE TABLE `settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(80) NOT NULL,
    `setting_value` TEXT NULL,
    `description`   VARCHAR(255) NULL,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 15. VIEWS
-- =====================================================================
CREATE OR REPLACE VIEW `vw_student_details` AS
SELECT
    s.`id`,
    s.`roll_no`,
    s.`full_name`,
    s.`email`,
    s.`mobile`,
    s.`gender`,
    s.`current_year`,
    s.`current_semester`,
    s.`status`,
    s.`profile_photo`,
    s.`date_of_birth`,
    s.`address`,
    s.`city`,
    s.`state`,
    s.`pincode`,
    s.`admission_date`,
    s.`email_verified_at`,
    s.`last_login_at`,
    s.`created_at`,
    d.`id`   AS `department_id`,
    d.`code` AS `department_code`,
    d.`name` AS `department`,
    c.`id`   AS `course_id`,
    c.`code` AS `course_code`,
    c.`name` AS `course`,
    c.`degree_type`
FROM `students` s
LEFT JOIN `departments` d ON d.`id` = s.`department_id`
LEFT JOIN `courses` c     ON c.`id` = s.`course_id`;

CREATE OR REPLACE VIEW `vw_event_details` AS
SELECT
    e.*,
    ec.`name`  AS `category`,
    ec.`color` AS `category_color`,
    (SELECT COUNT(*) FROM `event_registrations` er
      WHERE er.`event_id` = e.`id`
        AND er.`status` IN ('pending','confirmed','attended')) AS `registered_count`,
    CASE
        WHEN e.`max_participants` IS NULL THEN NULL
        ELSE GREATEST(
            e.`max_participants` -
            (SELECT COUNT(*) FROM `event_registrations` er
              WHERE er.`event_id` = e.`id`
                AND er.`status` IN ('pending','confirmed','attended')),
            0
        )
    END AS `seats_left`
FROM `events` e
LEFT JOIN `event_categories` ec ON ec.`id` = e.`category_id`;

CREATE OR REPLACE VIEW `vw_event_registration_details` AS
SELECT
    er.`id`,
    er.`registration_code`,
    er.`status` AS `registration_status`,
    er.`team_name`,
    er.`notes`,
    er.`registered_at`,
    er.`cancelled_at`,
    e.`id`          AS `event_id`,
    e.`title`       AS `event_title`,
    e.`event_date`,
    e.`start_time`,
    e.`venue`,
    ec.`name`       AS `event_category`,
    s.`id`          AS `student_id`,
    s.`roll_no`,
    s.`full_name`   AS `student_name`,
    s.`email`       AS `student_email`,
    s.`mobile`      AS `student_mobile`,
    d.`name`        AS `department`
FROM `event_registrations` er
JOIN `events` e            ON e.`id` = er.`event_id`
LEFT JOIN `event_categories` ec ON ec.`id` = e.`category_id`
JOIN `students` s          ON s.`id` = er.`student_id`
LEFT JOIN `departments` d  ON d.`id` = s.`department_id`;

CREATE OR REPLACE VIEW `vw_student_dashboard` AS
SELECT
    s.`id`            AS `student_id`,
    s.`roll_no`,
    s.`full_name`,
    s.`email`,
    d.`name`          AS `department`,
    c.`name`          AS `course`,
    s.`current_year`,
    s.`current_semester`,
    (SELECT COUNT(*) FROM `event_registrations` er
      WHERE er.`student_id` = s.`id`
        AND er.`status` IN ('pending','confirmed','attended')) AS `total_events_registered`,
    (SELECT COUNT(*) FROM `attendance` a
      WHERE a.`student_id` = s.`id` AND a.`status` = 'present') AS `classes_present`,
    (SELECT COUNT(*) FROM `attendance` a
      WHERE a.`student_id` = s.`id`) AS `classes_total`,
    (SELECT ROUND(AVG(r.`marks_obtained` / r.`max_marks` * 100), 2)
       FROM `results` r WHERE r.`student_id` = s.`id`) AS `average_percentage`
FROM `students` s
LEFT JOIN `departments` d ON d.`id` = s.`department_id`
LEFT JOIN `courses` c     ON c.`id` = s.`course_id`;

-- =====================================================================
-- 16. SEED DATA
-- =====================================================================

INSERT INTO `departments` (`id`, `code`, `name`, `description`) VALUES
(1, 'CS', 'Computer Science', 'Computer Science and Engineering'),
(2, 'EC', 'Electronics', 'Electronics and Communication Engineering'),
(3, 'ME', 'Mechanical', 'Mechanical Engineering'),
(4, 'CE', 'Civil', 'Civil Engineering'),
(5, 'IT', 'Information Technology', 'Information Technology'),
(6, 'MG', 'Management', 'Management Studies'),
(7, 'AS', 'Applied Sciences', 'Applied Sciences and Humanities');

INSERT INTO `courses`
(`id`, `department_id`, `code`, `name`, `degree_type`, `duration_years`, `total_semesters`) VALUES
(1, 1, 'BTECH-CSE', 'B.Tech Computer Science & Engineering', 'Bachelor', 4, 8),
(2, 1, 'MTECH-CSE', 'M.Tech Computer Science & Engineering', 'Master', 2, 4),
(3, 5, 'BTECH-IT',  'B.Tech Information Technology', 'Bachelor', 4, 8),
(4, 2, 'BTECH-EC',  'B.Tech Electronics & Communication', 'Bachelor', 4, 8),
(5, 3, 'BTECH-ME',  'B.Tech Mechanical Engineering', 'Bachelor', 4, 8),
(6, 4, 'BTECH-CE',  'B.Tech Civil Engineering', 'Bachelor', 4, 8),
(7, 1, 'BCA',       'Bachelor of Computer Applications', 'Bachelor', 3, 6),
(8, 1, 'MCA',       'Master of Computer Applications', 'Master', 2, 4);

-- Staff accounts (no self-registration):
--   Admin   : admin@studenthub.local   / Admin@123
--   Faculty : faculty@studenthub.local / Faculty@123
--   Events  : events@studenthub.local  / Admin@123
INSERT INTO `admins` (`id`, `full_name`, `email`, `password_hash`, `role`) VALUES
(1, 'Portal Administrator', 'admin@studenthub.local',
 '$2y$10$tFXPTd/M9s2C.fozZVVRWuD3hAx/FgBJoDlprHB27s1pNjgcbxlA2', 'super_admin'),
(2, 'Event Manager', 'events@studenthub.local',
 '$2y$10$tFXPTd/M9s2C.fozZVVRWuD3hAx/FgBJoDlprHB27s1pNjgcbxlA2', 'event_manager'),
(3, 'Faculty Coordinator', 'faculty@studenthub.local',
 '$2y$10$5YLdaP1EDai0N3LkjtYtW.Fr7kPEHXX2TKetE9thR9z96n3PX05t.', 'faculty');

-- Password for all seed students: Student@123
INSERT INTO `students`
(`id`, `roll_no`, `full_name`, `email`, `mobile`, `password_hash`, `department_id`, `course_id`,
 `current_year`, `current_semester`, `gender`, `city`, `state`, `admission_date`, `status`) VALUES
(1, 'CS21001', 'Rahul Sharma', 'rahul.sharma@charusat.edu', '9876500001',
 '$2y$10$bkT.n1lCwUAMNLYii1ibfeHTCZHsxbFQzFFZgnBGYSb//V7EL.8ei', 1, 1, 3, 5, 'Male', 'Changa', 'Gujarat', '2021-07-01', 'active'),
(2, 'EC21002', 'Priya Patel', 'priya.patel@charusat.edu', '9876500002',
 '$2y$10$bkT.n1lCwUAMNLYii1ibfeHTCZHsxbFQzFFZgnBGYSb//V7EL.8ei', 2, 4, 3, 5, 'Female', 'Anand', 'Gujarat', '2021-07-01', 'active'),
(3, 'ME21003', 'Amit Kumar', 'amit.kumar@charusat.edu', '9876500003',
 '$2y$10$bkT.n1lCwUAMNLYii1ibfeHTCZHsxbFQzFFZgnBGYSb//V7EL.8ei', 3, 5, 2, 3, 'Male', 'Rajkot', 'Gujarat', '2022-07-01', 'active'),
(4, 'CS21004', 'Sneha Desai', 'sneha.desai@charusat.edu', '9876500004',
 '$2y$10$bkT.n1lCwUAMNLYii1ibfeHTCZHsxbFQzFFZgnBGYSb//V7EL.8ei', 1, 1, 2, 3, 'Female', 'Vadodara', 'Gujarat', '2022-07-01', 'active'),
(5, 'CE21005', 'Vijay Singh', 'vijay.singh@charusat.edu', '9876500005',
 '$2y$10$bkT.n1lCwUAMNLYii1ibfeHTCZHsxbFQzFFZgnBGYSb//V7EL.8ei', 4, 6, 4, 7, 'Male', 'Ahmedabad', 'Gujarat', '2020-07-01', 'active'),
(6, 'EC21006', 'Neha Joshi', 'neha.joshi@charusat.edu', '9876500006',
 '$2y$10$bkT.n1lCwUAMNLYii1ibfeHTCZHsxbFQzFFZgnBGYSb//V7EL.8ei', 2, 4, 4, 7, 'Female', 'Surat', 'Gujarat', '2020-07-01', 'active'),
(7, 'ME21007', 'Rohit Mehta', 'rohit.mehta@charusat.edu', '9876500007',
 '$2y$10$bkT.n1lCwUAMNLYii1ibfeHTCZHsxbFQzFFZgnBGYSb//V7EL.8ei', 3, 5, 1, 1, 'Male', 'Jamnagar', 'Gujarat', '2023-07-01', 'active'),
(8, 'CS21008', 'Anita Shah', 'anita.shah@charusat.edu', '9876500008',
 '$2y$10$bkT.n1lCwUAMNLYii1ibfeHTCZHsxbFQzFFZgnBGYSb//V7EL.8ei', 1, 1, 1, 1, 'Female', 'Nadiad', 'Gujarat', '2023-07-01', 'active'),
(9, 'STU0009', 'Zeel Gadara', 'zeel@gmail.com', '9875643210',
 '$2y$10$bkT.n1lCwUAMNLYii1ibfeHTCZHsxbFQzFFZgnBGYSb//V7EL.8ei', 1, 1, 2, 3, 'Male', 'Rajkot', 'Gujarat', '2022-07-01', 'active');

INSERT INTO `event_categories` (`id`, `name`, `color`, `icon`) VALUES
(1, 'Technical', '#0d47a1', '💻'),
(2, 'Cultural',  '#d32f2f', '🎭'),
(3, 'Sports',    '#388e3c', '🏆'),
(4, 'Workshop',  '#f57c00', '🛠️'),
(5, 'Seminar',   '#6a1b9a', '🎤');

INSERT INTO `events`
(`id`, `title`, `slug`, `description`, `category_id`, `venue`, `event_date`, `start_time`, `end_time`,
 `registration_deadline`, `max_participants`, `registration_fee`, `organizer_name`, `contact_email`, `status`, `created_by`) VALUES
(1,  'Coding Competition',        'coding-competition',        'Test your coding skills in this exciting competition with prizes for winners.', 1, 'Computer Lab',       '2026-08-12', '10:00:00', '13:00:00', '2026-08-10 23:59:59', 100, 0.00, 'Coding Club',       'events@studenthub.local', 'completed', 2),
(2,  'Sports Day',                'sports-day',                'Annual sports event with various competitions and exciting prizes.',            3, 'University Ground',  '2026-08-20', '08:00:00', '17:00:00', '2026-08-18 23:59:59', 300, 0.00, 'Sports Committee',  'events@studenthub.local', 'completed', 2),
(3,  'Cultural Fest',             'cultural-fest',             'Celebration of arts, music, dance and culture with student performances.',      2, 'Auditorium',         '2026-08-25', '11:00:00', '18:00:00', '2026-08-22 23:59:59', 250, 0.00, 'Cultural Committee','events@studenthub.local', 'completed', 2),
(4,  'Web Development Workshop',  'web-development-workshop',  'Hands-on workshop covering HTML, CSS, JavaScript and PHP fundamentals.',         4, 'Computer Lab',       '2026-08-28', '09:30:00', '16:30:00', '2026-08-26 23:59:59', 60,  0.00, 'Web Club',          'events@studenthub.local', 'completed', 2),
(5,  'Hackathon',                 'hackathon',                 '24-hour team hackathon to build innovative solutions for real problems.',        1, 'Innovation Lab',     '2026-09-02', '09:00:00', '09:00:00', '2026-08-30 23:59:59', 120, 100.00, 'Innovation Cell',   'events@studenthub.local', 'completed', 2),
(6,  'Football Tournament',       'football-tournament',       'Inter-department football tournament played on the university ground.',          3, 'Football Ground',    '2026-09-10', '07:30:00', '12:00:00', '2026-09-08 23:59:59', 80,  0.00, 'Sports Committee',  'events@studenthub.local', 'completed', 2),
(7,  'Garba Night',               'garba-night',               'Traditional Garba evening with live music and student performances.',            2, 'Open Ground',        '2026-09-15', '18:00:00', '23:00:00', '2026-09-13 23:59:59', 500, 0.00, 'Cultural Committee','events@studenthub.local', 'completed', 2),
(8,  'Cyber Security Workshop',   'cyber-security-workshop',   'Learn ethical hacking basics, secure coding and cyber hygiene.',                  4, 'Computer Lab',       '2026-09-20', '10:00:00', '16:00:00', '2026-09-18 23:59:59', 60,  0.00, 'Cyber Club',        'events@studenthub.local', 'completed', 2),
(9,  'AI Seminar',                'ai-seminar',                'Industry experts discuss the latest trends in Artificial Intelligence.',        5, 'Seminar Hall',       '2026-09-25', '11:00:00', '13:00:00', '2026-09-23 23:59:59', 200, 0.00, 'AI Research Cell',  'events@studenthub.local', 'completed', 2),
(10, 'Cricket Tournament',        'cricket-tournament',        'Inter-college cricket tournament with exciting knockout matches.',               3, 'Cricket Ground',     '2026-09-28', '08:00:00', '17:00:00', '2026-09-25 23:59:59', 120, 0.00, 'Sports Committee',  'events@studenthub.local', 'completed', 2),
(11, 'Dance Competition',         'dance-competition',         'Solo and group dance competition featuring the best campus talent.',             2, 'Auditorium',         '2026-10-02', '17:00:00', '21:00:00', '2026-09-30 23:59:59', 150, 0.00, 'Cultural Committee','events@studenthub.local', 'completed', 2),
(12, 'Git Workshop',              'git-workshop',              'Version control essentials: branches, pull requests and collaboration.',         4, 'Computer Lab',       '2026-10-05', '10:00:00', '13:00:00', '2026-10-03 23:59:59', 50,  0.00, 'Web Club',          'events@studenthub.local', 'completed', 2),
(13, 'Cloud Computing Seminar',   'cloud-computing-seminar',   'Cloud platforms, deployment models and career opportunities.',                   5, 'Seminar Hall',       '2026-10-08', '11:00:00', '13:00:00', '2026-10-06 23:59:59', 200, 0.00, 'Cloud Club',        'events@studenthub.local', 'completed', 2),
(14, 'Badminton Tournament',      'badminton-tournament',      'Singles and doubles badminton tournament for all departments.',                  3, 'Sports Complex',     '2026-10-10', '09:00:00', '16:00:00', '2026-10-08 23:59:59', 64,  0.00, 'Sports Committee',  'events@studenthub.local', 'completed', 2),
(15, 'Photography Competition',   'photography-competition',   'Showcase your photography skills across themes like campus life and nature.',    2, 'Student Center',     '2026-10-15', '10:00:00', '17:00:00', '2026-10-13 23:59:59', 80,  0.00, 'Photography Club',  'events@studenthub.local', 'completed', 2),
(16, 'Annual Tech Fest',          'annual-tech-fest',          'Flagship technology festival with competitions, exhibitions and guest talks.',    1, 'Main Auditorium',    '2026-11-15', '09:00:00', '18:00:00', '2026-11-12 23:59:59', 400, 0.00, 'Tech Committee',    'events@studenthub.local', 'published', 2),
(17, 'Inter-College Sports Meet', 'inter-college-sports-meet', 'Athletics, team sports and individual events across partner colleges.',           3, 'University Ground',  '2026-11-22', '07:00:00', '17:00:00', '2026-11-19 23:59:59', 500, 0.00, 'Sports Committee',  'events@studenthub.local', 'published', 2),
(18, 'AI & Robotics Workshop',    'ai-robotics-workshop',     'Build and program a simple robot while learning AI fundamentals.',               4, 'Innovation Lab',     '2026-12-05', '09:30:00', '16:30:00', '2026-12-02 23:59:59', 60,  250.00, 'Innovation Cell',  'events@studenthub.local', 'published', 2),
(19, 'Entrepreneurship Seminar',  'entrepreneurship-seminar', 'Startup founders share their journey and practical business advice.',             5, 'Seminar Hall',       '2026-12-12', '11:00:00', '13:30:00', '2026-12-10 23:59:59', 200, 0.00, 'E-Cell',            'events@studenthub.local', 'published', 2),
(20, 'Winter Cultural Night',     'winter-cultural-night',    'Music, dance and drama performances to close the academic year.',                 2, 'Open Ground',        '2026-12-20', '18:00:00', '23:00:00', '2026-12-17 23:59:59', 600, 0.00, 'Cultural Committee','events@studenthub.local', 'published', 2),
(21, 'National Hackathon',        'national-hackathon',       'National-level 36-hour hackathon with exciting prizes and mentorship.',          1, 'Innovation Lab',     '2027-01-10', '09:00:00', '21:00:00', '2027-01-05 23:59:59', 200, 150.00, 'Innovation Cell',  'events@studenthub.local', 'published', 2);

INSERT INTO `event_registrations`
(`id`, `registration_code`, `event_id`, `student_id`, `status`, `team_name`, `registered_at`) VALUES
(1, 'REG-2026-0001', 16, 1, 'confirmed', NULL, '2026-10-20 10:15:00'),
(2, 'REG-2026-0002', 16, 2, 'confirmed', NULL, '2026-10-20 11:30:00'),
(3, 'REG-2026-0003', 16, 4, 'confirmed', NULL, '2026-10-21 09:05:00'),
(4, 'REG-2026-0004', 17, 1, 'confirmed', NULL, '2026-10-21 14:45:00'),
(5, 'REG-2026-0005', 18, 3, 'pending',   NULL, '2026-10-22 16:20:00'),
(6, 'REG-2026-0006', 5,  1, 'attended',  'Code Warriors', '2026-08-28 12:00:00'),
(7, 'REG-2026-0007', 2,  5, 'attended',  NULL, '2026-08-15 09:00:00');

INSERT INTO `event_registration_members`
(`registration_id`, `member_name`, `roll_no`, `email`, `is_leader`) VALUES
(6, 'Rahul Sharma', 'CS21001', 'rahul.sharma@charusat.edu', 1),
(6, 'Sneha Desai',  'CS21004', 'sneha.desai@charusat.edu', 0),
(6, 'Anita Shah',   'CS21008', 'anita.shah@charusat.edu', 0);

INSERT INTO `notice_categories` (`id`, `name`, `color`) VALUES
(1, 'Examination', '#d32f2f'),
(2, 'Fee',         '#f57c00'),
(3, 'Workshop',    '#0d47a1'),
(4, 'Holiday',     '#388e3c'),
(5, 'Sports',      '#6a1b9a'),
(6, 'Scholarship', '#00838f'),
(7, 'General',     '#455a64');

INSERT INTO `notices`
(`id`, `title`, `content`, `category_id`, `is_pinned`, `published_at`, `expires_at`, `created_by`) VALUES
(1, 'Semester Exam Schedule Released',
 'The semester examination schedule has been published. Students can download the detailed timetable from the examination section. Exams begin on 1 October 2026.',
 1, 1, '2026-09-25 10:00:00', '2026-10-31 23:59:59', 1),
(2, 'Fee Payment Deadline',
 'The last date for semester fee payment is 5 October 2026. Late payment will attract a fine of Rs. 500. Pay online through the student portal.',
 2, 1, '2026-09-26 09:30:00', '2026-10-05 23:59:59', 1),
(3, 'Workshop on AI and ML',
 'A two-day workshop on Artificial Intelligence and Machine Learning will be held on 8-9 October 2026 in the Seminar Hall. Registration is free for all students.',
 3, 0, '2026-09-28 11:00:00', '2026-10-09 23:59:59', 2),
(4, 'Holiday Notice - Diwali',
 'The university will remain closed from 10 to 12 October 2026 on account of Diwali. Hostel and library services will also be closed.',
 4, 0, '2026-10-01 08:00:00', '2026-10-13 23:59:59', 1),
(5, 'Sports Day Registration Open',
 'Registration for the annual Sports Day is now open. Students can register for athletics, football, cricket, badminton and more through the Events page.',
 5, 0, '2026-10-03 10:00:00', '2026-10-18 23:59:59', 2),
(6, 'Scholarship Application Reminder',
 'Merit and need-based scholarship applications close on 18 October 2026. Submit all supporting documents to the accounts office before the deadline.',
 6, 0, '2026-10-05 12:00:00', '2026-10-18 23:59:59', 1),
(7, 'Annual Tech Fest 2026 - Registrations Open',
 'Register now for the Annual Tech Fest on 15 November 2026. Coding, robotics, project exhibitions and guest talks are part of the event.',
 3, 1, '2026-10-20 09:00:00', '2026-11-12 23:59:59', 2),
(8, 'National Hackathon Announcement',
 'The National Hackathon will be held on 10 January 2027. Teams of 2-4 students can register through the Events page. Exciting prizes await the winners.',
 3, 0, '2026-10-25 10:00:00', '2027-01-05 23:59:59', 2);

INSERT INTO `faq_categories` (`id`, `name`) VALUES
(1, 'Attendance'),
(2, 'Results'),
(3, 'Events'),
(4, 'Fee'),
(5, 'General');

INSERT INTO `faqs` (`id`, `question`, `answer`, `category_id`, `display_order`) VALUES
(1, 'How do I check my attendance?', 'Go to the Attendance page from the menu to view your subject-wise attendance and overall percentage.', 1, 1),
(2, 'Where can I see my results?', 'You can view your internal, midterm and final results on the Results page. Results are published semester-wise.', 2, 2),
(3, 'How do I register for events?', 'Visit the All Events page, choose an event and click the Register button. Enter your roll number to confirm your participation.', 3, 3),
(4, 'What is the fee payment process?', 'Fee payment can be done online through the student portal using UPI, net banking or debit card. Keep the receipt for future reference.', 4, 4),
(5, 'How do I apply for leave?', 'Submit a leave application through the student portal with proper documentation. Your class teacher will approve or reject the request.', 5, 5),
(6, 'Can I update my profile details?', 'Yes. Open My Profile from the menu and update your contact details. Roll number, department and course changes must be requested from the administration.', 5, 6),
(7, 'How do I cancel an event registration?', 'Open the event registration details and cancel before the registration deadline. Cancelled seats are offered to waitlisted students.', 3, 7);

INSERT INTO `contact_messages`
(`name`, `email`, `phone`, `subject`, `message`, `status`) VALUES
('Test User', 'test.user@example.com', '9876500099', 'Query about event registration',
 'I would like to know more about the registration process for the Annual Tech Fest.', 'new');

INSERT INTO `subjects`
(`id`, `department_id`, `course_id`, `code`, `name`, `semester`, `credits`) VALUES
(1, 1, 1, 'CS501', 'Web Development', 5, 4),
(2, 1, 1, 'CS502', 'Database Management Systems', 5, 4),
(3, 1, 1, 'CS503', 'Operating Systems', 5, 4),
(4, 1, 1, 'CS504', 'Computer Networks', 5, 4),
(5, 1, 1, 'CS301', 'Data Structures', 3, 4),
(6, 1, 1, 'CS302', 'Object Oriented Programming', 3, 4),
(7, 2, 4, 'EC501', 'Digital Signal Processing', 5, 4),
(8, 3, 5, 'ME301', 'Thermodynamics', 3, 4),
(9, 4, 6, 'CE701', 'Structural Design', 7, 4),
(10, 5, 3, 'IT301', 'Java Programming', 3, 4);

INSERT INTO `attendance`
(`student_id`, `subject_id`, `attendance_date`, `status`, `marked_by`, `remarks`) VALUES
(1, 1, '2026-10-01', 'present', 1, NULL),
(1, 1, '2026-10-02', 'present', 1, NULL),
(1, 1, '2026-10-03', 'absent',  1, 'Medical leave'),
(1, 2, '2026-10-01', 'present', 1, NULL),
(1, 2, '2026-10-02', 'late',    1, 'Arrived 10 minutes late'),
(1, 3, '2026-10-01', 'present', 1, NULL),
(1, 3, '2026-10-02', 'present', 1, NULL),
(2, 7, '2026-10-01', 'present', 1, NULL),
(2, 7, '2026-10-02', 'present', 1, NULL),
(3, 8, '2026-10-01', 'absent',  1, 'Family function');

INSERT INTO `results`
(`student_id`, `subject_id`, `exam_type`, `marks_obtained`, `max_marks`, `grade`, `exam_date`, `published_at`) VALUES
(1, 1, 'internal', 28.00, 30.00, 'A+', '2026-09-15', '2026-09-20 10:00:00'),
(1, 2, 'internal', 26.00, 30.00, 'A',  '2026-09-16', '2026-09-20 10:00:00'),
(1, 3, 'internal', 24.00, 30.00, 'B+', '2026-09-17', '2026-09-20 10:00:00'),
(1, 1, 'final',    88.00, 100.00, 'A', '2026-10-15', '2026-10-20 10:00:00'),
(1, 2, 'final',    91.00, 100.00, 'A+', '2026-10-16', '2026-10-20 10:00:00'),
(2, 7, 'internal', 27.00, 30.00, 'A+', '2026-09-15', '2026-09-20 10:00:00');

-- Weekly timetable slots. Department + semester identify a class group.
INSERT INTO `timetable`
(`department_id`, `course_id`, `semester`, `day_of_week`, `start_time`, `end_time`,
 `subject_id`, `title`, `room`, `faculty_name`) VALUES
-- CS, semester 5 (subject rows 1-4)
(1, 1, 5, 1, '09:00:00', '10:00:00', 1, NULL, 'Room 201', 'Prof. R. Shah'),
(1, 1, 5, 1, '10:00:00', '11:00:00', 2, NULL, 'Room 201', 'Prof. M. Patel'),
(1, 1, 5, 1, '11:15:00', '12:15:00', 3, NULL, 'Room 202', 'Prof. A. Desai'),
(1, 1, 5, 1, '13:15:00', '14:15:00', 4, NULL, 'Room 202', 'Prof. K. Mehta'),
(1, 1, 5, 2, '09:00:00', '10:00:00', 2, NULL, 'Room 201', 'Prof. M. Patel'),
(1, 1, 5, 2, '10:00:00', '11:00:00', 1, NULL, 'Room 201', 'Prof. R. Shah'),
(1, 1, 5, 2, '11:15:00', '12:15:00', 4, NULL, 'Room 202', 'Prof. K. Mehta'),
(1, 1, 5, 2, '13:15:00', '14:15:00', 3, NULL, 'Room 202', 'Prof. A. Desai'),
(1, 1, 5, 3, '09:00:00', '10:00:00', 3, NULL, 'Room 202', 'Prof. A. Desai'),
(1, 1, 5, 3, '10:00:00', '11:00:00', 4, NULL, 'Room 202', 'Prof. K. Mehta'),
(1, 1, 5, 3, '11:15:00', '12:15:00', 1, NULL, 'Room 201', 'Prof. R. Shah'),
(1, 1, 5, 3, '13:15:00', '14:15:00', 2, NULL, 'Room 201', 'Prof. M. Patel'),
(1, 1, 5, 4, '09:00:00', '10:00:00', 4, NULL, 'Room 202', 'Prof. K. Mehta'),
(1, 1, 5, 4, '10:00:00', '11:00:00', 3, NULL, 'Room 202', 'Prof. A. Desai'),
(1, 1, 5, 4, '11:15:00', '12:15:00', 2, NULL, 'Room 201', 'Prof. M. Patel'),
(1, 1, 5, 4, '13:15:00', '14:15:00', 1, NULL, 'Room 201', 'Prof. R. Shah'),
-- CS, semester 3 (subject rows 5-6)
(1, 1, 3, 1, '09:00:00', '10:00:00', 5, NULL, 'Room 105', 'Prof. S. Joshi'),
(1, 1, 3, 1, '10:00:00', '11:00:00', 6, NULL, 'Room 105', 'Prof. P. Trivedi'),
(1, 1, 3, 1, '11:15:00', '12:15:00', 5, 'Data Structures Lab', 'CS Lab 2', 'Prof. S. Joshi'),
(1, 1, 3, 1, '13:15:00', '14:15:00', 6, 'OOP Lab', 'CS Lab 1', 'Prof. P. Trivedi'),
(1, 1, 3, 2, '09:00:00', '10:00:00', 6, NULL, 'Room 105', 'Prof. P. Trivedi'),
(1, 1, 3, 2, '10:00:00', '11:00:00', 5, NULL, 'Room 105', 'Prof. S. Joshi'),
(1, 1, 3, 2, '11:15:00', '12:15:00', 6, 'OOP Lab', 'CS Lab 1', 'Prof. P. Trivedi'),
(1, 1, 3, 2, '13:15:00', '14:15:00', 5, 'Data Structures Lab', 'CS Lab 2', 'Prof. S. Joshi'),
(1, 1, 3, 3, '09:00:00', '10:00:00', 5, NULL, 'Room 105', 'Prof. S. Joshi'),
(1, 1, 3, 3, '10:00:00', '11:00:00', 6, NULL, 'Room 105', 'Prof. P. Trivedi'),
(1, 1, 3, 3, '11:15:00', '12:15:00', 5, 'Data Structures Lab', 'CS Lab 2', 'Prof. S. Joshi'),
(1, 1, 3, 3, '13:15:00', '14:15:00', 6, 'OOP Lab', 'CS Lab 1', 'Prof. P. Trivedi'),
(1, 1, 3, 4, '09:00:00', '10:00:00', 6, NULL, 'Room 105', 'Prof. P. Trivedi'),
(1, 1, 3, 4, '10:00:00', '11:00:00', 5, NULL, 'Room 105', 'Prof. S. Joshi'),
(1, 1, 3, 4, '11:15:00', '12:15:00', 6, 'OOP Lab', 'CS Lab 1', 'Prof. P. Trivedi'),
(1, 1, 3, 4, '13:15:00', '14:15:00', 5, 'Data Structures Lab', 'CS Lab 2', 'Prof. S. Joshi'),
-- EC, semester 5
(2, 4, 5, 1, '09:00:00', '10:00:00', 7, NULL, 'EC Room 11', 'Prof. N. Bhatt'),
(2, 4, 5, 1, '10:00:00', '11:00:00', NULL, 'Communication Systems', 'EC Room 11', 'Prof. D. Vyas'),
(2, 4, 5, 1, '11:15:00', '12:15:00', NULL, 'Electronics Lab', 'EC Lab', 'Prof. N. Bhatt'),
(2, 4, 5, 2, '09:00:00', '10:00:00', NULL, 'Microprocessors', 'EC Room 12', 'Prof. H. Rana'),
(2, 4, 5, 2, '10:00:00', '11:00:00', 7, NULL, 'EC Room 11', 'Prof. N. Bhatt'),
(2, 4, 5, 2, '11:15:00', '12:15:00', NULL, 'Electronics Lab', 'EC Lab', 'Prof. N. Bhatt'),
-- ME, semester 3
(3, 5, 3, 1, '09:00:00', '10:00:00', 8, NULL, 'ME Room 5', 'Prof. V. Chauhan'),
(3, 5, 3, 1, '10:00:00', '11:00:00', NULL, 'Fluid Mechanics', 'ME Room 5', 'Prof. B. Solanki'),
(3, 5, 3, 1, '11:15:00', '12:15:00', NULL, 'Thermal Lab', 'Thermal Lab', 'Prof. V. Chauhan'),
(3, 5, 3, 2, '09:00:00', '10:00:00', NULL, 'Strength of Materials', 'ME Room 6', 'Prof. R. Pandya'),
(3, 5, 3, 2, '10:00:00', '11:00:00', 8, NULL, 'ME Room 5', 'Prof. V. Chauhan'),
(3, 5, 3, 2, '11:15:00', '12:15:00', NULL, 'Thermal Lab', 'Thermal Lab', 'Prof. V. Chauhan'),
-- CE, semester 7
(4, 6, 7, 1, '09:00:00', '10:00:00', 9, NULL, 'CE Room 3', 'Prof. J. Amin'),
(4, 6, 7, 1, '10:00:00', '11:00:00', NULL, 'Geotechnical Engineering', 'CE Room 3', 'Prof. T. Modi'),
(4, 6, 7, 1, '11:15:00', '12:15:00', NULL, 'Structural Lab', 'CE Lab', 'Prof. J. Amin'),
(4, 6, 7, 2, '09:00:00', '10:00:00', NULL, 'Transportation Engineering', 'CE Room 4', 'Prof. P. Dave'),
(4, 6, 7, 2, '10:00:00', '11:00:00', 9, NULL, 'CE Room 3', 'Prof. J. Amin'),
(4, 6, 7, 2, '11:15:00', '12:15:00', NULL, 'Structural Lab', 'CE Lab', 'Prof. J. Amin'),
-- CS, semester 1
(1, 1, 1, 1, '09:00:00', '10:00:00', NULL, 'Engineering Mathematics I', 'Room 101', 'Prof. A. Joshi'),
(1, 1, 1, 1, '10:00:00', '11:00:00', NULL, 'Programming Fundamentals', 'Room 101', 'Prof. R. Shah'),
(1, 1, 1, 1, '11:15:00', '12:15:00', NULL, 'Physics Lab', 'Physics Lab', 'Prof. S. Patel'),
(1, 1, 1, 2, '09:00:00', '10:00:00', NULL, 'Communication Skills', 'Room 102', 'Prof. M. Shah'),
(1, 1, 1, 2, '10:00:00', '11:00:00', NULL, 'Engineering Mathematics I', 'Room 101', 'Prof. A. Joshi'),
(1, 1, 1, 2, '11:15:00', '12:15:00', NULL, 'Programming Lab', 'CS Lab 1', 'Prof. R. Shah'),
-- EC, semester 7
(2, 4, 7, 1, '09:00:00', '10:00:00', NULL, 'Wireless Communication', 'EC Room 21', 'Prof. D. Vyas'),
(2, 4, 7, 1, '10:00:00', '11:00:00', NULL, 'VLSI Design', 'EC Room 21', 'Prof. H. Rana'),
(2, 4, 7, 1, '11:15:00', '12:15:00', NULL, 'EC Lab', 'EC Lab', 'Prof. N. Bhatt'),
(2, 4, 7, 2, '09:00:00', '10:00:00', NULL, 'Embedded Systems', 'EC Room 22', 'Prof. H. Rana'),
(2, 4, 7, 2, '10:00:00', '11:00:00', NULL, 'Wireless Communication', 'EC Room 21', 'Prof. D. Vyas'),
(2, 4, 7, 2, '11:15:00', '12:15:00', NULL, 'EC Lab', 'EC Lab', 'Prof. N. Bhatt'),
-- ME, semester 1
(3, 5, 1, 1, '09:00:00', '10:00:00', NULL, 'Engineering Mathematics I', 'ME Room 1', 'Prof. A. Joshi'),
(3, 5, 1, 1, '10:00:00', '11:00:00', NULL, 'Engineering Graphics', 'Drawing Hall', 'Prof. K. Patel'),
(3, 5, 1, 1, '11:15:00', '12:15:00', NULL, 'Workshop Practice', 'Workshop', 'Prof. B. Solanki'),
(3, 5, 1, 2, '09:00:00', '10:00:00', NULL, 'Engineering Physics', 'ME Room 1', 'Prof. S. Patel'),
(3, 5, 1, 2, '10:00:00', '11:00:00', NULL, 'Engineering Mathematics I', 'ME Room 1', 'Prof. A. Joshi'),
(3, 5, 1, 2, '11:15:00', '12:15:00', NULL, 'Workshop Practice', 'Workshop', 'Prof. B. Solanki');

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_name', 'StudentHub Portal', 'Name shown across the portal'),
('support_email', 'zeelgadara05@gmail.com', 'Support contact email'),
('support_phone', '+91 9998158131', 'Support contact phone'),
('academic_year', '2026-2027', 'Current academic year'),
('current_semester', 'Odd', 'Current semester type'),
('registration_open', '1', 'Allow new student registrations'),
('event_registration_open', '1', 'Allow event registrations'),
('college_name', 'CHARUSAT University', 'Institution name'),
('college_address', 'Changa, Gujarat, India', 'Institution address');

-- =====================================================================
-- END OF SCHEMA
-- =====================================================================
