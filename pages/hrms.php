<?php
$pageTitle = "HRMS & Payroll";
$currentPage = 'hrms';
ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-id-card"></i> HRMS & Payroll Management</h2>
        </div>
        <div class="card-body">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="alert alert-warning"><i class="fas fa-lock"></i> Please login to access HRMS.</div>
            <?php else: ?>
                <div class="d-flex gap-10 mb-20">
                    <button class="btn btn-outline btn-sm" onclick="showSection('employees')">Employees</button>
                    <button class="btn btn-outline btn-sm" onclick="showSection('attendance')">Attendance</button>
                    <button class="btn btn-outline btn-sm" onclick="showSection('leave')">Leave</button>
                    <button class="btn btn-outline btn-sm" onclick="showSection('payroll')">Payroll</button>
                </div>
                <div id="hrmsForms">
                    <div id="form-employees" class="mb-20" style="display:none;">
                        <h4>Add / Update Employee</h4>
                        <form id="employeeForm" class="grid-2">
                            <input type="text" name="employee_code" placeholder="Employee Code" class="form-control" required>
                            <input type="text" name="name" placeholder="Employee Name" class="form-control" required>
                            <input type="number" name="department_id" placeholder="Department ID" class="form-control" required>
                            <input type="number" name="designation_id" placeholder="Designation ID" class="form-control" required>
                            <input type="date" name="date_of_joining" class="form-control" required>
                            <input type="text" name="work_location" placeholder="Location" class="form-control">
                            <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                            <button class="btn btn-primary" type="submit">Save Employee</button>
                        </form>
                    </div>
                    <div id="form-attendance" class="mb-20" style="display:none;">
                        <h4>Log Attendance</h4>
                        <form id="attendanceForm" class="grid-2">
                            <input type="number" name="employee_id" placeholder="Employee ID" class="form-control" required>
                            <input type="date" name="attendance_date" class="form-control" required>
                            <input type="time" name="check_in" class="form-control">
                            <input type="time" name="check_out" class="form-control">
                            <button class="btn btn-primary" type="submit">Save Attendance</button>
                        </form>
                    </div>
                    <div id="form-leave" class="mb-20" style="display:none;">
                        <h4>Apply Leave</h4>
                        <form id="leaveForm" class="grid-2">
                            <input type="number" name="employee_id" placeholder="Employee ID" class="form-control" required>
                            <select name="leave_type" class="form-control"><option value="cl">CL</option><option value="sl">SL</option><option value="pl">PL</option></select>
                            <input type="date" name="start_date" class="form-control" required>
                            <input type="date" name="end_date" class="form-control" required>
                            <input type="text" name="reason" class="form-control" placeholder="Reason">
                            <button class="btn btn-primary" type="submit">Submit Leave</button>
                        </form>
                    </div>
                    <div id="form-payroll" class="mb-20" style="display:none;">
                        <h4>Run Payroll</h4>
                        <form id="payrollForm" class="grid-2">
                            <input type="number" name="employee_id" placeholder="Employee ID" class="form-control" required>
                            <input type="number" name="month" placeholder="Month (1-12)" class="form-control" required>
                            <input type="number" name="year" placeholder="Year" class="form-control" required>
                            <input type="number" step="0.01" name="basic" placeholder="Basic" class="form-control" required>
                            <input type="number" step="0.01" name="allowances" placeholder="Allowances" class="form-control">
                            <input type="number" step="0.01" name="bonuses" placeholder="Bonuses" class="form-control">
                            <input type="number" step="0.01" name="other_deductions" placeholder="Other Deductions" class="form-control">
                            <button class="btn btn-primary" type="submit">Process Payroll</button>
                        </form>
                    </div>
                </div>
                <div id="hrmsList"></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if (isset($_SESSION['user_id'])): ?>
