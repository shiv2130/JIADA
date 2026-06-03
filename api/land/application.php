<?php
/**
 * JIADA Portal - Land Application View
 * GET /api/land/application/{id}
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$applicationId = intval($_GET['id'] ?? 0);
if (!$applicationId) {
    jsonResponse(['success' => false, 'message' => 'Application ID is required'], 400);
}

$db = getDB();
$application = $db->fetchOne(
    "SELECT a.*, lb.plot_number, lb.plot_type, lb.area_sqm, lb.zone
     FROM land_applications a
     LEFT JOIN land_banks lb ON a.plot_id = lb.id
     WHERE a.id = ?",
    [$applicationId]
);

if (!$application) {
    jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
}

if (!empty($application['documents'])) {
    $application['documents'] = json_decode($application['documents'], true);
}

$wizard = $db->fetchOne("SELECT * FROM la_application_wizard WHERE application_id = ?", [$applicationId]);
if ($wizard) {
    foreach (['profile_json', 'project_json', 'finance_json', 'land_use_json', 'environment_json', 'declaration_json'] as $col) {
        if (!empty($wizard[$col])) {
            $wizard[$col] = json_decode($wizard[$col], true);
        } else {
            $wizard[$col] = null;
        }
    }
}

$documentRows = $db->fetchAll(
    "SELECT ad.*, dt.document_name as master_document_name
     FROM la_application_documents ad
     LEFT JOIN la_document_types dt ON dt.document_code = ad.document_code
     WHERE ad.application_id = ?
     ORDER BY ad.id ASC",
    [$applicationId]
);

$documents = [];
foreach ($documentRows as $row) {
    $label = trim((string)($row['master_document_name'] ?? $row['document_name'] ?? ''));
    if ($label === '') {
        $label = ucwords(str_replace('_', ' ', (string)($row['document_code'] ?? 'Document')));
    }

    $webPath = '';
    $filePath = (string)($row['file_path'] ?? '');
    if ($filePath !== '' && strpos($filePath, BASE_PATH) === 0) {
        $relativePath = str_replace('\\', '/', substr($filePath, strlen(BASE_PATH)));
        $webPath = APP_URL . $relativePath;
    }

    $documents[] = [
        'document_code' => $row['document_code'] ?? '',
        'document_name' => $label,
        'file_name' => $row['file_name'] ?? '',
        'file_path' => $row['file_path'] ?? '',
        'file_url' => $webPath,
        'uploaded_at' => $row['uploaded_at'] ?? null,
    ];
}

$legacyDocuments = [];
if (!empty($application['documents']) && is_array($application['documents'])) {
    foreach ($application['documents'] as $row) {
        if (!is_array($row)) {
            continue;
        }

        $documentCode = (string) ($row['document_code'] ?? '');
        $filePath = (string) ($row['file_path'] ?? '');
        if ($documentCode === '' || $filePath === '') {
            continue;
        }

        $webPath = '';
        if (strpos($filePath, BASE_PATH) === 0) {
            $relativePath = str_replace('\\', '/', substr($filePath, strlen(BASE_PATH)));
            $webPath = APP_URL . $relativePath;
        }

        $legacyDocuments[] = [
            'document_code' => $documentCode,
            'document_name' => $row['document_name'] ?? ucwords(str_replace('_', ' ', $documentCode)),
            'file_name' => $row['file_name'] ?? basename($filePath),
            'file_path' => $filePath,
            'file_url' => $webPath,
            'uploaded_at' => $row['uploaded_at'] ?? null,
        ];
    }
}

if (!empty($legacyDocuments)) {
    $existingDocumentKeys = [];
    foreach ($documents as $document) {
        $existingDocumentKeys[] = ($document['document_code'] ?? '') . '|' . ($document['file_path'] ?? '');
    }
    foreach ($legacyDocuments as $document) {
        $documentKey = ($document['document_code'] ?? '') . '|' . ($document['file_path'] ?? '');
        if (!in_array($documentKey, $existingDocumentKeys, true)) {
            $documents[] = $document;
        }
    }
}

$application['uploaded_documents'] = $documents;
$application['inspection_documents'] = array_values(array_filter($documents, function ($document) {
    return ($document['document_code'] ?? '') === 'inspection_report';
}));

$workflow = [];
$objections = [];
$inspectionUpdates = [];
$tableWorkflow = $db->fetchOne("SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_workflow_actions' LIMIT 1");
$tableObj = $db->fetchOne("SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_objections' LIMIT 1");

if ($tableWorkflow) {
    $workflow = $db->fetchAll(
        "SELECT id, status_code, action_type, action_note, action_at
         FROM la_workflow_actions
         WHERE application_id = ?
         ORDER BY id ASC",
        [$applicationId]
    );
    $inspectionUpdates = array_values(array_filter($workflow, function ($row) {
        $statusCode = strtoupper((string) ($row['status_code'] ?? ''));
        $actionType = strtolower((string) ($row['action_type'] ?? ''));
        return $statusCode === 'INSPECTION_REPORT_SUBMITTED' || $actionType === 'inspection';
    }));
}

if ($tableObj) {
    $objections = $db->fetchAll(
        "SELECT id, objection_text, status, due_date, created_at
         FROM la_objections
         WHERE application_id = ?
         ORDER BY id DESC",
        [$applicationId]
    );
}

$application['wizard'] = $wizard;
$application['workflow'] = $workflow;
$application['objections'] = $objections;
$application['inspection_updates'] = $inspectionUpdates;

jsonResponse(['success' => true, 'data' => $application]);
