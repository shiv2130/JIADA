<?php
/**
 * JIADA Portal - Land Application View
 * GET /api/land/application/{id}
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$applicationId = intval($_GET['id'] ?? 0);
if (!$applicationId) {
    jsonResponse(['success' => false, 'message' => 'Application ID is required'], 400);
}

$db = getDB();
$application = $db->fetchOne(
    "SELECT a.*, lb.plot_number, lb.plot_type, lb.area_sqm, lb.zone
     FROM land_applications a
     LEFT JOIN land_banks lb ON a.plot_id = lb.id
     WHERE a.id = ?",
    [$applicationId]
);

if (!$application) {
    jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
}

if (!empty($application['documents'])) {
    $application['documents'] = json_decode($application['documents'], true);
}

jsonResponse(['success' => true, 'data' => $application]);
