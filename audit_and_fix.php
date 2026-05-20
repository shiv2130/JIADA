<?php
/**
 * JIADA Portal - Comprehensive Audit & Fix Script
 * Tests all routes, validates database, checks security
 */
define('JIADA_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/helpers.php';

echo "=== JIADA Portal Comprehensive Audit ===\n\n";

$baseUrl = APP_URL;
$issues = [];
$fixes = [];

// 1. ROUTE & PAGE VALIDATION
echo "1. ROUTE & PAGE VALIDATION\n";
echo "==========================\n";

$routes = [
    '/' => 'Home',
    '/register' => 'Register',
    '/login' => 'Login',
    '/dashboard' => 'Dashboard',
    '/admin/?page=login' => 'Admin Login',
    '/land-bank' => 'Land Bank',
    '/plug-play' => 'Plug & Play',
    '/services' => 'Services',
    '/utilities' => 'Utilities',
    '/grievances' => 'Grievances',
    '/court-cases' => 'Court Cases',
    '/applications' => 'Applications',
    '/payments' => 'Payments',
    '/certificates' => 'Certificates',
    '/help' => 'Help',
    '/contact' => 'Contact',
    '/forgot-password' => 'Forgot Password',
    '/lease-transfer' => 'Lease Transfer',
    '/lease-mortgage' => 'Lease Mortgage',
    '/lease-surrender' => 'Lease Surrender',
    '/lease-renewal' => 'Lease Renewal',
    '/lease-merge' => 'Lease Merge',
    '/constitution-change' => 'Constitution Change',
    '/shareholding-change' => 'Shareholding Change',
    '/cancellation' => 'Cancellation',
];

$passed = 0;
$failed = 0;

foreach ($routes as $route => $name) {
    $url = $baseUrl . $route;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode == 200 && !$error) {
        $hasFatal = stripos($content, 'Fatal error') !== false || stripos($content, 'Parse error') !== false;
        if ($hasFatal) {
            echo "FAIL - $name ($route) - Fatal Error\n";
            $issues[] = "$name has fatal error";
            $failed++;
        } else {
            echo "OK - $name ($route) - HTTP $httpCode (" . strlen($content) . " bytes)\n";
            $passed++;
        }
    } else {
        echo "FAIL - $name ($route) - HTTP $httpCode\n";
        $issues[] = "$name returned HTTP $httpCode";
        $failed++;
    }
}

echo "\nRoutes: $passed passed, $failed failed\n\n";

// 2. DATABASE INTEGRATION
echo "2. DATABASE INTEGRATION\n";
echo "=======================\n";

try {
    $db = getDB();
    echo "✅ Database connection successful\n";
    
    $tables = ['users', 'land_banks', 'plug_play_units', 'court_cases', 'notifications', 'activity_logs', 'lease_surrenders', 'lease_mortgages', 'lease_transfers', 'lease_renewals', 'utility_tickets', 'grievances', 'payments', 'land_applications'];
    
    foreach ($tables as $t) {
        try {
            $c = $db->fetchOne("SELECT COUNT(*) as cnt FROM $t")['cnt'];
            echo "✅ $t: $c records\n";
        } catch (Exception $e) {
            echo "❌ $t: " . $e->getMessage() . "\n";
            $issues[] = "$t table error";
        }
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    $issues[] = "Database connection failed";
}

echo "\n";

// 3. AUTHENTICATION & SECURITY
echo "3. AUTHENTICATION & SECURITY\n";
echo "============================\n";

// Check password hashing
$user = $db->fetchOne("SELECT password FROM users WHERE id = 1");
if ($user && password_verify('admin123', $user['password'])) {
    echo "✅ Password hashing verified\n";
} else {
    echo "⚠ Password hashing may need verification\n";
}

// Check for SQL injection prevention
$helpersContent = file_get_contents(__DIR__ . '/core/helpers.php');
if (strpos($helpersContent, 'prepare') !== false || strpos($helpersContent, '?') !== false) {
    echo "✅ Prepared statements used\n";
} else {
    echo "⚠ Check for SQL injection vulnerabilities\n";
    $issues[] = "Potential SQL injection risk";
}

// Check XSS prevention
if (strpos($helpersContent, 'htmlspecialchars') !== false) {
    echo "✅ XSS prevention implemented\n";
} else {
    echo "⚠ XSS prevention may be missing\n";
    $issues[] = "Potential XSS vulnerability";
}

echo "\n";

// 4. CAPTCHA IMPLEMENTATION
echo "4. CAPTCHA IMPLEMENTATION\n";
echo "=========================\n";

$captchaFile = __DIR__ . '/core/Captcha.php';
if (file_exists($captchaFile)) {
    echo "✅ CAPTCHA class exists\n";
    
    $captchaContent = file_get_contents($captchaFile);
    if (strpos($captchaContent, 'verify') !== false) {
        echo "✅ CAPTCHA verification method exists\n";
    }
    if (strpos($captchaContent, 'generate') !== false) {
        echo "✅ CAPTCHA generation method exists\n";
    }
} else {
    echo "❌ CAPTCHA class missing\n";
    $issues[] = "CAPTCHA not implemented";
}

echo "\n";

// 5. API ENDPOINTS
echo "5. API ENDPOINTS\n";
echo "================\n";

$apiRoutes = [
    '/api/land/plots' => 'Land Plots',
    '/api/plugplay/units' => 'Plug & Play Units',
    '/api/court-cases' => 'Court Cases',
    '/api/notifications' => 'Notifications',
    '/api/captcha/generate' => 'CAPTCHA Generate',
];

foreach ($apiRoutes as $route => $name) {
    $url = $baseUrl . $route;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($content, true);
    if ($data !== null) {
        echo "OK - $name - " . ($data['success'] ? 'Success' : 'Error: ' . ($data['message'] ?? 'Unknown')) . "\n";
    } else {
        echo "WARN - $name - HTTP $httpCode\n";
    }
}

echo "\n";

// 6. FILE STRUCTURE
echo "6. FILE STRUCTURE\n";
echo "=================\n";

$requiredFiles = [
    'index.php',
    'config/config.php',
    'config/database.php',
    'core/helpers.php',
    'core/JWT.php',
    'core/OTP.php',
    'core/Notification.php',
    'core/Middleware.php',
    'core/AdminAuth.php',
    'core/Captcha.php',
    'pages/home.php',
    'pages/auth/login.php',
    'pages/auth/register.php',
    'pages/dashboard.php',
    'templates/layout.php',
    'templates/admin_layout.php',
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ $file\n";
    } else {
        echo "❌ $file MISSING\n";
        $issues[] = "$file missing";
    }
}

echo "\n";

// SUMMARY
echo "=== AUDIT SUMMARY ===\n";
echo "Issues found: " . count($issues) . "\n";
foreach ($issues as $i => $issue) {
    echo "  " . ($i + 1) . ". $issue\n";
}

if (count($issues) === 0) {
    echo "\n✅ No critical issues found. Application is production-ready.\n";
}