<?php
/**
 * Lease Transfer API - Transfer of lease hold rights
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? '';
    
    $where = $userType === 'applicant' ? "user_id = ?" : "1=1";
    $params = $userType === 'applicant' ? [$userId] : [];
    if ($status) { $where .= " AND status = ?"; $params[] = $status; }
    
    $transfers = $db->fetchAll(
        "SELECT t.*, l.plot_no, l.lease_no, u.name as from_user, u2.name as to_user_name 
         FROM lease_transfers t 
         LEFT JOIN lease_records l ON t.lease_id = l.id 
         LEFT JOIN users u ON t.from_user_id = u.id 
         LEFT JOIN users u2 ON t.to_user_id = u2.id 
         WHERE $where ORDER BY t.created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as total FROM lease_transfers WHERE $where", $params)['total'];
    
    jsonResponse([
        'success' => true,
        'data' => $transfers,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => intval($total), 'pages' => ceil($total / $limit)]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $columns = array_map(function($c){ return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM lease_transfers"));
    $staffRoles = ['admin','jiada_officer','regional_officer'];

    // Staff actions branch
    if ($action !== 'create') {
        if (!in_array($userType, $staffRoles)) {
            jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }
        $tid = intval($_POST['transfer_id'] ?? 0);
        if (!$tid) { jsonResponse(['success' => false, 'message' => 'Transfer ID required']); }
        $transfer = $db->fetchOne("SELECT * FROM lease_transfers WHERE id = ?", [$tid]);
        if (!$transfer) { jsonResponse(['success' => false, 'message' => 'Transfer not found']); }
        $remark = sanitize($_POST['remark'] ?? '');
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        switch ($action) {
            case 'objection':
                $count = $transfer['objection_count'] ?? 0;
                if (in_array('objection_count', $columns) && $count >= 2) {
                    jsonResponse(['success' => false, 'message' => 'Maximum two objections allowed']);
                }
                $update['status'] = 'objection';
                if (in_array('objection_count', $columns)) { $update['objection_count'] = $count + 1; }
                if (in_array('last_objection', $columns)) { $update['last_objection'] = $remark; }
                break;
            case 'approve':
                $update['status'] = 'approved';
                if (in_array('approved_at', $columns)) { $update['approved_at'] = date('Y-m-d H:i:s'); }
                if (in_array('approved_by', $columns)) { $update['approved_by'] = $userId; }
                break;
            case 'reject':
                $update['status'] = 'rejected';
                if (in_array('rejection_reason', $columns)) { $update['rejection_reason'] = $remark; }
                break;
            default:
                jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
        $db->update('lease_transfers', $update, 'id = ?', [$tid]);
        Notification::createInApp($transfer['from_user_id'],
            'Lease Transfer Status Updated',
            'Your transfer application ' . ($transfer['application_no'] ?? '') . ' is now ' . $update['status'],
            Notification::PRIORITY_MEDIUM,
            ['transfer_id' => $tid, 'status' => $update['status']]
        );
        if ($action === 'approve') {
            Notification::send($transfer['from_user_id'], Notification::TYPE_EMAIL,
                'Transfer Certificate Issued',
                'Your lease transfer application has been approved. Certificate will be shared to your registered email.',
                Notification::PRIORITY_MEDIUM,
                ['transfer_id' => $tid, 'application_no' => $transfer['application_no'] ?? '']
            );
        }
        jsonResponse(['success' => true, 'message' => 'Status updated', 'status' => $update['status']]);
    }

    // Applicant submission
    $leaseId = intval($_POST['lease_id'] ?? 0);
    $leaseNo = sanitize($_POST['lease_no'] ?? '');
    $toUserId = intval($_POST['to_user_id'] ?? 0);
    $toUserName = sanitize($_POST['to_user_name'] ?? '');
    $toUserMobile = sanitize($_POST['to_user_mobile'] ?? '');
    $toUserEmail = sanitize($_POST['to_user_email'] ?? '');
    $transferReason = sanitize($_POST['transfer_reason'] ?? '');
    $considerationAmount = floatval($_POST['consideration_amount'] ?? 0);
    $isMsme = intval($_POST['is_msme'] ?? 0);

    if ((!$leaseId && !$leaseNo) || !$toUserName || !$transferReason) {
        jsonResponse(['success' => false, 'message' => 'Lease ID/No, transferee name, and reason are required']);
    }
    if (strlen($toUserName) < 3 || strlen($toUserName) > 150) {
        jsonResponse(['success' => false, 'message' => 'Transferee name must be 3-150 characters']);
    }
    if (!empty($toUserMobile) && !preg_match('/^[0-9]{8,15}$/', $toUserMobile)) {
        jsonResponse(['success' => false, 'message' => 'Invalid mobile number']);
    }
    if (!empty($toUserEmail) && !filter_var($toUserEmail, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email']);
    }
    if (strlen($transferReason) < 10) {
        jsonResponse(['success' => false, 'message' => 'Reason must be at least 10 characters']);
    }
    if ($considerationAmount <= 0) {
        jsonResponse(['success' => false, 'message' => 'Consideration amount must be greater than zero']);
    }
    
    if ((!$leaseId && !$leaseNo) || !$toUserName || !$transferReason) {
        jsonResponse(['success' => false, 'message' => 'Lease ID/No, transferee name, and reason are required']);
    }
    
    // Attachments (optional)
    $attachments = [];
    if (!empty($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
        if (count($_FILES['documents']['name']) > 5) {
            jsonResponse(['success' => false, 'message' => 'You can upload up to 5 documents']);
        }
        foreach ($_FILES['documents']['name'] as $key => $filename) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_NO_FILE) continue;
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
            $fileResult = uploadFile($fileData, UPLOADS_PATH . '/lease_transfers');
            if (!$fileResult['success']) {
                jsonResponse(['success' => false, 'message' => 'Failed to upload document']);
            }
            $attachments[] = $fileResult['filename'];
        }
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
    
    // Check for outstanding dues
    $dues = $db->fetchOne("SELECT SUM(amount - paid_amount) as outstanding FROM payments WHERE lease_id = ? AND status != 'paid'", [$leaseId]);
    if ($dues['outstanding'] > 0) {
        jsonResponse(['success' => false, 'message' => 'Outstanding dues of ₹' . number_format($dues['outstanding'], 2) . ' must be cleared first']);
    }
    
    // Calculate premium (15% for MSME, 25% for others) based on lease premium when available
    $basePremium = floatval($lease['premium_amount'] ?? 0);
    if ($basePremium <= 0) { $basePremium = $considerationAmount; }
    $premiumPercent = $isMsme ? 15 : 25;
    $landPremium = ($basePremium * $premiumPercent) / 100;
    
    $applicationNo = 'JIADA/TRANS/' . date('Y') . '/' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    $insert = [
        'application_no' => $applicationNo,
        'lease_id' => $leaseId,
        'from_user_id' => $userId,
        'to_user_id' => $toUserId,
        'to_user_name' => $toUserName,
        'to_user_mobile' => $toUserMobile,
        'to_user_email' => $toUserEmail,
        'transfer_reason' => $transferReason,
        'consideration_amount' => $considerationAmount,
        'is_msme' => $isMsme,
        'premium_percent' => $premiumPercent,
        'land_premium' => $landPremium,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    if (!empty($attachments)) {
        if (in_array('attachments', $columns)) { $insert['attachments'] = json_encode($attachments); }
        elseif (in_array('documents', $columns)) { $insert['documents'] = json_encode($attachments); }
    }
    $transferId = $db->insert('lease_transfers', $insert);
    
    jsonResponse(['success' => true, 'message' => 'Transfer application submitted', 'data' => ['transfer_id' => $transferId, 'application_no' => $applicationNo, 'premium' => $landPremium]], 201);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
