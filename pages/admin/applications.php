<?php
$pageTitle = "Applications Management - Admin";
$currentPage = 'applications';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$sessionRole = strtolower(trim((string)($_SESSION['user_type'] ?? 'admin')));
$sessionRegionId = !empty($_SESSION['region_id']) ? (int) $_SESSION['region_id'] : 0;
$sessionAdminId = (int) ($_SESSION['admin_id'] ?? 0);
$isCounterClerk = in_array($sessionRole, ['counter_clerk', 'cc'], true);
$isIeo = in_array($sessionRole, ['ieo', 'industrial_extension_officer'], true);
$isRd = in_array($sessionRole, ['rd', 'regional_director', 'authority'], true);

function resolveNextOfficerId($db, int $applicationRegionId, array $roleOptions, int $preferredId = 0): int {
    if ($preferredId > 0) {
        return $preferredId;
    }
    if (empty($roleOptions)) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($roleOptions), '?'));
    $params = $roleOptions;
    $sql = "SELECT id FROM users WHERE status = 'active' AND user_type IN ($placeholders)";
    if ($applicationRegionId > 0) {
        $sql .= " AND region_id = ?";
        $params[] = $applicationRegionId;
    }
    $sql .= " ORDER BY id ASC LIMIT 1";
    $officer = $db->fetchOne($sql, $params);
    return !empty($officer['id']) ? (int) $officer['id'] : 0;
}

function persistInspectionArtifacts($db, int $applicationId, string $inspectionDate, string $inspectionReportText, array $inspectionFile, int $actionBy): array {
    $inspectionDate = trim($inspectionDate);
    $inspectionReportText = trim($inspectionReportText);
    $hasFile = !empty($inspectionFile['name']);

    if ($inspectionDate === '' && $inspectionReportText === '' && !$hasFile) {
        return ['success' => true, 'saved' => false, 'note' => ''];
    }

    $notes = [];
    if ($inspectionDate !== '') {
        $notes[] = 'Inspection date: ' . $inspectionDate;
    }
    if ($inspectionReportText !== '') {
        $notes[] = $inspectionReportText;
    }

    if ($hasFile) {
        $fileResult = uploadFile($inspectionFile, UPLOADS_PATH . '/inspection-reports');
        if (!$fileResult['success']) {
            return ['success' => false, 'message' => $fileResult['message'] ?? 'Inspection report upload failed'];
        }

        $documentPayload = [
            'document_code' => 'inspection_report',
            'document_name' => 'Inspection Report',
            'file_name' => $fileResult['filename'] ?? '',
            'file_path' => $fileResult['path'] ?? '',
            'uploaded_at' => date('Y-m-d H:i:s')
        ];

        $db->insert('la_application_documents', array_merge(
            ['application_id' => $applicationId],
            $documentPayload
        ));

        $existingApplication = $db->fetchOne("SELECT documents FROM land_applications WHERE id = ?", [$applicationId]);
        $existingDocuments = [];
        if (!empty($existingApplication['documents'])) {
            $decoded = json_decode($existingApplication['documents'], true);
            if (is_array($decoded)) {
                $existingDocuments = $decoded;
            }
        }
        $existingDocuments[] = $documentPayload;
        $db->update('land_applications', [
            'documents' => json_encode($existingDocuments)
        ], 'id = ?', [$applicationId]);
        $notes[] = 'Inspection report file uploaded';
    }

    $combinedNote = trim(implode(' | ', array_filter($notes)));
    if ($combinedNote !== '') {
        WorkflowService::logAction($applicationId, 'INSPECTION_REPORT_SUBMITTED', 'inspection', $actionBy, $combinedNote);
    }

    return ['success' => true, 'saved' => true, 'note' => $combinedNote];
}

