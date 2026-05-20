<?php
/**
 * Court Case View API
 * GET /api/court-case?id=#
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Case ID required'], 400);
}

$db = getDB();
$case = $db->fetchOne("SELECT * FROM court_cases WHERE id = ?", [$id]);

if (!$case) {
    jsonResponse(['success' => false, 'message' => 'Case not found'], 404);
}

if (!empty($case['documents'])) {
    $case['documents'] = json_decode($case['documents'], true);
}

jsonResponse(['success' => true, 'data' => $case]);
