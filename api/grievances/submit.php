<?php
/**
 * JIADA Portal - Grievance Submission API
 * POST /api/grievances/submit
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$user = Middleware::requireAuth();

$input = getPost();
$db = getDB();
$db->beginTransaction();

try {
    // Validate required fields
    $required = ['department', 'category', 'subject', 'description'];
    $missing = validateRequired($input, $required);
    
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }
    
    // Validate category
    $validCategories = ['service', 'billing', 'allotment', 'maintenance', 'corruption', 'harassment', 'other'];
    if (!in_array($input['category'], $validCategories)) {
        throw new Exception('Invalid grievance category');
    }
    if (strlen($input['subject']) < 5 || strlen($input['subject']) > 150) {
        throw new Exception('Subject must be between 5 and 150 characters');
    }
    if (strlen($input['description']) < 20) {
        throw new Exception('Description must be at least 20 characters');
    }
    if (strlen($input['department']) < 3 || strlen($input['department']) > 100) {
        throw new Exception('Department is invalid');
    }
    
    // Process attachments (max 3 files, pdf/jpg/png up to 5MB)
    $attachments = [];
    if (!empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
        if (count($_FILES['attachments']['name']) > 3) {
            throw new Exception('You can upload up to 3 attachments');
        }
        foreach ($_FILES['attachments']['name'] as $key => $filename) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $fileData = [
                'name' => $filename,
                'type' => $_FILES['attachments']['type'][$key],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                'error' => $_FILES['attachments']['error'][$key],
                'size' => $_FILES['attachments']['size'][$key]
            ];
            $validation = validateFileUpload($fileData, ['pdf', 'jpg', 'jpeg', 'png'], 5 * 1024 * 1024);
            if (!$validation['success']) {
                throw new Exception('Attachment error: ' . ($validation['message'] ?? 'Invalid file'));
            }
            $fileResult = uploadFile($fileData, UPLOADS_PATH . '/grievances');
            if (!$fileResult['success']) {
                throw new Exception('Attachment upload failed: ' . ($fileResult['message'] ?? 'Unknown error'));
            }
            $attachments[] = $fileResult['filename'];
        }
    }
    
    // Generate grievance number
    $grievanceNumber = generateReferenceNumber('GRV');
    
    // Find appropriate secondment officer when RBAC tables are available.
    $officer = null;
    try {
        $rolesTableExists = $db->fetchOne(
            "SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'roles' LIMIT 1"
        );
        $userRolesTableExists = $db->fetchOne(
            "SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_roles' LIMIT 1"
        );

        if ($rolesTableExists && $userRolesTableExists) {
            $officer = $db->fetchOne(
                "SELECT u.id FROM users u
                 INNER JOIN user_roles ur ON u.id = ur.user_id
                 INNER JOIN roles r ON ur.role_id = r.id
                 WHERE r.name LIKE '%secondment%'
                 AND u.status = 'active'
                 ORDER BY u.id ASC LIMIT 1"
            );
        } else {
            logMessage('Grievance officer auto-assignment skipped: roles/user_roles tables not found', 'info', 'general');
        }
    } catch (Exception $roleEx) {
        logMessage('Grievance officer auto-assignment skipped: ' . $roleEx->getMessage(), 'info', 'general');
    }
    
    // Calculate SLA deadline (5 business days)
    $slaDeadline = date(DATETIME_FORMAT, strtotime('+5 weekdays'));
    
    // Insert grievance
    $grievanceId = $db->insert('grievances', [
        'grievance_number' => $grievanceNumber,
        'user_id' => $user['id'],
        'department' => sanitize($input['department']),
        'category' => sanitize($input['category']),
        'subject' => sanitize($input['subject']),
        'description' => sanitize($input['description']),
        'attachments' => json_encode($attachments),
        'secondment_officer_id' => $officer['id'] ?? null,
        'status' => $officer ? 'assigned' : 'submitted',
        'sla_deadline' => $slaDeadline
    ]);
    
    // Notify the assigned officer
    if ($officer) {
        Notification::createInApp($officer['id'], 
            'New Grievance Assigned',
            "Grievance {$grievanceNumber} has been assigned to you for review.",
            Notification::PRIORITY_MEDIUM,
            ['grievance_id' => $grievanceId, 'grievance_number' => $grievanceNumber]
        );
    }
    
    // Notify user of submission
    Notification::send($user['id'], Notification::TYPE_EMAIL,
        'Grievance Submitted - JIADA Portal',
        "Your grievance ({$grievanceNumber}) has been submitted successfully. Category: {$input['category']}. You will receive a response within 5 business days.",
        Notification::PRIORITY_MEDIUM,
        ['grievance_number' => $grievanceNumber, 'category' => $input['category']]
    );
    
    $db->commit();
    
    logActivity("Grievance submitted: {$grievanceNumber}", $user['id']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Grievance submitted successfully',
        'data' => [
            'grievance_id' => $grievanceId,
            'grievance_number' => $grievanceNumber,
            'assigned_officer' => $officer ? true : false,
            'sla_deadline' => $slaDeadline,
            'next_action' => 'Awaiting officer response'
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
