<?php
/**
 * GET /api/land/track?application_id=#
 */
if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}
if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$applicationId = intval($_GET['application_id'] ?? 0);
if (!$applicationId) {
    jsonResponse(['success' => false, 'message' => 'application_id is required'], 400);
}

$db = getDB();
$app = $db->fetchOne("SELECT * FROM land_applications WHERE id = ? AND user_id = ?", [$applicationId, $user['id']]);
if (!$app) {
    jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
}

$workflow = [];
$objections = [];
$tableWorkflow = $db->fetchOne("SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_workflow_actions' LIMIT 1");
$tableObj = $db->fetchOne("SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_objections' LIMIT 1");

if ($tableWorkflow) {
    $workflow = $db->fetchAll("SELECT id, status_code, action_type, action_note, action_at FROM la_workflow_actions WHERE application_id = ? ORDER BY id ASC", [$applicationId]);
}
if ($tableObj) {
    $objections = $db->fetchAll("SELECT id, objection_text, status, due_date, created_at FROM la_objections WHERE application_id = ? ORDER BY id DESC", [$applicationId]);
}

jsonResponse([
    'success' => true,
    'data' => [
        'application' => $app,
        'sop_status' => WorkflowService::mapLegacyStatus($app['status'] ?? ''),
        'workflow' => $workflow,
        'objections' => $objections
    ]
]);

