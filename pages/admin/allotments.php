<?php
$pageTitle = "Allotments - Admin";
$currentPage = 'allotments';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'issue_provisional') {
            $applicationId = intval($_POST['application_id'] ?? 0);
            $app = $db->fetchOne("SELECT * FROM land_applications WHERE id = ?", [$applicationId]);
            if (!$app) throw new Exception('Application not found');

            $plot = $db->fetchOne("SELECT * FROM land_banks WHERE id = ?", [$app['plot_id']]);
            if (!$plot) throw new Exception('Plot not found');
            $premium = floatval($plot['base_price_per_sqm'] ?? 0) * floatval($plot['area_sqm'] ?? 0);
            $dueDate = date('Y-m-d', strtotime('+30 days'));
            $orderNo = generateReferenceNumber('ALLOT');

            $allotmentId = $db->insert('la_allotments', [
                'application_id' => $applicationId,
                'plot_id' => $app['plot_id'],
                'allotment_type' => 'single_applicant',
                'provisional_order_no' => $orderNo,
                'provisional_order_date' => date('Y-m-d'),
                'premium_amount' => $premium,
                'payment_due_date' => $dueDate,
                'status' => 'premium_pending',
                'created_by' => intval($_SESSION['admin_id']),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $db->update('land_applications', ['status' => 'provisional_allotted'], 'id = ?', [$applicationId]);
            WorkflowService::logAction($applicationId, 'PROVISIONAL_ALLOTMENT_ISSUED', 'approve', intval($_SESSION['admin_id']), 'Provisional allotment issued');

            PaymentService::createPendingPayment(intval($app['user_id']), 'premium', 'la_allotment', intval($allotmentId), floatval($premium));
            $_SESSION['flash_success'] = 'Provisional allotment issued';
        }

        if ($action === 'mark_premium_paid') {
            $allotmentId = intval($_POST['allotment_id'] ?? 0);
            $allotment = $db->fetchOne("SELECT * FROM la_allotments WHERE id = ?", [$allotmentId]);
            if (!$allotment) throw new Exception('Allotment not found');
            $db->update('la_allotments', ['status' => 'premium_paid'], 'id = ?', [$allotmentId]);
            WorkflowService::logAction(intval($allotment['application_id']), 'PREMIUM_PAID', 'approve', intval($_SESSION['admin_id']), 'Premium marked paid');
            $_SESSION['flash_success'] = 'Premium marked as paid';
        }

        if ($action === 'cancel_allotment') {
            $allotmentId = intval($_POST['allotment_id'] ?? 0);
            $allotment = $db->fetchOne("SELECT * FROM la_allotments WHERE id = ?", [$allotmentId]);
            if (!$allotment) throw new Exception('Allotment not found');
            $db->update('la_allotments', ['status' => 'cancelled'], 'id = ?', [$allotmentId]);
            $db->update('land_applications', ['status' => 'cancelled'], 'id = ?', [$allotment['application_id']]);
            WorkflowService::logAction(intval($allotment['application_id']), 'ALLOTMENT_CANCELLED', 'reject', intval($_SESSION['admin_id']), 'Allotment cancelled');
            $_SESSION['flash_success'] = 'Allotment cancelled';
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: ' . APP_URL . '/admin/?page=allotments');
    exit;
}

$approvedApps = $db->fetchAll(
    "SELECT a.id, a.application_number, a.user_id, u.name user_name, lb.plot_number
     FROM land_applications a
     LEFT JOIN users u ON u.id = a.user_id
     LEFT JOIN land_banks lb ON lb.id = a.plot_id
     WHERE a.status IN ('approved','under_scrutiny')
     AND NOT EXISTS (SELECT 1 FROM la_allotments l WHERE l.application_id = a.id)
     ORDER BY a.id DESC"
);

$allotments = $db->fetchAll(
    "SELECT l.*, a.application_number, u.name user_name, lb.plot_number
     FROM la_allotments l
     LEFT JOIN land_applications a ON a.id = l.application_id
     LEFT JOIN users u ON u.id = a.user_id
     LEFT JOIN land_banks lb ON lb.id = l.plot_id
     ORDER BY l.id DESC"
);

ob_start();
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-signature"></i> Issue Provisional Allotment</h3></div>
    <div class="card-body table-responsive">
        <table class="table">
            <thead><tr><th>App#</th><th>Applicant</th><th>Plot</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($approvedApps as $a): ?>
                <tr>
                    <td><?php echo e($a['application_number']); ?></td>
                    <td><?php echo e($a['user_name'] ?? '-'); ?></td>
                    <td><?php echo e($a['plot_number'] ?? '-'); ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="issue_provisional">
                            <input type="hidden" name="application_id" value="<?php echo $a['id']; ?>">
                            <button class="btn btn-sm btn-primary" type="submit">Issue Provisional</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($approvedApps)): ?><tr><td colspan="4" class="text-center">No eligible applications</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Allotment Register</h3>
        <button class="btn btn-sm btn-outline" onclick="downloadTableCsv('allotmentRegisterTable','allotment_register.csv')">Export CSV</button>
    </div>
    <div class="card-body table-responsive">
        <table class="table" id="allotmentRegisterTable">
            <thead><tr><th>Order No</th><th>App#</th><th>Applicant</th><th>Plot</th><th>Premium</th><th>Due</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($allotments as $l): ?>
                <tr>
                    <td><?php echo e($l['provisional_order_no']); ?></td>
                    <td><?php echo e($l['application_number'] ?? '-'); ?></td>
                    <td><?php echo e($l['user_name'] ?? '-'); ?></td>
                    <td><?php echo e($l['plot_number'] ?? '-'); ?></td>
                    <td><?php echo number_format(floatval($l['premium_amount']), 2); ?></td>
                    <td><?php echo e($l['payment_due_date']); ?></td>
                    <td><span class="badge badge-<?php echo $l['status'] === 'premium_paid' ? 'success' : ($l['status'] === 'cancelled' ? 'danger' : 'warning'); ?>"><?php echo e($l['status']); ?></span></td>
                    <td style="display:flex; gap:6px;">
                        <?php if (($l['status'] ?? '') !== 'premium_paid' && ($l['status'] ?? '') !== 'cancelled'): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="mark_premium_paid">
                            <input type="hidden" name="allotment_id" value="<?php echo $l['id']; ?>">
                            <button class="btn btn-sm btn-success" type="submit">Mark Premium Paid</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="cancel_allotment">
                            <input type="hidden" name="allotment_id" value="<?php echo $l['id']; ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Cancel</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($allotments)): ?><tr><td colspan="8" class="text-center">No allotments yet</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$scripts = '
<script>
function downloadTableCsv(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = table.querySelectorAll("tr");
    const csv = [];
    rows.forEach(row => {
        const cols = row.querySelectorAll("th,td");
        const line = [];
        cols.forEach(c => line.push(\'"\' + (c.innerText || "").replace(/"/g, \'""\') + \'"\'));
        csv.push(line.join(","));
    });
    const blob = new Blob([csv.join("\\n")], {type: "text/csv;charset=utf-8;"});
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}
</script>';
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
