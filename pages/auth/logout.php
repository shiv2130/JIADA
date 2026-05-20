<?php
if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

$userId = $_SESSION['user_id'] ?? null;
$token = $_SESSION['jwt_token'] ?? null;

if ($token) {
    JWT::blacklistToken($token);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

if ($userId) {
    logActivity('User logged out', $userId);
}

redirect(baseUrl('/login'));
