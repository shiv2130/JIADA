<?php
/**
 * POST /api/land/application-save
 * Save wizard section data for draft application.
 */
if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}
if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();
$input = getPost();
$applicationId = intval($input['application_id'] ?? 0);
$section = strtolower(trim((string)($input['section'] ?? '')));
$payload = $input['payload'] ?? null;

if (!$applicationId || !$section || $payload === null) {
    jsonResponse(['success' => false, 'message' => 'application_id, section, payload are required'], 400);
}

$allowedSections = ['profile_json', 'project_json', 'finance_json', 'land_use_json', 'environment_json', 'declaration_json'];
$sectionCol = $section . '_json';
if (!in_array($sectionCol, $allowedSections, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid section'], 400);
}

$db = getDB();
$app = $db->fetchOne("SELECT * FROM land_applications WHERE id = ? AND user_id = ?", [$applicationId, intval($user['id'])]);
if (!$app) {
    jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
}
if (($app['status'] ?? '') !== 'draft') {
    jsonResponse(['success' => false, 'message' => 'Application is locked for editing'], 400);
}

$wizard = $db->fetchOne("SELECT * FROM la_application_wizard WHERE application_id = ?", [$applicationId]);
$jsonPayload = is_string($payload) ? $payload : json_encode($payload);
if (!$jsonPayload) $jsonPayload = '{}';

if ($wizard) {
    $db->update('la_application_wizard', [$sectionCol => $jsonPayload], 'application_id = ?', [$applicationId]);
} else {
    $base = [
        'application_id' => $applicationId,
        'user_id' => intval($user['id']),
        'profile_json' => null,
        'project_json' => null,
        'finance_json' => null,
        'land_use_json' => null,
        'environment_json' => null,
        'declaration_json' => null,
        'final_submission_flag' => 0
    ];
    $base[$sectionCol] = $jsonPayload;
    $db->insert('la_application_wizard', $base);
}

jsonResponse(['success' => true, 'message' => 'Draft section saved']);

