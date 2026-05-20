<?php
/**
 * JIADA Portal - Registration API
 * POST /api/auth/register
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = getPost();
$db = getDB();
$db->beginTransaction();

try {
    // Validate required fields
    $required = ['name', 'mobile', 'user_type'];
    $missing = validateRequired($input, $required);
    
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }
    
    // Validate mobile
    if (!isValidPhone($input['mobile'])) {
        throw new Exception('Invalid mobile number. Must be a valid 10-digit Indian number.');
    }
    
    // Check if mobile already exists
    $existingUser = $db->fetchOne("SELECT id FROM users WHERE mobile = ?", [$input['mobile']]);
    if ($existingUser) {
        throw new Exception('Mobile number already registered');
    }
    
    // Validate email if provided
    if (!empty($input['email'])) {
        if (!isValidEmail($input['email'])) {
            throw new Exception('Invalid email address');
        }
        $existingEmail = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$input['email']]);
        if ($existingEmail) {
            throw new Exception('Email already registered');
        }
    }
    
    // Validate user type
    $allowedTypes = ['applicant', 'allottee', 'vendor'];
    if (!in_array($input['user_type'], $allowedTypes)) {
        throw new Exception('Invalid user type');
    }
    
    // Hash password if provided
    $password = null;
    if (!empty($input['password'])) {
        if (strlen($input['password']) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }
        $password = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    // Verify OTP if this is OTP-based registration
    if (!empty($input['otp_verified']) && $input['otp_verified'] == true) {
        // OTP already verified, proceed
    } elseif (!empty($input['otp']) && !empty($input['otp_id'])) {
        $otpResult = OTP::verifyById($input['otp_id'], $input['otp']);
        if (!$otpResult['success']) {
            throw new Exception($otpResult['message']);
        }
    }
    
    // Insert user
    $userId = $db->insert('users', [
        'name' => sanitize($input['name']),
        'email' => !empty($input['email']) ? sanitize($input['email']) : null,
        'mobile' => preg_replace('/[^0-9]/', '', $input['mobile']),
        'password' => $password,
        'user_type' => sanitize($input['user_type']),
        'company_name' => !empty($input['company_name']) ? sanitize($input['company_name']) : null,
        'company_type' => !empty($input['company_type']) ? sanitize($input['company_type']) : null,
        'gst_number' => !empty($input['gst_number']) ? strtoupper(sanitize($input['gst_number'])) : null,
        'udyam_number' => !empty($input['udyam_number']) ? sanitize($input['udyam_number']) : null,
        'is_msme' => !empty($input['is_msme']) ? 1 : 0,
        'msme_category' => !empty($input['msme_category']) ? sanitize($input['msme_category']) : null,
        'address' => !empty($input['address']) ? sanitize($input['address']) : null,
        'city' => !empty($input['city']) ? sanitize($input['city']) : null,
        'district' => !empty($input['district']) ? sanitize($input['district']) : null,
        'state' => !empty($input['state']) ? sanitize($input['state']) : 'Jharkhand',
        'pincode' => !empty($input['pincode']) ? sanitize($input['pincode']) : null,
        'mobile_verified' => !empty($input['otp_verified']) || !empty($input['otp']) ? 1 : 0,
        'email_verified' => !empty($input['otp_verified']) || !empty($input['otp']) ? 1 : 0,
        'notification_preferences' => json_encode(['email' => true, 'sms' => true, 'whatsapp' => false])
    ]);
    
    // Assign default role based on user_type (using user_type directly since roles table may not exist)
    try {
        $defaultRole = $db->fetchOne("SELECT id FROM roles WHERE slug = ?", [
            $input['user_type'] . '_role'
        ]);
        
        if (!$defaultRole) {
            $db->insert('roles', [
                'name' => ucfirst($input['user_type']),
                'slug' => $input['user_type'] . '_role',
                'user_type' => $input['user_type']
            ]);
            $roleId = $db->getConnection()->lastInsertId();
        } else {
            $roleId = $defaultRole['id'];
        }
        
        $db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleId]);
    } catch (Exception $roleEx) {
        // Roles table doesn't exist, skip role assignment - user_type is sufficient
        logMessage("Role assignment skipped: " . $roleEx->getMessage(), 'info', 'general');
    }
    
    $db->commit();
    
    // Generate token
    $tokenResult = JWT::generateToken($userId, $input['user_type'], [
        'name' => $input['name'],
        'email' => $input['email'] ?? null
    ]);
    
    logActivity("New user registered", $userId);
    
    jsonResponse([
        'success' => true,
        'message' => 'Registration successful',
        'data' => [
            'user' => [
                'id' => $userId,
                'name' => $input['name'],
                'email' => $input['email'] ?? null,
                'mobile' => preg_replace('/[^0-9]/', '', $input['mobile']),
                'user_type' => $input['user_type']
            ],
            'token' => $tokenResult['token'],
            'expires_in' => $tokenResult['expires_in']
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}