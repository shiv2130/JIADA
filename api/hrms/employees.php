<?php
/**
 * HRMS Employees API
 * GET /api/hrms/employees
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}


$method = getRequestMethod();
$user = Middleware::requireAuth();
$db = getDB();

if ($method === 'GET') {
    $dept = getGet('department_id', null);
    $status = getGet('status', null);
    $where = "1=1";
    $params = [];
    if ($dept) { $where .= " AND e.department_id = ?"; $params[] = intval($dept); }
    if ($status) { $where .= " AND e.status = ?"; $params[] = sanitize($status); }

    $employees = $db->fetchAll(
        "SELECT e.*, d.name as department_name, dg.name as designation_name
         FROM hr_employees e
         LEFT JOIN hr_departments d ON e.department_id = d.id
         LEFT JOIN hr_designations dg ON e.designation_id = dg.id
         WHERE $where
         ORDER BY e.id DESC LIMIT 300",
        $params
    );
    
    jsonResponse(['success' => true, 'data' => $employees]);
}

if ($method === 'POST') {
    $input = getPost();
    $required = ['employee_code','name','department_id','designation_id','date_of_joining'];
    $missing = validateRequired($input, $required);
    if ($missing) {
        jsonResponse(['success' => false, 'message' => 'Missing: '.implode(', ', $missing)], 400);
    }
    $data = [
        'employee_code' => sanitize($input['employee_code']),
        'name' => sanitize($input['name']),
        'department_id' => intval($input['department_id']),
        'designation_id' => intval($input['designation_id']),
        'date_of_joining' => sanitize($input['date_of_joining']),
        'work_location' => sanitize($input['work_location'] ?? ''),
        'status' => sanitize($input['status'] ?? 'active')
    ];
    $existing = $db->fetchOne("SELECT id FROM hr_employees WHERE employee_code = ?", [$data['employee_code']]);
    if ($existing) {
        $db->update('hr_employees', $data, 'id = ?', [$existing['id']]);
        jsonResponse(['success' => true, 'message' => 'Employee updated', 'employee_id' => $existing['id']]);
    } else {
        $id = $db->insert('hr_employees', $data);
        jsonResponse(['success' => true, 'message' => 'Employee created', 'employee_id' => $id]);
    }
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
