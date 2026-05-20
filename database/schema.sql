-- =====================================================
-- JIADA Portal - Complete Database Schema
-- Jharkhand Industrial Area Development Authority
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `jiada_portal` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jiada_portal`;

-- =====================================================
-- 1. USER MANAGEMENT & AUTHENTICATION
-- =====================================================

-- Users table
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_type` ENUM('applicant', 'allottee', 'authority', 'admin', 'vendor', 'employee') NOT NULL DEFAULT 'applicant',
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    `aadhaar_number` VARCHAR(14) DEFAULT NULL COMMENT 'XXX-XXXX-XXXX format',
    `pan_number` VARCHAR(10) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `district` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT 'Jharkhand',
    `pincode` VARCHAR(10) DEFAULT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `company_type` ENUM('proprietorship', 'partnership', 'pvt_ltd', 'public_ltd', 'llp', 'coop', 'other') DEFAULT NULL,
    `gst_number` VARCHAR(15) DEFAULT NULL,
    `udyam_number` VARCHAR(20) DEFAULT NULL,
    `is_msme` TINYINT(1) DEFAULT 0,
    `msme_category` ENUM('micro', 'small', 'medium') DEFAULT NULL,
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'blocked', 'pending_verification') NOT NULL DEFAULT 'pending_verification',
    `email_verified` TINYINT(1) DEFAULT 0,
    `mobile_verified` TINYINT(1) DEFAULT 0,
    `last_login` DATETIME DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    `notification_preferences` JSON DEFAULT NULL COMMENT '{"email": true, "sms": true, "whatsapp": false}',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_mobile` (`mobile`),
    UNIQUE KEY `unique_email` (`email`),
    KEY `idx_user_type` (`user_type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OTP Requests
CREATE TABLE `otp_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mobile` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `otp_hash` VARCHAR(64) NOT NULL,
    `purpose` ENUM('login', 'registration', 'mobile_verify', 'email_verify', 'password_reset', 'transaction') NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` INT NOT NULL DEFAULT 0,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `verified_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mobile_purpose` (`mobile`, `purpose`),
    KEY `idx_email_purpose` (`email`, `purpose`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Token blacklist for JWT
CREATE TABLE `token_blacklist` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token_hash` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions
CREATE TABLE `user_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `session_token` VARCHAR(255) NOT NULL,
    `device_info` VARCHAR(255) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_activity` DATETIME DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_session_token` (`session_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. ROLES & PERMISSIONS
-- =====================================================

CREATE TABLE `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `user_type` VARCHAR(50) NOT NULL DEFAULT 'authority',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `module` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
    `role_id` BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `fk_rp_permission` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_roles` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `assigned_by` BIGINT UNSIGNED DEFAULT NULL,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. LAND BANK & GIS
-- =====================================================

CREATE TABLE `land_banks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plot_number` VARCHAR(50) NOT NULL,
    `survey_number` VARCHAR(100) DEFAULT NULL,
    `plot_type` ENUM('industrial', 'commercial', 'flatted_factory', 'warehouse', 'mixed_use') NOT NULL,
    `area_sqm` DECIMAL(12,2) NOT NULL,
    `area_acres` DECIMAL(12,4) DEFAULT NULL,
    `zone` VARCHAR(100) DEFAULT NULL,
    `sector` VARCHAR(100) DEFAULT NULL,
    `location_lat` DECIMAL(10,8) DEFAULT NULL,
    `location_lng` DECIMAL(11,8) DEFAULT NULL,
    `boundary_coordinates` JSON DEFAULT NULL COMMENT 'GeoJSON polygon coordinates',
    `base_price_per_sqm` DECIMAL(12,2) DEFAULT NULL,
    `status` ENUM('available', 'allotted', 'reserved', 'blocked', 'under_development') NOT NULL DEFAULT 'available',
    `infrastructure_type` ENUM('raw_land', 'developed', 'plug_play') DEFAULT 'raw_land',
    `utilities_available` JSON DEFAULT NULL COMMENT '["electricity", "water", "gas", "sewage"]',
    `facing` VARCHAR(50) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_plot` (`plot_number`),
    KEY `idx_status` (`status`),
    KEY `idx_plot_type` (`plot_type`),
    KEY `idx_zone` (`zone`),
    SPATIAL KEY `location` (`location_lat`, `location_lng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. LAND ALLOTMENT
-- =====================================================

