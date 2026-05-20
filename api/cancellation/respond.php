<?php
/**
 * Cancellation Response API
 * POST /api/cancellation/respond
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
    $required = ['notice_number', 'response'];
    $missing = validateRequired($_POST, $required);
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }

    $noticeNumber = sanitize($_POST['notice_number']);
    $notice = $db->fetchOne("SELECT * FROM cancellations WHERE notice_number = ? AND user_id = ?", [$noticeNumber, $user['id']]);
    if (!$notice) {
        throw new Exception('Notice not found');
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

    $db->update('cancellations', [
        'user_response' => sanitize($_POST['response']),
        'clarification_count' => intval($notice['clarification_count'] ?? 0) + 1,
        'status' => 'response_pending',
        'documents' => json_encode(array_merge(json_decode($notice['documents'] ?? '[]', true), $documents))
    ], 'id = ?', [$notice['id']]);

    jsonResponse(['success' => true, 'message' => 'Response submitted']);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
