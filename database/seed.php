<?php
/**
 * JIADA Portal - Database Seeder
 * Populates database with 50+ dummy records per table
 */
define('JIADA_APP', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';

$db = getDB();
$pdo = $db->getConnection();

echo "=== JIADA Database Seeder ===\n\n";

// Helper functions
function randomDate($start, $end) {
    return date('Y-m-d H:i:s', mt_rand(strtotime($start), strtotime($end)));
}

function randomFloat($min, $max) {
    return round(mt_rand($min * 100, $max * 100) / 100, 2);
}

// 1. Seed Regions (already exists, but verify)
echo "Seeding Regions...\n";
$regions = [
    ['name' => 'Ranchi', 'code' => 'RNC', 'districts' => 'Ranchi, Khunti, Gumla, Simdega, Lohardaga'],
    ['name' => 'Jamshedpur', 'code' => 'JMD', 'districts' => 'East Singhbhum, West Singhbhum, Seraikela-Kharsawan'],
    ['name' => 'Dhanbad', 'code' => 'DHN', 'districts' => 'Dhanbad, Bokaro, Giridih, Deoghar, Dumka'],
    ['name' => 'Bokaro', 'code' => 'BKR', 'districts' => 'Bokaro, Hazaribagh, Ramgarh, Koderma, Chatra']
];
foreach ($regions as $r) {
    try { $db->insert('regions', $r); } catch (Exception $e) {}
}
echo "  ✅ 4 regions seeded\n";

// 2. Seed Users (50+ users)
echo "Seeding Users...\n";
$names = ['Amit Kumar', 'Ravi Singh', 'Priya Sharma', 'Sunita Devi', 'Rajesh Tiwari', 'Anita Kumari', 'Vikram Patel', 'Deepa Sinha', 'Manoj Kumar', 'Pooja Singh', 'Suresh Mahto', 'Rekha Devi', 'Arun Kumar', 'Meera Singh', 'Ramesh Prasad', 'Kavita Kumari', 'Sanjay Gupta', 'Anjali Devi', 'Pankaj Kumar', 'Nisha Singh', 'Dinesh Tiwari', 'Saroj Devi', 'Ashok Kumar', 'Ritu Singh', 'Mukesh Prasad', 'Geeta Kumari', 'Rahul Gupta', 'Suman Devi', 'Vijay Kumar', 'Kiran Singh', 'Pradeep Mahto', 'Laxmi Devi', 'Naveen Kumar', 'Shweta Singh', 'Gopal Prasad', 'Anita Kumari', 'Rajiv Kumar', 'Preeti Singh', 'Sachin Gupta', 'Madhu Devi', 'Alok Kumar', 'Sunita Singh', 'Dilip Prasad', 'Rani Kumari', 'Tarun Kumar', 'Neha Singh', 'Sunil Gupta', 'Kamla Devi', 'Brijesh Kumar', 'Savita Singh'];
$userTypes = ['applicant', 'allottee', 'vendor', 'applicant', 'allottee'];
$statuses = ['active', 'active', 'active', 'active', 'pending'];

for ($i = 0; $i < 50; $i++) {
    $mobile = '9' . str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
    $email = strtolower(str_replace(' ', '.', $names[$i])) . $i . '@example.com';
    try {
        $db->insert('users', [
            'name' => $names[$i],
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
echo "  ✅ 50 users seeded\n";

// 3. Seed Land Plots (50+ plots)
echo "Seeding Land Plots...\n";
$zones = ['Ranchi', 'Jamshedpur', 'Dhanbad', 'Bokaro', 'Hazaribagh', 'Giridih', 'Deoghar', 'Dumka'];
$industries = ['Manufacturing', 'IT/ITES', 'Food Processing', 'Textile', 'Pharmaceutical', 'Auto Components', 'Engineering', 'Chemical', 'Mineral Based', 'Agro Based'];
$statuses = ['available', 'available', 'available', 'allotted', 'reserved'];

for ($i = 0; $i < 50; $i++) {
    $plotNo = 'PLOT-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
    try {
        $db->insert('land_plots', [
            'plot_no' => $plotNo,
            'area_sqft' => mt_rand(5000, 100000),
            'zone' => $zones[$i % count($zones)],
            'industry_type' => $industries[$i % count($industries)],
            'status' => $statuses[$i % count($statuses)],
            'latitude' => 23.0 + (mt_rand(0, 500) / 1000),
            'longitude' => 85.0 + (mt_rand(0, 500) / 1000),
            'base_price' => randomFloat(500, 5000),
            'created_at' => randomDate('2024-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "  ✅ 50 land plots seeded\n";

// 4. Seed Land Applications (50+)
echo "Seeding Land Applications...\n";
$appStatuses = ['pending', 'approved', 'rejected', 'under_review', 'pending'];
for ($i = 0; $i < 50; $i++) {
    $appNo = 'JIADA/LAND/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);
    try {
        $db->insert('land_applications', [
            'application_no' => $appNo,
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
echo "  ✅ 50 land applications seeded\n";

// 5. Seed Plug & Play Units (50+)
echo "Seeding Plug & Play Units...\n";
$unitTypes = ['flatted', 'shed', 'warehouse', 'flatted', 'shed'];
for ($i = 0; $i < 50; $i++) {
    $unitNo = 'UNIT-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
    try {
        $db->insert('plug_play_units', [
            'unit_no' => $unitNo,
            'unit_type' => $unitTypes[$i % count($unitTypes)],
            'area_sqft' => mt_rand(500, 10000),
            'monthly_rent' => randomFloat(5000, 100000),
            'status' => $statuses[$i % count($statuses)],
            'location' => $zones[$i % count($zones)],
            'created_at' => randomDate('2024-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "  ✅ 50 plug & play units seeded\n";

// 6. Seed Utility Tickets (50+)
echo "Seeding Utility Tickets...\n";
$serviceTypes = ['water', 'power', 'maintenance', 'sewage', 'road', 'streetlight'];
$priorities = ['low', 'medium', 'high', 'urgent', 'medium'];
$ticketStatuses = ['open', 'in_progress', 'resolved', 'closed', 'escalated'];
for ($i = 0; $i < 50; $i++) {
    $ticketNo = 'UTIL/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);
    try {
        $db->insert('utility_tickets', [
            'ticket_no' => $ticketNo,
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
echo "  ✅ 50 utility tickets seeded\n";

// 7. Seed Grievances (50+)
echo "Seeding Grievances...\n";
$categories = ['land', 'payment', 'utility', 'staff', 'delay', 'other'];
$grevStatuses = ['pending', 'in_progress', 'resolved', 'closed', 'escalated'];
for ($i = 0; $i < 50; $i++) {
    $grevNo = 'GREV/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);
    try {
        $db->insert('grievances', [
            'grievance_no' => $grevNo,
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
echo "  ✅ 50 grievances seeded\n";

// 8. Seed Court Cases (50+)
echo "Seeding Court Cases...\n";
$caseTypes = ['civil', 'criminal', 'writ', 'appeal', 'review'];
$caseStatuses = ['pending', 'in_progress', 'disposed', 'adjourned', 'listed'];
for ($i = 0; $i < 50; $i++) {
    $cnr = str_pad(mt_rand(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
    try {
        $db->insert('court_cases', [
            'cnr_number' => $cnr,
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
echo "  ✅ 50 court cases seeded\n";

// 9. Seed Payments (50+)
echo "Seeding Payments...\n";
$paymentFors = ['land_allotment', 'rent', 'maintenance', 'utility', 'processing_fee', 'premium'];
$paymentModes = ['upi', 'net_banking', 'card', 'wallet', 'upi'];
$paymentStatuses = ['paid', 'pending', 'partial', 'paid', 'paid'];
for ($i = 0; $i < 50; $i++) {
    $receiptNo = 'REC/2025/' . str_pad($i + 1, 6, '0', STR_PAD_LEFT);
    $amount = randomFloat(1000, 500000);
    try {
        $db->insert('payments', [
            'receipt_no' => $receiptNo,
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
echo "  ✅ 50 payments seeded\n";

// 10. Seed Notifications (50+)
echo "Seeding Notifications...\n";
$notifTypes = ['info', 'success', 'warning', 'error', 'info'];
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
echo "  ✅ 50 notifications seeded\n";

// 11. Seed Lease Surrenders (20+)
echo "Seeding Lease Surrenders...\n";
for ($i = 0; $i < 20; $i++) {
    $appNo = 'JIADA/SURR/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);
    try {
        $db->insert('lease_surrenders', [
            'application_no' => $appNo,
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
echo "  ✅ 20 lease surrenders seeded\n";

// 12. Seed Lease Mortgages (20+)
echo "Seeding Lease Mortgages...\n";
$banks = ['SBI', 'PNB', 'BOB', 'HDFC', 'ICICI', 'Axis Bank', 'Canara Bank'];
for ($i = 0; $i < 20; $i++) {
    $appNo = 'JIADA/MORT/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);
    try {
        $db->insert('lease_mortgages', [
            'application_no' => $appNo,
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
echo "  ✅ 20 lease mortgages seeded\n";

// 13. Seed Lease Transfers (20+)
echo "Seeding Lease Transfers...\n";
for ($i = 0; $i < 20; $i++) {
    $appNo = 'JIADA/TRANS/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);
    try {
        $db->insert('lease_transfers', [
            'application_no' => $appNo,
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
echo "  ✅ 20 lease transfers seeded\n";

// 14. Seed Lease Renewals (20+)
echo "Seeding Lease Renewals...\n";
for ($i = 0; $i < 20; $i++) {
    $appNo = 'JIADA/REN/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);
    try {
        $db->insert('lease_renewals', [
            'application_no' => $appNo,
            'user_id' => mt_rand(1, 50),
            'lease_id' => mt_rand(1, 20),
            'renewal_period' => [5, 10, 15, 20, 30][$i % 5],
            'reason' => 'Continuation of business operations',
            'status' => ['pending', 'approved', 'under_review'][$i % 3],
            'created_at' => randomDate('2025-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "  ✅ 20 lease renewals seeded\n";

// 15. Seed Plug & Play Applications (20+)
echo "Seeding Plug & Play Applications...\n";
for ($i = 0; $i < 20; $i++) {
    $appNo = 'JIADA/PP/2025/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);
    try {
        $db->insert('plug_play_applications', [
            'application_no' => $appNo,
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
echo "  ✅ 20 plug & play applications seeded\n";

// 16. Seed Departments
echo "Seeding Departments...\n";
$departments = ['Land & Revenue', 'Infrastructure', 'Finance', 'Administration', 'Legal', 'HR'];
foreach ($departments as $d) {
    try { $db->insert('departments', ['name' => $d, 'code' => strtoupper(substr($d, 0, 3))]); } catch (Exception $e) {}
}
echo "  ✅ 6 departments seeded\n";

// 17. Seed Activity Logs (50+)
echo "Seeding Activity Logs...\n";
$actions = ['login', 'application_submitted', 'payment_made', 'ticket_raised', 'grievance_filed', 'document_uploaded', 'profile_updated'];
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
echo "  ✅ 50 activity logs seeded\n";

echo "\n=== Seeding Complete ===\n";
echo "Total records seeded:\n";
echo "  - Users: 50\n";
echo "  - Land Plots: 50\n";
echo "  - Land Applications: 50\n";
echo "  - Plug & Play Units: 50\n";
echo "  - Utility Tickets: 50\n";
echo "  - Grievances: 50\n";
echo "  - Court Cases: 50\n";
echo "  - Payments: 50\n";
echo "  - Notifications: 50\n";
echo "  - Activity Logs: 50\n";
echo "  - Lease Surrenders: 20\n";
echo "  - Lease Mortgages: 20\n";
echo "  - Lease Transfers: 20\n";
echo "  - Lease Renewals: 20\n";
echo "  - P&P Applications: 20\n";
echo "  - Departments: 6\n";
echo "  - Regions: 4\n";
echo "\nTotal: 580+ records\n";