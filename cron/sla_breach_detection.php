<?php
if (!defined('JIADA_APP')) define('JIADA_APP', true);
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$tableExists = $db->fetchOne("SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_workflow_actions' LIMIT 1");
if (!$tableExists) {
    echo "la_workflow_actions table not found" . PHP_EOL;
    exit;
}

$rows = $db->fetchAll("SELECT application_id, MAX(action_at) AS last_action_at FROM la_workflow_actions GROUP BY application_id HAVING DATEDIFF(CURDATE(), MAX(action_at)) > 7");

 $breachTableExists = $db->fetchOne("SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_timeline_breaches' LIMIT 1");
 if (!$breachTableExists) {
    echo 'SLA breach candidates: ' . count($rows) . ' (table la_timeline_breaches missing, skipped persistence)' . PHP_EOL;
    exit;
 }

foreach ($rows as $row) {
    $appId = intval($row['application_id']);
    $lastActionAt = $row['last_action_at'];
    $breachDays = max(1, intval((strtotime(date('Y-m-d')) - strtotime(substr($lastActionAt, 0, 10))) / 86400));
    $open = $db->fetchOne("SELECT id FROM la_timeline_breaches WHERE application_id = ? AND status = 'open' LIMIT 1", [$appId]);
    if (!$open) {
        $db->insert('la_timeline_breaches', [
            'application_id' => $appId,
            'last_action_at' => $lastActionAt,
            'breach_days' => $breachDays,
            'status' => 'open'
        ]);
    } else {
        $db->update('la_timeline_breaches', ['last_action_at' => $lastActionAt, 'breach_days' => $breachDays], 'id = ?', [intval($open['id'])]);
    }
}

// Close resolved breaches
$breachRows = $db->fetchAll("SELECT id, application_id FROM la_timeline_breaches WHERE status = 'open'");
foreach ($breachRows as $b) {
    $latest = $db->fetchOne("SELECT MAX(action_at) AS last_action_at FROM la_workflow_actions WHERE application_id = ?", [intval($b['application_id'])]);
    if (!$latest || empty($latest['last_action_at'])) continue;
    $days = intval((strtotime(date('Y-m-d')) - strtotime(substr($latest['last_action_at'], 0, 10))) / 86400);
    if ($days <= 7) {
        $db->update('la_timeline_breaches', ['status' => 'closed'], 'id = ?', [intval($b['id'])]);
    }
}

echo 'SLA breach candidates: ' . count($rows) . PHP_EOL;
