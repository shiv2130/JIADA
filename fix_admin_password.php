<?php
define('JIADA_APP', true);
require_once 'config/config.php';
require_once 'core/helpers.php';

$db = getDB();
$pdo = $db->getConnection();
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->execute([$hash, 'admin@jiada.in']);

// Verify
$user = $db->fetchOne('SELECT password FROM users WHERE email = ?', ['admin@jiada.in']);
echo "Password updated: " . (password_verify('admin123', $user['password']) ? 'OK' : 'FAIL') . "\n";