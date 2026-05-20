<?php
/**
 * Cancellation Notice API
 * POST /api/cancellation/notice
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireUserType(['admin', 'superadmin', 'jiada_officer', 'regional_officer']);
$db = getDB();

try {
    $required = ['lease_no', 'reason_category', 'reason_details'];
    $missing = validateRequired($_POST, $required);
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }

    $leaseNo = sanitize($_POST['lease_no']);
    $lease = $db->fetchOne("SELECT * FROM lease_records WHERE lease_no = ?", [$leaseNo]);
    if (!$lease) {
        throw new Exception('Lease not found');
    }

    $documents = [];
    if (!empty($_FILES['documents'])) {
        foreach ($_FILES['documents']['name'] as $key => $filename) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                $fileResult = uploadFile([
                    'name' => $filename,
                    'type' => $_FILES['documents']['type'][$key],
                    'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                    'error' => $_FILES['documents']['error'][$key],
                    'size' => $_FILES['documents']['size'][$key]
                ], UPLOADS_PATH . '/cancellations');
                if ($fileResult['success']) {
                    $documents[] = [
                        'filename' => $fileResult['filename'],
                        'path' => $fileResult['path'],
                        'type' => $_FILES['documents']['type'][$key]
                    ];
                }
            }
        }
    }

    $noticeNumber = generateReferenceNumber('CAN');
    $noticeId = $db->insert('cancellations', [
        'notice_number' => $noticeNumber,
        'lease_id' => $lease['id'],
        'user_id' => $lease['user_id'],
        'plot_id' => $lease['plot_id'] ?? 0,
        'reason_category' => sanitize($_POST['reason_category']),
        'reason_details' => sanitize($_POST['reason_details']),
        'documents' => json_encode($documents),
        'initiated_by' => $user['id'],
        'account_status' => 'partially_blocked',
        'status' => 'notice_issued',
        'response_deadline' => date('Y-m-d H:i:s', strtotime('+15 days'))
    ]);

    jsonResponse(['success' => true, 'message' => 'Cancellation notice issued', 'data' => ['notice_id' => $noticeId, 'notice_number' => $noticeNumber]]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
