-- Migration: add the "department manager" role and link categories to departments.
--
-- Apply this ONLY to an existing database that was created before the
-- department-manager feature. A fresh install already gets everything from
-- database/schema.sql, so this migration is a no-op there.
--
--   Usage:  mysql -u <user> -p it_helpdesk < database/migrations/2026_07_01_add_department_manager.sql
--
-- Idempotency: MySQL has no "ADD COLUMN IF NOT EXISTS" in older versions, so
-- run each statement once. Re-running a completed statement is a harmless error.

USE `it_helpdesk`;
SET NAMES utf8mb4;

-- 1) Add the new role to the users.role ENUM (keeps existing values/rows intact).
ALTER TABLE `users`
  MODIFY `role` ENUM('employee','agent','manager','admin') NOT NULL;

-- 2) Link each ticket category to an owning department.
ALTER TABLE `ticket_categories`
  ADD COLUMN `department_id` INT NULL AFTER `name_en`,
  ADD KEY `idx_categories_department` (`department_id`),
  ADD CONSTRAINT `fk_categories_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 3) Point existing categories at the first (default) department so managers
--    have a scope immediately; the admin can re-map them afterwards.
UPDATE `ticket_categories`
   SET `department_id` = (SELECT id FROM `departments` ORDER BY id LIMIT 1)
 WHERE `department_id` IS NULL;
