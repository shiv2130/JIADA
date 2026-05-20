<?php
$pageTitle = "Payments Management - Admin";
$currentPage = 'payments';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$payments = $db->fetchAll("SELECT p.*, u.name as user_name FROM payments p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC LIMIT 100");
$totalCollected = $db->fetchOne("SELECT SUM(amount) as total FROM payments WHERE status = 'paid'")['total'] ?? 0;
$totalPending = $db->fetchOne("SELECT SUM(amount - paid_amount) as total FROM payments WHERE status != 'paid'")['total'] ?? 0;

ob_start();
?>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
    <div class="card" style="padding: 20px; background: #e8f5e9;">
        <h4>Total Collected</h4>
        <p style="font-size: 24px; font-weight: bold; color: #2e7d32;">₹<?php echo number_format($totalCollected, 2); ?></p>
    </div>
    <div class="card" style="padding: 20px; background: #fff3e0;">
        <h4>Total Pending</h4>
        <p style="font-size: 24px; font-weight: bold; color: #e65100;">₹<?php echo number_format($totalPending, 2); ?></p>
    </div>
</div>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-credit-card"></i> Payments Management</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>ID</th><th>Receipt#</th><th>User</th><th>For</th><th>Amount</th><th>Paid</th><th>Mode</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td><?php echo e($p['receipt_no'] ?? '-'); ?></td>
                        <td><?php echo e($p['user_name'] ?? '-'); ?></td>
                        <td><?php echo e($p['payment_for'] ?? '-'); ?></td>
                        <td>₹<?php echo number_format($p['amount'], 2); ?></td>
                        <td>₹<?php echo number_format($p['paid_amount'] ?? 0, 2); ?></td>
                        <td><?php echo e($p['payment_mode'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo $p['status'] === 'paid' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'info'); ?>"><?php echo e($p['status']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($p['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)): ?><tr><td colspan="9" class="text-center">No payments found</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>