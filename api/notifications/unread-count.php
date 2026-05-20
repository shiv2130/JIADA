<?php
/**
 * Notifications Unread Count
 * GET /api/notifications/unread-count
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$db = getDB();
$count = $db->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0", [$user['id']]);

jsonResponse(['success' => true, 'data' => ['count' => intval($count['c'] ?? 0)]]);
