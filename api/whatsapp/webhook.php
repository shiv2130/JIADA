<?php
/**
 * WhatsApp Webhook API - Receive and process WhatsApp messages
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// Verify webhook token (Meta sends hub.mode, hub.verify_token, hub.challenge)
$verifyToken = defined('WHATSAPP_VERIFY_TOKEN') ? WHATSAPP_VERIFY_TOKEN : 'jiada_whatsapp_token';
$mode = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? '');
$token = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? '');
$challenge = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? '');

if ($mode === 'subscribe' && $token === $verifyToken) {
    echo $challenge;
    exit;
}

// Handle incoming messages
$input = file_get_contents('php://input');
$rawBody = $input;
$data = json_decode($input, true);

// Verify signature (X-Hub-Signature-256)
$verifySig = defined('WHATSAPP_VERIFY_SIGNATURE') ? WHATSAPP_VERIFY_SIGNATURE : false;
$appSecret = defined('WHATSAPP_APP_SECRET') ? WHATSAPP_APP_SECRET : '';
if (!$appSecret) { $appSecret = getSystemSetting('whatsapp_app_secret', ''); }
if (!$verifySig) { $verifySig = (getSystemSetting('whatsapp_verify_signature', '0') === '1'); }

if ($verifySig) {
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if (!$appSecret || !$signature) {
        http_response_code(403);
        exit;
    }
    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
    if (!hash_equals($expected, $signature)) {
        http_response_code(403);
        exit;
    }
}

if (!$data || !isset($data['entry'])) {
    http_response_code(400);
    exit;
}

$db = getDB();

foreach ($data['entry'] as $entry) {
    foreach ($entry['changes'] as $change) {
        if (isset($change['value']['messages'])) {
            foreach ($change['value']['messages'] as $message) {
                $from = $message['from'] ?? '';
                $text = $message['text']['body'] ?? '';
                $msgId = $message['id'] ?? '';
                
                // Log message
                $db->insert('whatsapp_messages', [
                    'message_id' => $msgId,
                    'from_number' => $from,
                    'message_text' => $text,
                    'received_at' => date('Y-m-d H:i:s')
                ]);
                
                // Process command
                $response = processWhatsAppCommand($text, $from, $db);
                if ($response) {
                    sendWhatsAppMessage($from, $response);
                }
            }
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);

function processWhatsAppCommand($text, $from, $db) {
    $text = strtolower(trim($text));
    
    // Main menu
    if (in_array($text, ['hi', 'hello', 'help', 'menu', 'start'])) {
        return "🏭 *JIADA Portal*\n\nWelcome to Jharkhand Industrial Area Development Authority\n\n*Main Menu:*\n1️⃣ Land Allotment Status\n2️⃣ Payment Status\n3️⃣ Application Status\n4️⃣ Utility Services\n5️⃣ Grievance Status\n6️⃣ Lease Services\n7️⃣ Contact Us\n\nReply with a number to continue.";
    }
    
    // Check status commands
    if (preg_match('/status\s*(\w+)/i', $text, $matches)) {
        $type = strtolower($matches[1]);
        $user = $db->fetchOne("SELECT id, name FROM users WHERE mobile = ?", [$from]);
        if (!$user) {
            return "Please register on the JIADA portal first at " . APP_URL . "/register";
        }
        
        switch ($type) {
            case 'application':
            case 'app':
                $app = $db->fetchOne("SELECT application_no, status FROM land_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1", [$user['id']]);
                if ($app) {
                    return "📋 *Application Status*\n\nRef: {$app['application_no']}\nStatus: " . ucfirst($app['status']);
                }
                return "No applications found.";
                
            case 'payment':
            case 'pay':
                $dues = $db->fetchOne("SELECT SUM(amount - paid_amount) as outstanding FROM payments WHERE user_id = ? AND status != 'paid'", [$user['id']]);
                return "💰 *Payment Status*\n\nOutstanding: ₹" . number_format($dues['outstanding'] ?? 0, 2) . "\n\nPay at: " . APP_URL . "/payments";
                
            case 'grievance':
            case 'grev':
                $g = $db->fetchOne("SELECT grievance_no, status FROM grievances WHERE user_id = ? ORDER BY created_at DESC LIMIT 1", [$user['id']]);
                if ($g) {
                    return "📝 *Grievance Status*\n\nRef: {$g['grievance_no']}\nStatus: " . ucfirst($g['status']);
                }
                return "No grievances found.";
        }
    }
    
    // Menu selections
    switch ($text) {
        case '1':
            return "🏭 *Land Allotment*\n\nView available plots at: " . APP_URL . "/land-bank\n\nTo check application status, type: status application";
        case '2':
            return "💰 *Payments*\n\nMake payments at: " . APP_URL . "/payments\n\nTo check dues, type: status payment";
        case '3':
            return "📋 *Applications*\n\nView all applications at: " . APP_URL . "/applications\n\nTo check status, type: status application";
        case '4':
            return "🔧 *Utility Services*\n\nRaise tickets at: " . APP_URL . "/utilities";
        case '5':
            return "📝 *Grievances*\n\nFile grievance at: " . APP_URL . "/grievances\n\nTo check status, type: status grievance";
        case '6':
            return "📄 *Lease Services*\n\nTransfer, Mortgage, Surrender at: " . APP_URL . "/services";
        case '7':
            return "📞 *Contact Us*\n\n📱 Phone: 0651-2220000\n📧 Email: support@jiada.in\n📍 Address: JIADA Bhawan, Dhurwa, Ranchi - 834004\n🌐 Portal: " . APP_URL;
        default:
            return "I didn't understand that. Type *menu* for options.";
    }
}

function sendWhatsAppMessage($to, $message) {
    $result = sendWhatsAppCloudMessage($to, $message);
    if (!$result['success']) {
        logMessage("WhatsApp message failed to $to: " . ($result['message'] ?? 'error'), 'error', 'whatsapp');
        return false;
    }
    return true;
}
