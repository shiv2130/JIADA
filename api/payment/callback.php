<?php
/**
 * JIADA Portal - Payment Callback API
 * POST /api/payment/callback
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = getPost();
$db = getDB();

try {
    // Validate required callback parameters
    if (empty($input['razorpay_payment_id']) || empty($input['razorpay_order_id']) || empty($input['razorpay_signature'])) {
        throw new Exception('Missing payment callback parameters');
    }
    
    $razorpayPaymentId = sanitize($input['razorpay_payment_id']);
    $razorpayOrderId = sanitize($input['razorpay_order_id']);
    $razorpaySignature = sanitize($input['razorpay_signature']);
    
    // Verify signature
    $generatedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, PAYMENT_API_SECRET);
    
    if ($generatedSignature !== $razorpaySignature) {
        throw new Exception('Invalid payment signature');
    }
    
    // Fetch payment record
    $payment = $db->fetchOne("SELECT * FROM payments WHERE payment_id = ?", [$input['receipt'] ?? $razorpayOrderId]);
    
    if (!$payment) {
        throw new Exception('Payment record not found');
    }
    
    // Update payment status
    $db->update('payments', [
        'transaction_id' => $razorpayPaymentId,
        'status' => 'success',
        'payment_mode' => 'online',
        'gateway_response' => json_encode($input),
        'paid_at' => date(DATETIME_FORMAT)
    ], 'id = :id', ['id' => $payment['id']]);
    
    // Update dues record
    $db->query(
        "UPDATE dues_outstanding SET paid_amount = paid_amount + ?, status = 'paid' WHERE user_id = ? AND dues_type = ? AND status = 'pending' ORDER BY due_date ASC LIMIT 1",
        [$payment['total_amount'], $payment['user_id'], $payment['payment_type']]
    );
    
    // Update related application/order status
    switch ($payment['reference_type']) {
        case 'application':
        case 'land_application':
            $db->update('land_applications', [
                'processing_fee_paid' => 1,
                'payment_reference' => $razorpayPaymentId,
                'status' => 'submitted'
            ], 'id = :id', ['id' => $payment['reference_id']]);
            break;
        case 'bid':
            $db->update('bid_participants', [
                'emd_paid' => 1,
                'emd_payment_reference' => $razorpayPaymentId,
                'status' => 'emd_paid'
            ], 'application_id = :id AND user_id = :user_id', ['id' => $payment['reference_id'], 'user_id' => $payment['user_id']]);
            break;
        case 'allotment_order':
            $db->update('allotment_orders', [
                'status' => 'completed',
                'outstanding_amount' => 0,
                'payment_received_at' => date(DATETIME_FORMAT)
            ], 'id = :id', ['id' => $payment['reference_id']]);

            $order = $db->fetchOne("SELECT * FROM allotment_orders WHERE id = ?", [$payment['reference_id']]);
            if ($order) {
                $db->update('land_banks', ['status' => 'allotted'], 'id = ?', [$order['plot_id']]);
                $db->update('land_applications', ['status' => 'allotted'], 'id = ?', [$order['application_id']]);

                try {
                    $existingLease = $db->fetchOne("SELECT id FROM lease_records WHERE plot_id = ? OR plot_no = ?", [$order['plot_id'], $order['plot_id']]);
                } catch (Exception $e) {
                    $existingLease = null;
                }
                if (!$existingLease) {
                    $leaseNo = generateReferenceNumber('LEASE');
                    try {
                        $db->insert('lease_records', [
                            'lease_no' => $leaseNo,
                            'plot_no' => $order['plot_id'],
                            'user_id' => $order['user_id'],
                            'premium_amount' => $order['premium_amount'],
                            'allotment_date' => date('Y-m-d'),
                            'status' => 'active',
                            'created_at' => date(DATETIME_FORMAT)
                        ]);
                    } catch (Exception $e) {
                        // Ignore if schema differs
                    }
                }
            }
            break;
        case 'plug_play':
            $db->update('plug_play_applications', [
                'scrutiny_fee_paid' => 1
            ], 'id = :id', ['id' => $payment['reference_id']]);
            break;
    }
    
    // Send payment receipt notification
    Notification::sendPaymentReceipt($payment['user_id'], [
        'amount' => $payment['total_amount'],
        'purpose' => $payment['payment_type'],
        'transaction_id' => $razorpayPaymentId,
        'payment_id' => $payment['payment_id']
    ]);
    
    // Generate receipt
    $receiptPath = generatePaymentReceipt($payment, $razorpayPaymentId);
    if ($receiptPath) {
        $db->update('payments', ['receipt_path' => $receiptPath], 'id = :id', ['id' => $payment['id']]);
    }
    
    logActivity("Payment successful: {$payment['payment_id']} - " . formatCurrency($payment['total_amount']), $payment['user_id']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Payment successful',
        'data' => [
            'payment_id' => $payment['payment_id'],
            'transaction_id' => $razorpayPaymentId,
            'amount' => $payment['total_amount'],
            'receipt_url' => $receiptPath ? baseUrl($receiptPath) : null
        ]
    ]);
    
} catch (Exception $e) {
    // Log failed payment
    if (isset($payment) && $payment) {
        $db->update('payments', [
            'status' => 'failed',
            'gateway_response' => json_encode($input),
            'remarks' => $e->getMessage()
        ], 'id = :id', ['id' => $payment['id']]);
    }
    
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}

/**
 * Generate payment receipt PDF
 */
function generatePaymentReceipt($payment, $transactionId) {
    $receiptDir = UPLOADS_PATH . '/receipts';
    if (!is_dir($receiptDir)) {
        mkdir($receiptDir, 0755, true);
    }
    
    $filename = 'receipt_' . $payment['payment_id'] . '.html';
    $filepath = $receiptDir . '/' . $filename;
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Receipt - {$payment['payment_id']}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
            .logo { font-size: 24px; font-weight: bold; color: #1a5276; }
            .details { width: 100%; border-collapse: collapse; }
            .details td, .details th { padding: 10px; border: 1px solid #ddd; }
            .details th { background: #f5f5f5; text-align: left; }
            .total { font-size: 18px; font-weight: bold; margin-top: 20px; }
            .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <div class='logo'>JIADA - Payment Receipt</div>
            <p>Jharkhand Industrial Area Development Authority</p>
        </div>
        
        <table class='details'>
            <tr><th>Payment ID</th><td>{$payment['payment_id']}</td></tr>
            <tr><th>Transaction ID</th><td>{$transactionId}</td></tr>
            <tr><th>Date</th><td>" . formatDate($payment['paid_at'] ?? date(DATETIME_FORMAT)) . "</td></tr>
            <tr><th>Payment Type</th><td>" . ucfirst(str_replace('_', ' ', $payment['payment_type'])) . "</td></tr>
            <tr><th>Amount</th><td>" . formatCurrency($payment['amount']) . "</td></tr>
            <tr><th>GST (18%)</th><td>" . formatCurrency($payment['gst_amount']) . "</td></tr>
            <tr class='total'><th>Total Paid</th><td>" . formatCurrency($payment['total_amount']) . "</td></tr>
        </table>
        
        <div class='footer'>
            <p>This is a computer-generated receipt. No signature required.</p>
            <p>For queries, contact: accounts@jiada.in</p>
        </div>
    </body>
    </html>";
    
    file_put_contents($filepath, $html);
    return 'uploads/receipts/' . $filename;
}
