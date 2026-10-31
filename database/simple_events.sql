-- Simple Events Fetch API demo
-- Safe to run more than once: the table is created only when missing and the
-- three sample rows use INSERT IGNORE.
--
-- Import from the project root:
--   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database/simple_events.sql

USE studenthub;

CREATE TABLE IF NOT EXISTS `simple_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL,
  `venue` VARCHAR(150) NOT NULL,
  `event_date` DATE NOT NULL,
  `status` ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `description` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_simple_events_date` (`event_date`),
  KEY `idx_simple_events_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `simple_events`
  (`id`, `title`, `venue`, `event_date`, `status`, `description`)
VALUES
  (1, 'Orientation Day', 'Main Auditorium', '2026-11-12', 'scheduled', 'Welcome session for first-year students.'),
  (2, 'Mini Hackathon', 'Computer Lab 2', '2026-11-20', 'scheduled', 'A short team coding challenge for all departments.'),
  (3, 'Sports Meet', 'College Ground', '2026-10-05', 'completed', 'Annual inter-department sports day.');
