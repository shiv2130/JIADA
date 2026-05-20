<?php
$pageTitle = "My Profile - Admin";
$currentPage = 'profile';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['admin_id']]);

ob_start();
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-user-circle"></i> My Profile</h3></div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="form-group"><label>Name</label><input type="text" class="form-control" value="<?php echo e($user['name'] ?? ''); ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" class="form-control" value="<?php echo e($user['email'] ?? ''); ?>"></div>
                <div class="form-group"><label>Mobile</label><input type="tel" class="form-control" value="<?php echo e($user['mobile'] ?? ''); ?>"></div>
                <div class="form-group"><label>User Type</label><input type="text" class="form-control" value="<?php echo e($user['user_type'] ?? ''); ?>" disabled></div>
                <button class="btn btn-primary">Update Profile</button>
            </div>
            <div>
                <h4>Change Password</h4>
                <div class="form-group"><label>Current Password</label><input type="password" class="form-control"></div>
                <div class="form-group"><label>New Password</label><input type="password" class="form-control"></div>
                <div class="form-group"><label>Confirm Password</label><input type="password" class="form-control"></div>
                <button class="btn btn-warning">Change Password</button>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>