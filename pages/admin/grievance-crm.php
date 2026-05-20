<?php
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$pageTitle = "Grievance CRM";
$currentPage = '/admin/?page=grievance-crm';
$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $gid = intval($_POST['grievance_id'] ?? 0);
    $note = sanitize($_POST['note'] ?? '');
    if ($action === 'auto_escalate') {
        $db->update('grievances', ['status' => 'escalated', 'updated_at' => date('Y-m-d H:i:s')], "status NOT IN ('resolved','closed') AND sla_deadline IS NOT NULL AND sla_deadline < NOW()");
        $_SESSION['flash_success'] = 'Overdue grievances escalated';
        header('Location: ' . APP_URL . '/admin/?page=grievance-crm');
        exit;
    }
    if ($action === 'send_reminder' && $gid) {
        $g = $db->fetchOne("SELECT * FROM grievances WHERE id = ?", [$gid]);
        if ($g) {
            $to = $g['secondment_officer_id'] ?? $g['user_id'];
            $db->insert('notifications', [
                'user_id' => $to,
                'type' => 'in_app',
                'subject' => 'Grievance Reminder',
                'message' => 'Reminder: grievance ' . ($g['grievance_number'] ?? $g['grievance_no']) . ' is pending action.',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        $_SESSION['flash_success'] = 'Reminder sent';
        header('Location: ' . APP_URL . '/admin/?page=grievance-crm');
        exit;
    }
    if ($gid && $action) {
        if ($note) {
            $db->insert('grievance_responses', [
                'grievance_id' => $gid,
                'user_id' => $_SESSION['admin_id'],
                'response' => $note,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        if ($action === 'resolve') { $update['status'] = 'resolved'; $update['resolved_at'] = date('Y-m-d H:i:s'); }
        if ($action === 'in_progress') { $update['status'] = 'under_review'; }
        if ($action === 'escalate') { $update['status'] = 'escalated'; }
        if ($action === 'close') { $update['status'] = 'closed'; $update['closed_at'] = date('Y-m-d H:i:s'); }
        $db->update('grievances', $update, 'id = ?', [$gid]);
        $_SESSION['flash_success'] = 'Grievance updated';
    } else {
        $_SESSION['flash_error'] = 'Invalid action';
    }
    header('Location: ' . APP_URL . '/admin/?page=grievance-crm');
    exit;
}

// Filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$where = "1=1";
$params = [];
if ($status) { $where .= " AND g.status = ?"; $params[] = $status; }
if ($search) {
    $where .= " AND (g.grievance_number LIKE ? OR g.subject LIKE ? OR u.name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$grievances = $db->fetchAll(
    "SELECT g.*, u.name as user_name
     FROM grievances g
     LEFT JOIN users u ON g.user_id = u.id
     WHERE $where
     ORDER BY g.created_at DESC
     LIMIT 200",
    $params
);

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-headset"></i> Grievance CRM</h3>
        <div style="display:flex; gap:10px; align-items:center;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="auto_escalate">
                <button class="btn btn-danger btn-sm" type="submit"><i class="fas fa-arrow-up"></i> Auto Escalate Overdue</button>
            </form>
            <form method="GET" action="<?php echo APP_URL; ?>/admin/" style="display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="page" value="grievance-crm">
            <input type="text" name="search" class="form-control" style="width:200px;" placeholder="Search..." value="<?php echo e($search); ?>">
            <select name="status" class="form-control" style="width:160px;">
                <option value="">All Status</option>
                <option value="submitted" <?php echo $status==='submitted'?'selected':''; ?>>Submitted</option>
                <option value="assigned" <?php echo $status==='assigned'?'selected':''; ?>>Assigned</option>
                <option value="under_review" <?php echo $status==='under_review'?'selected':''; ?>>Under Review</option>
                <option value="escalated" <?php echo $status==='escalated'?'selected':''; ?>>Escalated</option>
                <option value="resolved" <?php echo $status==='resolved'?'selected':''; ?>>Resolved</option>
                <option value="closed" <?php echo $status==='closed'?'selected':''; ?>>Closed</option>
            </select>
            <button class="btn btn-outline" type="submit"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Grievance #</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>SLA</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($grievances)): ?>
                    <tr><td colspan="7" class="text-center">No grievances found</td></tr>
                <?php else: foreach ($grievances as $g):
                    $sla = $g['sla_deadline'] ?? null;
                    $overdue = $sla && strtotime($sla) < time() && !in_array($g['status'], ['resolved','closed']);
                ?>
                    <tr>
                        <td><?php echo e($g['grievance_number'] ?? $g['grievance_no']); ?></td>
                        <td><?php echo e($g['user_name'] ?? '-'); ?></td>
                        <td><?php echo e($g['category']); ?></td>
                        <td><?php echo e($g['subject']); ?></td>
                        <td><span class="badge badge-<?php echo in_array($g['status'], ['resolved','closed']) ? 'success' : ($overdue ? 'danger' : 'warning'); ?>"><?php echo e($g['status']); ?></span></td>
                        <td>
                            <?php if ($sla): ?>
                                <?php
                                $hoursLeft = round((strtotime($sla) - time()) / 3600, 1);
                                echo date('d/m/Y', strtotime($sla));
                                echo $overdue ? ' (Overdue)' : ' (' . $hoursLeft . 'h)';
                                ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; gap:6px; align-items:center;">
                                <input type="hidden" name="grievance_id" value="<?php echo $g['id']; ?>">
                                <select name="action" class="form-control" style="width:140px;">
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolve">Resolve</option>
                                    <option value="escalate">Escalate</option>
                                    <option value="close">Close</option>
                                    <option value="send_reminder">Send Reminder</option>
                                </select>
                                <input type="text" name="note" class="form-control" placeholder="Response note" style="width:180px;">
                                <button class="btn btn-primary btn-sm" type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
