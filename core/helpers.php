<?php
/**
 * JIADA Portal - Helper Functions
 * Common utility functions used throughout the application
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

// ===========================
// General Helpers
// ===========================

/**
 *Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output for HTML
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate random string
 */
function generateRandomString($length = 10, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    $string = '';
    $max = strlen($characters) - 1;
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[random_int(0, $max)];
    }
    return $string;
}

/**
 * Generate OTP
 */
function generateOTP($length = OTP_LENGTH) {
    return generateRandomString($length, '0123456789');
}

/**
 * Format date for display
 */
function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = '₹') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Generate unique reference number
 */
function generateReferenceNumber($prefix = 'JIADA') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = null, $maxSize = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload failed'];
    }
    
    $allowedTypes = $allowedTypes ?? UPLOAD_ALLOWED_TYPES;
    $maxSize = $maxSize ?? UPLOAD_MAX_SIZE;
    
    $extension = getFileExtension($file['name']);
    
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    return ['success' => true];
}

/**
 * Upload file
 */
function uploadFile($file, $destinationPath = null, $newFilename = null) {
    $validation = validateFileUpload($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    $destinationPath = $destinationPath ?? UPLOAD_PATH;
    
    if (!is_dir($destinationPath)) {
        if (!mkdir($destinationPath, 0755, true) && !is_dir($destinationPath)) {
            return ['success' => false, 'message' => 'Upload directory could not be created'];
        }
    }

    if (!is_writable($destinationPath)) {
        return ['success' => false, 'message' => 'Upload directory is not writable'];
    }
    
    $extension = getFileExtension($file['name']);
    $filename = $newFilename ?? generateReferenceNumber('DOC') . '.' . $extension;
    
    $targetPath = $destinationPath . '/' . $filename;

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Temporary uploaded file is missing'];
    }

    if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
    }

    logError("Failed to move uploaded file to {$targetPath}", 'general');
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

// ===========================
// JSON Response Helpers
// ===========================

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send success response
 */
function successResponse($message, $data = null) {
    return [
        'success' => true,
        'message' => $message,
        'data' => $data
    ];
}

/**
 * Send error response
 */
function errorResponse($message, $code = 400, $errors = null) {
    return [
        'success' => false,
        'message' => $message,
        'errors' => $errors
    ];
}

// ===========================
// Redirect Helpers
// ===========================

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: {$url}");
    exit;
}

/**
 * Get base URL
 */
function baseUrl($path = '') {
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Get current URL
 */
function currentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// ===========================
// Request Helpers
// ===========================

/**
 * Get POST data
 */
function getPost($key = null, $default = null) {
    if ($key === null) {
        return $_POST;
    }
    return $_POST[$key] ?? $default;
}

/**
 * Get GET data
 */
function getGet($key = null, $default = null) {
    if ($key === null) {
        return $_GET;
    }
    return $_GET[$key] ?? $default;
}

/**
 * Get request method
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Check if AJAX request
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get all headers
 */
function getHeaders() {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $headerName = str_replace('HTTP_', '', $key);
            $headerName = ucwords(strtolower(str_replace('_', ' ', $headerName)));
            $headerName = str_replace(' ', '-', $headerName);
            $headers[$headerName] = $value;
        }
    }
    return $headers;
}

// ===========================
// Validation Helpers
// ===========================

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Indian format)
 */
function isValidPhone($phone) {
    return preg_match('/^[6-9]\d{9}$/', preg_replace('/[^0-9]/', '', $phone));
}

/**
 * Validate required fields
 */
