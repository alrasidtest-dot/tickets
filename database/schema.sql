-- schema.sql — IT Helpdesk Ticketing System
-- Creates all tables defined in docs/DATABASE.md + required seed data.
-- Charset: utf8mb4 everywhere.

CREATE DATABASE IF NOT EXISTS `it_helpdesk`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `it_helpdesk`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- departments
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id`      INT NOT NULL AUTO_INCREMENT,
  `name_ar` VARCHAR(100) NOT NULL,
  `name_en` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `employee_id`   VARCHAR(50) NOT NULL,
  `full_name`     VARCHAR(100) NOT NULL,
  `email`         VARCHAR(100) NOT NULL,
  `role`          ENUM('employee','agent','admin') NOT NULL,
  `department_id` INT NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_employee_id` (`employee_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  CONSTRAINT `fk_users_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ticket_categories
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_categories`;
CREATE TABLE `ticket_categories` (
  `id`        INT NOT NULL AUTO_INCREMENT,
  `name_ar`   VARCHAR(100) NOT NULL,
  `name_en`   VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ticket_priorities
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_priorities`;
CREATE TABLE `ticket_priorities` (
  `id`        INT NOT NULL AUTO_INCREMENT,
  `name_ar`   VARCHAR(50) NOT NULL,
  `name_en`   VARCHAR(50) NOT NULL,
  `level`     INT NOT NULL,          -- 1=urgent, 2=medium, 3=low
  `sla_hours` INT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ticket_statuses
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_statuses`;
CREATE TABLE `ticket_statuses` (
  `id`      INT NOT NULL AUTO_INCREMENT,
  `code`    VARCHAR(20) NOT NULL,    -- new/in_progress/on_hold/resolved/closed
  `name_ar` VARCHAR(50) NOT NULL,
  `name_en` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_statuses_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- tickets
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(20) NOT NULL,   -- format TCK-YYYYMMDD-XXXX
  `title`         VARCHAR(200) NOT NULL,
  `description`   TEXT NOT NULL,
  `category_id`   INT NOT NULL,
  `priority_id`   INT NOT NULL,
  `status_id`     INT NOT NULL DEFAULT 1, -- default = new (seeded as id=1)
  `created_by`    INT NOT NULL,
  `assigned_to`   INT NULL,
  `created_at`    DATETIME NOT NULL,
  `updated_at`    DATETIME NOT NULL,
  `closed_at`     DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_number` (`ticket_number`),
  KEY `idx_tickets_status` (`status_id`),
  KEY `idx_tickets_assigned` (`assigned_to`),
  KEY `idx_tickets_created_by` (`created_by`),
  KEY `idx_tickets_category` (`category_id`),
  -- RESTRICT: block deleting a category still referenced by any ticket.
  CONSTRAINT `fk_tickets_category`
    FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`)
    ON DELETE RESTRICT,
  -- RESTRICT: block deleting a priority still referenced by any ticket.
  CONSTRAINT `fk_tickets_priority`
    FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`)
    ON DELETE RESTRICT,
  -- RESTRICT: block deleting a status still referenced by any ticket.
  CONSTRAINT `fk_tickets_status`
    FOREIGN KEY (`status_id`) REFERENCES `ticket_statuses` (`id`)
    ON DELETE RESTRICT,
  CONSTRAINT `fk_tickets_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_tickets_assigned_to`
    FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ticket_comments
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_comments`;
CREATE TABLE `ticket_comments` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `ticket_id`  INT NOT NULL,
  `user_id`    INT NOT NULL,
  `comment`    TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comments_ticket` (`ticket_id`),
  CONSTRAINT `fk_comments_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ticket_attachments
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_attachments`;
CREATE TABLE `ticket_attachments` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `ticket_id`   INT NOT NULL,
  `comment_id`  INT NULL,
  `file_name`   VARCHAR(255) NOT NULL,
  `file_path`   VARCHAR(255) NOT NULL,
  `file_size`   INT NOT NULL,
  `uploaded_by` INT NOT NULL,
  `uploaded_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_attachments_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attachments_comment`
    FOREIGN KEY (`comment_id`) REFERENCES `ticket_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attachments_user`
    FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ticket_history
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `ticket_history`;
CREATE TABLE `ticket_history` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `ticket_id`  INT NOT NULL,
  `user_id`    INT NOT NULL,
  `action`     VARCHAR(50) NOT NULL,    -- create/status_change/assign/comment
  `old_value`  VARCHAR(100) NULL,
  `new_value`  VARCHAR(100) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_history_ticket` (`ticket_id`),
  CONSTRAINT `fk_history_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- notifications
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT NOT NULL,
  `ticket_id`   INT NOT NULL,
  `message_key` VARCHAR(50) NOT NULL,    -- translation key, e.g. notif_status_changed
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `email_sent`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`, `is_read`),
  CONSTRAINT `fk_notifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================================================
-- Seed data
-- ===========================================================================

-- ticket_statuses (new MUST be id=1 to match tickets.status_id DEFAULT 1)
INSERT INTO `ticket_statuses` (`id`, `code`, `name_ar`, `name_en`) VALUES
  (1, 'new',         'جديدة',        'New'),
  (2, 'in_progress', 'قيد المعالجة', 'In Progress'),
  (3, 'on_hold',     'معلّقة',       'On Hold'),
  (4, 'resolved',    'تم الحل',      'Resolved'),
  (5, 'closed',      'مغلقة',        'Closed');

-- ticket_priorities
INSERT INTO `ticket_priorities` (`name_ar`, `name_en`, `level`, `sla_hours`, `is_active`) VALUES
  ('عاجل',  'Urgent', 1, 4,  1),
  ('متوسط', 'Medium', 2, 24, 1),
  ('منخفض', 'Low',    3, 72, 1);

-- ticket_categories
INSERT INTO `ticket_categories` (`name_ar`, `name_en`, `is_active`) VALUES
  ('هاردوير', 'Hardware', 1),
  ('سوفتوير', 'Software', 1),
  ('شبكة',    'Network',  1),
  ('إيميل',   'Email',    1),
  ('أخرى',    'Other',    1);

-- departments (at least one default)
INSERT INTO `departments` (`name_ar`, `name_en`) VALUES
  ('قسم تقنية المعلومات', 'Information Technology');

-- initial admin user (employee_id must match the first row of the LDAP mock array)
-- agent001 / emp001 are added so the role-based login redirect (employee/agent/admin)
-- can be exercised end to end; they match the corresponding config/ldap.php mock users.
INSERT INTO `users` (`employee_id`, `full_name`, `email`, `role`, `department_id`, `is_active`, `created_at`) VALUES
  ('admin001', 'مدير النظام', 'admin@bank.local', 'admin',    1, 1, NOW()),
  ('agent001', 'فني الدعم',   'agent@bank.local', 'agent',    1, 1, NOW()),
  ('emp001',   'موظف تجريبي', 'emp@bank.local',   'employee', 1, 1, NOW());
