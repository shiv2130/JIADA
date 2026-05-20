<?php
/**
 * Change of Constitution API
 * POST /api/constitution/change
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$db = getDB();

try {
    $required = ['lease_no', 'change_type', 'old_constitution', 'new_constitution'];
    $missing = validateRequired($_POST, $required);
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }

    $leaseNo = sanitize($_POST['lease_no']);
    $lease = $db->fetchOne("SELECT * FROM lease_records WHERE lease_no = ? AND user_id = ?", [$leaseNo, $user['id']]);
    if (!$lease) {
        throw new Exception('Lease not found or inactive');
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
                ], UPLOADS_PATH . '/constitution');
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

    $applicationNumber = generateReferenceNumber('CCU');
    $appId = $db->insert('constitution_changes', [
        'application_number' => $applicationNumber,
        'user_id' => $user['id'],
        'lease_id' => $lease['id'],
        'change_type' => sanitize($_POST['change_type']),
        'old_constitution' => sanitize($_POST['old_constitution']),
        'new_constitution' => sanitize($_POST['new_constitution']),
        'documents' => json_encode($documents),
        'status' => 'submitted'
    ]);

    logActivity("Constitution change submitted: {$applicationNumber}", $user['id']);

    jsonResponse(['success' => true, 'message' => 'Application submitted', 'data' => ['application_id' => $appId, 'application_no' => $applicationNumber]], 201);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
