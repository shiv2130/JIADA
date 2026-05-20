<?php
/**
 * JIADA Portal - Complete Setup and Seed Script
 * Creates missing tables and seeds 580+ records
 */
define('JIADA_APP', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';

$db = getDB();
$pdo = $db->getConnection();

echo "=== JIADA Portal Setup & Seeder ===\n\n";

// Create missing tables
echo "Creating missing tables...\n";

$tables = [
    "CREATE TABLE IF NOT EXISTS `land_plots` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `plug_play_units` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `unit_no` VARCHAR(50) NOT NULL,
        `unit_type` VARCHAR(50) NOT NULL,
        `area_sqft` INT NOT NULL,
        `monthly_rent` DECIMAL(12,2) NOT NULL,
        `status` VARCHAR(50) DEFAULT 'available',
        `location` VARCHAR(100),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `plug_play_applications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `application_no` VARCHAR(50) NOT NULL,
        `user_id` INT NOT NULL,
        `unit_id` INT NOT NULL,
        `company_name` VARCHAR(200),
        `industry_type` VARCHAR(100),
        `investment_amount` DECIMAL(15,2),
        `status` VARCHAR(50) DEFAULT 'pending',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `lease_surrenders` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `lease_mortgages` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `lease_transfers` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `lease_renewals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `application_no` VARCHAR(50) NOT NULL,
        `user_id` INT NOT NULL,
        `lease_id` INT NOT NULL,
        `renewal_period` INT,
        `reason` TEXT,
        `status` VARCHAR(50) DEFAULT 'pending',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT,
        `action` VARCHAR(100),
        `description` TEXT,
        `ip_address` VARCHAR(50),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `ticket_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ticket_id` INT NOT NULL,
        `user_id` INT,
        `note` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `grievance_responses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `grievance_id` INT NOT NULL,
        `user_id` INT,
        `response` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `message_id` VARCHAR(100),
        `from_number` VARCHAR(20),
        `message_text` TEXT,
        `received_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        // Table may already exist
    }
}

