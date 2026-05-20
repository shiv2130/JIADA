<?php
/**
 * HRMS Leave API
 * GET /api/hrms/leave
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

$method = getRequestMethod();
$user = Middleware::requireAuth();
$db = getDB();

if ($method === 'GET') {
    $emp = getGet('employee_id', null);
    $status = getGet('status', null);
    $where = "1=1";
    $params = [];
    if ($emp) { $where .= " AND l.employee_id = ?"; $params[] = intval($emp); }
    if ($status) { $where .= " AND l.status = ?"; $params[] = sanitize($status); }

    $leaves = $db->fetchAll(
        "SELECT l.*, e.name as employee_name, e.employee_code
         FROM hr_leaves l
         LEFT JOIN hr_employees e ON l.employee_id = e.id
         WHERE $where
         ORDER BY l.start_date DESC LIMIT 300",
        $params
    );
    
    jsonResponse(['success' => true, 'data' => $leaves]);
}

if ($method === 'POST') {
    $employeeId = intval($_POST['employee_id'] ?? 0);
    $leaveType = sanitize($_POST['leave_type'] ?? '');
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $reason = sanitize($_POST['reason'] ?? '');

    if (!$employeeId || !$leaveType || !$start || !$end) {
        jsonResponse(['success' => false, 'message' => 'Employee, leave type, start and end dates are required'], 400);
    }
    $emp = $db->fetchOne("SELECT id FROM hr_employees WHERE id = ?", [$employeeId]);
    if (!$emp) { jsonResponse(['success' => false, 'message' => 'Invalid employee'], 400); }

    $leaveId = $db->insert('hr_leaves', [
        'employee_id' => $employeeId,
        'leave_type' => $leaveType,
        'start_date' => $start,
        'end_date' => $end,
        'reason' => $reason,
        'status' => 'pending',
        'applied_at' => date('Y-m-d H:i:s')
    ]);
    jsonResponse(['success' => true, 'message' => 'Leave request submitted', 'leave_id' => $leaveId]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
