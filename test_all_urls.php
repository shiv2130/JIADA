<?php
/**
 * JIADA Portal - Comprehensive URL Tester
 * Tests all URLs and reports results
 */
define('JIADA_APP', true);
require_once __DIR__ . '/config/config.php';

$urls = [
    'Home' => APP_URL . '/',
    'Land Bank' => APP_URL . '/land-bank',
    'Plug & Play' => APP_URL . '/plug-play',
    'Services' => APP_URL . '/services',
    'Utilities' => APP_URL . '/utilities',
    'Grievances' => APP_URL . '/grievances',
    'Court Cases' => APP_URL . '/court-cases',
    'Applications' => APP_URL . '/applications',
    'Payments' => APP_URL . '/payments',
    'Certificates' => APP_URL . '/certificates',
    'Help' => APP_URL . '/help',
    'Register' => APP_URL . '/register',
    'Login' => APP_URL . '/login',
    'Dashboard' => APP_URL . '/dashboard',
    'Admin Login' => APP_URL . '/admin/?page=login',
];

echo "=== JIADA Portal URL Test ===\n\n";

$passed = 0;
$failed = 0;

foreach ($urls as $name => $url) {
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
            echo "FAIL - $name ($url) - HTTP $httpCode (Fatal Error)\n";
            $failed++;
        } else {
            echo "OK - $name ($url) - HTTP $httpCode (" . strlen($content) . " bytes)\n";
            $passed++;
        }
    } else {
        echo "FAIL - $name ($url) - HTTP $httpCode" . ($error ? " ($error)" : "") . "\n";
        $failed++;
    }
}

echo "\n=== Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";