function canPerformAppAction(string $role, string $action): bool {
    $role = strtolower(trim($role));
    if (in_array($role, ['superadmin', 'admin'], true)) return true;
    $cc = ['assign_cc', 'raise_objection', 'forward_ieo'];
    $ieo = ['raise_objection', 'forward_do', 'submit_inspection_report'];
    $doRole = ['raise_objection', 'forward_rd'];
    $rd = ['raise_objection', 'revert_do', 'revert_ieo', 'mark_for_bidding', 'approve', 'reject', 'create_bid', 'non_entertain'];

    if (in_array($role, ['counter_clerk', 'cc'], true)) return in_array($action, $cc, true);
    if (in_array($role, ['ieo', 'industrial_extension_officer'], true)) return in_array($action, $ieo, true);
    if (in_array($role, ['do', 'rdd', 'regional_officer', 'jiada_officer'], true)) return in_array($action, $doRole, true);
    if (in_array($role, ['rd', 'regional_director', 'authority'], true)) return in_array($action, $rd, true);
    return false;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action && !canPerformAppAction($sessionRole, $action)) {
        $_SESSION['flash_error'] = 'You are not authorized for this action';
        header('Location: ' . APP_URL . '/admin/?page=applications');
        exit;
    }
    $appId = intval($_POST['application_id'] ?? 0);
    $assignTo = intval($_POST['assign_to'] ?? 0);
    $remarks = sanitize($_POST['remarks'] ?? '');
    $bidEnd = $_POST['bid_end_date'] ?? '';
    $inspectionDate = sanitize($_POST['inspection_date'] ?? '');
    $inspectionReportText = sanitize($_POST['inspection_report_text'] ?? '');
    $inspectionFile = $_FILES['inspection_report_file'] ?? [];
    $inspectionArtifactAttempted = (
        trim($inspectionDate) !== ''
        || trim($inspectionReportText) !== ''
        || !empty($inspectionFile['name'])
    );
    
    $app = $db->fetchOne("SELECT * FROM land_applications WHERE id = ?", [$appId]);
    if ($app) {
        $inspectionResult = ['success' => true, 'saved' => false, 'note' => ''];
        if ($isIeo && $inspectionArtifactAttempted) {
            $inspectionResult = persistInspectionArtifacts(
                $db,
                $appId,
                $inspectionDate,
                $inspectionReportText,
                $inspectionFile,
                intval($_SESSION['admin_id'])
            );
            if (!$inspectionResult['success']) {
                $_SESSION['flash_error'] = $inspectionResult['message'] ?? 'Inspection report upload failed';
                header('Location: ' . APP_URL . '/admin/?page=applications');
                exit;
            }
        }
        if ($action === 'assign_cc' && $assignTo) {
            $db->update('land_applications', ['assigned_to' => $assignTo, 'status' => 'under_scrutiny', 'scrutiny_remarks' => $remarks], 'id = ?', [$appId]);
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_UNDER_PRELIMINARY_SCRUTINY, intval($_SESSION['admin_id']), $remarks ?: 'Assigned to CC');
            Notification::createInApp($assignTo, 'Application Assigned', 'Land application assigned for scrutiny: ' . ($app['application_number'] ?? ''), Notification::PRIORITY_MEDIUM);
            $_SESSION['flash_success'] = 'Application assigned to CC';
        }
        if ($action === 'forward_ieo') {
            $nextOfficerId = resolveNextOfficerId($db, (int) ($app['region_id'] ?? 0), ['ieo', 'industrial_extension_officer'], $assignTo);
            $update = ['scrutiny_remarks' => $remarks ?: 'Forwarded to IEO'];
            if ($nextOfficerId > 0) {
                $update['assigned_to'] = $nextOfficerId;
            }
            $db->update('land_applications', $update, 'id = ?', [$appId]);
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_UNDER_IEO_REVIEW, intval($_SESSION['admin_id']), $remarks ?: 'Forwarded to IEO');
            $_SESSION['flash_success'] = 'Forwarded to IEO review';
        }
        if ($action === 'forward_do') {
            $nextOfficerId = resolveNextOfficerId($db, (int) ($app['region_id'] ?? 0), ['do', 'rdd', 'regional_officer', 'jiada_officer'], $assignTo);
            $update = ['scrutiny_remarks' => $remarks ?: 'Forwarded to DO/RDD'];
            if (!empty($inspectionResult['note'])) {
                $update['scrutiny_remarks'] = trim(($remarks ?: 'Forwarded to DO/RDD') . ' | ' . $inspectionResult['note']);
            }
            if ($nextOfficerId > 0) {
                $update['assigned_to'] = $nextOfficerId;
            }
            $db->update('land_applications', $update, 'id = ?', [$appId]);
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_UNDER_DO_REVIEW, intval($_SESSION['admin_id']), $remarks ?: 'Forwarded to DO/RDD');
            $_SESSION['flash_success'] = 'Forwarded to DO/RDD review';
        }
        if ($action === 'submit_inspection_report') {
            if (!$inspectionArtifactAttempted) {
                $_SESSION['flash_error'] = 'Please enter inspection findings or upload an inspection report file';
            } else {
                if (!empty($inspectionResult['note'])) {
                    $db->update('land_applications', [
                        'scrutiny_remarks' => $inspectionResult['note']
                    ], 'id = ?', [$appId]);
                }
                $_SESSION['flash_success'] = 'Inspection report recorded successfully';
            }
        }
        if ($action === 'revert_do') {
            $nextOfficerId = resolveNextOfficerId($db, (int) ($app['region_id'] ?? 0), ['do', 'rdd', 'regional_officer', 'jiada_officer'], $assignTo);
            $update = ['scrutiny_remarks' => $remarks ?: 'Reverted back to DO/RDD by RD'];
            if ($nextOfficerId > 0) {
                $update['assigned_to'] = $nextOfficerId;
            }
            $db->update('land_applications', $update, 'id = ?', [$appId]);
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_UNDER_DO_REVIEW, intval($_SESSION['admin_id']), $remarks ?: 'Reverted back to DO/RDD by RD');
            $_SESSION['flash_success'] = 'Application reverted back to DO/RDD';
        }
        if ($action === 'revert_ieo') {
            $nextOfficerId = resolveNextOfficerId($db, (int) ($app['region_id'] ?? 0), ['ieo', 'industrial_extension_officer'], $assignTo);
            $update = ['scrutiny_remarks' => $remarks ?: 'Reverted back to IEO by RD'];
            if ($nextOfficerId > 0) {
                $update['assigned_to'] = $nextOfficerId;
            }
            $db->update('land_applications', $update, 'id = ?', [$appId]);
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_UNDER_IEO_REVIEW, intval($_SESSION['admin_id']), $remarks ?: 'Reverted back to IEO by RD');
            $_SESSION['flash_success'] = 'Application reverted back to IEO';
        }
        if ($action === 'forward_rd') {
            $nextOfficerId = resolveNextOfficerId($db, (int) ($app['region_id'] ?? 0), ['rd', 'regional_director'], $assignTo);
            $update = ['scrutiny_remarks' => $remarks ?: 'Forwarded to RD'];
            if ($nextOfficerId > 0) {
                $update['assigned_to'] = $nextOfficerId;
            }
            $db->update('land_applications', $update, 'id = ?', [$appId]);
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_RD_PENDING, intval($_SESSION['admin_id']), $remarks ?: 'Forwarded to RD');
            $_SESSION['flash_success'] = 'Forwarded to RD decision';
        }
        if ($action === 'raise_objection') {
            $db->update('land_applications', ['scrutiny_remarks' => $remarks ?: 'Clarification required'], 'id = ?', [$appId]);
            $db->insert('la_objections', [
                'application_id' => $appId,
                'raised_by' => intval($_SESSION['admin_id']),
                'objection_text' => $remarks ?: 'Clarification required',
                'status' => 'open',
                'due_date' => date('Y-m-d', strtotime('+10 days')),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_DEFICIENCY_RAISED, intval($_SESSION['admin_id']), $remarks ?: 'Deficiency raised');
            $_SESSION['flash_success'] = 'Objection/deficiency raised';
        }
        if ($action === 'mark_for_bidding') {
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_APPROVED_FOR_BIDDING, intval($_SESSION['admin_id']), $remarks ?: 'Marked for bidding');
            $_SESSION['flash_success'] = 'Application marked for bidding';
        }
        if ($action === 'approve') {
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_APPROVED_FOR_ALLOTMENT, intval($_SESSION['admin_id']), $remarks ?: 'Approved');
            // Approve and issue provisional order if single application
            $db->update('land_applications', ['approved_by' => $_SESSION['admin_id'], 'approved_at' => date('Y-m-d H:i:s')], 'id = ?', [$appId]);
            $count = $db->fetchOne("SELECT COUNT(*) as c FROM land_applications WHERE plot_id = ? AND status IN ('submitted','under_scrutiny','approved')", [$app['plot_id']])['c'] ?? 0;
            if ($count <= 1) {
                issueProvisionalOrder($db, $app);
            }
            Notification::sendApplicationStatus($app['user_id'], 'Land Allotment', 'approved', $app['application_number']);
            $_SESSION['flash_success'] = 'Application approved';
        }
        if ($action === 'reject') {
            WorkflowService::moveApplicationStatus($appId, WorkflowService::STATUS_REJECTED, intval($_SESSION['admin_id']), $remarks ?: 'Rejected');
            $db->update('land_applications', ['rejected_reason' => $remarks], 'id = ?', [$appId]);
            Notification::sendApplicationStatus($app['user_id'], 'Land Allotment', 'rejected', $app['application_number']);
            $_SESSION['flash_success'] = 'Application rejected';
        }
        if ($action === 'non_entertain') {
            $reason = $remarks ?: 'Required information/documents not submitted';
            $db->update('land_applications', [
                'status' => 'rejected',
                'non_entertained_flag' => 1,
                'non_entertainment_reason' => $reason,
                'rejected_reason' => $reason
            ], 'id = ?', [$appId]);
            WorkflowService::logAction($appId, 'NON_ENTERTAINED', 'reject', intval($_SESSION['admin_id']), $reason);
            Notification::sendApplicationStatus($app['user_id'], 'Land Allotment', 'rejected', $app['application_number']);
            $_SESSION['flash_success'] = 'Application marked as non-entertained';
        }
        if ($action === 'create_bid') {
            if ($bidEnd) {
                BidService::createBidEventFromApplication($app, $bidEnd, intval($_SESSION['admin_id']));
                $db->update('land_applications', ['status' => 'bidding'], "plot_id = ? AND status IN ('submitted','under_scrutiny','approved','bidding')", [$app['plot_id']]);
                $_SESSION['flash_success'] = 'Bid created and applicants notified';
            } else {
                $_SESSION['flash_error'] = 'Bid end date required (must be at least 10 days from today)';
            }
        }
    }
    header('Location: ' . APP_URL . '/admin/?page=applications');
    exit;
}

