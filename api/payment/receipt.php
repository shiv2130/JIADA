<?php
/**
 * Payment Receipt API - Generate and download payment receipts
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$token = getBearerToken();
if (!$token) { jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401); }

$decoded = JWT::decode($token);
if (!$decoded) { jsonResponse(['success' => false, 'message' => 'Invalid token'], 401); }

$userId = $decoded['user_id'];
$userType = $decoded['user_type'] ?? 'applicant';
$db = getDB();

$paymentId = intval($_GET['id'] ?? 0);
if (!$paymentId) { jsonResponse(['success' => false, 'message' => 'Payment ID required']); }

$payment = $db->fetchOne(
    "SELECT p.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile 
     FROM payments p 
     LEFT JOIN users u ON p.user_id = u.id 
     WHERE p.id = ?",
    [$paymentId]
);

if (!$payment) { jsonResponse(['success' => false, 'message' => 'Payment not found']); }

// Check access permissions
if ($userType === 'applicant' && $payment['user_id'] != $userId) {
    jsonResponse(['success' => false, 'message' => 'Access denied']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Generate receipt data
    $receipt = [
        'receipt_no' => $payment['receipt_no'],
        'transaction_id' => $payment['transaction_id'],
        'payment_date' => formatDate($payment['payment_date']),
        'user_name' => $payment['user_name'],
        'user_email' => $payment['user_email'],
        'user_mobile' => $payment['user_mobile'],
        'amount' => $payment['amount'],
        'gst_amount' => $payment['gst_amount'] ?? 0,
        'total_amount' => $payment['amount'] + ($payment['gst_amount'] ?? 0),
        'payment_mode' => $payment['payment_mode'],
        'payment_for' => $payment['payment_for'],
        'description' => $payment['description'],
        'status' => $payment['status']
    ];
    
    jsonResponse(['success' => true, 'data' => $receipt]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);