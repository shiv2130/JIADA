<?php
/**
 * Notifications List API - Get user notifications
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$token = getBearerToken();
if (!$token) { jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401); }

$decoded = JWT::decode($token);
if (!$decoded) { jsonResponse(['success' => false, 'message' => 'Invalid token'], 401); }

$userId = $decoded['user_id'];
$db = getDB();

$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';

$where = "user_id = ?";
$params = [$userId];
if ($unreadOnly) { $where .= " AND is_read = 0"; }

$notifications = $db->fetchAll(
    "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$total = $db->fetchOne("SELECT COUNT(*) as total FROM notifications WHERE $where", $params)['total'];
$unreadCount = $db->fetchOne("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0", [$userId])['unread'];

jsonResponse([
    'success' => true,
    'data' => $notifications,
    'unread_count' => intval($unreadCount),
    'pagination' => ['page' => $page, 'limit' => $limit, 'total' => intval($total), 'pages' => ceil($total / $limit)]
]);