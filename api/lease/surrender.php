<?php
/**
 * Lease Surrender API - Surrender of allotted plots
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
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? '';
    
    $where = "user_id = ?";
    $params = [$userId];
    if ($status) { $where .= " AND status = ?"; $params[] = $status; }
    
    $surrenders = $db->fetchAll(
        "SELECT s.*, l.plot_no, l.lease_no, l.allotment_date 
         FROM lease_surrenders s 
         LEFT JOIN lease_records l ON s.lease_id = l.id 
         WHERE $where ORDER BY s.created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as total FROM lease_surrenders WHERE $where", $params)['total'];
    
    jsonResponse([
        'success' => true,
        'data' => $surrenders,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => intval($total), 'pages' => ceil($total / $limit)]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $columns = array_map(function($c){ return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM lease_surrenders"));

    // Admin/staff actions: objection, approve, reject
    if ($action !== 'create') {
        $surrenderId = intval($_POST['surrender_id'] ?? 0);
        if (!$surrenderId) { jsonResponse(['success' => false, 'message' => 'Surrender ID required']); }
        $surrender = $db->fetchOne("SELECT * FROM lease_surrenders WHERE id = ?", [$surrenderId]);
        if (!$surrender) { jsonResponse(['success' => false, 'message' => 'Surrender not found']); }

        $staffRoles = ['admin', 'jiada_officer', 'regional_officer'];
        if (!in_array($auth['user']['user_type'] ?? '', $staffRoles)) {
            jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }
        $remark = sanitize($_POST['remark'] ?? '');
        $updates = ['updated_at' => date('Y-m-d H:i:s')];

        switch ($action) {
            case 'objection':
                $count = $surrender['objection_count'] ?? 0;
                if (in_array('objection_count', $columns) && $count >= 2) {
                    jsonResponse(['success' => false, 'message' => 'Maximum two objections allowed']);
                }
                $updates['status'] = 'objection';
                if (in_array('objection_count', $columns)) { $updates['objection_count'] = $count + 1; }
                if (in_array('last_objection', $columns)) { $updates['last_objection'] = $remark; }
                break;
            case 'approve':
                $updates['status'] = 'approved';
                if (in_array('approved_at', $columns)) { $updates['approved_at'] = date('Y-m-d H:i:s'); }
                if (in_array('approved_by', $columns)) { $updates['approved_by'] = $auth['user']['id']; }
                break;
            case 'reject':
                $updates['status'] = 'rejected';
                if (in_array('rejection_reason', $columns)) { $updates['rejection_reason'] = $remark; }
                break;
            default:
                jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }

        $db->update('lease_surrenders', $updates, 'id = ?', [$surrenderId]);

        // Notify applicant
        Notification::createInApp($surrender['user_id'],
            'Surrender Status Updated',
            'Your surrender application ' . ($surrender['application_no'] ?? '') . ' is now ' . $updates['status'],
            Notification::PRIORITY_MEDIUM,
            ['surrender_id' => $surrenderId, 'status' => $updates['status']]
        );
        jsonResponse(['success' => true, 'message' => 'Status updated', 'status' => $updates['status']]);
    }

    // Applicant create flow
    $leaseId = intval($_POST['lease_id'] ?? 0);
    $leaseNo = sanitize($_POST['lease_no'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    $noticeDate = $_POST['notice_date'] ?? date('Y-m-d');

    if (strlen($reason) < 10) {
        jsonResponse(['success' => false, 'message' => 'Reason must be at least 10 characters']);
    }
    if ($noticeDate && !strtotime($noticeDate)) {
        jsonResponse(['success' => false, 'message' => 'Invalid notice date']);
    }

    if ((!$leaseId && !$leaseNo) || !$reason) {
        jsonResponse(['success' => false, 'message' => 'Lease ID/No and reason are required']);
    }
    
    // Verify lease ownership
    if (!$leaseId && $leaseNo) {
        $lease = $db->fetchOne("SELECT * FROM lease_records WHERE lease_no = ? AND user_id = ? AND status = 'active'", [$leaseNo, $userId]);
        $leaseId = $lease['id'] ?? 0;
    } else {
        $lease = $db->fetchOne("SELECT * FROM lease_records WHERE id = ? AND user_id = ? AND status = 'active'", [$leaseId, $userId]);
    }
    if (!$lease) {
        jsonResponse(['success' => false, 'message' => 'Invalid or inactive lease agreement']);
    }
    
    // Check minimum 3 months notice
    $allotmentDate = new DateTime($lease['allotment_date']);
    $noticeDateObj = new DateTime($noticeDate);
    $interval = $allotmentDate->diff($noticeDateObj);
    $monthsDiff = ($interval->y * 12) + $interval->m;
    
    if ($monthsDiff < 3) {
        jsonResponse(['success' => false, 'message' => 'Minimum 3 months advance notice required']);
    }
    
    // Process attachments (optional)
    $attachments = [];
    if (!empty($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
        if (count($_FILES['documents']['name']) > 3) {
            jsonResponse(['success' => false, 'message' => 'You can upload up to 3 supporting documents']);
        }
        foreach ($_FILES['documents']['name'] as $key => $filename) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $fileData = [
                'name' => $filename,
                'type' => $_FILES['documents']['type'][$key],
                'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                'error' => $_FILES['documents']['error'][$key],
                'size' => $_FILES['documents']['size'][$key]
            ];
            $validation = validateFileUpload($fileData, ['pdf','jpg','jpeg','png'], 5 * 1024 * 1024);
            if (!$validation['success']) {
                jsonResponse(['success' => false, 'message' => 'Document upload error: ' . ($validation['message'] ?? '')]);
            }
            $fileResult = uploadFile($fileData, UPLOADS_PATH . '/lease_surrenders');
            if (!$fileResult['success']) {
                jsonResponse(['success' => false, 'message' => 'Failed to upload document']);
            }
            $attachments[] = $fileResult['filename'];
        }
    }

    // Check outstanding dues
    $dues = $db->fetchOne("SELECT SUM(amount - paid_amount) as outstanding FROM payments WHERE lease_id = ? AND status != 'paid'", [$leaseId]);
    $hasDues = $dues['outstanding'] > 0;
    
    // Calculate refund percentage based on tenure
    $yearsDiff = $interval->y;
    $refundPercent = $yearsDiff <= 1 ? 75 : ($yearsDiff <= 2 ? 65 : ($yearsDiff <= 3 ? 55 : 0));
    $refundAmount = ($lease['premium_amount'] * $refundPercent) / 100;
    
    $applicationNo = 'JIADA/SURR/' . date('Y') . '/' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    $insertData = [
        'application_no' => $applicationNo,
        'user_id' => $userId,
        'lease_id' => $leaseId,
        'reason' => $reason,
        'notice_date' => $noticeDate,
        'has_outstanding_dues' => $hasDues ? 1 : 0,
        'outstanding_amount' => $dues['outstanding'] ?? 0,
        'tenure_years' => $yearsDiff,
        'refund_percent' => $refundPercent,
        'refund_amount' => $refundAmount,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    if (!empty($attachments)) {
        if (in_array('attachments', $columns)) {
            $insertData['attachments'] = json_encode($attachments);
        } elseif (in_array('documents', $columns)) {
            $insertData['documents'] = json_encode($attachments);
        }
    }
    $surrenderId = $db->insert('lease_surrenders', $insertData);
    
    jsonResponse([
        'success' => true,
        'message' => 'Surrender application submitted',
        'data' => [
            'surrender_id' => $surrenderId,
            'application_no' => $applicationNo,
            'refund_percent' => $refundPercent,
            'refund_amount' => $refundAmount,
            'has_dues' => $hasDues
        ]
    ], 201);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