<script>
function showSection(type){
    ['employees','attendance','leave','payroll'].forEach(t=>{
        document.getElementById('form-'+t).style.display = (t===type)?'block':'none';
    });
    loadHrms(type);
}
function loadHrms(type) {
    const urlMap = {
        employees: '<?php echo APP_URL; ?>/api/hrms/employees',
        attendance: '<?php echo APP_URL; ?>/api/hrms/attendance',
        leave: '<?php echo APP_URL; ?>/api/hrms/leave',
        payroll: '<?php echo APP_URL; ?>/api/hrms/payroll'
    };
    $.get(urlMap[type], function(res) {
        if (!res.success) { showAlert(res.message || 'Unable to load HRMS data', 'danger'); return; }
        let html = '<div class="table-responsive"><table class="table"><thead><tr>';
        if (type === 'employees') {
            html += '<th>Code</th><th>Name</th><th>Designation</th><th>Department</th><th>Status</th>';
            html += '</tr></thead><tbody>';
            res.data.forEach(e => {
                html += `<tr><td>${e.employee_code}</td><td>${e.name}</td><td>${e.designation_name || '-'}</td><td>${e.department_name || '-'}</td><td>${e.status}</td></tr>`;
            });
        } else if (type === 'attendance') {
            html += '<th>Date</th><th>Employee</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Hours</th>';
            html += '</tr></thead><tbody>';
            res.data.forEach(a => {
                html += `<tr><td>${a.attendance_date}</td><td>${a.employee_name}</td><td>${a.status || '-'}</td><td>${a.check_in || '-'}</td><td>${a.check_out || '-'}</td><td>${a.hours_worked || ''}</td></tr>`;
            });
        } else if (type === 'leave') {
            html += '<th>Employee</th><th>Type</th><th>Start</th><th>End</th><th>Status</th>';
            html += '</tr></thead><tbody>';
            res.data.forEach(l => {
                html += `<tr><td>${l.employee_name}</td><td>${l.leave_type}</td><td>${l.start_date}</td><td>${l.end_date}</td><td>${l.status}</td></tr>`;
            });
        } else if (type === 'payroll') {
            html += '<th>Employee</th><th>Month</th><th>Year</th><th>Net Pay</th><th>Status</th>';
            html += '</tr></thead><tbody>';
            res.data.forEach(p => {
                html += `<tr><td>${p.employee_name}</td><td>${p.month}</td><td>${p.year}</td><td>₹${p.net_pay || 0}</td><td>${p.status || '-'}</td></tr>`;
            });
        }
        html += '</tbody></table></div>';
        $('#hrmsList').html(html);
    });
}

// Form submissions
$('#employeeForm').on('submit', function(e){
    e.preventDefault();
    $.post('<?php echo APP_URL; ?>/api/hrms/employees', $(this).serialize(), function(res){
        if(res.success){ showAlert(res.message,'success'); loadHrms('employees'); $('#employeeForm')[0].reset(); }
        else { showAlert(res.message || 'Failed', 'danger'); }
    });
});
$('#attendanceForm').on('submit', function(e){
    e.preventDefault();
    $.post('<?php echo APP_URL; ?>/api/hrms/attendance', $(this).serialize(), function(res){
        if(res.success){ showAlert('Attendance saved','success'); loadHrms('attendance'); }
        else { showAlert(res.message || 'Failed', 'danger'); }
    });
});
$('#leaveForm').on('submit', function(e){
    e.preventDefault();
    $.post('<?php echo APP_URL; ?>/api/hrms/leave', $(this).serialize(), function(res){
        if(res.success){ showAlert('Leave submitted','success'); loadHrms('leave'); $('#leaveForm')[0].reset(); }
        else { showAlert(res.message || 'Failed', 'danger'); }
    });
});
$('#payrollForm').on('submit', function(e){
    e.preventDefault();
    $.post('<?php echo APP_URL; ?>/api/hrms/payroll', $(this).serialize(), function(res){
        if(res.success){ showAlert('Payroll processed. Net: ₹'+ res.net_pay, 'success'); loadHrms('payroll'); }
        else { showAlert(res.message || 'Failed', 'danger'); }
    });
});

showSection('employees');
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
