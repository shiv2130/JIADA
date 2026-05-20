<?php
/**
 * CAPTCHA Generate API
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Captcha.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

header('Content-Type: application/json');
$image = Captcha::generate();
echo json_encode(['success' => true, 'image' => $image]);