if (in_array($sessionRole, ['superadmin', 'admin'], true)) {
    $assignableRoles = ['counter_clerk', 'ieo', 'industrial_extension_officer', 'do', 'rdd', 'regional_officer', 'jiada_officer', 'rd', 'regional_director'];
} elseif ($isCounterClerk) {
    $assignableRoles = ['ieo', 'industrial_extension_officer'];
} elseif (in_array($sessionRole, ['ieo', 'industrial_extension_officer'], true)) {
    $assignableRoles = ['do', 'rdd', 'regional_officer', 'jiada_officer'];
} elseif (in_array($sessionRole, ['do', 'rdd', 'regional_officer', 'jiada_officer'], true)) {
    $assignableRoles = ['rd', 'regional_director'];
} elseif ($isRd) {
    $assignableRoles = ['ieo', 'industrial_extension_officer', 'do', 'rdd', 'regional_officer', 'jiada_officer'];
} else {
    $assignableRoles = [];
}

$officers = [];
if (!empty($assignableRoles)) {
    $rolePlaceholders = implode(',', array_fill(0, count($assignableRoles), '?'));
    $officerParams = $assignableRoles;
    $officerSql = "SELECT id, name, user_type, region_id FROM users WHERE status = 'active' AND user_type IN ($rolePlaceholders)";
    if ($sessionRegionId) {
        $officerSql .= " AND region_id = ?";
        $officerParams[] = $sessionRegionId;
    }
    $officerSql .= " ORDER BY name ASC";
    $officers = $db->fetchAll($officerSql, $officerParams);
}

