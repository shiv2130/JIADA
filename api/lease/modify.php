<?php
/**
 * Plot Modification API - Merge/Demerge/Amalgamate
 * POST /api/lease/modify
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
    $action = $_POST['action'] ?? 'create';
    $columns = array_map(function($c){ return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM plot_modification_requests"));
    $staffRoles = ['admin','jiada_officer','regional_officer'];

    if ($action !== 'create') {
        if (!in_array($user['user_type'] ?? '', $staffRoles)) {
            throw new Exception('Access denied');
        }
        $reqId = intval($_POST['request_id'] ?? 0);
        if (!$reqId) { throw new Exception('Request ID required'); }
        $req = $db->fetchOne("SELECT * FROM plot_modification_requests WHERE id = ?", [$reqId]);
        if (!$req) { throw new Exception('Request not found'); }
        $remark = sanitize($_POST['remark'] ?? '');
        $update = ['updated_at' => date('Y-m-d H:i:s')];

        switch ($action) {
            case 'objection':
                $count = $req['objection_count'] ?? 0;
                if (in_array('objection_count', $columns) && $count >= 2) {
                    throw new Exception('Maximum two objections allowed');
                }
                $update['status'] = 'objection';
                if (in_array('objection_count', $columns)) { $update['objection_count'] = $count + 1; }
                if (in_array('last_objection', $columns)) { $update['last_objection'] = $remark; }
                break;
            case 'approve':
                $update['status'] = 'approved';
                if (in_array('approved_at', $columns)) { $update['approved_at'] = date('Y-m-d H:i:s'); }
                if (in_array('approved_by', $columns)) { $update['approved_by'] = $user['id']; }
                break;
            case 'reject':
                $update['status'] = 'rejected';
                if (in_array('rejection_reason', $columns)) { $update['rejection_reason'] = $remark; }
                break;
            default:
                throw new Exception('Invalid action');
        }
        $db->update('plot_modification_requests', $update, 'id = ?', [$reqId]);

        Notification::createInApp($req['user_id'],
            'Plot Modification Status Updated',
            'Your merge/demerge/amalgamate request ' . ($req['application_number'] ?? '') . ' is now ' . $update['status'],
            Notification::PRIORITY_MEDIUM,
            ['request_id' => $reqId, 'status' => $update['status']]
        );
        if ($action === 'approve') {
            Notification::send($req['user_id'], Notification::TYPE_EMAIL,
                'Plot Modification Approved',
                'Your merge/demerge/amalgamate request has been approved. Certificate will be sent to your registered email.',
                Notification::PRIORITY_MEDIUM,
                ['request_id' => $reqId, 'application_number' => $req['application_number'] ?? '']
            );
        }

        jsonResponse(['success' => true, 'message' => 'Status updated', 'status' => $update['status']]);
    }

    $required = ['operation', 'primary_lease_no', 'plot_ids'];
    $missing = validateRequired($_POST, $required);
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }

    $operation = sanitize($_POST['operation']);
    $primaryLeaseNo = sanitize($_POST['primary_lease_no']);
    $plotIds = is_array($_POST['plot_ids']) ? $_POST['plot_ids'] : explode(',', $_POST['plot_ids']);
    $reason = sanitize($_POST['reason'] ?? '');
    if (strlen($reason) < 5) { throw new Exception('Reason must be at least 5 characters'); }

    $primaryLease = $db->fetchOne("SELECT * FROM lease_records WHERE lease_no = ? AND user_id = ?", [$primaryLeaseNo, $user['id']]);
    if (!$primaryLease) {
        throw new Exception('Primary lease not found');
    }

    $secondaryLeaseNos = !empty($_POST['secondary_lease_nos']) ? explode(',', $_POST['secondary_lease_nos']) : [];
    $secondaryLeaseIds = [];
    foreach ($secondaryLeaseNos as $leaseNo) {
        $leaseNo = trim($leaseNo);
        if (!$leaseNo) { continue; }
        $lease = $db->fetchOne("SELECT id FROM lease_records WHERE lease_no = ? AND user_id = ?", [$leaseNo, $user['id']]);
        if ($lease) { $secondaryLeaseIds[] = $lease['id']; }
    }

    // Calculate premium: 15% MSME, 25% otherwise, based on combined land premium if available
    $isMsme = intval($_POST['is_msme'] ?? 0);
    $premiumPercent = $isMsme ? 15 : 25;
    $landPremiumBase = floatval($primaryLease['premium_amount'] ?? 0);
    if (!empty($secondaryLeaseIds)) {
        $extra = $db->fetchAll("SELECT premium_amount FROM lease_records WHERE id IN (" . implode(',', array_fill(0, count($secondaryLeaseIds), '?')) . ")", $secondaryLeaseIds);
        foreach ($extra as $row) { $landPremiumBase += floatval($row['premium_amount'] ?? 0); }
    }
    $calculatedPremium = ($landPremiumBase * $premiumPercent) / 100;

    $requestId = $db->insert('plot_modification_requests', [
        'application_number' => generateReferenceNumber('PMR'),
        'modification_type' => $operation,
        'primary_lease_id' => $primaryLease['id'],
        'secondary_lease_ids' => json_encode($secondaryLeaseIds),
        'user_id' => $user['id'],
        'plot_ids' => json_encode($plotIds),
        'is_msme' => $isMsme,
        'premium_percent' => $premiumPercent,
        'calculated_premium' => $calculatedPremium,
        'status' => 'submitted',
        'created_at' => date('Y-m-d H:i:s')
    ]);

    jsonResponse(['success' => true, 'message' => 'Request submitted', 'data' => ['request_id' => $requestId, 'premium_percent' => $premiumPercent, 'calculated_premium' => $calculatedPremium]]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
