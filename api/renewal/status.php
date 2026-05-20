<?php
/**
 * Lease Renewal Status API
 * GET /api/renewal/status
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$db = getDB();

$renewals = $db->fetchAll(
    "SELECT lr.*, l.lease_no, l.plot_no
     FROM lease_renewals lr
     LEFT JOIN lease_records l ON lr.lease_id = l.id
     WHERE lr.user_id = ?
     ORDER BY lr.id DESC",
    [$user['id']]
);

jsonResponse(['success' => true, 'data' => $renewals]);