function validateRequired($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

// ===========================
// Logging Helpers
// ===========================

/**
 * Log message
 */
function logMessage($message, $level = 'info', $module = 'general') {
    $logDir = LOGS_PATH . '/' . $module;
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Log error
 */
function logError($message, $module = 'general') {
    logMessage($message, 'error', $module);
}

/**
 * Log activity
 */
function logActivity($message, $userId = null) {
    $userId = $userId ?? (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
    logMessage("User {$userId}: {$message}", 'activity', 'audit');
}

// ===========================
// System Settings Helper
// ===========================
function getSystemSetting($key, $default = null) {
    try {
        $db = getDB();
        $row = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]);
        if ($row && isset($row['setting_value'])) {
            return $row['setting_value'];
        }
    } catch (Exception $e) {
        // ignore if DB not available
    }
    return $default;
}

// ===========================
// WhatsApp Cloud API Helper
// ===========================

/**
 * Send WhatsApp message via Meta Cloud API
 */
function sendWhatsAppCloudMessage($to, $message) {
    $token = defined('WHATSAPP_ACCESS_TOKEN') ? WHATSAPP_ACCESS_TOKEN : '';
    $phoneId = defined('WHATSAPP_PHONE_NUMBER_ID') ? WHATSAPP_PHONE_NUMBER_ID : '';
    if (!$token) { $token = getSystemSetting('whatsapp_access_token', ''); }
    if (!$phoneId) { $phoneId = getSystemSetting('whatsapp_phone_number_id', ''); }
    if (!$token || !$phoneId) {
        logError('WhatsApp config missing', 'whatsapp');
        return ['success' => false, 'message' => 'WhatsApp configuration missing'];
    }
    $version = defined('WHATSAPP_API_VERSION') ? WHATSAPP_API_VERSION : 'v19.0';
    $url = "https://graph.facebook.com/{$version}/" . $phoneId . "/messages";
    
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => preg_replace('/[^0-9]/', '', $to),
        'type' => 'text',
        'text' => ['body' => $message]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logError("WhatsApp send error: {$error}", 'whatsapp');
        return ['success' => false, 'message' => $error];
    }
    
    if ($httpCode < 200 || $httpCode >= 300) {
        logError("WhatsApp send HTTP {$httpCode}: {$response}", 'whatsapp');
        return ['success' => false, 'message' => 'WhatsApp API error', 'response' => $response];
    }
    
    return ['success' => true, 'response' => $response];
}

// ===========================
// Pagination Helper
// ===========================

/**
 * Generate pagination
 */
function paginate($total, $perPage = PER_PAGE, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Generate pagination HTML
 */
function paginationHtml($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<nav class="pagination"><ul class="pagination-list">';
    
    // Previous button
    if ($pagination['has_previous']) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($pagination['current_page'] - 1) . '" class="page-link">Previous</a></li>';
    }
    
    // Page numbers
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $pagination['current_page'] ? ' active' : '';
        $html .= '<li><a href="' . $baseUrl . '&page=' . $i . '" class="page-link' . $active . '">' . $i . '</a></li>';
    }
    
    // Next button
    if ($pagination['has_next']) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($pagination['current_page'] + 1) . '" class="page-link">Next</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

// ===========================
// Template Helpers
// ===========================

/**
 * Render template
 */
function renderTemplate($template, $data = []) {
    extract($data);
    $templateFile = TEMPLATES_PATH . '/' . $template . '.php';
    
    if (file_exists($templateFile)) {
        ob_start();
        include $templateFile;
        return ob_get_clean();
    }
    
    return "Template not found: {$template}";
}

/**
 * Include partial
 */
function partial($partial, $data = []) {
    echo renderTemplate('partials/' . $partial, $data);
}

// ===========================
// CSRF Protection
// ===========================

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token field
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// ===========================
// Array Helpers
// ===========================

/**
 * Get array value by dot notation
 */
function arrayGet($array, $key, $default = null) {
    $keys = explode('.', $key);
    $value = $array;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * Merge arrays recursively
 */
function arrayMergeRecursiveDistinct(array &$array1, array &$array2) {
    $merged = $array1;
    
    foreach ($array2 as $key => $value) {
        if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
            $merged[$key] = arrayMergeRecursiveDistinct($merged[$key], $value);
        } else {
            $merged[$key] = $value;
        }
    }
    
    return $merged;
}

// ===========================
// String Helpers
// ===========================

/**
 * Slugify string
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text);
}

/**
 * Truncate string
 */
function truncate($string, $length = 100, $append = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    return substr($string, 0, $length) . $append;
}

/**
 * Convert bytes to human readable
 */
function bytesToHuman($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// ===========================
// Auth Helpers
// ===========================

/**
 * Get Bearer token from Authorization header
 */
function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    // Fallback: check $_SERVER
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

/**
 * Get current logged in user ID
 */
function getCurrentUserId() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']);
}

/**
 * Get user type
 */
function getUserType() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user_type'] ?? null;
}

/**
 * Require login
 */
function requireLogin($redirectUrl = null) {
    if (!isLoggedIn()) {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
        } else {
            $redirectUrl = $redirectUrl ?? APP_URL . '/login';
            header("Location: $redirectUrl");
            exit;
        }
    }
}
