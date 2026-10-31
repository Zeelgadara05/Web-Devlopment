-- =====================================================================
-- StudentHub Portal - upgrade for the login-gated portal
-- -------------------------------------------------------
-- Safe to run on an existing studenthub database. It:
--   1. adds the weekly timetable table,
--   2. adds the Faculty staff account,
--   3. seeds a demo timetable (existing slots are left untouched).
--
-- Import with:
--   C:\xampp\mysql\bin\mysql.exe -u root < database\portal_upgrade.sql
-- =====================================================================

USE `studenthub`;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `timetable` (
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

-- Faculty login: faculty@studenthub.local / Faculty@123
INSERT IGNORE INTO `admins` (`full_name`, `email`, `password_hash`, `role`, `status`) VALUES
('Faculty Coordinator', 'faculty@studenthub.local',
 '$2y$10$5YLdaP1EDai0N3LkjtYtW.Fr7kPEHXX2TKetE9thR9z96n3PX05t.', 'faculty', 'active');

INSERT IGNORE INTO `timetable`
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

SET FOREIGN_KEY_CHECKS = 1;
