<?php
if (!defined('JIADA_APP')) define('JIADA_APP', true);
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$today = date('Y-m-d');
$rows = $db->fetchAll("SELECT id, provisional_order_no FROM la_allotments WHERE status IN ('provisional','premium_pending') AND payment_due_date < ?", [$today]);

foreach ($rows as $row) {
    $db->update('la_allotments', ['status' => 'cancelled'], 'id = ?', [(int)$row['id']]);
}

echo 'Expired provisional allotments cancelled: ' . count($rows) . PHP_EOL;
