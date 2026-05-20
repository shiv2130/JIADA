<?php
/**
 * JIADA Portal - Logout API
 * POST /api/auth/logout
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Authenticate user
$auth = JWT::authenticate();

if (!$auth['success']) {
    jsonResponse($auth, 401);
}

$token = JWT::getAuthorizationToken();

// Blacklist the token
if ($token) {
    JWT::blacklistToken($token);
}

// Clear session
if (session_status() !== PHP_SESSION_NONE) {
    session_destroy();
}

logActivity("User logged out", $auth['user']['id']);

jsonResponse([
    'success' => true,
    'message' => 'Logged out successfully'
]);