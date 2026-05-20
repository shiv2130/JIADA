<?php
/**
 * JIADA Portal - Land Bid Submission
 * POST /api/land/bid
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$input = getPost();
$db = getDB();

try {
    $required = ['bid_id', 'application_id', 'bid_amount'];
    $missing = validateRequired($input, $required);
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }

    $bidId = intval($input['bid_id']);
    $applicationId = intval($input['application_id']);
    $bidAmount = floatval($input['bid_amount']);
    $bid = $db->fetchOne("SELECT * FROM bids WHERE id = ? AND status = 'active'", [$bidId]);
    if (!$bid) {
        throw new Exception('Bid window is not active');
    }
    $now = date('Y-m-d');
    if (!empty($bid['bid_start_date']) && $now < $bid['bid_start_date']) {
        throw new Exception('Bid has not started yet');
    }
    if (!empty($bid['bid_end_date']) && $now > $bid['bid_end_date']) {
        throw new Exception('Bid window is closed');
    }

    $application = $db->fetchOne(
        "SELECT * FROM land_applications WHERE id = ? AND user_id = ?",
        [$applicationId, $user['id']]
    );
    if (!$application) {
        throw new Exception('Invalid application reference');
    }

    $participant = $db->fetchOne(
        "SELECT * FROM bid_participants WHERE bid_id = ? AND user_id = ? AND application_id = ?",
        [$bidId, $user['id'], $applicationId]
    );
    if (!$participant) {
        throw new Exception('You are not registered for this bid');
    }
    if (intval($participant['emd_paid']) !== 1) {
        throw new Exception('Please pay EMD before submitting a bid');
    }

    $db->update('bid_participants', [
        'bid_amount' => $bidAmount,
        'bid_submitted_at' => date('Y-m-d H:i:s'),
        'status' => 'bid_submitted'
    ], 'id = ?', [$participant['id']]);

    logActivity("Bid submitted for bid_id {$bidId}", $user['id']);

    jsonResponse(['success' => true, 'message' => 'Bid submitted successfully']);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
