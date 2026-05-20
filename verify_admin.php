<?php
define('JIADA_APP', true);
require_once 'config/config.php';
require_once 'core/helpers.php';

$db = getDB();
$user = $db->fetchOne('SELECT * FROM users WHERE email = ?', ['admin@jiada.in']);

if ($user) {
    echo "Found: " . $user['name'] . " - " . $user['user_type'] . " - " . $user['status'] . "\n";
    $p = $db->fetchOne('SELECT password FROM users WHERE id = ?', [$user['id']]);
    echo "Password: " . (password_verify('admin123', $p['password']) ? 'OK' : 'FAIL') . "\n";
} else {
    echo "Not found\n";
}