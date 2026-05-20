<?php
/**
 * JIADA Portal - Main Configuration
 * Core Application Settings and Constants
 */

// Prevent direct access
if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

// ===========================
// Application Settings
// ===========================
define('APP_NAME', 'JIADA Portal');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/JIADA');
define('APP_ENV', 'development'); // development, staging, production
define('APP_DEBUG', true);
define('SINGLE_WINDOW_LOGIN_URL', ''); // e.g., https://singlewindow.jharkhand.gov.in/sso/login
define('SINGLE_WINDOW_RETURN_URL', APP_URL . '/sso/callback');

// ===========================
// Path Configuration
// ===========================
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('CORE_PATH', BASE_PATH . '/core');
define('MODULES_PATH', BASE_PATH . '/modules');
define('API_PATH', BASE_PATH . '/api');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('TEMPLATES_PATH', BASE_PATH . '/templates');
define('ASSETS_PATH', BASE_PATH . '/assets');
define('LOGS_PATH', BASE_PATH . '/logs');

// Include database configuration
require_once CONFIG_PATH . '/database.php';

// ===========================
// JWT Configuration
// ===========================
define('JWT_SECRET', 'jiada_secret_key_change_in_production_2026_fixed');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRE', 3600); // 1 hour
define('JWT_REFRESH_EXPIRE', 86400 * 7); // 7 days

// ===========================
// OTP Configuration
// ===========================
define('OTP_LENGTH', 6);
define('OTP_EXPIRE_TIME', 300); // 5 minutes
define('OTP_MAX_ATTEMPTS', 3);
define('OTP_RESEND_COOLDOWN', 60); // 1 minute

// ===========================
// Session Configuration
// ===========================
define('SESSION_LIFETIME', 3600);
define('SESSION_NAME', 'JIADA_SESSION');
define('SESSION_SECURE', false); // Set true for HTTPS

// ===========================
// Email Configuration
// ===========================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'noreply@jiada.in');
define('SMTP_PASSWORD', '');
define('MAIL_FROM', 'noreply@jiada.in');
define('MAIL_FROM_NAME', 'JIADA Portal');

// ===========================
// SMS Configuration
// ===========================
define('SMS_PROVIDER', 'custom'); // twilio, custom, etc.
define('SMS_API_KEY', '');
define('SMS_SENDER_ID', 'JIADA');
define('SMS_API_URL', '');

// ===========================
// Payment Gateway Configuration
// ===========================
define('PAYMENT_GATEWAY', 'razorpay'); // razorpay, payu, custom
define('PAYMENT_MERCHANT_ID', '');
define('PAYMENT_API_KEY', '');
define('PAYMENT_API_SECRET', '');
define('PAYMENT_MODE', 'test'); // test, production
define('PAYMENT_CALLBACK_URL', APP_URL . '/api/payment/callback');

// ===========================
// Redis Configuration
// ===========================
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');
define('REDIS_DB', 0);

// ===========================
// File Upload Configuration
// ===========================
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', [
    'pdf', 'jpg', 'jpeg', 'png', 'gif',
    'doc', 'docx', 'xls', 'xlsx',
    'zip', 'rar', 'txt'
]);
define('UPLOAD_PATH', UPLOADS_PATH . '/documents');

// ===========================
// GIS Configuration
// ===========================
define('GIS_TILE_SERVER', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
define('GIS_DEFAULT_CENTER', [23.3441, 85.3096]); // Jharkhand coordinates
define('GIS_DEFAULT_ZOOM', 8);

// ===========================
// WhatsApp Bot Configuration
// ===========================
define('WHATSAPP_API_URL', '');
define('WHATSAPP_API_KEY', '');
define('WHATSAPP_PHONE_ID', '');
define('WHATSAPP_PHONE_NUMBER_ID', '');
define('WHATSAPP_ACCESS_TOKEN', '');
define('WHATSAPP_VERIFY_TOKEN', 'jiada_whatsapp_token');
define('WHATSAPP_API_VERSION', 'v19.0');
define('WHATSAPP_APP_SECRET', '');
define('WHATSAPP_VERIFY_SIGNATURE', false); // set true after adding WHATSAPP_APP_SECRET

// ===========================
// HRMS Configuration
// ===========================
define('HRMS_WORKING_HOURS', 8);
define('HRMS_MONTHLY_WORKING_DAYS', 26);
define('HRMS_LATE_GRACE_PERIOD', 15); // minutes
define('HRMS_HALF_DAY_THRESHOLD', 4); // hours

// ===========================
// Pagination
// ===========================
define('PER_PAGE', 25);

// ===========================
// Date & Time
// ===========================
define('TIMEZONE', 'Asia/Kolkata');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y h:i A');

// Set timezone
date_default_timezone_set(TIMEZONE);

// ===========================
// Error Reporting
// ===========================
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ===========================
// Autoloader
// ===========================
spl_autoload_register(function ($class) {
    // Core classes
    $coreFile = CORE_PATH . '/' . $class . '.php';
    if (file_exists($coreFile)) {
        require_once $coreFile;
        return;
    }
    
    // Module classes
    $parts = explode('\\', $class);
    if (count($parts) > 1) {
        $module = strtolower($parts[0]);
        $className = $parts[1];
        $moduleFile = MODULES_PATH . '/' . $module . '/' . $className . '.php';
        if (file_exists($moduleFile)) {
            require_once $moduleFile;
            return;
        }
        
        // Controller classes
        if ($className === 'Controller') {
            $controllerFile = MODULES_PATH . '/' . $module . '/controller.php';
            if (file_exists($controllerFile)) {
                require_once $controllerFile;
                return;
            }
        }
    }
});

// ===========================
// Helper Functions
// ===========================
require_once CORE_PATH . '/helpers.php';
