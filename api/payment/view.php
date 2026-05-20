<?php
/**
 * Payment View API
 * GET /api/payment/{id}
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Payment ID required'], 400);
}

$db = getDB();
$payment = $db->fetchOne("SELECT * FROM payments WHERE id = ? AND user_id = ?", [$id, $user['id']]);

if (!$payment) {
    jsonResponse(['success' => false, 'message' => 'Payment not found'], 404);
}

jsonResponse(['success' => true, 'data' => $payment]);