$appSql = "SELECT la.*, u.name as user_name, lb.plot_number, lb.base_price_per_sqm, lb.area_sqm 
FROM land_applications la 
LEFT JOIN users u ON la.user_id = u.id 
LEFT JOIN land_banks lb ON la.plot_id = lb.id";
$appParams = [];
$appConditions = [];

if ($isCounterClerk) {
    if ($sessionRegionId) {
        $appConditions[] = "(la.assigned_to = ? OR (COALESCE(la.region_id, lb.region_id) = ? AND (la.assigned_to IS NULL OR la.status = 'submitted')))";
        $appParams[] = $sessionAdminId;
        $appParams[] = $sessionRegionId;
    } else {
        $appConditions[] = "(la.assigned_to = ? OR la.assigned_to IS NULL OR la.status = 'submitted')";
        $appParams[] = $sessionAdminId;
    }
} elseif (in_array($sessionRole, ['ieo', 'industrial_extension_officer'], true)) {
    if ($sessionRegionId) {
        $appConditions[] = "(la.assigned_to = ? OR (COALESCE(la.region_id, lb.region_id) = ? AND la.status = 'under_scrutiny' AND (la.scrutiny_remarks LIKE ? OR la.scrutiny_remarks LIKE ?)))";
        $appParams[] = $sessionAdminId;
        $appParams[] = $sessionRegionId;
        $appParams[] = '%Forwarded to IEO%';
        $appParams[] = '%IEO%';
    } else {
        $appConditions[] = "(la.assigned_to = ? OR (la.status = 'under_scrutiny' AND (la.scrutiny_remarks LIKE ? OR la.scrutiny_remarks LIKE ?)))";
        $appParams[] = $sessionAdminId;
        $appParams[] = '%Forwarded to IEO%';
        $appParams[] = '%IEO%';
    }
} elseif (in_array($sessionRole, ['do', 'rdd', 'regional_officer', 'jiada_officer'], true)) {
    if ($sessionRegionId) {
        $appConditions[] = "(la.assigned_to = ? OR (COALESCE(la.region_id, lb.region_id) = ? AND la.status = 'under_scrutiny' AND (la.scrutiny_remarks LIKE ? OR la.scrutiny_remarks LIKE ?)))";
        $appParams[] = $sessionAdminId;
        $appParams[] = $sessionRegionId;
        $appParams[] = '%Forwarded to DO/RDD%';
        $appParams[] = '%DO/RDD%';
    } else {
        $appConditions[] = "(la.assigned_to = ? OR (la.status = 'under_scrutiny' AND (la.scrutiny_remarks LIKE ? OR la.scrutiny_remarks LIKE ?)))";
        $appParams[] = $sessionAdminId;
        $appParams[] = '%Forwarded to DO/RDD%';
        $appParams[] = '%DO/RDD%';
    }
} elseif (in_array($sessionRole, ['rd', 'regional_director', 'authority'], true)) {
    if ($sessionRegionId) {
        $appConditions[] = "(la.assigned_to = ? OR (COALESCE(la.region_id, lb.region_id) = ? AND la.status = 'under_scrutiny' AND (la.scrutiny_remarks LIKE ? OR la.scrutiny_remarks LIKE ?)))";
        $appParams[] = $sessionAdminId;
        $appParams[] = $sessionRegionId;
        $appParams[] = '%Forwarded to RD%';
        $appParams[] = '%RD%';
    } else {
        $appConditions[] = "(la.assigned_to = ? OR (la.status = 'under_scrutiny' AND (la.scrutiny_remarks LIKE ? OR la.scrutiny_remarks LIKE ?)))";
        $appParams[] = $sessionAdminId;
        $appParams[] = '%Forwarded to RD%';
        $appParams[] = '%RD%';
    }
} elseif (!in_array($sessionRole, ['superadmin', 'admin'], true)) {
    if ($sessionRegionId) {
        $appConditions[] = "COALESCE(la.region_id, lb.region_id) = ?";
        $appParams[] = $sessionRegionId;
    }
    $appConditions[] = "(la.assigned_to = ? OR la.assigned_to IS NULL)";
    $appParams[] = $sessionAdminId;
} elseif ($sessionRegionId) {
    $appConditions[] = "COALESCE(la.region_id, lb.region_id) = ?";
    $appParams[] = $sessionRegionId;
}

