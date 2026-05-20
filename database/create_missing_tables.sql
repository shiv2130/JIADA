-- Create missing tables for JIADA Portal
USE jiada_portal;

-- Land Plots (alias for land_banks)
CREATE TABLE IF NOT EXISTS `land_plots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plot_no` VARCHAR(50) NOT NULL,
  `area_sqft` INT NOT NULL,
  `zone` VARCHAR(100),
  `industry_type` VARCHAR(100),
  `status` VARCHAR(50) DEFAULT 'available',
  `latitude` DECIMAL(10,6),
  `longitude` DECIMAL(10,6),
  `base_price` DECIMAL(12,2),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Plug & Play Units
CREATE TABLE IF NOT EXISTS `plug_play_units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `unit_no` VARCHAR(50) NOT NULL,
  `unit_type` VARCHAR(50) NOT NULL,
  `area_sqft` INT NOT NULL,
  `monthly_rent` DECIMAL(12,2) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'available',
  `location` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Plug & Play Applications
CREATE TABLE IF NOT EXISTS `plug_play_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(50) NOT NULL,
  `user_id` INT NOT NULL,
  `unit_id` INT NOT NULL,
  `company_name` VARCHAR(200),
  `industry_type` VARCHAR(100),
  `investment_amount` DECIMAL(15,2),
  `status` VARCHAR(50) DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lease Surrenders
CREATE TABLE IF NOT EXISTS `lease_surrenders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(50) NOT NULL,
  `user_id` INT NOT NULL,
  `lease_id` INT NOT NULL,
  `reason` TEXT,
  `notice_date` DATE,
  `has_outstanding_dues` TINYINT DEFAULT 0,
  `outstanding_amount` DECIMAL(12,2) DEFAULT 0,
  `tenure_years` INT DEFAULT 0,
  `refund_percent` INT DEFAULT 0,
  `refund_amount` DECIMAL(12,2) DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lease Mortgages
CREATE TABLE IF NOT EXISTS `lease_mortgages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(50) NOT NULL,
  `user_id` INT NOT NULL,
  `lease_id` INT NOT NULL,
  `bank_name` VARCHAR(100),
  `bank_branch` VARCHAR(100),
  `loan_amount` DECIMAL(15,2),
  `loan_tenure` INT,
  `purpose` TEXT,
  `consent_letter` TEXT,
  `status` VARCHAR(50) DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lease Transfers
CREATE TABLE IF NOT EXISTS `lease_transfers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(50) NOT NULL,
  `lease_id` INT NOT NULL,
  `from_user_id` INT NOT NULL,
  `to_user_id` INT,
  `to_user_name` VARCHAR(200),
  `to_user_mobile` VARCHAR(20),
  `to_user_email` VARCHAR(100),
  `transfer_reason` TEXT,
  `consideration_amount` DECIMAL(15,2),
  `is_msme` TINYINT DEFAULT 0,
  `premium_percent` INT DEFAULT 25,
  `land_premium` DECIMAL(15,2),
  `status` VARCHAR(50) DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lease Renewals
CREATE TABLE IF NOT EXISTS `lease_renewals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(50) NOT NULL,
  `user_id` INT NOT NULL,
  `lease_id` INT NOT NULL,
  `renewal_period` INT,
  `reason` TEXT,
  `status` VARCHAR(50) DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity Logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `action` VARCHAR(100),
  `description` TEXT,
  `ip_address` VARCHAR(50),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ticket History
CREATE TABLE IF NOT EXISTS `ticket_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT,
  `note` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grievance Responses
CREATE TABLE IF NOT EXISTS `grievance_responses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `grievance_id` INT NOT NULL,
  `user_id` INT,
  `response` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WhatsApp Messages
CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message_id` VARCHAR(100),
  `from_number` VARCHAR(20),
  `message_text` TEXT,
  `received_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add missing columns to existing tables
