<?php
$pageTitle = "User Management - Admin";
$currentPage = 'users';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$showAddForm = isset($_GET['show']) && $_GET['show'] === 'add';
$flashError = '';
$flashSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $mobile = preg_replace('/\D+/', '', (string) ($_POST['mobile'] ?? ''));
    $userType = trim((string) ($_POST['user_type'] ?? ''));
    $regionId = !empty($_POST['region_id']) ? (int) $_POST['region_id'] : null;
    $status = trim((string) ($_POST['status'] ?? 'active'));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $mobile === '' || $userType === '' || $password === '') {
        $flashError = 'Name, email, mobile, user type, and password are required.';
        $showAddForm = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flashError = 'Please enter a valid email address.';
        $showAddForm = true;
    } elseif (strlen($mobile) < 10) {
        $flashError = 'Please enter a valid mobile number.';
        $showAddForm = true;
    } else {
        $existing = $db->fetchOne(
            "SELECT id FROM users WHERE email = ? OR mobile = ? LIMIT 1",
            [$email, $mobile]
        );

        if ($existing) {
            $flashError = 'A user with this email or mobile already exists.';
            $showAddForm = true;
        } else {
            $columns = array_map(function($c) { return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM users"));

            $payload = [
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'user_type' => $userType,
                'status' => $status,
                'region_id' => $regionId,
            ];

            if (in_array('email_verified', $columns, true)) {
                $payload['email_verified'] = 1;
            }
            if (in_array('mobile_verified', $columns, true)) {
                $payload['mobile_verified'] = 1;
            }
            if (in_array('created_at', $columns, true)) {
                $payload['created_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('updated_at', $columns, true)) {
                $payload['updated_at'] = date('Y-m-d H:i:s');
            }

            $db->insert('users', $payload);
            header('Location: ' . APP_URL . '/admin/?page=users&success=1');
            exit;
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $flashSuccess = 'User added successfully.';
}

$regions = $db->fetchAll("SELECT id, name FROM regions ORDER BY name ASC");
$users = $db->fetchAll("SELECT u.*, r.name as region_name FROM users u LEFT JOIN regions r ON u.region_id = r.id ORDER BY u.id DESC LIMIT 100");

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> User Management</h3>
        <a href="<?php echo APP_URL; ?>/admin/?page=users&show=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add User</a>
    </div>
    <div class="card-body">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo e($flashSuccess); ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;"><?php echo e($flashError); ?></div>
        <?php endif; ?>

        <?php if ($showAddForm): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h4><i class="fas fa-user-plus"></i> Add New User</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo APP_URL; ?>/admin/?page=users&show=add">
                        <input type="hidden" name="action" value="add_user">
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px;">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo e($_POST['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Mobile</label>
                                <input type="text" name="mobile" class="form-control" value="<?php echo e($_POST['mobile'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>User Type</label>
                                <select name="user_type" class="form-control" required>
                                    <?php $selectedType = (string) ($_POST['user_type'] ?? ''); ?>
                                    <?php foreach (['superadmin','admin','regional_officer','counter_clerk','ieo','rd','employee','applicant'] as $type): ?>
                                        <option value="<?php echo e($type); ?>" <?php echo $selectedType === $type ? 'selected' : ''; ?>><?php echo e($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Region</label>
                                <select name="region_id" class="form-control">
                                    <option value="">No Region</option>
                                    <?php $selectedRegion = (string) ($_POST['region_id'] ?? ''); ?>
                                    <?php foreach ($regions as $region): ?>
                                        <option value="<?php echo (int) $region['id']; ?>" <?php echo $selectedRegion === (string) $region['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($region['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <?php $selectedStatus = (string) ($_POST['status'] ?? 'active'); ?>
                                    <option value="active" <?php echo $selectedStatus === 'active' ? 'selected' : ''; ?>>active</option>
                                    <option value="inactive" <?php echo $selectedStatus === 'inactive' ? 'selected' : ''; ?>>inactive</option>
                                    <option value="pending_verification" <?php echo $selectedStatus === 'pending_verification' ? 'selected' : ''; ?>>pending_verification</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex; gap:10px; margin-top: 15px;">
                            <button type="submit" class="btn btn-primary">Create User</button>
                            <a href="<?php echo APP_URL; ?>/admin/?page=users" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

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