if (!empty($appConditions)) {
    $appSql .= " WHERE " . implode(' AND ', $appConditions);
}

$appSql .= " ORDER BY la.id DESC LIMIT 100";
$apps = $db->fetchAll($appSql, $appParams);

function issueProvisionalOrder($db, $app) {
    $plot = $db->fetchOne("SELECT * FROM land_banks WHERE id = ?", [$app['plot_id']]);
    $premium = 0;
    if ($plot && !empty($plot['base_price_per_sqm']) && !empty($plot['area_sqm'])) {
        $premium = $plot['base_price_per_sqm'] * $plot['area_sqm'];
    }
    $orderNumber = generateReferenceNumber('ALLOT');
    $deadline = date('Y-m-d', strtotime('+30 days'));
    $orderId = $db->insert('allotment_orders', [
        'order_number' => $orderNumber,
        'application_id' => $app['id'],
        'user_id' => $app['user_id'],
        'plot_id' => $app['plot_id'],
        'order_type' => 'provisional',
        'allotment_date' => date('Y-m-d'),
        'premium_amount' => $premium,
        'outstanding_amount' => $premium,
        'payment_deadline' => $deadline,
        'status' => 'payment_pending',
        'issued_by' => $_SESSION['admin_id'] ?? null
    ]);
    $db->update('land_applications', ['status' => 'provisional_allotted'], 'id = ?', [$app['id']]);
    $db->update('land_banks', ['status' => 'reserved'], 'id = ?', [$app['plot_id']]);
    if ($premium > 0) {
        $paymentId = 'PAY' . date('YmdHis') . strtoupper(substr(uniqid(), -6));
        $db->insert('payments', [
            'payment_id' => $paymentId,
            'user_id' => $app['user_id'],
            'payment_type' => 'premium',
            'reference_type' => 'allotment_order',
            'reference_id' => $orderId,
            'amount' => $premium,
            'gst_amount' => 0,
            'total_amount' => $premium,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'remarks' => 'Premium payment due within 30 days'
        ]);
        $db->insert('dues_outstanding', [
            'user_id' => $app['user_id'],
            'dues_type' => 'premium',
            'due_amount' => $premium,
            'due_date' => $deadline,
            'status' => 'pending'
        ]);
    }
    Notification::createInApp($app['user_id'], 'Provisional Allotment Issued', 'Your provisional allotment order has been issued. Premium payment due by ' . $deadline, Notification::PRIORITY_HIGH);
    return $orderId;
}

