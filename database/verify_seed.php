<?php
define('JIADA_APP', true);
require_once __DIR__ . '/../config/config.php';
$db = getDB();
$tables = ['users', 'land_plots', 'land_applications', 'plug_play_units', 'utility_tickets', 'grievances', 'court_cases', 'payments', 'notifications', 'lease_surrenders', 'lease_mortgages', 'lease_transfers', 'lease_renewals', 'plug_play_applications', 'departments', 'regions'];
echo "=== Database Verification ===\n\n";
$total = 0;
foreach ($tables as $t) {
    try {
        $c = $db->fetchOne("SELECT COUNT(*) as cnt FROM $t")['cnt'];
        $total += $c;
        echo "$t: $c records\n";
    } catch (Exception $e) {
        echo "$t: table not found\n";
    }
}
echo "\nTotal: $total records\n";