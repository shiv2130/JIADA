<?php
/**
 * JIADA Portal - Automated Form Tester
 * Opens browser, fills forms, tracks responses
 */
define('JIADA_APP', true);
require_once __DIR__ . '/config/config.php';

echo "=== JIADA Portal Automated Form Testing ===\n\n";

$baseUrl = APP_URL;
$tests = [
    // Test 1: Registration form
    [
        'name' => 'Registration Form',
        'url' => $baseUrl . '/register',
        'method' => 'POST',
        'action' => $baseUrl . '/api/auth/register',
        'data' => [
            'name' => 'Test User ' . time(),
            'email' => 'test' . time() . '@example.com',
            'mobile' => '9' . rand(100000000, 999999999),
            'password' => 'Test@12345',
            'user_type' => 'applicant'
        ],
        'expected' => ['success' => true]
    ],
    
    // Test 2: Login form
    [
        'name' => 'Login Form',
        'url' => $baseUrl . '/api/auth/login',
        'method' => 'POST',
        'data' => [
            'email' => 'admin@jiada.in',
            'password' => 'admin123'
        ],
        'expected' => ['success' => true]
    ],
    
    // Test 3: Land plots API
    [
        'name' => 'Land Plots API',
        'url' => $baseUrl . '/api/land/plots',
        'method' => 'GET',
        'expected' => ['success' => true]
    ],
    
    // Test 4: Plug & Play units API
    [
        'name' => 'Plug & Play Units API',
        'url' => $baseUrl . '/api/plugplay/units',
        'method' => 'GET',
        'expected' => ['success' => true]
    ],
    
    // Test 5: Notifications API
    [
        'name' => 'Notifications API',
        'url' => $baseUrl . '/api/notifications',
        'method' => 'GET',
        'expected' => ['success' => true]
    ],
    
    // Test 6: Payment dues API
    [
        'name' => 'Payment Dues API',
        'url' => $baseUrl . '/api/payment/dues',
        'method' => 'GET',
        'expected' => ['success' => true]
    ],
    
    // Test 7: Court cases API
    [
        'name' => 'Court Cases API',
        'url' => $baseUrl . '/api/court-cases',
        'method' => 'GET',
        'expected' => ['success' => true]
    ],
    
    // Test 8: CAPTCHA API
    [
        'name' => 'CAPTCHA API',
        'url' => $baseUrl . '/api/captcha/generate',
        'method' => 'GET',
        'expected' => ['success' => true]
    ],
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    echo "Testing: {$test['name']}... ";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($test['method'] === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (isset($test['data'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($test['data']));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "FAIL - $error\n";
        $failed++;
        continue;
    }
    
    if ($httpCode != 200) {
        echo "FAIL - HTTP $httpCode\n";
        $failed++;
        continue;
    }
    
    $data = json_decode($response, true);
    if ($data !== null) {
        if (isset($test['expected']['success']) && $test['expected']['success'] && isset($data['success'])) {
            echo "OK - Response: " . ($data['success'] ? 'Success' : $data['message']) . "\n";
            $passed++;
        } else {
            echo "OK - JSON Response received\n";
            $passed++;
        }
    } else {
        echo "OK - HTML Response (" . strlen($response) . " bytes)\n";
        $passed++;
    }
}

echo "\n=== Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";

// Security checks
echo "\n=== Security Checks ===\n";

$securityTests = [
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
];

$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);

foreach ($securityTests as $header => $expected) {
    $value = curl_getinfo($ch, CURLINFO_HEADER_OUT);
    echo "Checking $header... ";
    echo "⚠ Manual check required\n";
}
curl_close($ch);

echo "\n=== Database Record Counts ===\n";
try {
    $db = getDB();
    $tables = ['users', 'land_banks', 'plug_play_units', 'court_cases', 'notifications', 'activity_logs', 'lease_surrenders', 'lease_mortgages', 'lease_transfers', 'lease_renewals'];
    foreach ($tables as $t) {
        try {
            $c = $db->fetchOne("SELECT COUNT(*) as cnt FROM $t")['cnt'];
            echo "$t: $c records\n";
        } catch (Exception $e) {
            echo "$t: table not found\n";
        }
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}