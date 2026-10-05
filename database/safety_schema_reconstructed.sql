-- RECONSTRUCTED from the supplied Safety Tours PHP source archive, October 2026.
-- Application table STRUCTURES ONLY: never restores old records/PDFs/actions.
-- Export your live database before importing; never DROP existing tables.
CREATE TABLE IF NOT EXISTS `safety_tours` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tour_date` DATETIME NOT NULL,
  `site` VARCHAR(255) NOT NULL,
  `area` VARCHAR(255) DEFAULT NULL,
  `lead_name` VARCHAR(255) NOT NULL,
  `participants` TEXT DEFAULT NULL,
  `recipients` TEXT DEFAULT NULL,
  `responses` LONGTEXT DEFAULT NULL,
  `score_achieved` INT DEFAULT NULL,
  `score_total` INT DEFAULT NULL,
  `score_percent` DECIMAL(6,2) DEFAULT NULL,
  `score_json` LONGTEXT DEFAULT NULL,
  `photos` LONGTEXT DEFAULT NULL,
  `signature_name` VARCHAR(255) DEFAULT NULL,
  `signature_path` VARCHAR(512) DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'Open',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_safety_tours_site` (`site`),
  KEY `idx_safety_tours_date` (`tour_date`),
  KEY `idx_safety_tours_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `safety_actions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tour_id` INT NOT NULL,
  `action` TEXT NOT NULL,
  `responsible` VARCHAR(255) DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `priority` VARCHAR(32) DEFAULT NULL,
  `severity` VARCHAR(32) DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'Open',
  `close_note` TEXT DEFAULT NULL,
  `close_photos` LONGTEXT DEFAULT NULL,
  `closed_by` VARCHAR(255) DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_safety_actions_tour` (`tour_id`),
  KEY `idx_safety_actions_status` (`status`),
  KEY `idx_safety_actions_due` (`due_date`),
  CONSTRAINT `fk_safety_actions_tour` FOREIGN KEY (`tour_id`) REFERENCES `safety_tours` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `safety_recipient_emails` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(254) NOT NULL,
  `label` VARCHAR(255) DEFAULT NULL,
  `use_count` INT NOT NULL DEFAULT 0,
  `last_used` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_safety_recipient_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `safety_audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `event` ENUM('create','update','close_action','reopen_action','delete_tour') NOT NULL,
  `tour_id` INT DEFAULT NULL,
  `action_id` INT DEFAULT NULL,
  `actor` VARCHAR(120) DEFAULT NULL,
  `action` VARCHAR(80) DEFAULT NULL,
  `entity_type` VARCHAR(80) DEFAULT NULL,
  `entity_id` INT DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_safety_audit_tour` (`tour_id`),
  KEY `idx_safety_audit_action` (`action_id`),
  KEY `idx_safety_audit_event_time` (`event_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
