-- =====================================================
-- JIADA Portal - Complete Database Schema with RBAC
-- Jharkhand Industrial Area Development Authority
-- Roles: SuperAdmin, Admin (JIADA HQ), JIADA Officers, 4 Regional Officers, Users, Vendors
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `jiada_portal` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `jiada_portal`;

-- =====================================================
-- 1. REGIONS (4 Regions for RBAC)
-- =====================================================
CREATE TABLE IF NOT EXISTS `regions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(10) NOT NULL,
    `headquarters` VARCHAR(100) NOT NULL,
    `districts` JSON DEFAULT NULL COMMENT 'Districts covered',
    `address` TEXT DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `regional_officer_id` BIGINT UNSIGNED DEFAULT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert 4 regions
INSERT INTO `regions` (`name`, `code`, `headquarters`, `districts`) VALUES
('Ranchi Region', 'RANCHI', 'Ranchi', '["Ranchi", "Khunti", "Gumla", "Simdega", "Lohardaga"]'),
('Jamshedpur Region', 'JAMSHEDPUR', 'Jamshedpur', '["East Singhbhum", "West Singhbhum", "Seraikela-Kharsawan"]'),
('Dhanbad Region', 'DHANBAD', 'Dhanbad', '["Dhanbad", "Bokaro", "Giridih", "Deoghar", "Dumka"]'),
('Bokaro Region', 'BOKARO', 'Bokaro Steel City', '["Bokaro", "Hazaribagh", "Ramgarh", "Koderma", "Chatra"]');

-- =====================================================
-- 2. USERS WITH REGION & ROLE-BASED ACCESS
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_type` ENUM('superadmin', 'admin', 'jiada_officer', 'regional_officer', 'applicant', 'allottee', 'vendor', 'employee') NOT NULL DEFAULT 'applicant',
    `region_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'For regional officers',
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    `aadhaar_number` VARCHAR(14) DEFAULT NULL,
    `pan_number` VARCHAR(10) DEFAULT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `employee_code` VARCHAR(50) DEFAULT NULL,
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
    `notification_preferences` JSON DEFAULT NULL,
    `permissions` JSON DEFAULT NULL COMMENT 'Override permissions',
    `created_by` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_mobile` (`mobile`),
    UNIQUE KEY `unique_email` (`email`),
    KEY `idx_user_type` (`user_type`),
    KEY `idx_region_id` (`region_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default SuperAdmin
INSERT INTO `users` (`user_type`, `name`, `email`, `mobile`, `password`, `status`, `mobile_verified`, `email_verified`) VALUES
('superadmin', 'JIADA Super Administrator', 'admin@jiada.in', '9999999999', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 1, 1);

-- =====================================================
-- 3. DEPARTMENTS & SECTIONS (RBAC)
-- =====================================================
CREATE TABLE IF NOT EXISTS `departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `parent_id` BIGINT UNSIGNED DEFAULT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `head_id` BIGINT UNSIGNED DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. MODULE PERMISSIONS WITH ROLE-BASED ACCESS
-- =====================================================
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_type` VARCHAR(50) NOT NULL,
    `module` VARCHAR(100) NOT NULL,
    `can_view` TINYINT(1) DEFAULT 0,
    `can_create` TINYINT(1) DEFAULT 0,
    `can_edit` TINYINT(1) DEFAULT 0,
    `can_delete` TINYINT(1) DEFAULT 0,
    `can_approve` TINYINT(1) DEFAULT 0,
    `can_export` TINYINT(1) DEFAULT 0,
    `can_view_reports` TINYINT(1) DEFAULT 0,
    `region_restricted` TINYINT(1) DEFAULT 0 COMMENT 'Only own region data',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_role_module` (`role_type`, `module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default permissions for all roles
INSERT INTO `role_permissions` (`role_type`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_approve`, `can_export`, `can_view_reports`, `region_restricted`) VALUES
-- SuperAdmin - Full access everywhere
('superadmin', 'dashboard', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'land_allotment', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'payments', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'users', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'grievances', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'utilities', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'court_cases', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'lease_management', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'hrms', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'reports', 1, 1, 1, 1, 1, 1, 1, 0),
('superadmin', 'settings', 1, 1, 1, 1, 1, 1, 1, 0),

-- Admin (JIADA HQ) - Full access
('admin', 'dashboard', 1, 1, 1, 1, 1, 1, 1, 0),
('admin', 'land_allotment', 1, 1, 1, 1, 1, 1, 1, 0),
('admin', 'payments', 1, 1, 1, 1, 1, 1, 1, 0),
('admin', 'users', 1, 1, 1, 0, 0, 1, 1, 0),
('admin', 'grievances', 1, 1, 1, 1, 1, 1, 1, 0),
('admin', 'utilities', 1, 1, 1, 1, 1, 1, 1, 0),
('admin', 'court_cases', 1, 1, 1, 0, 0, 1, 1, 0),
('admin', 'lease_management', 1, 1, 1, 1, 1, 1, 1, 0),
('admin', 'reports', 1, 0, 0, 0, 0, 1, 1, 0),

-- JIADA Officer - Department level
('jiada_officer', 'dashboard', 1, 1, 1, 0, 0, 1, 1, 0),
('jiada_officer', 'land_allotment', 1, 1, 1, 0, 0, 1, 1, 0),
('jiada_officer', 'payments', 1, 0, 0, 0, 0, 1, 1, 0),
('jiada_officer', 'grievances', 1, 1, 1, 0, 1, 0, 1, 0),
('jiada_officer', 'utilities', 1, 1, 1, 0, 1, 0, 1, 0),
('jiada_officer', 'court_cases', 1, 1, 1, 0, 0, 0, 1, 0),
('jiada_officer', 'lease_management', 1, 1, 1, 0, 0, 0, 1, 0),

-- Regional Officer - Own region only
('regional_officer', 'dashboard', 1, 1, 1, 0, 1, 1, 1, 1),
('regional_officer', 'land_allotment', 1, 1, 1, 0, 1, 1, 1, 1),
('regional_officer', 'payments', 1, 0, 0, 0, 0, 1, 1, 1),
('regional_officer', 'grievances', 1, 1, 1, 0, 1, 1, 1, 1),
('regional_officer', 'utilities', 1, 1, 1, 0, 1, 1, 1, 1),
('regional_officer', 'court_cases', 1, 1, 1, 0, 0, 0, 1, 1),
('regional_officer', 'lease_management', 1, 1, 1, 0, 1, 0, 1, 1),

-- Applicant/User - View only, create
('applicant', 'dashboard', 1, 0, 0, 0, 0, 0, 0, 1),
('applicant', 'land_allotment', 1, 1, 0, 0, 0, 0, 0, 1),
('applicant', 'payments', 1, 0, 0, 0, 0, 0, 0, 1),
('applicant', 'grievances', 1, 1, 0, 0, 0, 0, 0, 1),
('applicant', 'utilities', 1, 1, 0, 0, 0, 0, 0, 1),

-- Vendor - View and update assigned tickets
('vendor', 'dashboard', 1, 0, 0, 0, 0, 0, 0, 1),
('vendor', 'utilities', 1, 0, 1, 0, 0, 0, 0, 1);

-- =====================================================
-- 5. OTP & AUTH (Updated)
-- =====================================================
CREATE TABLE IF NOT EXISTS `otp_requests` (
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
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `token_blacklist` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token_hash` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. DASHBOARD WIDGETS CONFIG
-- =====================================================
CREATE TABLE IF NOT EXISTS `dashboard_widgets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_type` VARCHAR(50) NOT NULL,
    `widget_key` VARCHAR(100) NOT NULL,
    `widget_title` VARCHAR(100) NOT NULL,
    `widget_type` ENUM('stat_card', 'chart', 'table', 'list', 'map') NOT NULL,
    `position` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `config` JSON DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_role_widget` (`role_type`, `widget_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default dashboard widgets for each role
INSERT INTO `dashboard_widgets` (`role_type`, `widget_key`, `widget_title`, `widget_type`, `position`, `config`) VALUES
-- SuperAdmin Dashboard
('superadmin', 'total_users', 'Total Users', 'stat_card', 1, '{"icon": "users", "color": "blue", "api": "/api/dashboard/stats/total_users"}'),
('superadmin', 'total_applications', 'Total Applications', 'stat_card', 2, '{"icon": "file-alt", "color": "green", "api": "/api/dashboard/stats/total_applications"}'),
('superadmin', 'revenue_collected', 'Revenue Collected', 'stat_card', 3, '{"icon": "rupee-sign", "color": "orange", "api": "/api/dashboard/stats/revenue_collected"}'),
('superadmin', 'pending_approvals', 'Pending Approvals', 'stat_card', 4, '{"icon": "clock", "color": "red", "api": "/api/dashboard/stats/pending_approvals"}'),
('superadmin', 'region_wise_stats', 'Region-wise Statistics', 'chart', 5, '{"type": "bar"}'),
('superadmin', 'recent_activities', 'Recent Activities', 'list', 6, '{"limit": 10}'),

-- Admin Dashboard
('admin', 'total_applications', 'Total Applications', 'stat_card', 1, '{"icon": "file-alt", "color": "blue"}'),
('admin', 'pending_approvals', 'Pending Approvals', 'stat_card', 2, '{"icon": "clock", "color": "orange"}'),
('admin', 'revenue_today', "Today's Revenue", 'stat_card', 3, '{"icon": "rupee-sign", "color": "green"}'),
('admin', 'open_grievances', 'Open Grievances', 'stat_card', 4, '{"icon": "exclamation", "color": "red"}'),

-- Regional Officer Dashboard
('regional_officer', 'region_applications', 'Region Applications', 'stat_card', 1, '{"icon": "map", "color": "blue"}'),
('regional_officer', 'region_revenue', 'Region Revenue', 'stat_card', 2, '{"icon": "rupee-sign", "color": "green"}'),
('regional_officer', 'region_grievances', 'Region Grievances', 'stat_card', 3, '{"icon": "comment", "color": "orange"}'),
('regional_officer', 'pending_tasks', 'Pending Tasks', 'stat_card', 4, '{"icon": "tasks", "color": "red"}'),

-- Applicant Dashboard
('applicant', 'my_applications', 'My Applications', 'stat_card', 1, '{"icon": "file", "color": "blue"}'),
('applicant', 'pending_payments', 'Pending Payments', 'stat_card', 2, '{"icon": "credit-card", "color": "orange"}'),
('applicant', 'active_tickets', 'Active Tickets', 'stat_card', 3, '{"icon": "tools", "color": "green"}');

-- =====================================================
-- 7. ALL CORE MODULE TABLES (Preserved from original)
-- =====================================================

-- Land Bank
CREATE TABLE IF NOT EXISTS `land_banks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plot_number` VARCHAR(50) NOT NULL,
    `survey_number` VARCHAR(100) DEFAULT NULL,
    `plot_type` ENUM('industrial', 'commercial', 'flatted_factory', 'warehouse', 'mixed_use') NOT NULL,
    `area_sqm` DECIMAL(12,2) NOT NULL,
    `zone` VARCHAR(100) DEFAULT NULL,
    `sector` VARCHAR(100) DEFAULT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `location_lat` DECIMAL(10,8) DEFAULT NULL,
    `location_lng` DECIMAL(11,8) DEFAULT NULL,
    `boundary_coordinates` JSON DEFAULT NULL,
    `base_price_per_sqm` DECIMAL(12,2) DEFAULT NULL,
    `status` ENUM('available', 'allotted', 'reserved', 'blocked', 'under_development') NOT NULL DEFAULT 'available',
    `infrastructure_type` ENUM('raw_land', 'developed', 'plug_play') DEFAULT 'raw_land',
    `utilities_available` JSON DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_plot` (`plot_number`),
    KEY `idx_status` (`status`),
    KEY `idx_region_id` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Land Applications
CREATE TABLE IF NOT EXISTS `land_applications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_number` VARCHAR(50) NOT NULL,
    `reference_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `application_type` ENUM('new_allotment', 'bidding') NOT NULL,
    `purpose` VARCHAR(50) NOT NULL,
    `proposed_investment` DECIMAL(15,2) DEFAULT NULL,
    `employment_generation` INT DEFAULT NULL,
    `project_description` TEXT DEFAULT NULL,
    `documents` JSON DEFAULT NULL,
    `processing_fee_amount` DECIMAL(10,2) DEFAULT NULL,
    `processing_fee_paid` TINYINT(1) DEFAULT 0,
    `status` ENUM('draft', 'submitted', 'under_scrutiny', 'approved', 'rejected', 'provisional_allotted', 'allotted', 'cancelled') NOT NULL DEFAULT 'draft',
    `scrutiny_remarks` TEXT DEFAULT NULL,
    `assigned_to` BIGINT UNSIGNED DEFAULT NULL,
    `approved_by` BIGINT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `rejected_reason` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_application` (`application_number`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_region_id` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Utility Tickets
CREATE TABLE IF NOT EXISTS `utility_tickets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED DEFAULT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `utility_type` ENUM('water', 'electricity', 'sewage', 'road', 'street_light', 'other') NOT NULL,
    `priority` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    `subject` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `attachments` JSON DEFAULT NULL,
    `assigned_to` BIGINT UNSIGNED DEFAULT NULL,
    `vendor_id` BIGINT UNSIGNED DEFAULT NULL,
    `status` ENUM('open', 'assigned', 'in_progress', 'resolved', 'escalated', 'closed', 'reopened') NOT NULL DEFAULT 'open',
    `escalation_count` INT DEFAULT 0,
    `resolved_at` DATETIME DEFAULT NULL,
    `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
    `resolution_notes` TEXT DEFAULT NULL,
    `feedback_rating` TINYINT DEFAULT NULL,
    `feedback_comment` TEXT DEFAULT NULL,
    `sla_deadline` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_ticket` (`ticket_number`),
    KEY `idx_region_id` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grievances
CREATE TABLE IF NOT EXISTS `grievances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `grievance_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
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
    `sla_deadline` DATETIME DEFAULT NULL,
    `escalated_to` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_grievance` (`grievance_number`),
    KEY `idx_region_id` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments
CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id` VARCHAR(50) NOT NULL,
    `transaction_id` VARCHAR(100) DEFAULT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `payment_type` ENUM('application_fee', 'processing_fee', 'emd', 'premium', 'rent', 'maintenance', 'utility', 'penalty', 'scrutiny_fee', 'six_month_rent', 'other') NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` BIGINT UNSIGNED DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `gst_amount` DECIMAL(10,2) DEFAULT 0,
    `total_amount` DECIMAL(12,2) NOT NULL,
    `payment_mode` ENUM('upi', 'net_banking', 'credit_card', 'debit_card', 'wallet', 'dd', 'online') NOT NULL,
    `status` ENUM('pending', 'initiated', 'success', 'failed', 'cancelled', 'refunded', 'timeout') NOT NULL DEFAULT 'pending',
    `gateway_response` JSON DEFAULT NULL,
    `receipt_path` VARCHAR(255) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_payment` (`payment_id`),
    KEY `idx_region_id` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dues Outstanding
CREATE TABLE IF NOT EXISTS `dues_outstanding` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `dues_type` VARCHAR(50) NOT NULL,
    `due_amount` DECIMAL(12,2) NOT NULL,
    `paid_amount` DECIMAL(12,2) DEFAULT 0,
    `due_date` DATE NOT NULL,
    `status` ENUM('pending', 'partial', 'paid', 'overdue', 'waived') NOT NULL DEFAULT 'pending',
    PRIMARY KEY (`id`),
    KEY `idx_region_id` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
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
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
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
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mobile App Tokens (For WebView auto-login)
CREATE TABLE IF NOT EXISTS `mobile_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `device_name` VARCHAR(100) DEFAULT NULL,
    `fcm_token` VARCHAR(255) DEFAULT NULL COMMENT 'Firebase push token',
    `jwt_token` TEXT DEFAULT NULL,
    `refresh_token` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_sync` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_device` (`user_id`, `device_id`),
    KEY `idx_fcm` (`fcm_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mobile App Data Cache (Images & text stored locally)
CREATE TABLE IF NOT EXISTS `mobile_cached_data` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `data_type` ENUM('image', 'document', 'text', 'notification') NOT NULL,
    `data_key` VARCHAR(100) NOT NULL,
    `data_value` TEXT DEFAULT NULL,
    `file_path` VARCHAR(255) DEFAULT NULL,
    `is_synced` TINYINT(1) DEFAULT 0,
    `synced_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_key_user` (`user_id`, `data_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Court Cases (for tracking)
CREATE TABLE IF NOT EXISTS `court_cases` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_number` VARCHAR(100) NOT NULL,
    `cnr_number` VARCHAR(50) DEFAULT NULL,
    `court_name` VARCHAR(255) NOT NULL,
    `case_type` VARCHAR(50) NOT NULL,
    `subject` VARCHAR(50) NOT NULL,
    `title` VARCHAR(500) NOT NULL,
    `petitioner_name` VARCHAR(255) NOT NULL,
    `respondent_name` VARCHAR(255) NOT NULL,
    `case_status` VARCHAR(50) NOT NULL DEFAULT 'filed',
    `next_hearing_date` DATE DEFAULT NULL,
    `last_hearing_order` TEXT DEFAULT NULL,
    `documents` JSON DEFAULT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lease Records
CREATE TABLE IF NOT EXISTS `lease_records` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lease_number` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plot_id` BIGINT UNSIGNED NOT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `lease_start_date` DATE NOT NULL,
    `lease_end_date` DATE NOT NULL,
    `annual_rent` DECIMAL(12,2) NOT NULL,
    `maintenance_charges` DECIMAL(10,2) DEFAULT 0,
    `security_deposit` DECIMAL(12,2) DEFAULT 0,
    `status` ENUM('active', 'expired', 'terminated', 'surrendered', 'renewed') NOT NULL DEFAULT 'active',
    `document_path` VARCHAR(255) DEFAULT NULL,
    `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_region_id` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- HRMS Tables
CREATE TABLE IF NOT EXISTS `hr_employees` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_code` VARCHAR(50) NOT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `department_id` BIGINT UNSIGNED DEFAULT NULL,
    `region_id` BIGINT UNSIGNED DEFAULT NULL,
    `date_of_joining` DATE NOT NULL,
    `status` ENUM('active', 'on_leave', 'suspended', 'left') NOT NULL DEFAULT 'active',
    `basic_salary` DECIMAL(10,2) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Settings
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` VARCHAR(20) DEFAULT 'string',
    `description` TEXT DEFAULT NULL,
    `updated_by` BIGINT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp Interactions
CREATE TABLE IF NOT EXISTS `whatsapp_interactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `phone_number` VARCHAR(20) NOT NULL,
    `user_id` BIGINT UNSIGNED DEFAULT NULL,
    `message_type` VARCHAR(50) NOT NULL,
    `message_content` TEXT DEFAULT NULL,
    `intent` VARCHAR(100) DEFAULT NULL,
    `response_sent` TEXT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'received',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign keys for region
ALTER TABLE `users` ADD CONSTRAINT `fk_user_region` FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE SET NULL;
ALTER TABLE `land_banks` ADD CONSTRAINT `fk_land_region` FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE SET NULL;
ALTER TABLE `land_applications` ADD CONSTRAINT `fk_app_region` FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE SET NULL;

COMMIT;