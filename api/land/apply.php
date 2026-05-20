<?php
/**
 * JIADA Portal - Land Application API
 * POST /api/land/apply - Submit land allotment application
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Require authentication
$user = Middleware::requireAuth();

$input = getPost();
$db = getDB();
$db->beginTransaction();

try {
    $isFinalSubmit = intval($input['final_submit'] ?? 0) === 1;
    $applicationIdInput = intval($input['application_id'] ?? 0);
    if ($isFinalSubmit) {
        $required = ['plot_id', 'purpose', 'project_description'];
        $missing = validateRequired($input, $required);
        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
    } else {
        if (empty($input['plot_id']) && !$applicationIdInput) {
            throw new Exception('plot_id is required for draft creation');
        }
    }
    
    $plotId = intval($input['plot_id'] ?? 0);
    $existingDocuments = [];
    if ($applicationIdInput) {
        $existingApp = $db->fetchOne("SELECT * FROM land_applications WHERE id = ? AND user_id = ?", [$applicationIdInput, $user['id']]);
        if (!$existingApp) {
            throw new Exception('Application not found');
        }
        if (($existingApp['status'] ?? '') !== 'draft') {
            throw new Exception('Application is locked');
        }
        if (!empty($existingApp['documents'])) {
            $decodedDocuments = json_decode($existingApp['documents'], true);
            if (is_array($decodedDocuments)) {
                $existingDocuments = $decodedDocuments;
            }
        }
        $plotId = intval($existingApp['plot_id']);
    }

    // Verify plot exists and is available
    $plot = $db->fetchOne("SELECT * FROM land_banks WHERE id = ? AND status IN ('available','reserved')", [$plotId]);
    if (!$plot) {
        throw new Exception('Plot is not available for allotment');
    }
    
    // Check for duplicate applications
    if (!$applicationIdInput) {
        $existingApp = $db->fetchOne(
            "SELECT id FROM land_applications WHERE user_id = ? AND plot_id = ? AND status NOT IN ('rejected', 'cancelled')",
            [$user['id'], $plotId]
        );
        if ($existingApp) {
            throw new Exception('You already have an active application for this plot');
        }
    }
    
    // Process uploaded documents
    $documents = [];
    $applicantTypeCode = strtolower(trim((string)($input['applicant_type_code'] ?? 'individual')));
    $checklistTable = $db->fetchOne("SELECT 1 ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_document_types'");
    $mandatoryCodes = [];
    if ($checklistTable) {
        $mandatoryRows = $db->fetchAll(
            "SELECT document_code FROM la_document_types WHERE is_active = 1 AND is_mandatory = 1 AND (applicant_type_code IS NULL OR applicant_type_code = ?)",
            [$applicantTypeCode]
        );
        foreach ($mandatoryRows as $row) $mandatoryCodes[] = $row['document_code'];
    }

    if (!empty($_FILES['doc_files']) && is_array($_FILES['doc_files']['name'])) {
        foreach ($_FILES['doc_files']['name'] as $docCode => $filename) {
            if ($_FILES['doc_files']['error'][$docCode] === UPLOAD_ERR_OK) {
                $fileResult = uploadFile([
                    'name' => $filename,
                    'type' => $_FILES['doc_files']['type'][$docCode],
                    'tmp_name' => $_FILES['doc_files']['tmp_name'][$docCode],
                    'error' => $_FILES['doc_files']['error'][$docCode],
                    'size' => $_FILES['doc_files']['size'][$docCode]
                ]);
                if ($fileResult['success']) {
                    $documents[] = [
                        'document_code' => $docCode,
                        'filename' => $fileResult['filename'],
                        'path' => $fileResult['path'],
                        'type' => $_FILES['doc_files']['type'][$docCode]
                    ];
                } else {
                    throw new Exception('Failed to upload document "' . $docCode . '": ' . ($fileResult['message'] ?? 'Unknown upload error'));
                }
            }
        }
    } elseif (!empty($_FILES['documents'])) {
        foreach ($_FILES['documents']['name'] as $key => $filename) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                $fileResult = uploadFile([
                    'name' => $filename,
                    'type' => $_FILES['documents']['type'][$key],
                    'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                    'error' => $_FILES['documents']['error'][$key],
                    'size' => $_FILES['documents']['size'][$key]
                ]);
                if ($fileResult['success']) {
                    $documents[] = [
                        'document_code' => 'generic_' . $key,
                        'filename' => $fileResult['filename'],
                        'path' => $fileResult['path'],
                        'type' => $_FILES['documents']['type'][$key]
                    ];
                } else {
                    throw new Exception('Failed to upload supporting document #' . ($key + 1) . ': ' . ($fileResult['message'] ?? 'Unknown upload error'));
                }
            }
        }
    }

    $documentsByCode = [];
    foreach ($existingDocuments as $doc) {
        $docCode = trim((string)($doc['document_code'] ?? ''));
        if ($docCode !== '') {
            $documentsByCode[$docCode] = $doc;
        }
    }
    foreach ($documents as $doc) {
        $docCode = trim((string)($doc['document_code'] ?? ''));
        if ($docCode !== '') {
            $documentsByCode[$docCode] = $doc;
        }
    }
    $mergedDocuments = array_values($documentsByCode);

    if ($isFinalSubmit && !empty($mandatoryCodes)) {
        $uploadedCodes = array_map(function($d) { return $d['document_code'] ?? ''; }, $mergedDocuments);
        $missingDocs = array_values(array_diff($mandatoryCodes, $uploadedCodes));
        if (!empty($missingDocs)) {
            throw new Exception('Mandatory documents missing: ' . implode(', ', $missingDocs));
        }
    }
    
    // Generate application details
    $applicationNumber = generateReferenceNumber('LAND');
    $referenceNumber = generateReferenceNumber('REF');

    // Calculate processing fee from category + area (fallback category C for legacy plots)
    $plotAreaAcre = FeeCalculationService::sqmToAcre(floatval($plot['area_sqm'] ?? 0));
    $landCategoryCode = strtoupper(trim($plot['land_category_code'] ?? 'C'));
    $processingFee = FeeCalculationService::calculateProcessingFee($landCategoryCode, $plotAreaAcre);
    
    // Check if there's an active bid for this plot
    $activeBid = $db->fetchOne(
        "SELECT id FROM bids WHERE plot_id = ? AND status = 'active' AND bid_end_date > NOW()",
        [$plot['id']]
    );
    
    $applicationType = $activeBid ? 'bidding' : 'new_allotment';
    $purpose = strtolower(trim((string) ($input['purpose'] ?? 'industry')));
    $allowedPurpose = ['industry', 'institution', 'society', 'trust'];
    if (!in_array($purpose, $allowedPurpose, true)) {
        throw new Exception('Invalid purpose of allotment');
    }
    
    // Insert application
    if ($applicationIdInput) {
        $applicationId = $applicationIdInput;
        $db->update('land_applications', [
            'purpose' => $purpose,
            'applicant_type_code' => $applicantTypeCode,
            'proposed_investment' => !empty($input['proposed_investment']) ? floatval($input['proposed_investment']) : null,
            'employment_generation' => !empty($input['employment_generation']) ? intval($input['employment_generation']) : null,
            'project_description' => sanitize($input['project_description'] ?? ''),
            'documents' => json_encode($mergedDocuments),
            'processing_fee_amount' => $processingFee,
            'status' => $isFinalSubmit ? ($activeBid ? 'bidding' : 'submitted') : 'draft'
        ], 'id = ?', [$applicationId]);
    } else {
        $applicationId = $db->insert('land_applications', [
            'application_number' => $applicationNumber,
            'reference_number' => $referenceNumber,
            'user_id' => $user['id'],
            'plot_id' => $plot['id'],
            'application_type' => $applicationType,
            'purpose' => $purpose,
            'applicant_type_code' => $applicantTypeCode,
            'proposed_investment' => !empty($input['proposed_investment']) ? floatval($input['proposed_investment']) : null,
            'employment_generation' => !empty($input['employment_generation']) ? intval($input['employment_generation']) : null,
            'project_description' => sanitize($input['project_description'] ?? ''),
            'documents' => json_encode($mergedDocuments),
            'processing_fee_amount' => $processingFee,
            'status' => $isFinalSubmit ? ($activeBid ? 'bidding' : 'submitted') : 'draft'
        ]);
    }

    if ($isFinalSubmit && $activeBid) {
        $db->insert('bid_participants', [
            'bid_id' => $activeBid['id'],
            'user_id' => $user['id'],
            'application_id' => $applicationId,
            'emd_paid' => 0,
            'status' => 'emd_pending'
        ]);
    }

    if ($isFinalSubmit && $checklistTable) {
        $db->delete('la_application_documents', 'application_id = ?', [$applicationId]);
        foreach ($mergedDocuments as $doc) {
            $db->insert('la_application_documents', [
                'application_id' => $applicationId,
                'document_code' => $doc['document_code'] ?? 'generic',
                'document_name' => $doc['document_code'] ?? 'Document',
                'file_name' => $doc['filename'] ?? '',
                'file_path' => $doc['path'] ?? '',
                'uploaded_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    // Create pending processing fee record for payment tracking
    if ($isFinalSubmit) {
        PaymentService::createPendingProcessingFee(intval($user['id']), intval($applicationId), floatval($processingFee));
        $wizard = $db->fetchOne("SELECT id FROM la_application_wizard WHERE application_id = ?", [$applicationId]);
        if ($wizard) {
            $db->update('la_application_wizard', [
                'final_submission_flag' => 1,
                'locked_at' => date('Y-m-d H:i:s')
            ], 'application_id = ?', [$applicationId]);
        }
    }

    // Best-effort workflow/audit/notification integration for SOP module tables
    WorkflowService::logAction(
        intval($applicationId),
        $isFinalSubmit ? ($activeBid ? 'APPROVED_FOR_BIDDING' : 'SUBMITTED') : 'DRAFT',
        'submit',
        intval($user['id']),
        $isFinalSubmit ? 'Application filed by applicant' : 'Application saved as draft'
    );

    $db->commit();
    if ($isFinalSubmit) {
        AuditService::log("Land application submitted: {$applicationNumber}", intval($user['id']));
        NotificationService::notifyApplicationSubmitted(intval($user['id']), $applicationNumber);
    } else {
        AuditService::log("Land application draft saved: {$applicationNumber}", intval($user['id']));
    }
    
    jsonResponse([
        'success' => true,
        'message' => $isFinalSubmit ? 'Application submitted successfully' : 'Draft saved successfully',
        'data' => [
            'application_id' => $applicationId,
            'application_number' => $applicationNumber,
            'reference_number' => $referenceNumber,
            'processing_fee' => $processingFee,
            'application_type' => $applicationType,
            'next_step' => $isFinalSubmit ? 'payment' : 'continue_draft'
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
