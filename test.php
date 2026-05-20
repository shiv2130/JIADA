<?php
// JIADA Application Test Script - Direct access, no routing needed
echo "=== JIADA Portal Test ===\n\n";

// Test 1: DB Connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=jiada_portal", "root", "");
    echo "✅ Database: Connected\n";
    
    // Test 2: Tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Tables: " . count($tables) . " tables found\n";
    
    // Test 3: SuperAdmin user
    $user = $pdo->query("SELECT id, user_type, name, email, status FROM users WHERE user_type='superadmin' LIMIT 1")->fetch();
    echo "✅ SuperAdmin: " . ($user ? $user['name'] . ' (' . $user['email'] . ')' : 'NOT FOUND') . "\n";
    
    // Test 4: Regions
    $regions = $pdo->query("SELECT COUNT(*) FROM regions")->fetchColumn();
    echo "✅ Regions: $regions configured\n";
    
    // Test 5: Role permissions
    $perms = $pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
    echo "✅ Permissions: $perms entries\n";
    
} catch (Exception $e) {
    echo "❌ Database: " . $e->getMessage() . "\n";
}

// App config check
$configFile = __DIR__ . '/config/config.php';
echo "\n✅ Config: " . (file_exists($configFile) ? 'Found' : 'MISSING') . "\n";

echo "\nAccess URLs:\n";
echo "  Admin: http://localhost:81/JIADA/admin/?page=login\n";
echo "  Home:  http://localhost:81/JIADA/\n";
echo "\nDefault login: admin@jiada.in / password\n";
?>