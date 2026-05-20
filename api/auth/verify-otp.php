<?php
/**
 * JIADA Portal - Verify OTP API
 * POST /api/auth/verify-otp
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = getPost();

// Validate required fields
if (empty($input['otp_id'])) {
    jsonResponse(['success' => false, 'message' => 'OTP ID is required'], 400);
}

if (empty($input['otp'])) {
    jsonResponse(['success' => false, 'message' => 'OTP is required'], 400);
}

$result = OTP::verifyById($input['otp_id'], $input['otp']);

jsonResponse($result, $result['success'] ? 200 : 400);