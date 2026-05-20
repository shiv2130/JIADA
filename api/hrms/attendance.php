<?php
/**
 * HRMS Attendance API
 * GET /api/hrms/attendance
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

$method = getRequestMethod();
$user = Middleware::requireAuth();
$db = getDB();

if ($method === 'GET') {
    $emp = getGet('employee_id', null);
    $from = getGet('from', null);
    $to = getGet('to', null);
    $where = "1=1";
    $params = [];
    if ($emp) { $where .= " AND a.employee_id = ?"; $params[] = intval($emp); }
    if ($from) { $where .= " AND a.attendance_date >= ?"; $params[] = $from; }
    if ($to) { $where .= " AND a.attendance_date <= ?"; $params[] = $to; }

    $attendance = $db->fetchAll(
        "SELECT a.*, e.name as employee_name, e.employee_code
         FROM hr_attendance a
         LEFT JOIN hr_employees e ON a.employee_id = e.id
         WHERE $where
         ORDER BY a.attendance_date DESC LIMIT 300",
        $params
    );
    
    jsonResponse(['success' => true, 'data' => $attendance]);
}

if ($method === 'POST') {
    $employeeId = intval($_POST['employee_id'] ?? 0);
    $date = $_POST['attendance_date'] ?? date('Y-m-d');
    $checkIn = $_POST['check_in'] ?? null;
    $checkOut = $_POST['check_out'] ?? null;

    if (!$employeeId || !$date) {
        jsonResponse(['success' => false, 'message' => 'Employee and date are required'], 400);
    }
    $emp = $db->fetchOne("SELECT id FROM hr_employees WHERE id = ?", [$employeeId]);
    if (!$emp) { jsonResponse(['success' => false, 'message' => 'Invalid employee'], 400); }

    $hoursWorked = null;
    if ($checkIn && $checkOut) {
        $hoursWorked = (strtotime($checkOut) - strtotime($checkIn)) / 3600;
    }
    $status = 'present';
    if ($hoursWorked !== null) {
        if ($hoursWorked < HRMS_HALF_DAY_THRESHOLD) { $status = 'half_day'; }
        if ($hoursWorked <= 0) { $status = 'absent'; }
    }

    $existing = $db->fetchOne("SELECT id FROM hr_attendance WHERE employee_id = ? AND attendance_date = ?", [$employeeId, $date]);
    $data = [
        'employee_id' => $employeeId,
        'attendance_date' => $date,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'hours_worked' => $hoursWorked,
        'status' => $status
    ];
    if ($existing) {
        $db->update('hr_attendance', $data, 'id = ?', [$existing['id']]);
        $id = $existing['id'];
    } else {
        $id = $db->insert('hr_attendance', $data);
    }
    jsonResponse(['success' => true, 'message' => 'Attendance saved', 'attendance_id' => $id, 'status' => $status]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
