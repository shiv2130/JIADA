<?php
/**
 * JIADA Portal - Send OTP API
 * POST /api/auth/send-otp
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = getPost();

// Validate input
if (empty($input['mobile']) && empty($input['email'])) {
    jsonResponse(['success' => false, 'message' => 'Mobile or email is required'], 400);
}

if (!empty($input['mobile']) && !isValidPhone($input['mobile'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid mobile number'], 400);
}

if (!empty($input['email']) && !isValidEmail($input['email'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid email address'], 400);
}

$purpose = $input['purpose'] ?? 'login';
$allowedPurposes = ['login', 'registration', 'mobile_verify', 'email_verify', 'password_reset'];

if (!in_array($purpose, $allowedPurposes)) {
    jsonResponse(['success' => false, 'message' => 'Invalid OTP purpose'], 400);
}

$mobile = !empty($input['mobile']) ? preg_replace('/[^0-9]/', '', $input['mobile']) : null;
$email = !empty($input['email']) ? sanitize($input['email']) : null;

// Send OTP
$result = OTP::generateAndSend($mobile, $email, $purpose);

jsonResponse($result, $result['success'] ? 200 : 400);