-- JIADA Land Allotment MIS - Phase 1 extension
-- Safe-to-apply migration for existing app (creates la_* tables only)

CREATE TABLE IF NOT EXISTS `la_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_no` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `publish_date` DATE NOT NULL,
    `application_start_date` DATE NOT NULL,
    `application_end_date` DATE NOT NULL,
    `status` ENUM('draft','published','closed','cancelled') NOT NULL DEFAULT 'draft',
    `created_by` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_la_notification_no` (`notification_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_notification_plots` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `floor_price` DECIMAL(14,2) DEFAULT NULL,
    `is_withdrawn` TINYINT(1) NOT NULL DEFAULT 0,
    `withdrawn_reason` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_la_notification_plot` (`notification_id`, `plot_id`),
    KEY `idx_la_np_plot` (`plot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_notification_corrigenda` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_id` BIGINT UNSIGNED NOT NULL,
    `corrigendum_no` VARCHAR(50) NOT NULL,
    `corrigendum_date` DATE NOT NULL,
    `description` TEXT NOT NULL,
    `created_by` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_la_corr_notification` (`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_workflow_actions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `status_code` VARCHAR(60) NOT NULL,
    `action_type` VARCHAR(40) NOT NULL,
    `action_by` BIGINT UNSIGNED DEFAULT NULL,
    `action_note` TEXT DEFAULT NULL,
    `action_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_la_wa_app` (`application_id`),
    KEY `idx_la_wa_status` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_objections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `raised_by` BIGINT UNSIGNED DEFAULT NULL,
    `objection_text` TEXT NOT NULL,
    `status` ENUM('open','clarified','closed','reopened') NOT NULL DEFAULT 'open',
    `due_date` DATE DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_la_obj_application` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_clarifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `objection_id` BIGINT UNSIGNED NOT NULL,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `submitted_by` BIGINT UNSIGNED NOT NULL,
    `clarification_text` TEXT NOT NULL,
    `submitted_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_la_clarification_application` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_bid_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `notification_id` BIGINT UNSIGNED DEFAULT NULL,
    `bid_event_no` VARCHAR(50) NOT NULL,
    `bid_notice_date` DATE NOT NULL,
    `bid_date` DATE NOT NULL,
    `floor_price` DECIMAL(14,2) NOT NULL,
    `emd_amount` DECIMAL(14,2) NOT NULL,
    `status` ENUM('draft','published','closed','completed','cancelled') NOT NULL DEFAULT 'draft',
    `created_by` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_la_bid_event_no` (`bid_event_no`),
    KEY `idx_la_be_plot` (`plot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_bid_participants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bid_event_id` BIGINT UNSIGNED NOT NULL,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `emd_payment_id` BIGINT UNSIGNED DEFAULT NULL,
    `is_eligible` TINYINT(1) NOT NULL DEFAULT 0,
    `bid_amount` DECIMAL(14,2) DEFAULT NULL,
    `bid_submitted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_la_bp` (`bid_event_id`, `application_id`),
    KEY `idx_la_bp_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_allotments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `allotment_type` ENUM('single_applicant','highest_bidder','second_highest_bidder') NOT NULL,
    `provisional_order_no` VARCHAR(50) NOT NULL,
    `provisional_order_date` DATE NOT NULL,
    `premium_amount` DECIMAL(14,2) NOT NULL,
    `payment_due_date` DATE NOT NULL,
    `status` ENUM('provisional','premium_pending','premium_paid','cancelled') NOT NULL DEFAULT 'provisional',
    `created_by` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_la_order_no` (`provisional_order_no`),
    KEY `idx_la_allotment_app` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_possessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `allotment_id` BIGINT UNSIGNED NOT NULL,
    `possession_letter_no` VARCHAR(50) NOT NULL,
    `possession_date` DATE DEFAULT NULL,
    `status` ENUM('pending','granted') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_la_possession_allotment` (`allotment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_registry_process` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `allotment_id` BIGINT UNSIGNED NOT NULL,
    `cte_received_flag` TINYINT(1) NOT NULL DEFAULT 0,
    `construction_permit_applied_flag` TINYINT(1) NOT NULL DEFAULT 0,
    `bpams_reference_no` VARCHAR(100) DEFAULT NULL,
    `registry_initiated_date` DATE DEFAULT NULL,
    `lease_deed_no` VARCHAR(100) DEFAULT NULL,
    `lease_deed_date` DATE DEFAULT NULL,
    `status` ENUM('pending_cte','pending_construction_permit','registry_initiated','lease_executed') NOT NULL DEFAULT 'pending_cte',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_la_registry_allotment` (`allotment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_timeline_breaches` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `last_action_at` DATETIME NOT NULL,
    `breach_days` INT NOT NULL,
    `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_la_breach_open` (`application_id`, `status`),
    KEY `idx_la_breach_app` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_application_wizard` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `profile_json` JSON DEFAULT NULL,
    `project_json` JSON DEFAULT NULL,
    `finance_json` JSON DEFAULT NULL,
    `land_use_json` JSON DEFAULT NULL,
    `environment_json` JSON DEFAULT NULL,
    `declaration_json` JSON DEFAULT NULL,
    `final_submission_flag` TINYINT(1) NOT NULL DEFAULT 0,
    `locked_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_la_wizard_app` (`application_id`),
    KEY `idx_la_wizard_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_document_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_code` VARCHAR(80) NOT NULL,
    `document_name` VARCHAR(255) NOT NULL,
    `applicant_type_code` VARCHAR(80) DEFAULT NULL,
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_la_doc_type` (`document_code`, `applicant_type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `la_application_documents` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `document_code` VARCHAR(80) NOT NULL,
    `document_name` VARCHAR(255) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_la_app_doc_app` (`application_id`),
    KEY `idx_la_app_doc_code` (`document_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `land_applications`
    ADD COLUMN IF NOT EXISTS `applicant_type_code` VARCHAR(80) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `non_entertained_flag` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `non_entertainment_reason` TEXT DEFAULT NULL;

INSERT IGNORE INTO `la_document_types` (`document_code`, `document_name`, `applicant_type_code`, `is_mandatory`, `is_active`) VALUES
('dpr', 'Detailed Project Report (DPR)', NULL, 1, 1),
('id_proof', 'Identity Proof', NULL, 1, 1),
('address_proof', 'Address Proof', NULL, 1, 1),
('declaration', 'Declaration Undertaking', NULL, 1, 1),
('incorporation_cert', 'Certificate of Incorporation', 'pvt_ltd', 1, 1),
('moa', 'MOA', 'pvt_ltd', 1, 1),
('aoa', 'AOA', 'pvt_ltd', 1, 1),
('partnership_deed', 'Partnership Deed', 'partnership', 1, 1),
('llp_registration', 'LLP Registration', 'llp', 1, 1),
('proprietor_profile', 'Proprietor Profile', 'proprietorship', 1, 1),
('consortium_mou', 'Consortium MoU', 'consortium', 1, 1);
