<?php
$pageTitle = "User Management - Admin";
$currentPage = 'users';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$users = $db->fetchAll("SELECT u.*, r.name as region_name FROM users u LEFT JOIN regions r ON u.region_id = r.id ORDER BY u.id DESC LIMIT 100");

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> User Management</h3>
        <button class="btn btn-primary btn-sm" onclick="alert('Add user form')"><i class="fas fa-plus"></i> Add User</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Type</th><th>Region</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td><?php echo e($u['name']); ?></td>
                        <td><?php echo e($u['email']); ?></td>
                        <td><?php echo e($u['mobile']); ?></td>
                        <td><span class="badge badge-info"><?php echo e($u['user_type']); ?></span></td>
                        <td><?php echo e($u['region_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo $u['status'] === 'active' ? 'success' : 'warning'; ?>"><?php echo e($u['status']); ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="alert('Edit user <?php echo $u['id']; ?>')">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="alert('Delete user <?php echo $u['id']; ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>