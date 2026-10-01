-- =====================================================================
-- Optional manual migration: formal letter + signature columns for
-- HRMS requests. You normally DO NOT need to run this: the endpoints
-- (api/request_submit.php, api/requests.php, api/staff_requests.php)
-- add these columns automatically on first use. Use this file only if
-- you prefer to migrate manually via phpMyAdmin.
-- Safe to run twice (errors on duplicate columns can be ignored).
-- =====================================================================

ALTER TABLE `hrms_approval_requests`
  ADD `letter_path`    VARCHAR(255) NULL,
  ADD `letter_name`    VARCHAR(255) NULL,
  ADD `signature_path` VARCHAR(255) NULL,
  ADD `signed_at`      TIMESTAMP NULL DEFAULT NULL,
  ADD `signed_by`      VARCHAR(150) NULL;

ALTER TABLE `hrms_leave_requests`
  ADD `letter_path`    VARCHAR(255) NULL,
  ADD `letter_name`    VARCHAR(255) NULL,
  ADD `signature_path` VARCHAR(255) NULL,
  ADD `signed_at`      TIMESTAMP NULL DEFAULT NULL,
  ADD `signed_by`      VARCHAR(150) NULL;

-- Folder for stored letters + signatures (create manually if needed):
--   C:\xampp\htdocs\INVENTORY\hrms\uploads\requests\
-- (PHP also creates it automatically on first upload.)
