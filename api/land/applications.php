<?php
/**
 * Land Applications API - List and manage land allotment applications
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$auth = JWT::authenticate();
if (!$auth['success']) { jsonResponse(['success' => false, 'message' => $auth['message']], $auth['code'] ?? 401); }
$userId = $auth['user']['id'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $where = "user_id = ?";
    $params = [$userId];
    
    if ($status) {
        $where .= " AND status = ?";
        $params[] = $status;
    }
    
    $applications = $db->fetchAll(
        "SELECT a.*, lb.plot_number, lb.plot_type, lb.area_sqm, lb.zone
         FROM land_applications a 
         LEFT JOIN land_banks lb ON a.plot_id = lb.id
         WHERE $where 
         ORDER BY a.created_at DESC 
         LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as total FROM land_applications WHERE $where", $params)['total'];
    
    jsonResponse([
        'success' => true,
        'data' => $applications,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => intval($total),
            'pages' => ceil($total / $limit)
        ]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jsonResponse(['success' => false, 'message' => 'Use /api/land/apply for new applications'], 400);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
