<?php
$pageTitle = "Possession - Admin";
$currentPage = 'possession';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_possession') {
            $allotmentId = intval($_POST['allotment_id'] ?? 0);
            $allot = $db->fetchOne("SELECT * FROM la_allotments WHERE id = ?", [$allotmentId]);
            if (!$allot) throw new Exception('Allotment not found');
            $exists = $db->fetchOne("SELECT id FROM la_possessions WHERE allotment_id = ?", [$allotmentId]);
            if ($exists) throw new Exception('Possession already exists for this allotment');
            $letterNo = generateReferenceNumber('POS');
            $db->insert('la_possessions', [
                'allotment_id' => $allotmentId,
                'possession_letter_no' => $letterNo,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            WorkflowService::logAction(intval($allot['application_id']), 'POSSESSION_PENDING', 'forward', intval($_SESSION['admin_id']), 'Possession letter generated');
            $_SESSION['flash_success'] = 'Possession letter generated';
        }

        if ($action === 'grant_possession') {
            $possessionId = intval($_POST['possession_id'] ?? 0);
            $date = $_POST['possession_date'] ?? date('Y-m-d');
            $pos = $db->fetchOne("SELECT p.*, a.application_id FROM la_possessions p INNER JOIN la_allotments a ON a.id = p.allotment_id WHERE p.id = ?", [$possessionId]);
            if (!$pos) throw new Exception('Possession record not found');
            $db->update('la_possessions', ['status' => 'granted', 'possession_date' => $date], 'id = ?', [$possessionId]);
            WorkflowService::logAction(intval($pos['application_id']), 'POSSESSION_GRANTED', 'approve', intval($_SESSION['admin_id']), 'Possession granted');
            $_SESSION['flash_success'] = 'Possession granted';
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: ' . APP_URL . '/admin/?page=possession');
    exit;
}

$eligibleAllotments = $db->fetchAll(
    "SELECT l.*, ap.application_number, u.name user_name, lb.plot_number
     FROM la_allotments l
     LEFT JOIN land_applications ap ON ap.id = l.application_id
     LEFT JOIN users u ON u.id = ap.user_id
     LEFT JOIN land_banks lb ON lb.id = l.plot_id
     WHERE l.status = 'premium_paid'
     AND NOT EXISTS (SELECT 1 FROM la_possessions p WHERE p.allotment_id = l.id)
     ORDER BY l.id DESC"
);

$possessions = $db->fetchAll(
    "SELECT p.*, l.application_id, l.provisional_order_no, ap.application_number, u.name user_name, lb.plot_number
     FROM la_possessions p
     INNER JOIN la_allotments l ON l.id = p.allotment_id
     LEFT JOIN land_applications ap ON ap.id = l.application_id
     LEFT JOIN users u ON u.id = ap.user_id
     LEFT JOIN land_banks lb ON lb.id = l.plot_id
     ORDER BY p.id DESC"
);

ob_start();
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-key"></i> Create Possession Case</h3></div>
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
                            <input type="hidden" name="action" value="create_possession">
                            <input type="hidden" name="allotment_id" value="<?php echo $a['id']; ?>">
                            <button class="btn btn-sm btn-primary" type="submit">Generate Letter</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($eligibleAllotments)): ?><tr><td colspan="5" class="text-center">No premium-paid allotment pending for possession creation</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-handshake"></i> Possession Register</h3>
        <button class="btn btn-sm btn-outline" onclick="downloadTableCsv('possessionRegisterTable','possession_register.csv')">Export CSV</button>
    </div>
    <div class="card-body table-responsive">
        <table class="table" id="possessionRegisterTable">
            <thead><tr><th>Letter No</th><th>Order</th><th>App#</th><th>Applicant</th><th>Plot</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($possessions as $p): ?>
                <tr>
                    <td><?php echo e($p['possession_letter_no']); ?></td>
                    <td><?php echo e($p['provisional_order_no']); ?></td>
                    <td><?php echo e($p['application_number'] ?? '-'); ?></td>
                    <td><?php echo e($p['user_name'] ?? '-'); ?></td>
                    <td><?php echo e($p['plot_number'] ?? '-'); ?></td>
                    <td><span class="badge badge-<?php echo $p['status'] === 'granted' ? 'success' : 'warning'; ?>"><?php echo e($p['status']); ?></span></td>
                    <td><?php echo e($p['possession_date'] ?? '-'); ?></td>
                    <td>
                        <?php if (($p['status'] ?? '') !== 'granted'): ?>
                        <form method="post" style="display:flex; gap:6px;">
                            <input type="hidden" name="action" value="grant_possession">
                            <input type="hidden" name="possession_id" value="<?php echo $p['id']; ?>">
                            <input type="date" class="form-control form-control-sm" name="possession_date" value="<?php echo date('Y-m-d'); ?>" required>
                            <button class="btn btn-sm btn-success" type="submit">Grant</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($possessions)): ?><tr><td colspan="8" class="text-center">No possession records yet</td></tr><?php endif; ?>
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
