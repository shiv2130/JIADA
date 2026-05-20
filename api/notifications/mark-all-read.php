<?php
/**
 * Mark All Notifications Read
 * POST /api/notifications/mark-all-read
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$db = getDB();

$db->update('notifications', [
    'is_read' => 1,
    'read_at' => date('Y-m-d H:i:s'),
    'status' => 'read'
], 'user_id = ?', [$user['id']]);

jsonResponse(['success' => true, 'message' => 'All notifications marked as read']);
