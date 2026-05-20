<?php
// Single Window SSO redirect placeholder
require_once __DIR__ . '/../../config/config.php';

$loginUrl = SINGLE_WINDOW_LOGIN_URL;
$returnUrl = SINGLE_WINDOW_RETURN_URL;

if (!$loginUrl) {
    echo '<h3>Single Window SSO is not configured yet.</h3>';
    echo '<p>Please set SINGLE_WINDOW_LOGIN_URL in config.php</p>';
    echo '<p><a href="' . APP_URL . '/login">Back to Login</a></p>';
    exit;
}

$redirect = $loginUrl;
// append return url parameter if needed
$sep = (strpos($redirect, '?') === false) ? '?' : '&';
$redirect .= $sep . 'return_url=' . urlencode($returnUrl);

header('Location: ' . $redirect);
exit;