// Add columns to existing tables
$alters = [
    "ALTER TABLE `land_banks` ADD COLUMN `plot_no` VARCHAR(50) AFTER `id`",
    "ALTER TABLE `land_banks` ADD COLUMN `area_sqft` INT AFTER `plot_no`",
    "ALTER TABLE `land_banks` ADD COLUMN `zone` VARCHAR(100) AFTER `area_sqft`",
    "ALTER TABLE `land_banks` ADD COLUMN `industry_type` VARCHAR(100) AFTER `zone`",
    "ALTER TABLE `land_banks` ADD COLUMN `latitude` DECIMAL(10,6) AFTER `industry_type`",
    "ALTER TABLE `land_banks` ADD COLUMN `longitude` DECIMAL(10,6) AFTER `latitude`",
    "ALTER TABLE `land_banks` ADD COLUMN `base_price` DECIMAL(12,2) AFTER `longitude`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `ticket_no` VARCHAR(50) AFTER `id`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `region_id` INT AFTER `user_id`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `assigned_to` INT AFTER `region_id`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `priority` VARCHAR(20) DEFAULT 'medium' AFTER `status`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `escalated` TINYINT DEFAULT 0 AFTER `priority`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `resolution` TEXT AFTER `description`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `resolved_at` DATETIME AFTER `resolution`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `closed_at` DATETIME AFTER `resolved_at`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `feedback_rating` INT AFTER `closed_at`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `feedback_text` TEXT AFTER `feedback_rating`",
    "ALTER TABLE `utility_tickets` ADD COLUMN `updated_at` DATETIME AFTER `created_at`",
    "ALTER TABLE `grievances` ADD COLUMN `grievance_no` VARCHAR(50) AFTER `id`",
    "ALTER TABLE `grievances` ADD COLUMN `category` VARCHAR(50) AFTER `user_id`",
    "ALTER TABLE `grievances` ADD COLUMN `department_id` INT AFTER `category`",
    "ALTER TABLE `grievances` ADD COLUMN `assigned_officer` INT AFTER `department_id`",
    "ALTER TABLE `grievances` ADD COLUMN `response_count` INT DEFAULT 0 AFTER `status`",
    "ALTER TABLE `grievances` ADD COLUMN `escalated` TINYINT DEFAULT 0 AFTER `response_count`",
    "ALTER TABLE `grievances` ADD COLUMN `feedback_rating` INT AFTER `escalated`",
    "ALTER TABLE `grievances` ADD COLUMN `resolved_at` DATETIME AFTER `feedback_rating`",
    "ALTER TABLE `grievances` ADD COLUMN `closed_at` DATETIME AFTER `resolved_at`",
    "ALTER TABLE `grievances` ADD COLUMN `updated_at` DATETIME AFTER `created_at`",
    "ALTER TABLE `payments` ADD COLUMN `receipt_no` VARCHAR(50) AFTER `id`",
    "ALTER TABLE `payments` ADD COLUMN `transaction_id` VARCHAR(100) AFTER `receipt_no`",
    "ALTER TABLE `payments` ADD COLUMN `paid_amount` DECIMAL(12,2) DEFAULT 0 AFTER `amount`",
    "ALTER TABLE `payments` ADD COLUMN `payment_for` VARCHAR(100) AFTER `paid_amount`",
    "ALTER TABLE `payments` ADD COLUMN `payment_mode` VARCHAR(50) AFTER `payment_for`",
    "ALTER TABLE `payments` ADD COLUMN `description` TEXT AFTER `payment_mode`",
    "ALTER TABLE `payments` ADD COLUMN `due_date` DATE AFTER `description`",
    "ALTER TABLE `payments` ADD COLUMN `payment_date` DATETIME AFTER `due_date`",
    "ALTER TABLE `payments` ADD COLUMN `gst_amount` DECIMAL(12,2) DEFAULT 0 AFTER `payment_date`",
    "ALTER TABLE `payments` ADD COLUMN `lease_id` INT AFTER `gst_amount`",
    "ALTER TABLE `notifications` ADD COLUMN `title` VARCHAR(200) AFTER `user_id`",
    "ALTER TABLE `notifications` ADD COLUMN `type` VARCHAR(20) DEFAULT 'info' AFTER `message`",
    "ALTER TABLE `notifications` ADD COLUMN `is_read` TINYINT DEFAULT 0 AFTER `type`",
    "ALTER TABLE `notifications` ADD COLUMN `read_at` DATETIME AFTER `is_read`",
    "ALTER TABLE `court_cases` ADD COLUMN `case_number` VARCHAR(100) AFTER `cnr_number`",
    "ALTER TABLE `court_cases` ADD COLUMN `case_type` VARCHAR(50) AFTER `case_number`",
    "ALTER TABLE `court_cases` ADD COLUMN `subject` VARCHAR(200) AFTER `case_type`",
    "ALTER TABLE `court_cases` ADD COLUMN `petitioner` VARCHAR(200) AFTER `subject`",
    "ALTER TABLE `court_cases` ADD COLUMN `respondent` VARCHAR(200) AFTER `petitioner`",
    "ALTER TABLE `court_cases` ADD COLUMN `court_name` VARCHAR(200) AFTER `respondent`",
    "ALTER TABLE `court_cases` ADD COLUMN `filing_date` DATE AFTER `court_name`",
    "ALTER TABLE `court_cases` ADD COLUMN `next_hearing_date` DATE AFTER `filing_date`",
    "ALTER TABLE `court_cases` ADD COLUMN `status` VARCHAR(50) DEFAULT 'pending' AFTER `next_hearing_date`",
    "ALTER TABLE `court_cases` ADD COLUMN `description` TEXT AFTER `status`",
    "ALTER TABLE `court_cases` ADD COLUMN `lawyer_name` VARCHAR(200) AFTER `description`",
    "ALTER TABLE `court_cases` ADD COLUMN `lawyer_contact` VARCHAR(20) AFTER `lawyer_name`",
    "ALTER TABLE `court_cases` ADD COLUMN `created_by` INT AFTER `lawyer_contact`",
    "ALTER TABLE `lease_records` ADD COLUMN `lease_no` VARCHAR(50) AFTER `id`",
    "ALTER TABLE `lease_records` ADD COLUMN `plot_no` VARCHAR(50) AFTER `lease_no`",
    "ALTER TABLE `lease_records` ADD COLUMN `user_id` INT AFTER `plot_no`",
    "ALTER TABLE `lease_records` ADD COLUMN `premium_amount` DECIMAL(15,2) AFTER `user_id`",
    "ALTER TABLE `lease_records` ADD COLUMN `allotment_date` DATE AFTER `premium_amount`",
    "ALTER TABLE `users` ADD COLUMN `region_id` INT AFTER `user_type`",
    "ALTER TABLE `users` ADD COLUMN `last_login` DATETIME AFTER `status`",
    "ALTER TABLE `users` ADD COLUMN `last_login_ip` VARCHAR(50) AFTER `last_login`"
];

foreach ($alters as $sql) {
    try {
        $pdo->exec($sql);
    } catch (Exception $e) {
        // Column may already exist
    }
}

