<?php
/**
 * Land Survey Data API
 * GET /api/survey/data
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$db = getDB();

$surveys = $db->fetchAll("SELECT * FROM land_surveys ORDER BY created_at DESC LIMIT 200");
foreach ($surveys as &$s) {
    if (!empty($s['boundary_coordinates'])) {
        $s['boundary_coordinates'] = json_decode($s['boundary_coordinates'], true);
    }
}

jsonResponse(['success' => true, 'data' => $surveys]);
