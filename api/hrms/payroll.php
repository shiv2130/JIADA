<?php
/**
 * HRMS Payroll API
 * GET /api/hrms/payroll
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

$method = getRequestMethod();
$user = Middleware::requireAuth();
$db = getDB();

if ($method === 'GET') {
    $emp = getGet('employee_id', null);
    $month = getGet('month', null);
    $year = getGet('year', null);
    $where = "1=1";
    $params = [];
    if ($emp) { $where .= " AND p.employee_id = ?"; $params[] = intval($emp); }
    if ($month) { $where .= " AND p.month = ?"; $params[] = intval($month); }
    if ($year) { $where .= " AND p.year = ?"; $params[] = intval($year); }

    $payroll = $db->fetchAll(
        "SELECT p.*, e.name as employee_name, e.employee_code
         FROM hr_payroll p
         LEFT JOIN hr_employees e ON p.employee_id = e.id
         WHERE $where
         ORDER BY p.year DESC, p.month DESC LIMIT 300",
        $params
    );
    
    jsonResponse(['success' => true, 'data' => $payroll]);
}

if ($method === 'POST') {
    $employeeId = intval($_POST['employee_id'] ?? 0);
    $month = intval($_POST['month'] ?? 0);
    $year = intval($_POST['year'] ?? 0);
    $basic = floatval($_POST['basic'] ?? 0);
    $allowances = floatval($_POST['allowances'] ?? 0);
    $bonuses = floatval($_POST['bonuses'] ?? 0);
    $deductions = floatval($_POST['other_deductions'] ?? 0);

    if (!$employeeId || !$month || !$year || $basic <= 0) {
        jsonResponse(['success' => false, 'message' => 'Employee, month, year, and basic pay are required'], 400);
    }
    $emp = $db->fetchOne("SELECT id FROM hr_employees WHERE id = ?", [$employeeId]);
    if (!$emp) { jsonResponse(['success' => false, 'message' => 'Invalid employee'], 400); }

    // Simple payroll math
    $hra = $basic * 0.40;
    $da = $basic * 0.10;
    $gross = $basic + $hra + $da + $allowances + $bonuses;
    $pf = min($basic * 0.12, 1800);
    $pt = 200;
    $esi = 0;
    $tds = 0;
    $totalDeductions = $pf + $pt + $esi + $tds + $deductions;
    $net = $gross - $totalDeductions;

    // Upsert payroll for month/year
    $existing = $db->fetchOne("SELECT id FROM hr_payroll WHERE employee_id = ? AND month = ? AND year = ?", [$employeeId, $month, $year]);
    $data = [
        'employee_id' => $employeeId,
        'month' => $month,
        'year' => $year,
        'basic' => $basic,
        'hra' => $hra,
        'da' => $da,
        'other_allowances' => $allowances,
        'bonus' => $bonuses,
        'pf' => $pf,
        'pt' => $pt,
        'esi' => $esi,
        'tds' => $tds,
        'other_deductions' => $deductions,
        'gross_pay' => $gross,
        'net_pay' => $net,
        'status' => 'processed'
    ];
    if ($existing) {
        $db->update('hr_payroll', $data, 'id = ?', [$existing['id']]);
        $payrollId = $existing['id'];
    } else {
        $payrollId = $db->insert('hr_payroll', $data);
    }
    jsonResponse(['success' => true, 'message' => 'Payroll processed', 'payroll_id' => $payrollId, 'net_pay' => $net]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
