<?php
$pageTitle = "Plug & Play Management - Admin";
$currentPage = 'plug-play';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$units = $db->fetchAll("SELECT * FROM plug_play_units ORDER BY id DESC LIMIT 100");
$apps = $db->fetchAll("SELECT ppa.*, u.name as user_name, ppu.unit_no FROM plug_play_applications ppa LEFT JOIN users u ON ppa.user_id = u.id LEFT JOIN plug_play_units ppu ON ppa.unit_id = ppu.id ORDER BY ppa.id DESC LIMIT 20");

ob_start();
?>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-building"></i> Units</h3></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>Unit#</th><th>Type</th><th>Area</th><th>Rent</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($units as $u): ?>
                    <tr>
                        <td><?php echo e($u['unit_no']); ?></td>
                        <td><?php echo e($u['unit_type']); ?></td>
                        <td><?php echo $u['area_sqft']; ?> sqft</td>
                        <td>₹<?php echo number_format($u['monthly_rent'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $u['status'] === 'available' ? 'success' : 'warning'; ?>"><?php echo e($u['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-file-alt"></i> Applications</h3></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>App#</th><th>User</th><th>Unit</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($apps as $a): ?>
                    <tr>
                        <td><?php echo e($a['application_no']); ?></td>
                        <td><?php echo e($a['user_name'] ?? '-'); ?></td>
                        <td><?php echo e($a['unit_no'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo $a['status'] === 'approved' ? 'success' : ($a['status'] === 'pending' ? 'warning' : 'danger'); ?>"><?php echo e($a['status']); ?></span></td>
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