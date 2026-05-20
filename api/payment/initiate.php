<?php
/**
 * JIADA Portal - Payment Initiation API
 * POST /api/payment/initiate
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require authentication
$user = Middleware::requireAuth();

$input = getPost();
$db = getDB();
$db->beginTransaction();

try {
    // Validate required fields
    $required = ['payment_type', 'reference_type', 'reference_id', 'amount'];
    $missing = validateRequired($input, $required);
    
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }
    
    // Validate payment type/reference type and amount
    $validTypes = ['application_fee', 'processing_fee', 'emd', 'premium', 'rent', 'maintenance', 'utility', 'penalty', 'scrutiny_fee', 'six_month_rent', 'other'];
    $validRefs  = ['application', 'lease', 'utility', 'grievance', 'plug_play', 'renewal', 'other'];
    if (!in_array($input['payment_type'], $validTypes)) {
        throw new Exception('Invalid payment type');
    }
    if (!in_array($input['reference_type'], $validRefs)) {
        throw new Exception('Invalid reference type');
    }
    if (floatval($input['amount']) <= 0) {
        throw new Exception('Amount must be greater than zero');
    }
    
    // Calculate GST if applicable
    $gstAmount = 0;
    if (in_array($input['payment_type'], ['application_fee', 'processing_fee', 'scrutiny_fee'])) {
        $gstAmount = floatval($input['amount']) * 0.18; // 18% GST
    }
    
    $totalAmount = floatval($input['amount']) + $gstAmount;
    
    // Generate payment & invoice IDs
    $paymentId = 'PAY' . date('YmdHis') . strtoupper(substr(uniqid(), -6));
    $invoiceNo = generateReferenceNumber('INV');
    
    // Store payment record
    $paymentData = [
        'payment_id' => $paymentId,
        'user_id' => $user['id'],
        'payment_type' => sanitize($input['payment_type']),
        'reference_type' => sanitize($input['reference_type']),
        'reference_id' => intval($input['reference_id']),
        'amount' => floatval($input['amount']),
        'gst_amount' => $gstAmount,
        'total_amount' => $totalAmount,
        'status' => 'pending',
        'remarks' => !empty($input['remarks']) ? sanitize($input['remarks']) : null
    ];
    if (in_array('invoice_no', array_column($db->fetchAll("SHOW COLUMNS FROM payments"), 'Field'))) {
        $paymentData['invoice_no'] = $invoiceNo;
    }
    
    $paymentResult = $db->insert('payments', $paymentData);
    
    // Create Razorpay order
    $orderData = null;
    if (PAYMENT_GATEWAY === 'razorpay') {
        $orderData = createRazorpayOrder($paymentId, $totalAmount);
    }
    
    // Also create outstanding dues record
    $db->insert('dues_outstanding', [
        'user_id' => $user['id'],
        'dues_type' => sanitize($input['payment_type']),
        'due_amount' => $totalAmount,
        'due_date' => date('Y-m-d'),
        'status' => 'pending'
    ]);
    
    $db->commit();
    
    logActivity("Payment initiated: {$paymentId} - " . formatCurrency($totalAmount), $user['id']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Payment initiated successfully',
        'data' => [
            'payment_id' => $paymentId,
            'amount' => floatval($input['amount']),
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'invoice_no' => $invoiceNo,
            'order_id' => $orderData['id'] ?? null,
            'razorpay_key' => PAYMENT_API_KEY,
            'callback_url' => PAYMENT_CALLBACK_URL,
            'prefill' => [
                'name' => $user['name'],
                'email' => $user['email'] ?? '',
                'contact' => $user['mobile'] ?? ''
            ],
            'notes' => [
                'user_id' => $user['id'],
                'payment_type' => $input['payment_type'],
                'reference_type' => $input['reference_type'],
                'reference_id' => $input['reference_id']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}

/**
 * Create Razorpay Order
 */
function createRazorpayOrder($receipt, $amount) {
    $amountPaise = intval($amount * 100);
    
    $orderData = [
        'receipt' => $receipt,
        'amount' => $amountPaise,
        'currency' => 'INR',
        'payment_capture' => 1
    ];
    
    // Razorpay API call
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.razorpay.com/v1/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => PAYMENT_API_KEY . ':' . PAYMENT_API_SECRET,
        CURLOPT_POSTFIELDS => json_encode($orderData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    
    throw new Exception('Payment gateway error');
}
