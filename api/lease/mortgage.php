<?php
/**
 * Lease Mortgage API - Apply for mortgage of lease hold rights
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
    
    $mortgages = $db->fetchAll(
        "SELECT m.*, l.plot_no, l.lease_no FROM lease_mortgages m 
         LEFT JOIN lease_records l ON m.lease_id = l.id 
         WHERE $where ORDER BY m.created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    
    $total = $db->fetchOne("SELECT COUNT(*) as total FROM lease_mortgages WHERE $where", $params)['total'];
    
    jsonResponse([
        'success' => true,
        'data' => $mortgages,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => intval($total), 'pages' => ceil($total / $limit)]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $columns = array_map(function($c){ return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM lease_mortgages"));
    $staffRoles = ['admin','jiada_officer','regional_officer'];

    // Staff actions: objection / approve / reject
    if ($action !== 'create') {
        if (!in_array($auth['user']['user_type'] ?? '', $staffRoles)) {
            jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }
        $mid = intval($_POST['mortgage_id'] ?? 0);
        if (!$mid) { jsonResponse(['success' => false, 'message' => 'Mortgage ID required']); }
        $mort = $db->fetchOne("SELECT * FROM lease_mortgages WHERE id = ?", [$mid]);
        if (!$mort) { jsonResponse(['success' => false, 'message' => 'Mortgage not found']); }
        $remark = sanitize($_POST['remark'] ?? '');
        $update = ['updated_at' => date('Y-m-d H:i:s')];

        switch ($action) {
            case 'objection':
                $count = $mort['objection_count'] ?? 0;
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
                if (in_array('approved_by', $columns)) { $update['approved_by'] = $auth['user']['id']; }
                break;
            case 'reject':
                $update['status'] = 'rejected';
                if (in_array('rejection_reason', $columns)) { $update['rejection_reason'] = $remark; }
                break;
            default:
                jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
        $db->update('lease_mortgages', $update, 'id = ?', [$mid]);

        Notification::createInApp($mort['user_id'],
            'Mortgage Status Updated',
            'Your mortgage application ' . ($mort['application_no'] ?? '') . ' is now ' . $update['status'],
            Notification::PRIORITY_MEDIUM,
            ['mortgage_id' => $mid, 'status' => $update['status']]
        );
        if ($action === 'approve') {
            Notification::send($mort['user_id'], Notification::TYPE_EMAIL,
                'Mortgage Certificate Issued',
                'Your mortgage application has been approved. Certificate will be shared to your registered email.',
                Notification::PRIORITY_MEDIUM,
                ['mortgage_id' => $mid, 'application_no' => $mort['application_no'] ?? '']
            );
        }
        jsonResponse(['success' => true, 'message' => 'Status updated', 'status' => $update['status']]);
    }

    // Applicant create flow
    $leaseId = intval($_POST['lease_id'] ?? 0);
    $leaseNo = sanitize($_POST['lease_no'] ?? '');
    $bankName = sanitize($_POST['bank_name'] ?? '');
    $bankBranch = sanitize($_POST['bank_branch'] ?? '');
    $loanAmount = floatval($_POST['loan_amount'] ?? 0);
    $loanTenure = intval($_POST['loan_tenure'] ?? 0);
    $purpose = sanitize($_POST['purpose'] ?? '');
    $consentLetter = sanitize($_POST['consent_letter'] ?? '');

    if ($loanAmount <= 0) {
        jsonResponse(['success' => false, 'message' => 'Loan amount must be greater than zero']);
    }
    if (strlen($bankName) < 2 || strlen($bankName) > 150) {
        jsonResponse(['success' => false, 'message' => 'Bank name must be 2-150 characters']);
    }
    if (!empty($bankBranch) && strlen($bankBranch) > 150) {
        jsonResponse(['success' => false, 'message' => 'Bank branch is too long']);
    }
    if ($loanTenure < 0 || $loanTenure > 600) {
        jsonResponse(['success' => false, 'message' => 'Loan tenure must be between 0 and 600 months']);
    }
    if (!empty($purpose) && strlen($purpose) < 5) {
        jsonResponse(['success' => false, 'message' => 'Purpose must be at least 5 characters']);
    }
    if (!empty($_FILES['consent_letter']) && $_FILES['consent_letter']['error'] === UPLOAD_ERR_OK) {
        $validation = validateFileUpload($_FILES['consent_letter'], ['pdf', 'jpg', 'jpeg', 'png'], 5 * 1024 * 1024);
        if (!$validation['success']) {
            jsonResponse(['success' => false, 'message' => 'Consent letter invalid: ' . ($validation['message'] ?? '')]);
        }
        $fileResult = uploadFile($_FILES['consent_letter'], UPLOADS_PATH . '/mortgage');
        if ($fileResult['success']) {
            $consentLetter = $fileResult['path'];
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to upload consent letter']);
        }
    }
    
    if ((!$leaseId && !$leaseNo) || !$bankName || !$loanAmount) {
        jsonResponse(['success' => false, 'message' => 'Lease ID/No, bank name, and loan amount are required']);
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
    
    // Check for existing pending mortgage
    $existing = $db->fetchOne("SELECT id FROM lease_mortgages WHERE lease_id = ? AND status IN ('pending', 'approved')", [$leaseId]);
    if ($existing) {
        jsonResponse(['success' => false, 'message' => 'Lease already has a pending or active mortgage']);
    }
    
    $applicationNo = 'JIADA/MORT/' . date('Y') . '/' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    $mortgageId = $db->insert('lease_mortgages', [
        'application_no' => $applicationNo,
        'user_id' => $userId,
        'lease_id' => $leaseId,
        'bank_name' => $bankName,
        'bank_branch' => $bankBranch,
        'loan_amount' => $loanAmount,
        'loan_tenure' => $loanTenure,
        'purpose' => $purpose,
        'consent_letter' => $consentLetter,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    jsonResponse(['success' => true, 'message' => 'Mortgage application submitted', 'data' => ['mortgage_id' => $mortgageId, 'application_no' => $applicationNo]], 201);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
