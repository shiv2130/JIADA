<?php
/**
 * Plug & Play Units API - List and apply for flatted factory units
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$token = getBearerToken();
$decoded = $token ? JWT::decode($token) : null;
$userId = ($decoded && $decoded['success']) ? ($decoded['data']['sub'] ?? null) : null;
$userType = ($decoded && $decoded['success']) ? ($decoded['data']['userType'] ?? 'applicant') : 'guest';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? 'available';
    $type = $_GET['type'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $where = "1=1";
    $params = [];
    if ($status) { $where .= " AND status = ?"; $params[] = $status; }
    if ($type) { $where .= " AND unit_type = ?"; $params[] = $type; }
    
    $units = $db->fetchAll(
        "SELECT * FROM plug_play_units WHERE $where ORDER BY unit_no ASC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as total FROM plug_play_units WHERE $where", $params)['total'];
    
    jsonResponse([
        'success' => true,
        'data' => $units,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => intval($total), 'pages' => ceil($total / $limit)]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$userId) { jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401); }
    $unitId = intval($_POST['unit_id'] ?? 0);
    $companyName = sanitize($_POST['company_name'] ?? '');
    $industryType = sanitize($_POST['industry_type'] ?? '');
    $investmentAmount = floatval($_POST['investment_amount'] ?? 0);
    
    if (!$unitId || !$companyName) {
        jsonResponse(['success' => false, 'message' => 'Unit ID and company name are required']);
    }
    if ($unitId < 1) {
        jsonResponse(['success' => false, 'message' => 'Invalid unit'], 400);
    }
    if (strlen($companyName) < 3 || strlen($companyName) > 150) {
        jsonResponse(['success' => false, 'message' => 'Company name must be between 3 and 150 characters'], 400);
    }
    if (!empty($industryType) && strlen($industryType) > 100) {
        jsonResponse(['success' => false, 'message' => 'Industry type is too long'], 400);
    }
    if ($investmentAmount < 0) {
        jsonResponse(['success' => false, 'message' => 'Investment amount must be zero or positive'], 400);
    }
    
    $unit = $db->fetchOne("SELECT * FROM plug_play_units WHERE id = ? AND status = 'available'", [$unitId]);
    if (!$unit) {
        jsonResponse(['success' => false, 'message' => 'Unit not available']);
    }
    
    $applicationNo = 'JIADA/PP/' . date('Y') . '/' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    $appId = $db->insert('plug_play_applications', [
        'application_no' => $applicationNo,
        'user_id' => $userId,
        'unit_id' => $unitId,
        'company_name' => $companyName,
        'industry_type' => $industryType,
        'investment_amount' => $investmentAmount,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    jsonResponse(['success' => true, 'message' => 'Application submitted', 'data' => ['application_id' => $appId, 'application_no' => $applicationNo]], 201);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
