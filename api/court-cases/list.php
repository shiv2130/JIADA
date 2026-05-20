<?php
/**
 * Court Cases List API - List and search court cases
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$auth = JWT::authenticate();
if (!$auth['success']) { jsonResponse(['success' => false, 'message' => $auth['message']], $auth['code'] ?? 401); }
$userType = $auth['user']['user_type'] ?? 'applicant';
if (!in_array($userType, ['admin', 'jiada_officer', 'regional_officer'])) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

$db = getDB();
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$where = "1=1";
$params = [];
if ($status) { $where .= " AND case_status = ?"; $params[] = $status; }
if ($type) { $where .= " AND case_type = ?"; $params[] = $type; }
if ($search) { $where .= " AND (cnr_number LIKE ? OR subject LIKE ? OR petitioner_name LIKE ? OR respondent_name LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]); }

$cases = $db->fetchAll(
    "SELECT * FROM court_cases WHERE $where ORDER BY next_hearing_date ASC, created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$total = $db->fetchOne("SELECT COUNT(*) as total FROM court_cases WHERE $where", $params)['total'];

jsonResponse([
    'success' => true,
    'data' => $cases,
    'pagination' => ['page' => $page, 'limit' => $limit, 'total' => intval($total), 'pages' => ceil($total / $limit)]
]);
