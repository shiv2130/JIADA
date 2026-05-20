<?php
$pageTitle = "Lease Management - Admin";
$currentPage = 'lease';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();

// Handle surrender actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['surrender_id'], $_POST['action'])) {
    $sid = intval($_POST['surrender_id']);
    $action = $_POST['action'];
    $remark = sanitize($_POST['remark'] ?? '');
    $columns = array_map(function($c){ return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM lease_surrenders"));
    $surrender = $db->fetchOne("SELECT * FROM lease_surrenders WHERE id = ?", [$sid]);
    if ($surrender) {
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        if ($action === 'approve') {
            $update['status'] = 'approved';
            if (in_array('approved_at', $columns)) { $update['approved_at'] = date('Y-m-d H:i:s'); }
            if (in_array('approved_by', $columns)) { $update['approved_by'] = $_SESSION['admin_id']; }
        } elseif ($action === 'reject') {
            $update['status'] = 'rejected';
            if (in_array('rejection_reason', $columns)) { $update['rejection_reason'] = $remark; }
        } elseif ($action === 'objection') {
            $count = $surrender['objection_count'] ?? 0;
            if (!in_array('objection_count', $columns) || $count < 2) {
                $update['status'] = 'objection';
                if (in_array('objection_count', $columns)) { $update['objection_count'] = $count + 1; }
                if (in_array('last_objection', $columns)) { $update['last_objection'] = $remark; }
            }
        }
        $db->update('lease_surrenders', $update, 'id = ?', [$sid]);
    }
    header('Location: ' . APP_URL . '/admin/?page=lease');
    exit;
}

// Handle transfer actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_id'], $_POST['action'])) {
    $tid = intval($_POST['transfer_id']);
    $action = $_POST['action'];
    $remark = sanitize($_POST['remark'] ?? '');
    $columns = array_map(function($c){ return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM lease_transfers"));
    $transfer = $db->fetchOne("SELECT * FROM lease_transfers WHERE id = ?", [$tid]);
    if ($transfer) {
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        if ($action === 'approve') {
            $update['status'] = 'approved';
            if (in_array('approved_at', $columns)) { $update['approved_at'] = date('Y-m-d H:i:s'); }
            if (in_array('approved_by', $columns)) { $update['approved_by'] = $_SESSION['admin_id']; }
        } elseif ($action === 'reject') {
            $update['status'] = 'rejected';
            if (in_array('rejection_reason', $columns)) { $update['rejection_reason'] = $remark; }
        } elseif ($action === 'objection') {
            $count = $transfer['objection_count'] ?? 0;
            if (!in_array('objection_count', $columns) || $count < 2) {
                $update['status'] = 'objection';
                if (in_array('objection_count', $columns)) { $update['objection_count'] = $count + 1; }
                if (in_array('last_objection', $columns)) { $update['last_objection'] = $remark; }
            }
        }
        $db->update('lease_transfers', $update, 'id = ?', [$tid]);
    }
    header('Location: ' . APP_URL . '/admin/?page=lease');
    exit;
}

$surrenders = $db->fetchAll("SELECT ls.*, u.name as user_name FROM lease_surrenders ls LEFT JOIN users u ON ls.user_id = u.id ORDER BY ls.id DESC LIMIT 20");
$mortgages = $db->fetchAll("SELECT lm.*, u.name as user_name FROM lease_mortgages lm LEFT JOIN users u ON lm.user_id = u.id ORDER BY lm.id DESC LIMIT 20");
$transfers = $db->fetchAll("SELECT lt.*, u.name as user_name FROM lease_transfers lt LEFT JOIN users u ON lt.from_user_id = u.id ORDER BY lt.id DESC LIMIT 20");
$renewals = $db->fetchAll("SELECT lr.*, u.name as user_name FROM lease_renewals lr LEFT JOIN users u ON lr.user_id = u.id ORDER BY lr.id DESC LIMIT 20");

ob_start();
?>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 20px; text-align: center; background: #e3f2fd;">
        <h3><?php echo count($surrenders); ?></h3><p>Surrender Requests</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #e8f5e9;">
        <h3><?php echo count($mortgages); ?></h3><p>Mortgage Requests</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #fff3e0;">
        <h3><?php echo count($transfers); ?></h3><p>Transfer Requests</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #f3e5f5;">
        <h3><?php echo count($renewals); ?></h3><p>Renewal Requests</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-undo"></i> Surrenders</h3></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>App#</th><th>User</th><th>Refund%</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($surrenders as $s): ?>
                    <tr><td><?php echo e($s['application_no']); ?></td><td><?php echo e($s['user_name'] ?? '-'); ?></td>
                    <td><?php echo $s['refund_percent']; ?>%</td>
                    <td><span class="badge badge-<?php echo $s['status'] === 'approved' ? 'success' : ($s['status']==='rejected'?'danger':($s['status']==='objection'?'warning':'info')); ?>"><?php echo e($s['status']); ?></span></td>
                    <td>
                        <form method="post" style="display:flex; gap:6px; align-items:center;">
                            <input type="hidden" name="surrender_id" value="<?php echo $s['id']; ?>">
                            <select name="action" class="form-control form-control-sm">
                                <option value="approve">Approve</option>
                                <option value="reject">Reject</option>
                                <option value="objection">Raise Objection</option>
                            </select>
                            <input type="text" name="remark" class="form-control form-control-sm" placeholder="Remark">
                            <button class="btn btn-sm btn-primary" type="submit">Update</button>
                        </form>
                    </td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-hand-holding-usd"></i> Mortgages</h3></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>App#</th><th>User</th><th>Bank</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($mortgages as $m): ?>
                    <tr><td><?php echo e($m['application_no']); ?></td><td><?php echo e($m['user_name'] ?? '-'); ?></td>
                    <td><?php echo e($m['bank_name']); ?></td>
                    <td><span class="badge badge-<?php echo $m['status'] === 'approved' ? 'success' : 'warning'; ?>"><?php echo e($m['status']); ?></span></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-exchange-alt"></i> Transfers</h3></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>App#</th><th>From</th><th>To</th><th>Premium</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($transfers as $t): ?>
                    <tr><td><?php echo e($t['application_no']); ?></td><td><?php echo e($t['user_name'] ?? '-'); ?></td>
                    <td><?php echo e($t['to_user_name']); ?></td>
                    <td><?php echo '₹' . number_format($t['land_premium'] ?? 0, 2); ?></td>
                    <td><span class="badge badge-<?php echo $t['status'] === 'approved' ? 'success' : ($t['status']==='rejected'?'danger':($t['status']==='objection'?'warning':'info')); ?>"><?php echo e($t['status']); ?></span></td>
                    <td>
                        <form method="post" style="display:flex; gap:6px; align-items:center;">
                            <input type="hidden" name="transfer_id" value="<?php echo $t['id']; ?>">
                            <select name="action" class="form-control form-control-sm">
                                <option value="approve">Approve</option>
                                <option value="reject">Reject</option>
                                <option value="objection">Raise Objection</option>
                            </select>
                            <input type="text" name="remark" class="form-control form-control-sm" placeholder="Remark">
                            <button class="btn btn-sm btn-primary" type="submit">Update</button>
                        </form>
                    </td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-sync-alt"></i> Renewals</h3></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>App#</th><th>User</th><th>Period</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($renewals as $r): ?>
                    <tr><td><?php echo e($r['application_no']); ?></td><td><?php echo e($r['user_name'] ?? '-'); ?></td>
                    <td><?php echo $r['renewal_period']; ?> yrs</td>
                    <td><span class="badge badge-<?php echo $r['status'] === 'approved' ? 'success' : 'warning'; ?>"><?php echo e($r['status']); ?></span></td></tr>
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
