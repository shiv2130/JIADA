<?php
$pageTitle = "HRMS & Payroll - Admin";
$currentPage = 'hrms';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$employees = $db->fetchAll("SELECT * FROM hr_employees ORDER BY id DESC LIMIT 50");

ob_start();
?>
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 20px; text-align: center; background: #e3f2fd;">
        <h3><?php echo count($employees); ?></h3><p>Total Employees</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #e8f5e9;">
        <h3><?php echo count(array_filter($employees, fn($e) => ($e['status'] ?? 'active') === 'active')); ?></h3><p>Active</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #fff3e0;">
        <h3>0</h3><p>On Leave</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #f3e5f5;">
        <h3>₹0</h3><p>Monthly Payroll</p>
    </div>
</div>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-id-card"></i> Employee Directory</h3><button class="btn btn-primary btn-sm">Add Employee</button></div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>ID</th><th>Name</th><th>Department</th><th>Designation</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($employees as $e): ?>
                <tr><td><?php echo $e['id']; ?></td><td><?php echo e($e['name'] ?? 'N/A'); ?></td><td>-</td><td>-</td>
                <td><span class="badge badge-success">Active</span></td>
                <td><button class="btn btn-sm btn-outline">View</button></td></tr>
                <?php endforeach; ?>
                <?php if (empty($employees)): ?><tr><td colspan="6" class="text-center">No employees found</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>