<?php
$pageTitle = "Notifications - Admin";
$currentPage = 'notifications';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$notifications = $db->fetchAll("SELECT n.*, u.name as user_name FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT 100");

ob_start();
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-bell"></i> Notifications</h3></div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>ID</th><th>User</th><th>Title</th><th>Message</th><th>Type</th><th>Read</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($notifications as $n): ?>
                <tr><td><?php echo $n['id']; ?></td><td><?php echo e($n['user_name'] ?? '-'); ?></td>
                <td><?php echo e($n['title'] ?? '-'); ?></td><td><?php echo e(substr($n['message'], 0, 40)) . '...'; ?></td>
                <td><span class="badge badge-<?php echo $n['type'] === 'success' ? 'success' : ($n['type'] === 'error' ? 'danger' : 'info'); ?>"><?php echo e($n['type']); ?></span></td>
                <td><?php echo $n['is_read'] ? '<span class="badge badge-success">Read</span>' : '<span class="badge badge-warning">Unread</span>'; ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($notifications)): ?><tr><td colspan="7" class="text-center">No notifications found</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>