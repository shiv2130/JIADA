<?php
/**
 * JIADA Portal - Login API
 * POST /api/auth/login
 */

if (!defined('JIADA_APP')) {
    // die('Direct access not permitted');
    define('JIADA_APP', true);
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = getPost();

if (empty($input['email']) && empty($input['mobile'])) {
    jsonResponse(['success' => false, 'message' => 'Mobile or email is required'], 400);
}

if (!empty($input['mobile']) && !isValidPhone($input['mobile'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid mobile number'], 400);
}

if (!empty($input['email']) && !isValidEmail($input['email'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid email address'], 400);
}

$db = getDB();

// Check if user exists
$where = [];
$params = [];

if (!empty($input['mobile'])) {
    $where[] = 'mobile = ?';
    $params[] = preg_replace('/[^0-9]/', '', $input['mobile']);
}

if (!empty($input['email'])) {
    $where[] = 'email = ?';
    $params[] = $input['email'];
}

$whereClause = '(' . implode(' OR ', $where) . ')';

$user = $db->fetchOne(
    "SELECT * FROM users WHERE {$whereClause}",
    $params
);

if (!$user) {
    jsonResponse([
        'success' => false,
        'message' => 'User not found. Please register first.',
        'redirect' => baseUrl('/register')
    ], 404);
}

// Check user status
if ($user['status'] === 'blocked') {
    jsonResponse(['success' => false, 'message' => 'Your account has been blocked. Please contact JIADA office.'], 403);
}

if ($user['status'] === 'inactive') {
    jsonResponse(['success' => false, 'message' => 'Your account is inactive. Please complete verification.'], 403);
}

// Password login is the default UI flow. OTP is only used when explicitly provided.
if (!empty($input['password'])) {
    if (empty($user['password']) || !password_verify($input['password'], $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
    }
} elseif (!empty($input['otp'])) {
    $otpResult = OTP::verify($user['mobile'], $user['email'], $input['otp'], 'login');
    if (!$otpResult['success']) {
        jsonResponse(['success' => false, 'message' => $otpResult['message']], 401);
    }
} else {
    jsonResponse(['success' => false, 'message' => 'Password is required'], 400);
}

// Generate JWT Token
$tokenResult = JWT::generateToken($user['id'], $user['user_type'], [
    'name' => $user['name'],
    'email' => $user['email']
]);

// Update login info
$db->update('users', [
    'last_login' => date(DATETIME_FORMAT),
    'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
], 'id = :id', ['id' => $user['id']]);

// Log the activity
logActivity("User logged in successfully", $user['id']);

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_type'] = $user['user_type'];
$_SESSION['jwt_token'] = $tokenResult['token'];

jsonResponse([
    'success' => true,
    'message' => 'Login successful',
    'data' => [
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'mobile' => $user['mobile'],
            'user_type' => $user['user_type'],
            'company_name' => $user['company_name'],
            'is_msme' => (bool)$user['is_msme']
        ],
        'token' => $tokenResult['token'],
        'expires_in' => $tokenResult['expires_in'],
        'expires_at' => $tokenResult['expires_at']
    ]
]);
