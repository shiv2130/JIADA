<?php
/**
 * Cancellation Notices List API
 * GET /api/cancellation/list
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$db = getDB();

$notices = $db->fetchAll(
    "SELECT c.*, l.lease_no, l.plot_no
     FROM cancellations c
     LEFT JOIN lease_records l ON c.lease_id = l.id
     WHERE c.user_id = ?
     ORDER BY c.created_at DESC",
    [$user['id']]
);

jsonResponse(['success' => true, 'data' => $notices]);