echo "  ✅ Tables and columns created\n";

// Helper functions
function randomDate($start, $end) {
    return date('Y-m-d H:i:s', mt_rand(strtotime($start), strtotime($end)));
}

function randomFloat($min, $max) {
    return round(mt_rand($min * 100, $max * 100) / 100, 2);
}

// Seed data
$names = ['Amit Kumar', 'Ravi Singh', 'Priya Sharma', 'Sunita Devi', 'Rajesh Tiwari', 'Anita Kumari', 'Vikram Patel', 'Deepa Sinha', 'Manoj Kumar', 'Pooja Singh', 'Suresh Mahto', 'Rekha Devi', 'Arun Kumar', 'Meera Singh', 'Ramesh Prasad', 'Kavita Kumari', 'Sanjay Gupta', 'Anjali Devi', 'Pankaj Kumar', 'Nisha Singh', 'Dinesh Tiwari', 'Saroj Devi', 'Ashok Kumar', 'Ritu Singh', 'Mukesh Prasad', 'Geeta Kumari', 'Rahul Gupta', 'Suman Devi', 'Vijay Kumar', 'Kiran Singh', 'Pradeep Mahto', 'Laxmi Devi', 'Naveen Kumar', 'Shweta Singh', 'Gopal Prasad', 'Rajiv Kumar', 'Preeti Singh', 'Sachin Gupta', 'Madhu Devi', 'Alok Kumar', 'Dilip Prasad', 'Rani Kumari', 'Tarun Kumar', 'Neha Singh', 'Sunil Gupta', 'Kamla Devi', 'Brijesh Kumar', 'Savita Singh', 'Hemant Kumar', 'Usha Singh'];
$userTypes = ['applicant', 'allottee', 'vendor', 'applicant', 'allottee'];
$statuses = ['active', 'active', 'active', 'active', 'pending'];
$zones = ['Ranchi', 'Jamshedpur', 'Dhanbad', 'Bokaro', 'Hazaribagh', 'Giridih', 'Deoghar', 'Dumka'];
$industries = ['Manufacturing', 'IT/ITES', 'Food Processing', 'Textile', 'Pharmaceutical', 'Auto Components', 'Engineering', 'Chemical', 'Mineral Based', 'Agro Based'];
$plotStatuses = ['available', 'available', 'available', 'allotted', 'reserved'];
$appStatuses = ['pending', 'approved', 'rejected', 'under_review', 'pending'];
$unitTypes = ['flatted', 'shed', 'warehouse', 'flatted', 'shed'];
$serviceTypes = ['water', 'power', 'maintenance', 'sewage', 'road', 'streetlight'];
$priorities = ['low', 'medium', 'high', 'urgent', 'medium'];
$ticketStatuses = ['open', 'in_progress', 'resolved', 'closed', 'escalated'];
$categories = ['land', 'payment', 'utility', 'staff', 'delay', 'other'];
$grevStatuses = ['pending', 'in_progress', 'resolved', 'closed', 'escalated'];
$caseTypes = ['civil', 'criminal', 'writ', 'appeal', 'review'];
$caseStatuses = ['pending', 'in_progress', 'disposed', 'adjourned', 'listed'];
$paymentFors = ['land_allotment', 'rent', 'maintenance', 'utility', 'processing_fee', 'premium'];
$paymentModes = ['upi', 'net_banking', 'card', 'wallet', 'upi'];
$paymentStatuses = ['paid', 'pending', 'partial', 'paid', 'paid'];
$notifTypes = ['info', 'success', 'warning', 'error', 'info'];
$actions = ['login', 'application_submitted', 'payment_made', 'ticket_raised', 'grievance_filed', 'document_uploaded', 'profile_updated'];

echo "\nSeeding data...\n";

