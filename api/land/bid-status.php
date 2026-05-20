<?php
/**
 * JIADA Portal - Bid Status API
 * GET /api/land/bid-status?application_id=#
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$applicationId = intval($_GET['application_id'] ?? 0);
if (!$applicationId) {
    jsonResponse(['success' => false, 'message' => 'Application ID is required'], 400);
}

$db = getDB();

$participant = $db->fetchOne(
    "SELECT bp.*, b.bid_start_date, b.bid_end_date, b.emd_amount, b.status as bid_status
     FROM bid_participants bp
     INNER JOIN bids b ON b.id = bp.bid_id
     WHERE bp.application_id = ? AND bp.user_id = ?
     ORDER BY b.id DESC LIMIT 1",
    [$applicationId, $user['id']]
);

if (!$participant) {
    jsonResponse(['success' => true, 'data' => null]);
}

jsonResponse(['success' => true, 'data' => $participant]);
