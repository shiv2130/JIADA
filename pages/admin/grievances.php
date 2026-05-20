<?php
$pageTitle = "Grievances Management - Admin";
$currentPage = 'grievances';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$grevs = $db->fetchAll("SELECT g.*, u.name as user_name, d.name as dept_name FROM grievances g LEFT JOIN users u ON g.user_id = u.id LEFT JOIN departments d ON g.department_id = d.id ORDER BY g.id DESC LIMIT 100");

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-headset"></i> Grievances Management</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>ID</th><th>Grev#</th><th>User</th><th>Category</th><th>Department</th><th>Subject</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($grevs as $g): ?>
                    <tr>
                        <td><?php echo $g['id']; ?></td>
                        <td><?php echo e($g['grievance_no']); ?></td>
                        <td><?php echo e($g['user_name'] ?? '-'); ?></td>
                        <td><?php echo e($g['category']); ?></td>
                        <td><?php echo e($g['dept_name'] ?? '-'); ?></td>
                        <td><?php echo e(substr($g['subject'], 0, 30)) . '...'; ?></td>
                        <td><span class="badge badge-<?php echo $g['status'] === 'resolved' ? 'success' : ($g['status'] === 'pending' ? 'warning' : 'info'); ?>"><?php echo e($g['status']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($g['created_at'])); ?></td>
                        <td><button class="btn btn-sm btn-outline">View</button> <button class="btn btn-sm btn-primary">Respond</button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($grevs)): ?><tr><td colspan="9" class="text-center">No grievances found</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>