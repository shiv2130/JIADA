<?php
/**
 * Court Case Update API
 * POST /api/court-case/update
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
    $caseId = intval($_POST['case_id'] ?? 0);
    if (!$caseId) {
        throw new Exception('Case ID is required');
    }

    $update = [
        'case_status' => sanitize($_POST['case_status'] ?? ''),
        'next_hearing_date' => !empty($_POST['next_hearing_date']) ? $_POST['next_hearing_date'] : null,
        'last_hearing_order' => sanitize($_POST['last_hearing_order'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $db->update('court_cases', $update, 'id = ?', [$caseId]);

    jsonResponse(['success' => true, 'message' => 'Case updated']);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