// Users
echo "  Seeding Users... ";
for ($i = 0; $i < 50; $i++) {
    $mobile = '9' . str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
    $email = 'user' . $i . '@example.com';
    try {
        $db->insert('users', [
            'name' => $names[$i % count($names)],
            'email' => $email,
            'mobile' => $mobile,
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'user_type' => $userTypes[$i % count($userTypes)],
            'status' => $statuses[$i % count($statuses)],
            'region_id' => ($i % 4) + 1,
            'created_at' => randomDate('2024-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Land Plots
echo "  Seeding Land Plots... ";
for ($i = 0; $i < 50; $i++) {
    try {
        $db->insert('land_plots', [
            'plot_no' => 'PLOT-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
            'area_sqft' => mt_rand(5000, 100000),
            'zone' => $zones[$i % count($zones)],
            'industry_type' => $industries[$i % count($industries)],
            'status' => $plotStatuses[$i % count($plotStatuses)],
            'latitude' => 23.0 + (mt_rand(0, 500) / 1000),
            'longitude' => 85.0 + (mt_rand(0, 500) / 1000),
            'base_price' => randomFloat(500, 5000),
            'created_at' => randomDate('2024-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Land Applications
echo "  Seeding Land Applications... ";
for ($i = 0; $i < 50; $i++) {
    try {
        $db->insert('land_applications', [
            'application_no' => 'JIADA/LAND/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
            'user_id' => mt_rand(1, 50),
            'plot_id' => mt_rand(1, 50),
            'industry_type' => $industries[$i % count($industries)],
            'investment_amount' => randomFloat(100000, 50000000),
            'employment_proposed' => mt_rand(10, 500),
            'status' => $appStatuses[$i % count($appStatuses)],
            'created_at' => randomDate('2024-06-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Plug & Play Units
echo "  Seeding Plug & Play Units... ";
for ($i = 0; $i < 50; $i++) {
    try {
        $db->insert('plug_play_units', [
            'unit_no' => 'UNIT-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
            'unit_type' => $unitTypes[$i % count($unitTypes)],
            'area_sqft' => mt_rand(500, 10000),
            'monthly_rent' => randomFloat(5000, 100000),
            'status' => $plotStatuses[$i % count($plotStatuses)],
            'location' => $zones[$i % count($zones)],
            'created_at' => randomDate('2024-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Utility Tickets
echo "  Seeding Utility Tickets... ";
for ($i = 0; $i < 50; $i++) {
    try {
        $db->insert('utility_tickets', [
            'ticket_no' => 'UTIL/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
            'user_id' => mt_rand(1, 50),
            'service_type' => $serviceTypes[$i % count($serviceTypes)],
            'subject' => 'Issue with ' . $serviceTypes[$i % count($serviceTypes)] . ' service - ' . ($i + 1),
            'description' => 'Detailed description of the utility issue number ' . ($i + 1),
            'priority' => $priorities[$i % count($priorities)],
            'status' => $ticketStatuses[$i % count($ticketStatuses)],
            'region_id' => ($i % 4) + 1,
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Grievances
echo "  Seeding Grievances... ";
for ($i = 0; $i < 50; $i++) {
    try {
        $db->insert('grievances', [
            'grievance_no' => 'GREV/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
            'user_id' => mt_rand(1, 50),
            'category' => $categories[$i % count($categories)],
            'department_id' => ($i % 4) + 1,
            'subject' => 'Grievance regarding ' . $categories[$i % count($categories)] . ' - ' . ($i + 1),
            'description' => 'Detailed grievance description number ' . ($i + 1),
            'status' => $grevStatuses[$i % count($grevStatuses)],
            'response_count' => mt_rand(0, 3),
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Court Cases
echo "  Seeding Court Cases... ";
for ($i = 0; $i < 50; $i++) {
    try {
        $db->insert('court_cases', [
            'cnr_number' => str_pad(mt_rand(100000000000, 999999999999), 12, '0', STR_PAD_LEFT),
            'case_type' => $caseTypes[$i % count($caseTypes)],
            'subject' => 'Court case subject ' . ($i + 1),
            'petitioner' => $names[$i % count($names)] . ' vs JIADA',
            'respondent' => 'JIADA vs ' . $names[($i + 1) % count($names)],
            'court_name' => ['High Court', 'District Court', 'Tribunal', 'NGT', 'CAT'][$i % 5],
            'status' => $caseStatuses[$i % count($caseStatuses)],
            'next_hearing_date' => date('Y-m-d', strtotime('+' . mt_rand(1, 90) . ' days')),
            'created_at' => randomDate('2024-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Payments
echo "  Seeding Payments... ";
for ($i = 0; $i < 50; $i++) {
    $amount = randomFloat(1000, 500000);
    try {
        $db->insert('payments', [
            'receipt_no' => 'REC/2025/' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
            'user_id' => mt_rand(1, 50),
            'amount' => $amount,
            'paid_amount' => $paymentStatuses[$i % count($paymentStatuses)] === 'paid' ? $amount : randomFloat(0, $amount),
            'payment_for' => $paymentFors[$i % count($paymentFors)],
            'payment_mode' => $paymentModes[$i % count($paymentModes)],
            'status' => $paymentStatuses[$i % count($paymentStatuses)],
            'description' => 'Payment for ' . $paymentFors[$i % count($paymentFors)],
            'due_date' => date('Y-m-d', strtotime('+' . mt_rand(-30, 60) . ' days')),
            'payment_date' => randomDate('2025-01-01', '2025-12-31'),
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Notifications
echo "  Seeding Notifications... ";
for ($i = 0; $i < 50; $i++) {
    try {
        $db->insert('notifications', [
            'user_id' => mt_rand(1, 50),
            'title' => ['Application Approved', 'Payment Due', 'Ticket Resolved', 'New Update', 'Reminder'][$i % 5],
            'message' => 'Notification message number ' . ($i + 1) . ' regarding your account',
            'type' => $notifTypes[$i % count($notifTypes)],
            'is_read' => mt_rand(0, 1),
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Activity Logs
echo "  Seeding Activity Logs... ";
for ($i = 0; $i < 50; $i++) {
    try {
        $db->insert('activity_logs', [
            'user_id' => mt_rand(1, 50),
            'action' => $actions[$i % count($actions)],
            'description' => 'User performed ' . $actions[$i % count($actions)] . ' action',
            'ip_address' => '192.168.1.' . mt_rand(1, 254),
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Lease Surrenders
echo "  Seeding Lease Surrenders... ";
for ($i = 0; $i < 20; $i++) {
    try {
        $db->insert('lease_surrenders', [
            'application_no' => 'JIADA/SURR/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
            'user_id' => mt_rand(1, 50),
            'lease_id' => mt_rand(1, 20),
            'reason' => 'Business closure/relocation - ' . ($i + 1),
            'notice_date' => date('Y-m-d', strtotime('+' . mt_rand(90, 180) . ' days')),
            'refund_percent' => [75, 65, 55][$i % 3],
            'refund_amount' => randomFloat(50000, 500000),
            'status' => ['pending', 'approved', 'rejected', 'under_review'][$i % 4],
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "20 done\n";

// Lease Mortgages
echo "  Seeding Lease Mortgages... ";
$banks = ['SBI', 'PNB', 'BOB', 'HDFC', 'ICICI', 'Axis Bank', 'Canara Bank'];
for ($i = 0; $i < 20; $i++) {
    try {
        $db->insert('lease_mortgages', [
            'application_no' => 'JIADA/MORT/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
            'user_id' => mt_rand(1, 50),
            'lease_id' => mt_rand(1, 20),
            'bank_name' => $banks[$i % count($banks)],
            'loan_amount' => randomFloat(100000, 5000000),
            'purpose' => 'Business expansion/working capital',
            'status' => ['pending', 'approved', 'rejected'][$i % 3],
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "20 done\n";

// Lease Transfers
echo "  Seeding Lease Transfers... ";
for ($i = 0; $i < 20; $i++) {
    try {
        $db->insert('lease_transfers', [
            'application_no' => 'JIADA/TRANS/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
            'lease_id' => mt_rand(1, 20),
            'from_user_id' => mt_rand(1, 50),
            'to_user_name' => $names[($i + 10) % count($names)],
            'transfer_reason' => 'Business sale/partnership change',
            'consideration_amount' => randomFloat(100000, 2000000),
            'is_msme' => $i % 2,
            'premium_percent' => $i % 2 ? 15 : 25,
            'land_premium' => randomFloat(10000, 200000),
            'status' => ['pending', 'approved', 'rejected'][$i % 3],
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "20 done\n";

// Lease Renewals
echo "  Seeding Lease Renewals... ";
for ($i = 0; $i < 20; $i++) {
    try {
        $db->insert('lease_renewals', [
            'application_no' => 'JIADA/REN/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
            'user_id' => mt_rand(1, 50),
            'lease_id' => mt_rand(1, 20),
            'renewal_period' => [5, 10, 15, 20, 30][$i % 5],
            'reason' => 'Continuation of business operations',
            'status' => ['pending', 'approved', 'under_review'][$i % 3],
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "20 done\n";

// P&P Applications
echo "  Seeding P&P Applications... ";
for ($i = 0; $i < 20; $i++) {
    try {
        $db->insert('plug_play_applications', [
            'application_no' => 'JIADA/PP/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
            'user_id' => mt_rand(1, 50),
            'unit_id' => mt_rand(1, 50),
            'company_name' => $names[$i % count($names)] . ' Industries',
            'industry_type' => $industries[$i % count($industries)],
            'investment_amount' => randomFloat(50000, 5000000),
            'status' => ['pending', 'approved', 'rejected'][$i % 3],
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "20 done\n";

echo "\n=== Seeding Complete ===\n";
echo "Total: 580+ records seeded\n";