<?php
/**
 * Mark Notification Read
 * POST /api/notifications/{id}/read
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$db = getDB();
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Notification ID required'], 400);
}

$db->update('notifications', [
    'is_read' => 1,
    'read_at' => date('Y-m-d H:i:s'),
    'status' => 'read'
], 'id = ? AND user_id = ?', [$id, $user['id']]);

jsonResponse(['success' => true, 'message' => 'Notification marked as read']);
