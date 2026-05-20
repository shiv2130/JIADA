<?php
/**
 * Payment Dues API - Check outstanding dues for a user
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$auth = JWT::authenticate();
if (!$auth['success']) { jsonResponse(['success' => false, 'message' => $auth['message']], $auth['code'] ?? 401); }
$userId = $auth['user']['id'];
$db = getDB();

// Get all outstanding dues
$dues = $db->fetchAll(
    "SELECT p.id, p.receipt_no, p.amount, p.paid_amount, (p.amount - p.paid_amount) as outstanding,
            p.payment_for, p.description, p.due_date, p.status,
            l.plot_no, l.lease_no
     FROM payments p
     LEFT JOIN lease_records l ON p.lease_id = l.id
     WHERE p.user_id = ? AND p.status != 'paid' AND p.paid_amount < p.amount
     ORDER BY p.due_date ASC",
    [$userId]
);

$totalOutstanding = 0;
foreach ($dues as $due) {
    $totalOutstanding += $due['outstanding'];
}

// Get payment history summary
$history = $db->fetchAll(
    "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total_paid, COUNT(*) as count
     FROM payments 
     WHERE user_id = ? AND status = 'paid' AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
     ORDER BY month DESC",
    [$userId]
);

jsonResponse([
    'success' => true,
    'data' => [
        'dues' => $dues,
        'total_outstanding' => $totalOutstanding,
        'dues_count' => count($dues),
        'payment_history' => $history
    ]
]);
