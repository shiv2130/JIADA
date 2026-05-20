<?php
$pageTitle = "Registry - Admin";
$currentPage = 'registry';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'init_registry') {
            $allotmentId = intval($_POST['allotment_id'] ?? 0);
            $allot = $db->fetchOne("SELECT * FROM la_allotments WHERE id = ?", [$allotmentId]);
            if (!$allot) throw new Exception('Allotment not found');
            $exists = $db->fetchOne("SELECT id FROM la_registry_process WHERE allotment_id = ?", [$allotmentId]);
            if ($exists) throw new Exception('Registry process already exists');
            $db->insert('la_registry_process', [
                'allotment_id' => $allotmentId,
                'cte_received_flag' => 0,
                'construction_permit_applied_flag' => 0,
                'status' => 'pending_cte'
            ]);
            WorkflowService::logAction(intval($allot['application_id']), 'REGISTRY_INITIATED', 'forward', intval($_SESSION['admin_id']), 'Registry process initialized');
            $_SESSION['flash_success'] = 'Registry process initialized';
        }

        if ($action === 'update_registry') {
            $registryId = intval($_POST['registry_id'] ?? 0);
            $cte = intval($_POST['cte_received_flag'] ?? 0) ? 1 : 0;
            $permit = intval($_POST['construction_permit_applied_flag'] ?? 0) ? 1 : 0;
            $bpams = sanitize($_POST['bpams_reference_no'] ?? '');
            $leaseNo = sanitize($_POST['lease_deed_no'] ?? '');
            $leaseDate = $_POST['lease_deed_date'] ?? null;

            $proc = $db->fetchOne("SELECT r.*, a.application_id FROM la_registry_process r INNER JOIN la_allotments a ON a.id = r.allotment_id WHERE r.id = ?", [$registryId]);
            if (!$proc) throw new Exception('Registry record not found');

            $status = 'pending_cte';
            if ($cte && !$permit) $status = 'pending_construction_permit';
            if ($cte && $permit) $status = 'registry_initiated';
            if ($cte && $permit && $leaseNo) $status = 'lease_executed';

            $db->update('la_registry_process', [
                'cte_received_flag' => $cte,
                'construction_permit_applied_flag' => $permit,
                'bpams_reference_no' => $bpams ?: null,
                'registry_initiated_date' => ($cte && $permit) ? date('Y-m-d') : null,
                'lease_deed_no' => $leaseNo ?: null,
                'lease_deed_date' => $leaseDate ?: null,
                'status' => $status
            ], 'id = ?', [$registryId]);

            WorkflowService::logAction(intval($proc['application_id']), strtoupper($status), 'forward', intval($_SESSION['admin_id']), 'Registry status updated');
            $_SESSION['flash_success'] = 'Registry updated';
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: ' . APP_URL . '/admin/?page=registry');
    exit;
}

$eligibleAllotments = $db->fetchAll(
    "SELECT l.id, l.provisional_order_no, l.application_id, ap.application_number, u.name user_name, lb.plot_number
     FROM la_allotments l
     INNER JOIN la_possessions p ON p.allotment_id = l.id AND p.status = 'granted'
     LEFT JOIN land_applications ap ON ap.id = l.application_id
     LEFT JOIN users u ON u.id = ap.user_id
     LEFT JOIN land_banks lb ON lb.id = l.plot_id
     WHERE NOT EXISTS (SELECT 1 FROM la_registry_process r WHERE r.allotment_id = l.id)
     ORDER BY l.id DESC"
);

$registryRows = $db->fetchAll(
    "SELECT r.*, l.provisional_order_no, ap.application_number, u.name user_name, lb.plot_number
     FROM la_registry_process r
     INNER JOIN la_allotments l ON l.id = r.allotment_id
     LEFT JOIN land_applications ap ON ap.id = l.application_id
     LEFT JOIN users u ON u.id = ap.user_id
     LEFT JOIN land_banks lb ON lb.id = l.plot_id
     ORDER BY r.id DESC"
);

ob_start();
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-book"></i> Initialize Registry</h3></div>
    <div class="card-body table-responsive">
        <table class="table">
            <thead><tr><th>Order</th><th>App#</th><th>Applicant</th><th>Plot</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($eligibleAllotments as $a): ?>
                <tr>
                    <td><?php echo e($a['provisional_order_no']); ?></td>
                    <td><?php echo e($a['application_number'] ?? '-'); ?></td>
                    <td><?php echo e($a['user_name'] ?? '-'); ?></td>
                    <td><?php echo e($a['plot_number'] ?? '-'); ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="init_registry">
                            <input type="hidden" name="allotment_id" value="<?php echo $a['id']; ?>">
                            <button class="btn btn-sm btn-primary" type="submit">Init Registry</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($eligibleAllotments)): ?><tr><td colspan="5" class="text-center">No eligible possession-granted allotments</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Registry Process Register</h3>
        <button class="btn btn-sm btn-outline" onclick="downloadTableCsv('registryRegisterTable','registry_register.csv')">Export CSV</button>
    </div>
    <div class="card-body table-responsive">
        <table class="table" id="registryRegisterTable">
            <thead><tr><th>Order</th><th>App#</th><th>Applicant</th><th>Plot</th><th>Status</th><th>Update</th></tr></thead>
            <tbody>
            <?php foreach ($registryRows as $r): ?>
                <tr>
                    <td><?php echo e($r['provisional_order_no']); ?></td>
                    <td><?php echo e($r['application_number'] ?? '-'); ?></td>
                    <td><?php echo e($r['user_name'] ?? '-'); ?></td>
                    <td><?php echo e($r['plot_number'] ?? '-'); ?></td>
                    <td><span class="badge badge-info"><?php echo e($r['status']); ?></span></td>
                    <td>
                        <form method="post" style="display:grid; grid-template-columns: repeat(5, minmax(120px, 1fr)); gap:6px;">
                            <input type="hidden" name="action" value="update_registry">
                            <input type="hidden" name="registry_id" value="<?php echo $r['id']; ?>">
                            <select class="form-control form-control-sm" name="cte_received_flag">
                                <option value="0" <?php echo intval($r['cte_received_flag']) ? '' : 'selected'; ?>>CTE No</option>
                                <option value="1" <?php echo intval($r['cte_received_flag']) ? 'selected' : ''; ?>>CTE Yes</option>
                            </select>
                            <select class="form-control form-control-sm" name="construction_permit_applied_flag">
                                <option value="0" <?php echo intval($r['construction_permit_applied_flag']) ? '' : 'selected'; ?>>Permit No</option>
                                <option value="1" <?php echo intval($r['construction_permit_applied_flag']) ? 'selected' : ''; ?>>Permit Yes</option>
                            </select>
                            <input class="form-control form-control-sm" name="bpams_reference_no" placeholder="BPAMS Ref" value="<?php echo e($r['bpams_reference_no'] ?? ''); ?>">
                            <input class="form-control form-control-sm" name="lease_deed_no" placeholder="Lease Deed No" value="<?php echo e($r['lease_deed_no'] ?? ''); ?>">
                            <div style="display:flex; gap:6px;">
                                <input class="form-control form-control-sm" type="date" name="lease_deed_date" value="<?php echo e($r['lease_deed_date'] ?? ''); ?>">
                                <button class="btn btn-sm btn-success" type="submit">Save</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($registryRows)): ?><tr><td colspan="6" class="text-center">No registry cases yet</td></tr><?php endif; ?>
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
