<?php
/**
 * JIADA Portal - Land Plot API
 * GET /api/land/plot/{id}
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$plotId = intval($_GET['id'] ?? 0);
if (!$plotId) {
    jsonResponse(['success' => false, 'message' => 'Plot ID is required'], 400);
}

$db = getDB();
$plot = $db->fetchOne("SELECT * FROM land_banks WHERE id = ?", [$plotId]);

if (!$plot) {
    jsonResponse(['success' => false, 'message' => 'Plot not found'], 404);
}

if (!empty($plot['boundary_coordinates'])) {
    $plot['boundary_coordinates'] = json_decode($plot['boundary_coordinates'], true);
}
if (!empty($plot['utilities_available'])) {
    $plot['utilities_available'] = json_decode($plot['utilities_available'], true);
}

jsonResponse(['success' => true, 'data' => $plot]);
