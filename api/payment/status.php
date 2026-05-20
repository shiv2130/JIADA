<?php
/**
 * Payment Status API
 * GET /api/payment/status?payment_id=...
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$paymentId = sanitize($_GET['payment_id'] ?? '');

if (!$paymentId) {
    jsonResponse(['success' => false, 'message' => 'Payment ID required'], 400);
}

$db = getDB();
$payment = $db->fetchOne(
    "SELECT * FROM payments WHERE payment_id = ? AND user_id = ?",
    [$paymentId, $user['id']]
);

if (!$payment) {
    jsonResponse(['success' => false, 'message' => 'Payment not found'], 404);
}

jsonResponse(['success' => true, 'data' => $payment]);