ob_start();
?>
<?php if ($isCounterClerk): ?>
<div class="alert alert-info">
    <i class="fas fa-inbox"></i>
    Counter Clerk desk: verify documents, raise deficiencies, and forward completed applications to IEO.
</div>
<?php elseif ($isIeo): ?>
<div class="alert alert-info">
    <i class="fas fa-industry"></i>
    IEO desk: review CC-forwarded applications, inspect project details, raise clarifications, and forward complete cases to DO/RDD.
</div>
<?php elseif ($isRd): ?>
<div class="alert alert-info">
    <i class="fas fa-balance-scale"></i>
    RD desk: review DO/RDD-forwarded applications, take final regional decisions, send files back when needed, and approve, reject, or mark for bidding.
</div>
<?php endif; ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-alt"></i> <?php echo $isCounterClerk ? 'Counter Clerk Applications Queue' : ($isIeo ? 'IEO Applications Queue' : ($isRd ? 'RD Decision Queue' : 'Applications Management')); ?></h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>ID</th><th>App#</th><th>User</th><th>Plot</th><th>Purpose</th><th>Investment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($apps as $a): ?>
                    <tr>
                        <td><?php echo $a['id']; ?></td>
                        <td><?php echo e($a['application_number'] ?? $a['application_no']); ?></td>
                        <td><?php echo e($a['user_name'] ?? '-'); ?></td>
                        <td><?php echo e($a['plot_number'] ?? '-'); ?></td>
                        <td><?php echo e($a['purpose'] ?? '-'); ?></td>
                        <td>INR <?php echo number_format(floatval($a['proposed_investment'] ?? 0), 2); ?></td>
                        <?php
                        $status = $a['status'] ?? 'pending';
                        $badge = 'secondary';
                        if (in_array($status, ['approved', 'provisional_allotted', 'allotted'])) $badge = 'success';
                        elseif (in_array($status, ['under_scrutiny', 'submitted'])) $badge = 'warning';
                        elseif (in_array($status, ['bidding', 'payment_pending'])) $badge = 'info';
                        elseif (in_array($status, ['rejected', 'cancelled'])) $badge = 'danger';
                        ?>
                        <td><span class="badge badge-<?php echo $badge; ?>"><?php echo e($status); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($a['created_at'])); ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline" href="<?php echo APP_URL; ?>/application-view?id=<?php echo $a['id']; ?>">View</a>
                            <form method="post" enctype="multipart/form-data" style="margin-top:8px; display:flex; flex-direction:column; gap:6px; min-width:260px;">
                                <input type="hidden" name="application_id" value="<?php echo $a['id']; ?>">
                                <select name="action" class="form-control form-control-sm">
                                    <option value="">Select action</option>
                                    <?php if (!$isCounterClerk && canPerformAppAction($sessionRole, 'assign_cc')): ?><option value="assign_cc">Assign to CC</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'forward_ieo')): ?><option value="forward_ieo">Forward to IEO</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'forward_do')): ?><option value="forward_do">Forward to DO/RDD</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'submit_inspection_report')): ?><option value="submit_inspection_report">Submit Inspection Report</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'forward_rd')): ?><option value="forward_rd">Forward to RD</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'revert_do')): ?><option value="revert_do">Revert to DO/RDD</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'revert_ieo')): ?><option value="revert_ieo">Revert to IEO</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'raise_objection')): ?><option value="raise_objection">Raise Deficiency</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'mark_for_bidding')): ?><option value="mark_for_bidding">Mark for Bidding</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'approve')): ?><option value="approve">Approve</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'reject')): ?><option value="reject">Reject</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'non_entertain')): ?><option value="non_entertain">Mark Non-Entertained</option><?php endif; ?>
                                    <?php if (canPerformAppAction($sessionRole, 'create_bid')): ?><option value="create_bid">Create Closed Bid</option><?php endif; ?>
                                </select>
                                <?php if (!empty($officers)): ?>
                                <select name="assign_to" class="form-control form-control-sm">
                                    <option value="0"><?php echo $isCounterClerk ? 'Select IEO (optional)' : ($isIeo ? 'Select DO/RDD (optional)' : ($isRd ? 'Select IEO / DO-RDD (optional)' : 'Assign to (optional)')); ?></option>
                                    <?php foreach ($officers as $o): ?>
                                        <option value="<?php echo $o['id']; ?>"><?php echo e($o['name'] . ' - ' . ucfirst(str_replace('_', ' ', $o['user_type'])) . (!empty($o['region_id']) ? ' (Region ' . $o['region_id'] . ')' : '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                                <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Remarks or rejection reason">
                                <?php if ($isIeo): ?>
                                <input type="date" name="inspection_date" class="form-control form-control-sm" placeholder="Inspection date">
                                <textarea name="inspection_report_text" class="form-control form-control-sm" rows="2" placeholder="Inspection findings / report summary"></textarea>
                                <input type="file" name="inspection_report_file" class="form-control form-control-sm">
                                <?php endif; ?>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Bid Date</label>
                                        <input type="date" name="bid_end_date" class="form-control form-control-sm">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">Submit</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($apps)): ?><tr><td colspan="9" class="text-center">No applications found</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
