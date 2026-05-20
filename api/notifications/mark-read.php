<?php
/**
 * Notifications Mark Read API - Mark notifications as read
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405); }

$token = getBearerToken();
if (!$token) { jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401); }

$decoded = JWT::decode($token);
if (!$decoded) { jsonResponse(['success' => false, 'message' => 'Invalid token'], 401); }

$userId = $decoded['user_id'];
$db = getDB();

$notificationId = intval($_POST['notification_id'] ?? 0);
$markAll = isset($_POST['mark_all']) && $_POST['mark_all'] === '1';

if ($markAll) {
    $db->update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 'user_id = ? AND is_read = 0', [$userId]);
    jsonResponse(['success' => true, 'message' => 'All notifications marked as read']);
}

if (!$notificationId) {
    jsonResponse(['success' => false, 'message' => 'Notification ID required']);
}

$notification = $db->fetchOne("SELECT * FROM notifications WHERE id = ? AND user_id = ?", [$notificationId, $userId]);
if (!$notification) {
    jsonResponse(['success' => false, 'message' => 'Notification not found']);
}

$db->update('notifications', ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 'id = ?', [$notificationId]);
jsonResponse(['success' => true, 'message' => 'Notification marked as read']);