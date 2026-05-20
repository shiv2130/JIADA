<?php
/**
 * Lease Renewal API - Apply for lease renewal
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405); }

$auth = JWT::authenticate();
if (!$auth['success']) { jsonResponse(['success' => false, 'message' => $auth['message']], $auth['code'] ?? 401); }
$userId = $auth['user']['id'];
$db = getDB();

$leaseId = intval($_POST['lease_id'] ?? 0);
$leaseNo = sanitize($_POST['lease_no'] ?? '');
$renewalPeriod = intval($_POST['renewal_period'] ?? 0);
$reason = sanitize($_POST['reason'] ?? '');

if ($leaseId < 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid lease id']);
}
if ($renewalPeriod <= 0 || $renewalPeriod > 99) {
    jsonResponse(['success' => false, 'message' => 'Renewal period must be between 1 and 99 years']);
}
if (!empty($reason) && strlen($reason) < 10) {
    jsonResponse(['success' => false, 'message' => 'Reason must be at least 10 characters']);
}

if ((!$leaseId && !$leaseNo) || !$renewalPeriod) {
    jsonResponse(['success' => false, 'message' => 'Lease ID/No and renewal period are required']);
}

if (!$leaseId && $leaseNo) {
    $lease = $db->fetchOne("SELECT * FROM lease_records WHERE lease_no = ? AND user_id = ? AND status = 'active'", [$leaseNo, $userId]);
    $leaseId = $lease['id'] ?? 0;
} else {
    $lease = $db->fetchOne("SELECT * FROM lease_records WHERE id = ? AND user_id = ? AND status = 'active'", [$leaseId, $userId]);
}
if (!$lease) {
    jsonResponse(['success' => false, 'message' => 'Invalid or inactive lease agreement']);
}

// Check for outstanding dues
$dues = $db->fetchOne("SELECT SUM(amount - paid_amount) as outstanding FROM payments WHERE lease_id = ? AND status != 'paid'", [$leaseId]);
if ($dues['outstanding'] > 0) {
    jsonResponse(['success' => false, 'message' => 'Clear outstanding dues of ₹' . number_format($dues['outstanding'], 2) . ' first']);
}

$applicationNo = 'JIADA/REN/' . date('Y') . '/' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

$renewalId = $db->insert('lease_renewals', [
    'application_no' => $applicationNo,
    'user_id' => $userId,
    'lease_id' => $leaseId,
    'renewal_period' => $renewalPeriod,
    'reason' => $reason,
    'status' => 'pending',
    'created_at' => date('Y-m-d H:i:s')
]);

jsonResponse(['success' => true, 'message' => 'Renewal application submitted', 'data' => ['renewal_id' => $renewalId, 'application_no' => $applicationNo]], 201);
