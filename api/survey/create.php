<?php
/**
 * Land Survey Create API
 * POST /api/survey/create
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
    $required = ['district', 'block', 'mouza', 'area_surveyed'];
    $missing = validateRequired($_POST, $required);
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }

    $surveyNumber = generateReferenceNumber('SVY');
    $boundary = !empty($_POST['boundary_coordinates']) ? json_encode($_POST['boundary_coordinates']) : null;

    $reportPath = null;
    if (!empty($_FILES['report']) && $_FILES['report']['error'] === UPLOAD_ERR_OK) {
        $fileResult = uploadFile($_FILES['report'], UPLOADS_PATH . '/surveys');
        if ($fileResult['success']) {
            $reportPath = $fileResult['path'];
        }
    }

    $surveyId = $db->insert('land_surveys', [
        'survey_number' => $surveyNumber,
        'revenue_survey_no' => sanitize($_POST['revenue_survey_no'] ?? ''),
        'khatian_no' => sanitize($_POST['khatian_no'] ?? ''),
        'plot_no' => sanitize($_POST['plot_no'] ?? ''),
        'district' => sanitize($_POST['district']),
        'block' => sanitize($_POST['block']),
        'mouza' => sanitize($_POST['mouza']),
        'thana_no' => sanitize($_POST['thana_no'] ?? ''),
        'area_surveyed' => floatval($_POST['area_surveyed']),
        'boundary_coordinates' => $boundary,
        'land_type' => sanitize($_POST['land_type'] ?? ''),
        'possession_status' => sanitize($_POST['possession_status'] ?? ''),
        'surveyor_id' => $user['id'],
        'survey_date' => !empty($_POST['survey_date']) ? $_POST['survey_date'] : date('Y-m-d'),
        'report_path' => $reportPath,
        'status' => 'submitted',
        'remarks' => sanitize($_POST['remarks'] ?? '')
    ]);

    jsonResponse(['success' => true, 'message' => 'Survey created', 'data' => ['survey_id' => $surveyId, 'survey_number' => $surveyNumber]], 201);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
