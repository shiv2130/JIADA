<?php
/**
 * GET /api/land/application-draft?application_id=#
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
$app = $db->fetchOne("SELECT * FROM land_applications WHERE id = ? AND user_id = ?", [$applicationId, intval($user['id'])]);
if (!$app) {
    jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
}

$wizard = $db->fetchOne("SELECT * FROM la_application_wizard WHERE application_id = ?", [$applicationId]);
if ($wizard) {
    foreach (['profile_json','project_json','finance_json','land_use_json','environment_json','declaration_json'] as $col) {
        if (!empty($wizard[$col])) {
            $wizard[$col] = json_decode($wizard[$col], true);
        } else {
            $wizard[$col] = null;
        }
    }
}

jsonResponse([
    'success' => true,
    'data' => [
        'application' => $app,
        'wizard' => $wizard
    ]
]);

