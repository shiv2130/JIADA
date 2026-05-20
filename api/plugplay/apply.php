<?php
/**
 * Plug & Play Apply API
 * POST /api/plugplay/apply
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
    $required = ['unit_id', 'company_name'];
    $missing = validateRequired($_POST, $required);
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }

    $unitId = intval($_POST['unit_id']);
    $companyName = sanitize($_POST['company_name']);
    $industryType = sanitize($_POST['industry_type'] ?? '');
    $investmentAmount = floatval($_POST['investment_amount'] ?? 0);

    if ($unitId < 1) {
        throw new Exception('Invalid unit');
    }
    if (strlen($companyName) < 3 || strlen($companyName) > 150) {
        throw new Exception('Company name must be between 3 and 150 characters');
    }
    if (!empty($industryType) && strlen($industryType) > 100) {
        throw new Exception('Industry type is too long');
    }
    if ($investmentAmount < 0) {
        throw new Exception('Investment amount must be zero or positive');
    }

    $unit = $db->fetchOne("SELECT * FROM plug_play_units WHERE id = ? AND status = 'available'", [$unitId]);
    if (!$unit) {
        throw new Exception('Unit not available');
    }

    $applicationNo = generateReferenceNumber('PPU');

    $documents = [];
    if (!empty($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
        if (count($_FILES['documents']['name']) > 5) {
            throw new Exception('You can upload up to 5 documents');
        }
        foreach ($_FILES['documents']['name'] as $key => $filename) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $fileData = [
                'name' => $filename,
                'type' => $_FILES['documents']['type'][$key],
                'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                'error' => $_FILES['documents']['error'][$key],
                'size' => $_FILES['documents']['size'][$key]
            ];

            $validation = validateFileUpload($fileData, ['pdf', 'jpg', 'jpeg', 'png'], 5 * 1024 * 1024);
            if (!$validation['success']) {
                throw new Exception('Document upload error: ' . ($validation['message'] ?? 'Invalid file'));
            }

            $fileResult = uploadFile($fileData, UPLOADS_PATH . '/plugplay');
            if (!$fileResult['success']) {
                throw new Exception('Document upload failed: ' . ($fileResult['message'] ?? 'Unknown error'));
            }
            $documents[] = [
                'filename' => $fileResult['filename'],
                'path' => $fileResult['path'],
                'type' => $fileData['type']
            ];
        }
    }

    $appId = $db->insert('plug_play_applications', [
        'application_no' => $applicationNo,
        'user_id' => $user['id'],
        'unit_id' => $unitId,
        'company_name' => $companyName,
        'industry_type' => $industryType,
        'investment_amount' => $investmentAmount,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);

    logActivity("Plug & Play application submitted: {$applicationNo}", $user['id']);

    jsonResponse([
        'success' => true,
        'message' => 'Application submitted',
        'data' => [
            'application_id' => $appId,
            'application_no' => $applicationNo,
            'six_month_rent_due' => floatval($unit['monthly_rent']) * 6
        ]
    ], 201);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
