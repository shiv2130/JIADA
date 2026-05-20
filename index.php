<?php
/**
 * JIADA Portal - Main Entry Point
 * Jharkhand Industrial Area Development Authority
 * 
 * @version 1.0.0
 * @package JIADA
 */

// Define application constant
define('JIADA_APP', true);

// Security Headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Show all errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
require_once __DIR__ . '/config/config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Create required directories
$requiredDirs = [
    UPLOADS_PATH,
    UPLOAD_PATH,
    LOGS_PATH,
    LOGS_PATH . '/audit',
    LOGS_PATH . '/otp',
    LOGS_PATH . '/payments',
    LOGS_PATH . '/general',
    UPLOADS_PATH . '/survey'
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Router
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($basePath !== '/') {
    $requestUri = substr($requestUri, strlen($basePath));
}
$requestUri = '/' . ltrim($requestUri, '/');

// Remove query string
$requestUri = parse_url($requestUri, PHP_URL_PATH);

// Route mapping
$routes = [
    // Public routes
    '/' => 'pages/home.php',
    '/login' => 'pages/auth/login.php',
    '/logout' => 'pages/auth/logout.php',
    '/register' => 'pages/auth/register.php',
    '/forgot-password' => 'pages/auth/forgot-password.php',
    '/reset-password' => 'pages/auth/reset-password.php',
    '/sso/redirect' => 'pages/sso/redirect.php',
    '/sso/callback' => 'pages/sso/callback.php',
    
    // API routes
    '/api/auth/login' => 'api/auth/login.php',
    '/api/auth/login-otp' => 'api/auth/login-otp.php',
    '/api/auth/verify-otp' => 'api/auth/verify-otp.php',
    '/api/auth/register' => 'api/auth/register.php',
    '/api/auth/refresh' => 'api/auth/refresh.php',
    '/api/auth/logout' => 'api/auth/logout.php',
    '/api/auth/send-otp' => 'api/auth/send-otp.php',
    
    // Land Allotment API
    '/api/land/plots' => 'api/land/plots.php',
    '/api/land/apply' => 'api/land/apply.php',
    '/api/land/applications' => 'api/land/applications.php',
    '/api/land/bids' => 'api/land/bids.php',
    '/api/land/bid' => 'api/land/bid.php',
    '/api/land/bid-status' => 'api/land/bid-status.php',
    '/api/land/track' => 'api/land/track.php',
    '/api/land/clarification' => 'api/land/clarification.php',
    '/api/land/application-save' => 'api/land/application-save.php',
    '/api/land/application-draft' => 'api/land/application-draft.php',
    '/api/land/document-checklist' => 'api/land/document-checklist.php',
    
    // Plug & Play API
    '/api/plugplay/units' => 'api/plugplay/units.php',
    '/api/plugplay/apply' => 'api/plugplay/apply.php',
    
    // Utility Management API
    '/api/utilities/tickets' => 'api/utilities/tickets.php',
    '/api/utilities/ticket' => 'api/utilities/ticket.php',
    
    // Grievance API
    '/api/grievances/submit' => 'api/grievances/submit.php',
    '/api/grievances/list' => 'api/grievances/list.php',
    '/api/grievances/respond' => 'api/grievances/respond.php',
    
    // Lease Management API
    '/api/lease/surrender' => 'api/lease/surrender.php',
    '/api/lease/mortgage' => 'api/lease/mortgage.php',
    '/api/lease/transfer' => 'api/lease/transfer.php',
    '/api/lease/modify' => 'api/lease/modify.php',
    
    // Payment API
    '/api/payment/initiate' => 'api/payment/initiate.php',
    '/api/payment/callback' => 'api/payment/callback.php',
    '/api/payment/status' => 'api/payment/status.php',
    '/api/payment/receipt' => 'api/payment/receipt.php',
    '/api/payment/dues' => 'api/payment/dues.php',
    
    // Court Case API
    '/api/court-cases' => 'api/court-cases/list.php',
    '/api/court-case' => 'api/court-cases/view.php',
    '/api/court-case/add' => 'api/court-cases/add.php',
    '/api/court-case/update' => 'api/court-cases/update.php',
    
    // Change of Constitution/Shareholding
    '/api/constitution/change' => 'api/constitution/change.php',
    '/api/shareholding/change' => 'api/shareholding/change.php',
    
    // Cancellation API
    '/api/cancellation/notice' => 'api/cancellation/notice.php',
    '/api/cancellation/respond' => 'api/cancellation/respond.php',
    
    // Renewal API
    '/api/renewal/apply' => 'api/renewal/apply.php',
    '/api/renewal/status' => 'api/renewal/status.php',

    // Cancellation API
    '/api/cancellation/notice' => 'api/cancellation/notice.php',
    '/api/cancellation/respond' => 'api/cancellation/respond.php',
    '/api/cancellation/list' => 'api/cancellation/list.php',
    
    // Notification API
    '/api/notifications' => 'api/notifications/list.php',
    '/api/notifications/unread-count' => 'api/notifications/unread-count.php',
    '/api/notifications/mark-read' => 'api/notifications/mark-read.php',
    '/api/notifications/mark-all-read' => 'api/notifications/mark-all-read.php',
    
    // HRMS API
    '/api/hrms/employees' => 'api/hrms/employees.php',
    '/api/hrms/attendance' => 'api/hrms/attendance.php',
    '/api/hrms/leave' => 'api/hrms/leave.php',
    '/api/hrms/payroll' => 'api/hrms/payroll.php',
    // BRAP tracker
    '/api/brap/items' => 'api/brap/items.php',
    // Survey GIS
    '/api/survey/parcels' => 'api/survey/parcels.php',
    '/api/survey/verify' => 'api/survey/verify.php',
    
    // Land Survey API
    '/api/survey/create' => 'api/survey/create.php',
    '/api/survey/data' => 'api/survey/data.php',
    '/api/survey/gis' => 'api/survey/gis.php',
    
    // WhatsApp Webhook
    '/api/whatsapp/webhook' => 'api/whatsapp/webhook.php',
    '/api/whatsapp/message' => 'api/whatsapp/message.php',
    
    // CAPTCHA
    '/api/captcha/generate' => 'api/captcha/generate.php',
    
    // Dashboard
    '/dashboard' => 'pages/dashboard.php',
    '/master-dashboard' => 'pages/master-dashboard.php',
    '/profile' => 'pages/profile.php',
    '/applications' => 'pages/applications.php',
    '/payments' => 'pages/payments.php',
    '/notifications' => 'pages/notifications.php',
    '/hrms' => 'pages/hrms.php',
    '/brap' => 'pages/brap.php',
    '/survey' => 'pages/survey.php',
    
    // Public pages
    '/land-bank' => 'pages/land-bank.php',
    '/land-apply' => 'pages/land-apply.php',
    '/plug-play' => 'pages/plug-play.php',
    '/plugplay-apply' => 'pages/plugplay-apply.php',
    '/services' => 'pages/services.php',
    '/utilities' => 'pages/utilities.php',
    '/grievances' => 'pages/grievances.php',
    '/court-cases' => 'pages/court-cases.php',
    '/application-view' => 'pages/application-view.php',
    '/certificates' => 'pages/certificates.php',
    '/help' => 'pages/help.php',
    '/contact' => 'pages/contact.php',
    '/forgot-password' => 'pages/auth/forgot-password.php',
    '/lease-transfer' => 'pages/lease-transfer.php',
    '/lease-mortgage' => 'pages/lease-mortgage.php',
    '/lease-surrender' => 'pages/lease-surrender.php',
    '/lease-renewal' => 'pages/lease-renewal.php',
    '/lease-merge' => 'pages/lease-merge.php',
    '/constitution-change' => 'pages/constitution-change.php',
    '/shareholding-change' => 'pages/shareholding-change.php',
    '/cancellation' => 'pages/cancellation.php',
    '/whatsapp-bot' => 'pages/whatsapp-bot.php',
    '/survey' => 'pages/survey.php',
    '/brap' => 'pages/brap.php',
    '/mobile-app' => 'pages/mobile-app.php',
    
    // Admin routes
    '/admin/login' => 'pages/admin/login.php',
    '/admin/dashboard' => 'pages/admin/dashboard.php',
    '/admin/logout' => 'pages/admin/logout.php',
    '/admin/users' => 'pages/admin/generic.php',
    '/admin/land-bank' => 'pages/admin/generic.php',
    '/admin/applications' => 'pages/admin/generic.php',
    '/admin/bids' => 'pages/admin/generic.php',
    '/admin/grievances' => 'pages/admin/generic.php',
    '/admin/court-cases' => 'pages/admin/generic.php',
    '/admin/payments' => 'pages/admin/generic.php',
    '/admin/reports' => 'pages/admin/generic.php',
    '/admin/settings' => 'pages/admin/generic.php',
    '/admin/lease' => 'pages/admin/generic.php',
    '/admin/utilities' => 'pages/admin/generic.php',
    '/admin/hrms' => 'pages/admin/generic.php',
    '/admin/surveys' => 'pages/admin/generic.php',
    '/admin/audit-log' => 'pages/admin/generic.php',
    '/admin/mobile-app' => 'pages/admin/generic.php',
    '/admin/plug-play' => 'pages/admin/generic.php',
];

// Find matching route
$matchedRoute = null;

// Exact match first
if (isset($routes[$requestUri])) {
    $matchedRoute = $routes[$requestUri];
} else {
    // Pattern matching for dynamic routes
    $patterns = [
        '/^\/api\/land\/plot\/(\d+)$/' => 'api/land/plot.php',
        '/^\/api\/land\/application\/(\d+)$/' => 'api/land/application.php',
        '/^\/api\/notifications\/(\d+)\/read$/' => 'api/notifications/read.php',
        '/^\/api\/utilities\/ticket\/(\d+)$/' => 'api/utilities/ticket.php',
        '/^\/api\/grievances\/(\d+)$/' => 'api/grievances/view.php',
        '/^\/api\/grievances\/(\d+)\/respond$/' => 'api/grievances/respond.php',
        '/^\/api\/payment\/(\d+)$/' => 'api/payment/view.php',
    ];
    
    foreach ($patterns as $pattern => $handler) {
        if (preg_match($pattern, $requestUri, $matches)) {
            $_GET['id'] = $matches[1] ?? null;
            $matchedRoute = $handler;
            break;
        }
    }
}

// Handle API requests (JSON)
$isApi = strpos($requestUri, '/api/') === 0;

// Handle file serving
$fileRoutes = [
    '/uploads/' => UPLOADS_PATH,
    '/assets/' => ASSETS_PATH,
];

foreach ($fileRoutes as $urlPrefix => $fileBase) {
    if (strpos($requestUri, $urlPrefix) === 0) {
        $filePath = $fileBase . substr($requestUri, strlen($urlPrefix) - 1);
        if (file_exists($filePath)) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'pdf' => 'application/pdf',
                'json' => 'application/json',
            ];
            
            if (isset($mimeTypes[$extension])) {
                header("Content-Type: " . $mimeTypes[$extension]);
            }
            header("Cache-Control: public, max-age=86400");
            readfile($filePath);
            exit;
        }
    }
}

// Load the matched route
if ($matchedRoute && file_exists(BASE_PATH . '/' . $matchedRoute)) {
    if ($isApi) {
        // Set JSON headers for API
        header('Content-Type: application/json');
        
        // Handle CORS for API
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        
        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        // Read JSON body for non-GET requests
        if (in_array(getRequestMethod(), ['POST', 'PUT', 'PATCH'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                $jsonBody = file_get_contents('php://input');
                $_POST = json_decode($jsonBody, true) ?? [];
            }
        }
    }
    
    require_once BASE_PATH . '/' . $matchedRoute;
} else {
    // 404 handler
    http_response_code(404);
    if ($isApi) {
        jsonResponse(['success' => false, 'message' => 'Endpoint not found'], 404);
    } else {
        echo '<h1>404 - Page Not Found</h1>';
        echo '<p>Requested URL: ' . htmlspecialchars($_SERVER['REQUEST_URI']) . '</p>';
        echo '<p><a href="' . APP_URL . '">Go Home</a></p>';
    }
}