CREATE TABLE `land_applications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_number` VARCHAR(50) NOT NULL,
    `reference_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `application_type` ENUM('new_allotment', 'bidding') NOT NULL,
    `purpose` ENUM('manufacturing', ' warehousing', 'services', 'commercial', 'other') NOT NULL,
    `proposed_investment` DECIMAL(15,2) DEFAULT NULL,
    `employment_generation` INT DEFAULT NULL,
    `project_description` TEXT DEFAULT NULL,
    `documents` JSON DEFAULT NULL,
    `processing_fee_amount` DECIMAL(10,2) DEFAULT NULL,
    `processing_fee_paid` TINYINT(1) DEFAULT 0,
    `payment_reference` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('draft', 'submitted', 'under_scrutiny', 'approved', 'rejected', 'provisional_allotted', 'allotted', 'cancelled') NOT NULL DEFAULT 'draft',
    `scrutiny_remarks` TEXT DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `bid_id` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_application` (`application_number`),
    UNIQUE KEY `unique_reference` (`reference_number`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_plot_id` (`plot_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bidding table
CREATE TABLE `bids` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bid_number` VARCHAR(50) NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `bid_start_date` DATETIME NOT NULL,
    `bid_end_date` DATETIME NOT NULL,
    `base_price` DECIMAL(15,2) NOT NULL,
    `emd_amount` DECIMAL(10,2) NOT NULL,
    `status` ENUM('upcoming', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
    `winning_bid_id` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_bid_number` (`bid_number`),
    KEY `idx_plot_id` (`plot_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bid_participants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bid_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `emd_paid` TINYINT(1) DEFAULT 0,
    `emd_payment_reference` VARCHAR(100) DEFAULT NULL,
    `bid_amount` DECIMAL(15,2) DEFAULT NULL,
    `bid_submitted_at` DATETIME DEFAULT NULL,
    `rank` INT DEFAULT NULL,
    `status` ENUM('registered', 'emd_pending', 'bid_submitted', 'won', 'lost', 'refunded') NOT NULL DEFAULT 'registered',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bid_id` (`bid_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Allotment orders
CREATE TABLE `allotment_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(50) NOT NULL,
    `application_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `order_type` ENUM('provisional', 'final') NOT NULL,
    `allotment_date` DATE NOT NULL,
    `premium_amount` DECIMAL(15,2) NOT NULL,
    `emd_adjusted` DECIMAL(10,2) DEFAULT 0,
    `outstanding_amount` DECIMAL(15,2) DEFAULT NULL,
    `payment_deadline` DATE DEFAULT NULL,
    `lease_period_years` INT DEFAULT 30,
    `annual_rent` DECIMAL(10,2) DEFAULT NULL,
    `maintenance_charges` DECIMAL(10,2) DEFAULT NULL,
    `terms_conditions` TEXT DEFAULT NULL,
    `status` ENUM('issued', 'accepted', 'payment_pending', 'completed', 'cancelled') NOT NULL DEFAULT 'issued',
    `document_path` VARCHAR(255) DEFAULT NULL,
    `issued_by` BIGINT UNSIGNED DEFAULT NULL,
    `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `accepted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_order` (`order_number`),
    KEY `idx_application_id` (`application_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. LEASE MANAGEMENT
-- =====================================================

CREATE TABLE `lease_records` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lease_number` VARCHAR(50) NOT NULL,
    `allotment_order_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `lease_start_date` DATE NOT NULL,
    `lease_end_date` DATE NOT NULL,
    `annual_rent` DECIMAL(12,2) NOT NULL,
    `maintenance_charges` DECIMAL(10,2) DEFAULT 0,
    `security_deposit` DECIMAL(12,2) DEFAULT 0,
    `rent_revision_percentage` DECIMAL(5,2) DEFAULT 15,
    `rent_revision_period_years` INT DEFAULT 5,
    `status` ENUM('active', 'expired', 'terminated', 'surrendered', 'renewed') NOT NULL DEFAULT 'active',
    `document_path` VARCHAR(255) DEFAULT NULL,
    `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_lease` (`lease_number`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_plot_id` (`plot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Surrender requests
CREATE TABLE `surrender_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_number` VARCHAR(50) NOT NULL,
    `lease_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `surrender_reason` TEXT NOT NULL,
    `notice_date` DATE NOT NULL,
    `proposed_surrender_date` DATE NOT NULL,
    `outstanding_dues` DECIMAL(12,2) DEFAULT 0,
    `refundable_amount` DECIMAL(12,2) DEFAULT 0,
    `refund_percentage` DECIMAL(5,2) DEFAULT NULL COMMENT '75% <1yr, 65% <2yr, 55% <3yr',
    `documents` JSON DEFAULT NULL,
    `ceo_remarks` TEXT DEFAULT NULL,
    `status` ENUM('submitted', 'under_review', 'objection_raised', 'clarification_requested', 'approved', 'rejected', 'refunded') NOT NULL DEFAULT 'submitted',
    `objection_count` INT DEFAULT 0,
    `clarification_count` INT DEFAULT 0,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_request` (`request_number`),
    KEY `idx_lease_id` (`lease_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mortgage requests
CREATE TABLE `mortgage_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_number` VARCHAR(50) NOT NULL,
    `lease_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `bank_name` VARCHAR(255) NOT NULL,
    `branch` VARCHAR(255) DEFAULT NULL,
    `loan_amount` DECIMAL(15,2) NOT NULL,
    `consent_letter_path` VARCHAR(255) NOT NULL,
    `documents` JSON DEFAULT NULL,
    `status` ENUM('submitted', 'under_scrutiny', 'objection_raised', 'clarification_requested', 'approved', 'rejected', 'certificate_issued') NOT NULL DEFAULT 'submitted',
    `certificate_path` VARCHAR(255) DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_request` (`request_number`),
    KEY `idx_lease_id` (`lease_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transfer requests
CREATE TABLE `transfer_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_number` VARCHAR(50) NOT NULL,
    `lease_id` BIGINT UNSIGNED NOT NULL,
    `from_user_id` BIGINT UNSIGNED NOT NULL,
    `to_user_id` BIGINT UNSIGNED DEFAULT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `transfer_type` ENUM('sale', 'gift', 'inheritance', 'other') NOT NULL,
    `consideration_amount` DECIMAL(15,2) DEFAULT NULL,
    `transferee_details` JSON DEFAULT NULL,
    `documents` JSON DEFAULT NULL,
    `processing_fee_amount` DECIMAL(10,2) DEFAULT NULL,
    `premium_amount` DECIMAL(12,2) DEFAULT NULL,
    `outstanding_dues` DECIMAL(12,2) DEFAULT 0,
    `status` ENUM('submitted', 'under_verification', 'objection_raised', 'clarification_requested', 'premium_calculated', 'payment_pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'submitted',
    `certificate_path` VARCHAR(255) DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_request` (`request_number`),
    KEY `idx_lease_id` (`lease_id`),
    KEY `idx_from_user` (`from_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merge/Demerge/Amalgamate requests
CREATE TABLE `plot_modification_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_number` VARCHAR(50) NOT NULL,
    `modification_type` ENUM('merge', 'demerge', 'amalgamate') NOT NULL,
    `primary_lease_id` BIGINT UNSIGNED NOT NULL,
    `secondary_lease_ids` JSON DEFAULT NULL COMMENT 'For merge/amalgamate',
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_ids` JSON NOT NULL,
    `new_boundaries` JSON DEFAULT NULL COMMENT 'GeoJSON coordinates',
    `noc_documents` JSON DEFAULT NULL,
    `land_premium_percentage` DECIMAL(5,2) DEFAULT NULL COMMENT '15% MSME, 25% others',
    `land_premium_amount` DECIMAL(12,2) DEFAULT NULL,
    `outstanding_dues` DECIMAL(12,2) DEFAULT 0,
    `status` ENUM('submitted', 'under_scrutiny', 'objection_raised', 'clarification_requested', 'premium_calculated', 'payment_pending', 'approved', 'rejected', 'certificate_issued') NOT NULL DEFAULT 'submitted',
    `certificate_path` VARCHAR(255) DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_request` (`request_number`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. PLUG & PLAY ALLOTMENT
-- =====================================================

CREATE TABLE `plug_play_units` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `unit_number` VARCHAR(50) NOT NULL,
    `building_name` VARCHAR(100) NOT NULL,
    `floor` INT DEFAULT NULL,
    `area_sqm` DECIMAL(10,2) NOT NULL,
    `type` ENUM('flatted_factory', 'office', 'warehouse', 'shed') NOT NULL,
    `monthly_rent` DECIMAL(10,2) NOT NULL,
    `maintenance_charges` DECIMAL(10,2) DEFAULT 0,
    `power_connection` ENUM('single_phase', 'three_phase', 'ht') DEFAULT NULL,
    `power_load_kw` DECIMAL(8,2) DEFAULT NULL,
    `amenities` JSON DEFAULT NULL,
    `status` ENUM('available', 'booked', 'allotted', 'maintenance', 'blocked') NOT NULL DEFAULT 'available',
    `remarks` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_unit` (`unit_number`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `plug_play_applications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `unit_id` BIGINT UNSIGNED NOT NULL,
    `scrutiny_fee_amount` DECIMAL(10,2) NOT NULL,
    `scrutiny_fee_paid` TINYINT(1) DEFAULT 0,
    `documents` JSON DEFAULT NULL,
    `six_month_rent_amount` DECIMAL(12,2) DEFAULT NULL,
    `six_month_rent_paid` TINYINT(1) DEFAULT 0,
    `status` ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'allotted', 'cancelled') NOT NULL DEFAULT 'draft',
    `committee_remarks` TEXT DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_application` (`application_number`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. UTILITY MANAGEMENT
-- =====================================================

CREATE TABLE `utility_tickets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED DEFAULT NULL,
    `unit_id` BIGINT UNSIGNED DEFAULT NULL,
    `utility_type` ENUM('water', 'electricity', 'sewage', 'road', 'street_light', 'other') NOT NULL,
    `priority` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    `subject` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `attachments` JSON DEFAULT NULL,
    `assigned_to` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Authority user',
    `vendor_id` BIGINT UNSIGNED DEFAULT NULL,
    `status` ENUM('open', 'assigned', 'in_progress', 'resolved', 'escalated', 'closed', 'reopened') NOT NULL DEFAULT 'open',
    `escalation_count` INT DEFAULT 0,
    `resolved_at` DATETIME DEFAULT NULL,
    `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
    `resolution_notes` TEXT DEFAULT NULL,
    `feedback_rating` TINYINT DEFAULT NULL COMMENT '1-5 stars',
    `feedback_comment` TEXT DEFAULT NULL,
    `sla_deadline` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_ticket` (`ticket_number`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_utility_type` (`utility_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_updates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `update_type` ENUM('status_change', 'comment', 'assignment', 'escalation', 'resolution') NOT NULL,
    `message` TEXT DEFAULT NULL,
    `attachments` JSON DEFAULT NULL,
    `old_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. GRIEVANCE REDRESSAL
-- =====================================================

CREATE TABLE `grievances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `grievance_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `category` ENUM('service', 'billing', 'allotment', 'maintenance', 'corruption', 'harassment', 'other') NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `attachments` JSON DEFAULT NULL,
    `secondment_officer_id` BIGINT UNSIGNED DEFAULT NULL,
    `status` ENUM('submitted', 'assigned', 'under_review', 'response_pending', 'clarification_requested', 'resolved', 'dissatisfied', 'escalated', 'closed') NOT NULL DEFAULT 'submitted',
    `resubmission_count` INT DEFAULT 0,
    `max_resubmissions` INT DEFAULT 2,
    `officer_response` TEXT DEFAULT NULL,
    `response_at` DATETIME DEFAULT NULL,
    `resolution_date` DATETIME DEFAULT NULL,
    `feedback_satisfied` TINYINT(1) DEFAULT NULL,
    `feedback_comment` TEXT DEFAULT NULL,
    `sla_deadline` DATETIME DEFAULT NULL COMMENT '5 days',
    `escalated_to` BIGINT UNSIGNED DEFAULT NULL COMMENT 'RD/MD',
    `committee_id` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_grievance` (`grievance_number`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. COURT CASE MANAGEMENT
-- =====================================================

CREATE TABLE `court_cases` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_number` VARCHAR(100) NOT NULL,
    `cnr_number` VARCHAR(50) DEFAULT NULL COMMENT 'Court Case Number',
    `court_name` VARCHAR(255) NOT NULL,
    `court_location` VARCHAR(255) DEFAULT NULL,
    `case_type` ENUM('civil', 'criminal', 'writ', 'appeal', 'review', 'other') NOT NULL,
    `subject` ENUM('land_dispute', 'allotment', 'recovery', 'service', 'constitutional', 'other') NOT NULL,
    `title` VARCHAR(500) NOT NULL,
    `petitioner_name` VARCHAR(255) NOT NULL,
    `respondent_name` VARCHAR(255) NOT NULL,
    `case_status` ENUM('filed', 'admitted', 'pending', 'heard', 'reserved', 'decided', 'disposed', 'closed', 'appeal_filed') NOT NULL DEFAULT 'filed',
    `filing_date` DATE DEFAULT NULL,
    `registration_date` DATE DEFAULT NULL,
    `next_hearing_date` DATE DEFAULT NULL,
    `last_hearing_date` DATE DEFAULT NULL,
    `last_hearing_order` TEXT DEFAULT NULL,
    `judge_name` VARCHAR(255) DEFAULT NULL,
    `case_category` VARCHAR(100) DEFAULT NULL,
    `documents` JSON DEFAULT NULL,
    `assigned_counsel` VARCHAR(255) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_case` (`case_number`),
    KEY `idx_cnr` (`cnr_number`),
    KEY `idx_status` (`case_status`),
    KEY `idx_next_hearing` (`next_hearing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `case_hearings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_id` BIGINT UNSIGNED NOT NULL,
    `hearing_date` DATE NOT NULL,
    `hearing_type` VARCHAR(100) DEFAULT NULL,
    `order` TEXT DEFAULT NULL,
    `next_hearing_date` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `documents` JSON DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_case_id` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. CHANGE OF CONSTITUTION / SHAREHOLDING
-- =====================================================

CREATE TABLE `constitution_changes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `lease_id` BIGINT UNSIGNED NOT NULL,
    `change_type` ENUM('proprietor_to_partnership', 'partnership_to_company', 'merger', 'conversion', 'name_change', 'other') NOT NULL,
    `old_constitution` VARCHAR(100) NOT NULL,
    `new_constitution` VARCHAR(100) NOT NULL,
    `documents` JSON DEFAULT NULL COMMENT 'CA certified documents',
    `clarification_count` INT DEFAULT 0,
    `status` ENUM('submitted', 'under_scrutiny', 'clarification_requested', 'approved', 'rejected', 'certificate_issued') NOT NULL DEFAULT 'submitted',
    `certificate_path` VARCHAR(255) DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_application` (`application_number`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shareholding_changes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `lease_id` BIGINT UNSIGNED NOT NULL,
    `change_type` ENUM('transfer', 'addition', 'removal', 'restructuring') NOT NULL,
    `old_shareholding` JSON DEFAULT NULL,
    `new_shareholding` JSON DEFAULT NULL,
    `blood_relative_percentage_before` DECIMAL(5,2) DEFAULT NULL,
    `blood_relative_percentage_after` DECIMAL(5,2) DEFAULT NULL,
    `premium_applicable` TINYINT(1) DEFAULT 0,
    `premium_amount` DECIMAL(12,2) DEFAULT NULL,
    `documents` JSON DEFAULT NULL COMMENT 'CA certified, partnership deeds, affidavits',
    `status` ENUM('submitted', 'under_verification', 'objection_raised', 'premium_calculated', 'payment_pending', 'approved', 'rejected', 'certificate_issued') NOT NULL DEFAULT 'submitted',
    `certificate_path` VARCHAR(255) DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_application` (`application_number`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. CANCELLATION
-- =====================================================

CREATE TABLE `cancellations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notice_number` VARCHAR(50) NOT NULL,
    `lease_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `reason_category` ENUM('non_use', 'violation', 'non_payment', 'misrepresentation', 'legal_order', 'voluntary', 'other') NOT NULL,
    `reason_details` TEXT NOT NULL,
    `documents` JSON DEFAULT NULL,
    `initiated_by` BIGINT UNSIGNED NOT NULL COMMENT 'Admin/Authority',
    `account_status` ENUM('partially_blocked', 'fully_blocked', 'unblocked', 'cancelled') NOT NULL DEFAULT 'partially_blocked',
    `user_response` TEXT DEFAULT NULL,
    `clarification_count` INT DEFAULT 0,
    `response_deadline` DATETIME DEFAULT NULL,
    `status` ENUM('notice_issued', 'response_pending', 'under_review', 'unblocked', 'escalated', 'cancelled') NOT NULL DEFAULT 'notice_issued',
    `final_remarks` TEXT DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_notice` (`notice_number`),
    KEY `idx_lease_id` (`lease_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. LEASE RENEWAL
-- =====================================================

CREATE TABLE `lease_renewals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `renewal_number` VARCHAR(50) NOT NULL,
    `lease_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `current_lease_end` DATE NOT NULL,
    `requested_renewal_period` INT NOT NULL COMMENT 'Years',
    `proposed_renewal_start` DATE NOT NULL,
    `documents` JSON DEFAULT NULL,
    `outstanding_dues` DECIMAL(12,2) DEFAULT 0,
    `renewal_premium` DECIMAL(12,2) DEFAULT NULL,
    `objections` TEXT DEFAULT NULL,
    `clarification_count` INT DEFAULT 0,
    `status` ENUM('submitted', 'under_review', 'objection_raised', 'clarification_requested', 'approved', 'rejected', 'certificate_issued', 'blocked') NOT NULL DEFAULT 'submitted',
    `certificate_path` VARCHAR(255) DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `new_lease_end_date` DATE DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_renewal` (`renewal_number`),
    KEY `idx_lease_id` (`lease_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. PAYMENT & REVENUE
-- =====================================================

CREATE TABLE `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id` VARCHAR(50) NOT NULL,
    `transaction_id` VARCHAR(100) DEFAULT NULL COMMENT 'Gateway transaction ID',
    `user_id` BIGINT UNSIGNED NOT NULL,
    `payment_type` ENUM('application_fee', 'processing_fee', 'emd', 'premium', 'rent', 'maintenance', 'utility', 'penalty', 'scrutiny_fee', 'six_month_rent', 'other') NOT NULL,
    `reference_type` VARCHAR(50) COMMENT 'application, allotment, bid, lease, renewal, utility, etc.',
    `reference_id` BIGINT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `gst_amount` DECIMAL(10,2) DEFAULT 0,
    `total_amount` DECIMAL(12,2) NOT NULL,
    `payment_mode` ENUM('upi', 'net_banking', 'credit_card', 'debit_card', 'wallet', 'dd', 'online') NOT NULL,
    `gateway` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('pending', 'initiated', 'success', 'failed', 'cancelled', 'refunded', 'timeout') NOT NULL DEFAULT 'pending',
    `gateway_response` JSON DEFAULT NULL,
    `receipt_path` VARCHAR(255) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_payment` (`payment_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_transaction` (`transaction_id`),
    KEY `idx_status` (`status`),
    KEY `idx_reference` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dues_outstanding` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED DEFAULT NULL,
    `lease_id` BIGINT UNSIGNED DEFAULT NULL,
    `dues_type` ENUM('rent', 'maintenance', 'penalty', 'utility', 'premium', 'other') NOT NULL,
    `due_amount` DECIMAL(12,2) NOT NULL,
    `paid_amount` DECIMAL(12,2) DEFAULT 0,
    `outstanding_amount` DECIMAL(12,2) GENERATED ALWAYS AS (`due_amount` - `paid_amount`) STORED,
    `due_date` DATE NOT NULL,
    `penalty_applicable` TINYINT(1) DEFAULT 0,
    `penalty_amount` DECIMAL(10,2) DEFAULT 0,
    `status` ENUM('pending', 'partial', 'paid', 'overdue', 'waived') NOT NULL DEFAULT 'pending',
    `remarks` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. NOTIFICATIONS
-- =====================================================

CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('email', 'sms', 'in_app', 'push', 'whatsapp') NOT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `data` JSON DEFAULT NULL,
    `status` ENUM('pending', 'sent', 'delivered', 'failed', 'read') NOT NULL DEFAULT 'pending',
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `failed_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_id` BIGINT UNSIGNED DEFAULT 0,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `channel` VARCHAR(50) NOT NULL,
    `recipient` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `status` ENUM('sent', 'failed', 'delivered', 'bounced') NOT NULL,
    `response_data` JSON DEFAULT NULL,
    `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_channel` (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. HRMS & PAYROLL
-- =====================================================

CREATE TABLE `hr_departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `parent_id` BIGINT UNSIGNED DEFAULT NULL,
    `head_id` BIGINT UNSIGNED DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hr_designations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `grade` VARCHAR(50) DEFAULT NULL,
    `level` INT DEFAULT 1,
    `min_pay_band` DECIMAL(10,2) DEFAULT NULL,
    `max_pay_band` DECIMAL(10,2) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hr_employees` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_code` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `gender` ENUM('male', 'female', 'other') DEFAULT NULL,
    `email` VARCHAR(255) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `department_id` BIGINT UNSIGNED DEFAULT NULL,
    `designation_id` BIGINT UNSIGNED DEFAULT NULL,
    `reporting_to` BIGINT UNSIGNED DEFAULT NULL,
    `date_of_joining` DATE NOT NULL,
    `employment_type` ENUM('permanent', 'contract', 'daily_wage', 'deputation') NOT NULL,
    `status` ENUM('active', 'on_leave', 'suspended', 'left') NOT NULL DEFAULT 'active',
    `probation_end_date` DATE DEFAULT NULL,
    `basic_salary` DECIMAL(10,2) DEFAULT NULL,
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `account_number` VARCHAR(50) DEFAULT NULL,
    `ifsc_code` VARCHAR(15) DEFAULT NULL,
    `pan_number` VARCHAR(10) DEFAULT NULL,
    `aadhaar_number` VARCHAR(14) DEFAULT NULL,
    `pf_number` VARCHAR(50) DEFAULT NULL,
    `esi_number` VARCHAR(50) DEFAULT NULL,
    `present_address` TEXT DEFAULT NULL,
    `permanent_address` TEXT DEFAULT NULL,
    `emergency_contact` VARCHAR(20) DEFAULT NULL,
    `documents` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_employee_code` (`employee_code`),
    UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hr_attendance` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `attendance_date` DATE NOT NULL,
    `check_in` TIME DEFAULT NULL,
    `check_out` TIME DEFAULT NULL,
    `working_hours` DECIMAL(5,2) DEFAULT 0,
    `status` ENUM('present', 'absent', 'half_day', 'leave', 'holiday', 'week_off') NOT NULL,
    `shift_type` VARCHAR(50) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_employee_date` (`employee_id`, `attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hr_leaves` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `leave_type` ENUM('casual', 'earned', 'sick', 'maternity', 'paternity', 'unpaid', 'comp_off', 'other') NOT NULL,
    `from_date` DATE NOT NULL,
    `to_date` DATE NOT NULL,
    `days_count` DECIMAL(5,2) NOT NULL,
    `reason` TEXT DEFAULT NULL,
    `status` ENUM('applied', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'applied',
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hr_leave_balances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `leave_type` VARCHAR(50) NOT NULL,
    `year` INT NOT NULL,
    `opening_balance` DECIMAL(5,2) DEFAULT 0,
    `allocated` DECIMAL(5,2) DEFAULT 0,
    `availed` DECIMAL(5,2) DEFAULT 0,
    `closing_balance` DECIMAL(5,2) GENERATED ALWAYS AS (`opening_balance` + `allocated` - `availed`) STORED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_employee_type_year` (`employee_id`, `leave_type`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hr_salary_structure` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `effective_from` DATE NOT NULL,
    `basic` DECIMAL(10,2) NOT NULL,
    `da` DECIMAL(10,2) DEFAULT 0,
    `hra` DECIMAL(10,2) DEFAULT 0,
    `conveyance` DECIMAL(10,2) DEFAULT 0,
    `special_allowance` DECIMAL(10,2) DEFAULT 0,
    `pf_employer` DECIMAL(10,2) DEFAULT 0,
    `esi_employer` DECIMAL(10,2) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hr_payroll` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` BIGINT UNSIGNED NOT NULL,
    `month` INT NOT NULL,
    `year` INT NOT NULL,
    `basic` DECIMAL(10,2) NOT NULL,
    `da` DECIMAL(10,2) DEFAULT 0,
    `hra` DECIMAL(10,2) DEFAULT 0,
    `conveyance` DECIMAL(10,2) DEFAULT 0,
    `special_allowance` DECIMAL(10,2) DEFAULT 0,
    `arrears` DECIMAL(10,2) DEFAULT 0,
    `bonus` DECIMAL(10,2) DEFAULT 0,
    `gross_earnings` DECIMAL(10,2) GENERATED ALWAYS AS (`basic` + `da` + `hra` + `conveyance` + `special_allowance` + `arrears` + `bonus`) STORED,
    `pf_employee` DECIMAL(10,2) DEFAULT 0,
    `esi_employee` DECIMAL(10,2) DEFAULT 0,
    `professional_tax` DECIMAL(8,2) DEFAULT 0,
    `tds` DECIMAL(10,2) DEFAULT 0,
    `loan_deduction` DECIMAL(10,2) DEFAULT 0,
    `other_deductions` DECIMAL(10,2) DEFAULT 0,
    `total_deductions` DECIMAL(10,2) GENERATED ALWAYS AS (`pf_employee` + `esi_employee` + `professional_tax` + `tds` + `loan_deduction` + `other_deductions`) STORED,
    `net_salary` DECIMAL(10,2) GENERATED ALWAYS AS (`basic` + `da` + `hra` + `conveyance` + `special_allowance` + `arrears` + `bonus` - `pf_employee` - `esi_employee` - `professional_tax` - `tds` - `loan_deduction` - `other_deductions`) STORED,
    `working_days` INT DEFAULT 0,
    `present_days` INT DEFAULT 0,
    `leave_days` INT DEFAULT 0,
    `lop_days` INT DEFAULT 0,
    `status` ENUM('draft', 'processed', 'paid') NOT NULL DEFAULT 'draft',
    `payslip_path` VARCHAR(255) DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_employee_month_year` (`employee_id`, `month`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 16. LAND SURVEY MODULE
-- =====================================================

CREATE TABLE `land_surveys` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `survey_number` VARCHAR(50) NOT NULL,
    `revenue_survey_no` VARCHAR(100) DEFAULT NULL,
    `khatian_no` VARCHAR(100) DEFAULT NULL,
    `plot_no` VARCHAR(100) DEFAULT NULL,
    `district` VARCHAR(100) NOT NULL,
    `block` VARCHAR(100) NOT NULL,
    `mouza` VARCHAR(100) NOT NULL,
    `thana_no` VARCHAR(50) DEFAULT NULL,
    `area_surveyed` DECIMAL(12,2) NOT NULL,
    `boundary_coordinates` JSON DEFAULT NULL COMMENT 'GeoJSON',
    `land_type` ENUM('agricultural', 'government_waste', 'forest', 'private', 'other') DEFAULT NULL,
    `possession_status` ENUM('free', 'encumbered', 'disputed', 'partial') DEFAULT NULL,
    `surveyor_id` BIGINT UNSIGNED DEFAULT NULL,
    `survey_date` DATE DEFAULT NULL,
    `report_path` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending', 'in_progress', 'completed', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    `remarks` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_survey` (`survey_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `survey_field_data` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `survey_id` BIGINT UNSIGNED NOT NULL,
    `surveyor_id` BIGINT UNSIGNED NOT NULL,
    `latitude` DECIMAL(10,8) NOT NULL,
    `longitude` DECIMAL(11,8) NOT NULL,
    `accuracy` DECIMAL(8,2) DEFAULT NULL,
    `photo_path` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_survey_id` (`survey_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 17. WORKFLOW ENGINE
-- =====================================================

CREATE TABLE `workflow_definitions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workflow_name` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_workflow` (`workflow_name`, `module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `workflow_steps` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workflow_id` BIGINT UNSIGNED NOT NULL,
    `step_name` VARCHAR(100) NOT NULL,
    `step_order` INT NOT NULL,
    `assigned_role` VARCHAR(100) NOT NULL,
    `action_type` ENUM('review', 'approve', 'reject', 'verify', 'escalate') NOT NULL,
    `sla_days` INT DEFAULT 5,
    `escaltion_role` VARCHAR(100) DEFAULT NULL,
    `is_terminal` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `workflow_instances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workflow_id` BIGINT UNSIGNED NOT NULL,
    `reference_type` VARCHAR(50) NOT NULL,
    `reference_id` BIGINT UNSIGNED NOT NULL,
    `current_step` INT NOT NULL DEFAULT 1,
    `status` ENUM('pending', 'in_progress', 'completed', 'rejected', 'escalated', 'cancelled') NOT NULL DEFAULT 'pending',
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `workflow_instance_steps` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `instance_id` BIGINT UNSIGNED NOT NULL,
    `step_id` BIGINT UNSIGNED NOT NULL,
    `assigned_to` BIGINT UNSIGNED DEFAULT NULL,
    `action` ENUM('pending', 'approved', 'rejected', 'escalated', 'forwarded', 'commented') NOT NULL DEFAULT 'pending',
    `comments` TEXT DEFAULT NULL,
    `action_at` DATETIME DEFAULT NULL,
    `sla_deadline` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 18. AUDIT LOGS
-- =====================================================

CREATE TABLE `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` BIGINT UNSIGNED DEFAULT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_module` (`module`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 19. SYSTEM SETTINGS
-- =====================================================

CREATE TABLE `system_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('string', 'json', 'boolean', 'number') DEFAULT 'string',
    `description` TEXT DEFAULT NULL,
    `updated_by` BIGINT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 20. WHATSAPP BOT
-- =====================================================

CREATE TABLE `whatsapp_interactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `phone_number` VARCHAR(20) NOT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `message_type` ENUM('text', 'button', 'list', 'media', 'location') NOT NULL,
    `message_content` TEXT DEFAULT NULL,
    `intent` VARCHAR(100) DEFAULT NULL,
    `response_sent` TEXT DEFAULT NULL,
    `status` ENUM('received', 'processed', 'responded', 'failed') NOT NULL DEFAULT 'received',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_phone` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ADD FOREIGN KEYS
-- =====================================================

ALTER TABLE `user_roles`
    ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `role_permissions`
    ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_sessions`
    ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;