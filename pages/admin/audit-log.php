<?php
$pageTitle = "Audit Log - Admin";
$currentPage = 'audit-log';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$logs = $db->fetchAll("SELECT al.*, u.name as user_name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 100");

ob_start();
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-history"></i> Audit Log</h3></div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>ID</th><th>User</th><th>Action</th><th>Description</th><th>IP</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                <tr><td><?php echo $l['id']; ?></td><td><?php echo e($l['user_name'] ?? 'System'); ?></td>
                <td><span class="badge badge-info"><?php echo e($l['action']); ?></span></td>
                <td><?php echo e($l['description']); ?></td><td><?php echo e($l['ip_address'] ?? '-'); ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($l['created_at'])); ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?><tr><td colspan="6" class="text-center">No logs found</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>