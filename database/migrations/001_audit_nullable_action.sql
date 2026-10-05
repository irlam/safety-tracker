-- One-time, non-destructive compatibility fix for recovered Safety databases.
-- Export/backup the database first. Does not delete or alter any audit rows.
-- delete_tour.php records an event but does not supply an action label.
ALTER TABLE safety_audit_log
    MODIFY COLUMN `action` VARCHAR(80) NULL DEFAULT NULL;
