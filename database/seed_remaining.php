<?php
define('JIADA_APP', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';

$db = getDB();

function randomDate($start, $end) {
    return date('Y-m-d H:i:s', mt_rand(strtotime($start), strtotime($end)));
}

function randomFloat($min, $max) {
    return round(mt_rand($min * 100, $max * 100) / 100, 2);
}

$zones = ['Ranchi', 'Jamshedpur', 'Dhanbad', 'Bokaro', 'Hazaribagh', 'Giridih', 'Deoghar', 'Dumka'];
$industries = ['Manufacturing', 'IT/ITES', 'Food Processing', 'Textile', 'Pharmaceutical', 'Auto Components', 'Engineering', 'Chemical', 'Mineral Based', 'Agro Based'];
$plotStatuses = ['available', 'available', 'available', 'allotted', 'reserved'];
$appStatuses = ['pending', 'approved', 'rejected', 'under_review', 'pending'];
$serviceTypes = ['water', 'power', 'maintenance', 'sewage', 'road', 'streetlight'];
$priorities = ['low', 'medium', 'high', 'urgent', 'medium'];
$ticketStatuses = ['open', 'in_progress', 'resolved', 'closed', 'escalated'];
$categories = ['land', 'payment', 'utility', 'staff', 'delay', 'other'];
$grevStatuses = ['pending', 'in_progress', 'resolved', 'closed', 'escalated'];
$paymentFors = ['land_allotment', 'rent', 'maintenance', 'utility', 'processing_fee', 'premium'];
$paymentModes = ['upi', 'net_banking', 'card', 'wallet', 'upi'];
$paymentStatuses = ['paid', 'pending', 'partial', 'paid', 'paid'];

echo "Seeding remaining tables...\n\n";

// Land Banks (50 plots)
echo "Seeding Land Banks... ";
for ($i = 0; $i < 50; $i++) {
    try {
        $db->insert('land_banks', [
            'plot_number' => 'PLOT-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
            'plot_type' => 'industrial',
            'area_sqm' => mt_rand(500, 10000),
            'zone' => $zones[$i % count($zones)],
            'industry_type' => $industries[$i % count($industries)],
            'latitude' => 23.0 + (mt_rand(0, 500) / 1000),
            'longitude' => 85.0 + (mt_rand(0, 500) / 1000),
            'base_price_per_sqm' => randomFloat(500, 5000),
            'region_id' => ($i % 4) + 1,
            'status' => $plotStatuses[$i % count($plotStatuses)],
            'created_at' => randomDate('2024-01-01', '2025-12-31')
        ]);
    } catch (Exception $e) {}
}
echo "50 done\n";

// Land Applications (50)
echo "Seeding Land Applications... ";
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

// Utility Tickets (50)
echo "Seeding Utility Tickets... ";
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

// Grievances (50)
echo "Seeding Grievances... ";
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

// Payments (50)
echo "Seeding Payments... ";
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

echo "\n=== Seeding Complete ===\n";