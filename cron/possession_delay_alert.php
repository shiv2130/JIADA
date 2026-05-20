<?php
if (!defined('JIADA_APP')) define('JIADA_APP', true);
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$rows = $db->fetchAll("SELECT p.id, a.user_id, a.provisional_order_no, p.status FROM la_possessions p INNER JOIN la_allotments a ON a.id = p.allotment_id WHERE p.status = 'pending' AND a.status = 'premium_paid' AND DATEDIFF(CURDATE(), a.payment_due_date) > 10");

foreach ($rows as $row) {
    if (class_exists('Notification')) {
        Notification::createInApp((int)$row['user_id'], 'Possession Delay Alert', 'Possession pending beyond timeline for order ' . $row['provisional_order_no'], Notification::PRIORITY_MEDIUM);
    }
}

echo 'Possession delay alerts: ' . count($rows) . PHP_EOL;
