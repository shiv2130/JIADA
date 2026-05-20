<?php
/**
 * Utility Ticket API - View, update, and manage individual utility tickets
 */
if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

$user = Middleware::requireAuth();
$userId = $user['id'];
$userType = $user['user_type'] ?? 'applicant';
$db = getDB();
$columns = array_map(function($c) { return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM utility_tickets"));

$ticketId = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$ticketId) { jsonResponse(['success' => false, 'message' => 'Ticket ID required']); }

$ticket = $db->fetchOne(
    "SELECT t.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile,
            a.name as assigned_to_name, v.name as vendor_name
     FROM utility_tickets t
     LEFT JOIN users u ON t.user_id = u.id
     LEFT JOIN users a ON t.assigned_to = a.id
     LEFT JOIN users v ON t.vendor_id = v.id
     WHERE t.id = ?",
    [$ticketId]
);

if (!$ticket) { jsonResponse(['success' => false, 'message' => 'Ticket not found']); }

// Check access permissions
if ($userType === 'applicant' && $ticket['user_id'] != $userId) {
    jsonResponse(['success' => false, 'message' => 'Access denied']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get ticket history
    $history = $db->fetchAll(
        "SELECT h.*, u.name as user_name FROM ticket_updates h 
         LEFT JOIN users u ON h.user_id = u.id 
         WHERE h.ticket_id = ? ORDER BY h.created_at ASC",
        [$ticketId]
    );
    
    $ticket['history'] = $history;
    jsonResponse(['success' => true, 'data' => $ticket]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            if (!in_array($userType, ['admin', 'jiada_officer', 'regional_officer'])) {
                jsonResponse(['success' => false, 'message' => 'Access denied']);
            }
            $status = sanitize($_POST['status'] ?? '');
            $validStatuses = ['open', 'assigned', 'in_progress', 'resolved', 'closed', 'escalated', 'reopened'];
            if (!in_array($status, $validStatuses)) {
                jsonResponse(['success' => false, 'message' => 'Invalid status']);
            }
            $db->update('utility_tickets', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'update_type' => 'status_change',
                'message' => 'Status updated to ' . $status,
                'old_status' => $ticket['status'],
                'new_status' => $status
            ]);
            break;
            
        case 'assign_authority':
            if (!in_array($userType, ['admin', 'jiada_officer'])) {
                jsonResponse(['success' => false, 'message' => 'Access denied']);
            }
            $assignedTo = intval($_POST['assigned_to'] ?? 0);
            $db->update('utility_tickets', ['assigned_to' => $assignedTo, 'status' => 'assigned', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'update_type' => 'assignment',
                'message' => 'Authority assigned',
                'old_status' => $ticket['status'],
                'new_status' => 'assigned'
            ]);
            if ($assignedTo) {
                Notification::createInApp($assignedTo, 'Utility Ticket Assigned', 'Ticket ' . ($ticket['ticket_number'] ?? '') . ' assigned to you.', Notification::PRIORITY_HIGH);
                Notification::sendSMS($assignedTo, 'Utility ticket ' . ($ticket['ticket_number'] ?? '') . ' assigned to you.');
            }
            break;

        case 'assign_vendor':
            if (!in_array($userType, ['admin', 'jiada_officer', 'regional_officer'])) {
                jsonResponse(['success' => false, 'message' => 'Access denied']);
            }
            $vendorId = intval($_POST['vendor_id'] ?? 0);
            $db->update('utility_tickets', ['vendor_id' => $vendorId, 'status' => 'in_progress', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'update_type' => 'assignment',
                'message' => 'Vendor assigned',
                'old_status' => $ticket['status'],
                'new_status' => 'in_progress'
            ]);
            if ($vendorId) {
                Notification::createInApp($vendorId, 'Utility Ticket Assigned', 'Vendor task assigned for ticket ' . ($ticket['ticket_number'] ?? ''), Notification::PRIORITY_HIGH);
                Notification::sendSMS($vendorId, 'Vendor task assigned for utility ticket ' . ($ticket['ticket_number'] ?? ''));
            }
            break;
            
        case 'escalate':
            // Only owner or internal staff can escalate and only when ticket is not closed
            if ($userType === 'applicant' && $ticket['user_id'] != $userId) {
                jsonResponse(['success' => false, 'message' => 'Access denied']);
            }
            $allowedStatuses = ['open', 'assigned', 'in_progress', 'resolved', 'escalated'];
            if (!in_array($ticket['status'], $allowedStatuses)) {
                jsonResponse(['success' => false, 'message' => 'Ticket cannot be escalated in current status']);
            }

            $escalationCount = intval($ticket['escalation_count'] ?? 0) + 1;
            $nextAssignee = null;
            if (!empty($ticket['assigned_to'])) {
                $assignedUser = $db->fetchOne("SELECT id, user_type FROM users WHERE id = ?", [$ticket['assigned_to']]);
                if ($assignedUser && $assignedUser['user_type'] === 'jiada_officer') {
                    $nextAssignee = $db->fetchOne("SELECT id FROM users WHERE user_type = 'regional_officer' AND status = 'active' ORDER BY id ASC LIMIT 1");
                } elseif ($assignedUser && $assignedUser['user_type'] === 'regional_officer') {
                    $nextAssignee = $db->fetchOne("SELECT id FROM users WHERE user_type IN ('admin','superadmin') AND status = 'active' ORDER BY id ASC LIMIT 1");
                }
            }
            $db->update('utility_tickets', [
                'status' => 'escalated',
                'escalation_count' => $escalationCount,
                'assigned_to' => $nextAssignee['id'] ?? $ticket['assigned_to'],
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'update_type' => 'escalation',
                'message' => 'Ticket escalated',
                'old_status' => $ticket['status'],
                'new_status' => 'escalated'
            ]);
            if (!empty($nextAssignee['id'])) {
                Notification::createInApp($nextAssignee['id'], 'Utility Ticket Escalated', 'Ticket ' . ($ticket['ticket_number'] ?? '') . ' escalated to you.', Notification::PRIORITY_URGENT);
                Notification::sendSMS($nextAssignee['id'], 'Utility ticket ' . ($ticket['ticket_number'] ?? '') . ' escalated to you.');
            }
            break;
            
        case 'add_note':
            $note = sanitize($_POST['note'] ?? '');
            if (!$note) {
                jsonResponse(['success' => false, 'message' => 'Note is required']);
            }
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'update_type' => 'comment',
                'message' => $note
            ]);
            break;
            
        case 'resolve':
            $resolution = sanitize($_POST['resolution'] ?? '');
            $updateData = [
                'status' => 'resolved',
                'resolved_at' => date('Y-m-d H:i:s'),
                'resolved_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            if (in_array('resolution_notes', $columns)) {
                $updateData['resolution_notes'] = $resolution;
            }
            if (in_array('resolution', $columns)) {
                $updateData['resolution'] = $resolution;
            }
            $db->update('utility_tickets', $updateData, 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'update_type' => 'resolution',
                'message' => $resolution,
                'old_status' => $ticket['status'],
                'new_status' => 'resolved'
            ]);
            Notification::createInApp($ticket['user_id'], 'Utility Ticket Resolved', 'Ticket ' . ($ticket['ticket_number'] ?? '') . ' resolved. Please provide feedback.', Notification::PRIORITY_MEDIUM);
            break;
            
        case 'feedback':
            $rating = intval($_POST['rating'] ?? 0);
            $feedback = sanitize($_POST['feedback'] ?? '');
            if ($rating < 1 || $rating > 5) {
                jsonResponse(['success' => false, 'message' => 'Rating must be 1-5']);
            }
            $feedbackData = [
                'feedback_rating' => $rating,
                'status' => 'closed',
                'closed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            if (in_array('feedback_comment', $columns)) {
                $feedbackData['feedback_comment'] = $feedback;
            }
            if (in_array('feedback_text', $columns)) {
                $feedbackData['feedback_text'] = $feedback;
            }
            $db->update('utility_tickets', $feedbackData, 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'update_type' => 'comment',
                'message' => 'Feedback submitted: ' . $rating . ' star(s)',
                'old_status' => $ticket['status'],
                'new_status' => 'closed'
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action']);
    }
    
    jsonResponse(['success' => true, 'message' => 'Ticket updated successfully']);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
