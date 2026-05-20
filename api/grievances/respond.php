<?php
/**
 * Grievance Respond API - Add response to grievance
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/JWT.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405); }

$token = getBearerToken();
if (!$token) { jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401); }

$decoded = JWT::decode($token);
if (!$decoded) { jsonResponse(['success' => false, 'message' => 'Invalid token'], 401); }

$userId = $decoded['user_id'];
$userType = $decoded['user_type'] ?? 'applicant';
$db = getDB();

$grievanceId = intval($_POST['grievance_id'] ?? 0);
$response = sanitize($_POST['response'] ?? '');
$action = $_POST['action'] ?? 'respond';
$rating = intval($_POST['rating'] ?? 0);

if (strlen($response) < 3 && $action !== 'feedback') {
    jsonResponse(['success' => false, 'message' => 'Response must be at least 3 characters']);
}

if (!$grievanceId) { jsonResponse(['success' => false, 'message' => 'Grievance ID required']); }

$grievance = $db->fetchOne("SELECT * FROM grievances WHERE id = ?", [$grievanceId]);
if (!$grievance) { jsonResponse(['success' => false, 'message' => 'Grievance not found']); }

// Check permissions (RBAC)
$staffRoles = ['admin', 'jiada_officer', 'regional_officer'];
if ($userType === 'applicant' && $grievance['user_id'] != $userId) {
    jsonResponse(['success' => false, 'message' => 'Access denied']);
}
if ($userType === 'applicant' && !in_array($action, ['respond', 'feedback'])) {
    jsonResponse(['success' => false, 'message' => 'Applicants cannot perform this action'], 403);
}
if ($userType !== 'applicant' && !in_array($userType, $staffRoles)) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}
if ($userType !== 'applicant' && $action === 'feedback') {
    jsonResponse(['success' => false, 'message' => 'Only applicant can submit feedback'], 403);
}
if ($action === 'feedback' && $grievance['status'] !== 'resolved') {
    jsonResponse(['success' => false, 'message' => 'Feedback allowed only after resolution'], 400);
}

$db->beginTransaction();
try {
    // Add response/comment
    if ($action !== 'feedback') {
        $db->insert('grievance_responses', [
            'grievance_id' => $grievanceId,
            'user_id' => $userId,
            'response' => $response,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Update grievance based on action with RBAC
    $newStatus = $grievance['status'];
    switch ($action) {
        case 'respond':
            if (in_array($userType, $staffRoles)) {
                $newStatus = 'in_progress';
                $db->update('grievances', ['status' => $newStatus, 'response_count' => $grievance['response_count'] + 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$grievanceId]);
            }
            break;
        case 'resolve':
            if (!in_array($userType, $staffRoles)) { throw new Exception('Only officers can resolve'); }
            $newStatus = 'resolved';
            $db->update('grievances', ['status' => $newStatus, 'resolved_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$grievanceId]);
            break;
        case 'escalate':
            if (!in_array($userType, $staffRoles)) { throw new Exception('Only officers can escalate'); }
            $newStatus = 'escalated';
            $db->update('grievances', ['status' => $newStatus, 'escalated' => 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$grievanceId]);
            break;
        case 'close':
            if (!in_array($userType, $staffRoles)) { throw new Exception('Only officers can close'); }
            $newStatus = 'closed';
            $db->update('grievances', ['status' => $newStatus, 'closed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$grievanceId]);
            break;
        case 'feedback':
            if ($userType !== 'applicant') { throw new Exception('Only applicant can submit feedback'); }
            if ($rating < 1 || $rating > 5) { throw new Exception('Rating must be 1-5'); }
            $newStatus = 'closed';
            $db->update('grievances', ['status' => $newStatus, 'feedback_rating' => $rating, 'closed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$grievanceId]);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
    $db->commit();
    // Notify applicant on status change (skip if same)
    if ($newStatus !== $grievance['status']) {
        Notification::createInApp($grievance['user_id'],
            'Grievance Status Updated',
            'Your grievance ' . ($grievance['grievance_number'] ?? $grievance['grievance_no'] ?? '') . ' is now ' . $newStatus,
            Notification::PRIORITY_MEDIUM,
            ['grievance_id' => $grievanceId, 'status' => $newStatus]
        );
    }

    // Escalation notification to admins
    if ($action === 'escalate') {
        $admin = $db->fetchOne("SELECT id FROM users WHERE user_type IN ('superadmin','admin') AND status = 'active' ORDER BY id ASC LIMIT 1");
        if ($admin) {
            Notification::createInApp($admin['id'],
                'Grievance Escalated',
                'Grievance ' . ($grievance['grievance_number'] ?? $grievance['grievance_no'] ?? '') . ' has been escalated.',
                Notification::PRIORITY_URGENT,
                ['grievance_id' => $grievanceId]
            );
        }
    }
    
    jsonResponse(['success' => true, 'message' => 'Response submitted successfully', 'new_status' => $newStatus]);
} catch (Exception $e) {
    $db->rollback();
    jsonResponse(['success' => false, 'message' => 'Failed to submit response: ' . $e->getMessage()]);
}
