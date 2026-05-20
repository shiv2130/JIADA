<?php
/**
 * WhatsApp Send Message API (Stub)
 * POST /api/whatsapp/message
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$db = getDB();

try {
    $required = ['phone_number', 'message'];
    $missing = validateRequired($_POST, $required);
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }

    $phone = sanitize($_POST['phone_number']);
    $message = sanitize($_POST['message']);

    // Send message via Meta Cloud API
    $send = sendWhatsAppCloudMessage($phone, $message);
    if (!$send['success']) {
        // Allow configuration to be added later; still log the request
        $db->insert('whatsapp_interactions', [
            'phone_number' => $phone,
            'user_id' => $user['id'],
            'message_type' => 'outbound',
            'message_content' => $message,
            'intent' => 'manual_send',
            'response_sent' => 'not_configured',
            'status' => 'failed'
        ]);
        jsonResponse(['success' => false, 'message' => $send['message'] ?? 'WhatsApp send failed. Configure Meta credentials.'], 400);
    }
    
    // Store interaction
    $db->insert('whatsapp_interactions', [
        'phone_number' => $phone,
        'user_id' => $user['id'],
        'message_type' => 'outbound',
        'message_content' => $message,
        'intent' => 'manual_send',
        'response_sent' => 'sent',
        'status' => 'sent'
    ]);

    jsonResponse(['success' => true, 'message' => 'Message sent', 'data' => $send['response'] ?? null]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
