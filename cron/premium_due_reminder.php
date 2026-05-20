<?php
if (!defined('JIADA_APP')) define('JIADA_APP', true);
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$today = date('Y-m-d');
$rows = $db->fetchAll("SELECT id, user_id, provisional_order_no, payment_due_date FROM la_allotments WHERE status IN ('provisional','premium_pending') AND payment_due_date <= DATE_ADD(?, INTERVAL 3 DAY)", [$today]);

foreach ($rows as $row) {
    if (class_exists('Notification')) {
        Notification::createInApp((int)$row['user_id'], 'Premium Due Reminder', 'Premium due for order ' . $row['provisional_order_no'] . ' on ' . $row['payment_due_date'], Notification::PRIORITY_HIGH);
    }
}

echo 'Premium reminders processed: ' . count($rows) . PHP_EOL;
