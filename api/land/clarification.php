<?php
/**
 * POST /api/land/clarification
 */
if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}
if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$input = getPost();
$objectionId = intval($input['objection_id'] ?? 0);
$text = trim((string)($input['clarification_text'] ?? ''));

if (!$objectionId || $text === '') {
    jsonResponse(['success' => false, 'message' => 'objection_id and clarification_text are required'], 400);
}

$db = getDB();
$obj = $db->fetchOne(
    "SELECT o.*, a.user_id, a.id AS application_id
     FROM la_objections o
     INNER JOIN land_applications a ON a.id = o.application_id
     WHERE o.id = ?",
    [$objectionId]
);
if (!$obj || intval($obj['user_id']) !== intval($user['id'])) {
    jsonResponse(['success' => false, 'message' => 'Objection not found'], 404);
}

$tableClarification = $db->fetchOne("SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_clarifications' LIMIT 1");
if ($tableClarification) {
    $db->insert('la_clarifications', [
        'objection_id' => $objectionId,
        'application_id' => $obj['application_id'],
        'submitted_by' => intval($user['id']),
        'clarification_text' => sanitize($text),
        'submitted_at' => date('Y-m-d H:i:s')
    ]);
}

$db->update('la_objections', ['status' => 'clarified'], 'id = ?', [$objectionId]);
WorkflowService::logAction(intval($obj['application_id']), 'CLARIFICATION_SUBMITTED', 'clarification', intval($user['id']), $text);

jsonResponse(['success' => true, 'message' => 'Clarification submitted']);