ALTER TABLE `land_banks` ADD COLUMN IF NOT EXISTS `plot_no` VARCHAR(50) AFTER `id`;
ALTER TABLE `land_banks` ADD COLUMN IF NOT EXISTS `area_sqft` INT AFTER `plot_no`;
ALTER TABLE `land_banks` ADD COLUMN IF NOT EXISTS `zone` VARCHAR(100) AFTER `area_sqft`;
ALTER TABLE `land_banks` ADD COLUMN IF NOT EXISTS `industry_type` VARCHAR(100) AFTER `zone`;
ALTER TABLE `land_banks` ADD COLUMN IF NOT EXISTS `latitude` DECIMAL(10,6) AFTER `industry_type`;
ALTER TABLE `land_banks` ADD COLUMN IF NOT EXISTS `longitude` DECIMAL(10,6) AFTER `latitude`;
ALTER TABLE `land_banks` ADD COLUMN IF NOT EXISTS `base_price` DECIMAL(12,2) AFTER `longitude`;

ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `ticket_no` VARCHAR(50) AFTER `id`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `region_id` INT AFTER `user_id`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `assigned_to` INT AFTER `region_id`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `priority` VARCHAR(20) DEFAULT 'medium' AFTER `status`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `escalated` TINYINT DEFAULT 0 AFTER `priority`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `resolution` TEXT AFTER `description`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `resolved_at` DATETIME AFTER `resolution`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `closed_at` DATETIME AFTER `resolved_at`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `feedback_rating` INT AFTER `closed_at`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `feedback_text` TEXT AFTER `feedback_rating`;
ALTER TABLE `utility_tickets` ADD COLUMN IF NOT EXISTS `updated_at` DATETIME AFTER `created_at`;

ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `grievance_no` VARCHAR(50) AFTER `id`;
ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `category` VARCHAR(50) AFTER `user_id`;
ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `department_id` INT AFTER `category`;
ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `assigned_officer` INT AFTER `department_id`;
ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `response_count` INT DEFAULT 0 AFTER `status`;
ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `escalated` TINYINT DEFAULT 0 AFTER `response_count`;
ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `feedback_rating` INT AFTER `escalated`;
ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `resolved_at` DATETIME AFTER `feedback_rating`;
ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `closed_at` DATETIME AFTER `resolved_at`;
ALTER TABLE `grievances` ADD COLUMN IF NOT EXISTS `updated_at` DATETIME AFTER `created_at`;

ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `receipt_no` VARCHAR(50) AFTER `id`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `transaction_id` VARCHAR(100) AFTER `receipt_no`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `paid_amount` DECIMAL(12,2) DEFAULT 0 AFTER `amount`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `payment_for` VARCHAR(100) AFTER `paid_amount`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `payment_mode` VARCHAR(50) AFTER `payment_for`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `description` TEXT AFTER `payment_mode`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `due_date` DATE AFTER `description`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `payment_date` DATETIME AFTER `due_date`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `gst_amount` DECIMAL(12,2) DEFAULT 0 AFTER `payment_date`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `lease_id` INT AFTER `gst_amount`;

ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `title` VARCHAR(200) AFTER `user_id`;
ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `type` VARCHAR(20) DEFAULT 'info' AFTER `message`;
ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `is_read` TINYINT DEFAULT 0 AFTER `type`;
ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `read_at` DATETIME AFTER `is_read`;

ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `case_number` VARCHAR(100) AFTER `cnr_number`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `case_type` VARCHAR(50) AFTER `case_number`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `subject` VARCHAR(200) AFTER `case_type`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `petitioner` VARCHAR(200) AFTER `subject`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `respondent` VARCHAR(200) AFTER `petitioner`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `court_name` VARCHAR(200) AFTER `respondent`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `filing_date` DATE AFTER `court_name`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `next_hearing_date` DATE AFTER `filing_date`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `status` VARCHAR(50) DEFAULT 'pending' AFTER `next_hearing_date`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `description` TEXT AFTER `status`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `lawyer_name` VARCHAR(200) AFTER `description`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `lawyer_contact` VARCHAR(20) AFTER `lawyer_name`;
ALTER TABLE `court_cases` ADD COLUMN IF NOT EXISTS `created_by` INT AFTER `lawyer_contact`;

ALTER TABLE `lease_records` ADD COLUMN IF NOT EXISTS `lease_no` VARCHAR(50) AFTER `id`;
ALTER TABLE `lease_records` ADD COLUMN IF NOT EXISTS `plot_no` VARCHAR(50) AFTER `lease_no`;
ALTER TABLE `lease_records` ADD COLUMN IF NOT EXISTS `user_id` INT AFTER `plot_no`;
ALTER TABLE `lease_records` ADD COLUMN IF NOT EXISTS `premium_amount` DECIMAL(15,2) AFTER `user_id`;
ALTER TABLE `lease_records` ADD COLUMN IF NOT EXISTS `allotment_date` DATE AFTER `premium_amount`;

ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `region_id` INT AFTER `user_type`;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login` DATETIME AFTER `status`;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login_ip` VARCHAR(50) AFTER `last_login`;

-- BRAP tracker
CREATE TABLE IF NOT EXISTS `brap_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `owner_role` VARCHAR(100) NOT NULL,
  `due_date` DATE DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  `evidence_url` VARCHAR(500) DEFAULT NULL,
  `last_reviewed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_owner_role` (`owner_role`)
);

-- Survey submissions
CREATE TABLE IF NOT EXISTS `survey_submissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcel_id` BIGINT UNSIGNED NOT NULL,
  `geojson` LONGTEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'verified',
  `notes` TEXT DEFAULT NULL,
  `photos` JSON DEFAULT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `collected_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parcel` (`parcel_id`),
  KEY `idx_status` (`status`)
);

SELECT 'All missing tables and columns created successfully!' AS status;
