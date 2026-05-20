<?php
$pageTitle = "Court Cases Management - Admin";
$currentPage = 'court-cases';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$cases = $db->fetchAll("SELECT * FROM court_cases ORDER BY next_hearing_date ASC LIMIT 100");

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-balance-scale"></i> Court Cases Management</h3>
        <button class="btn btn-primary btn-sm" onclick="alert('Add case form')"><i class="fas fa-plus"></i> Add Case</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>ID</th><th>CNR#</th><th>Type</th><th>Subject</th><th>Petitioner</th><th>Respondent</th><th>Court</th><th>Status</th><th>Next Hearing</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($cases as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td><?php echo e($c['cnr_number']); ?></td>
                        <td><?php echo e($c['case_type'] ?? '-'); ?></td>
                        <td><?php echo e(substr($c['subject'], 0, 20)) . '...'; ?></td>
                        <td><?php echo e($c['petitioner'] ?? '-'); ?></td>
                        <td><?php echo e($c['respondent'] ?? '-'); ?></td>
                        <td><?php echo e($c['court_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo $c['status'] === 'disposed' ? 'success' : ($c['status'] === 'pending' ? 'warning' : 'info'); ?>"><?php echo e($c['status']); ?></span></td>
                        <td><?php echo $c['next_hearing_date'] ? date('d/m/Y', strtotime($c['next_hearing_date'])) : '-'; ?></td>
                        <td><button class="btn btn-sm btn-outline">View</button> <button class="btn btn-sm btn-primary">Update</button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cases)): ?><tr><td colspan="10" class="text-center">No cases found</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>