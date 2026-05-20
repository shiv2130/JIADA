<?php
/**
 * Grievances List API - List grievances with pagination and filtering
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$auth = JWT::authenticate();
if (!$auth['success']) { jsonResponse(['success' => false, 'message' => $auth['message']], $auth['code'] ?? 401); }
$userId = $auth['user']['id'];
$userType = $auth['user']['user_type'] ?? 'applicant';
$db = getDB();

$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$department = $_GET['department'] ?? '';
$search = trim($_GET['search'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$overdue = isset($_GET['overdue']) ? intval($_GET['overdue']) : null;

$where = $userType === 'applicant' ? "g.user_id = ?" : "1=1";
$params = $userType === 'applicant' ? [$userId] : [];

if ($status) { $where .= " AND g.status = ?"; $params[] = $status; }
if ($category) { $where .= " AND g.category = ?"; $params[] = $category; }
if ($department) { $where .= " AND g.department = ?"; $params[] = $department; }
if ($search) { 
    $where .= " AND (g.grievance_number LIKE ? OR g.subject LIKE ? OR g.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($from) { $where .= " AND g.created_at >= ?"; $params[] = $from . " 00:00:00"; }
if ($to) { $where .= " AND g.created_at <= ?"; $params[] = $to . " 23:59:59"; }
if ($overdue !== null) { $where .= " AND (g.sla_deadline IS NOT NULL) AND (g.sla_deadline < NOW()) AND g.status NOT IN ('resolved','closed')"; }

$grievances = $db->fetchAll(
    "SELECT g.*, o.name as officer_name,
            (SELECT COUNT(*) FROM grievance_responses WHERE grievance_id = g.id) as response_count,
            CASE WHEN g.sla_deadline IS NOT NULL AND g.status NOT IN ('resolved','closed') AND g.sla_deadline < NOW() THEN 1 ELSE 0 END as sla_overdue
     FROM grievances g
     LEFT JOIN users o ON g.secondment_officer_id = o.id
     WHERE $where 
     ORDER BY g.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$total = $db->fetchOne("SELECT COUNT(*) as total FROM grievances g WHERE $where", $params)['total'];

jsonResponse([
    'success' => true,
    'data' => $grievances,
    'pagination' => ['page' => $page, 'limit' => $limit, 'total' => intval($total), 'pages' => ceil($total / $limit)]
]);
