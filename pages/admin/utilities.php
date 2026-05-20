<?php
$pageTitle = "Utilities Management - Admin";
$currentPage = 'utilities';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = intval($_POST['ticket_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($ticketId && $action) {
        if ($action === 'assign_authority') {
            $assignedTo = intval($_POST['assigned_to'] ?? 0);
            $db->update('utility_tickets', ['assigned_to' => $assignedTo, 'status' => 'assigned', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $_SESSION['admin_id'],
                'update_type' => 'assignment',
                'message' => 'Authority assigned',
                'old_status' => 'open',
                'new_status' => 'assigned'
            ]);
        }
        if ($action === 'assign_vendor') {
            $vendorId = intval($_POST['vendor_id'] ?? 0);
            $db->update('utility_tickets', ['vendor_id' => $vendorId, 'status' => 'in_progress', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $_SESSION['admin_id'],
                'update_type' => 'assignment',
                'message' => 'Vendor assigned',
                'old_status' => 'assigned',
                'new_status' => 'in_progress'
            ]);
        }
        if ($action === 'resolve') {
            $notes = sanitize($_POST['resolution_notes'] ?? '');
            $db->update('utility_tickets', [
                'status' => 'resolved',
                'resolved_at' => date('Y-m-d H:i:s'),
                'resolved_by' => $_SESSION['admin_id'],
                'resolution_notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $_SESSION['admin_id'],
                'update_type' => 'resolution',
                'message' => $notes,
                'old_status' => 'in_progress',
                'new_status' => 'resolved'
            ]);
        }
        if ($action === 'escalate') {
            $db->update('utility_tickets', ['status' => 'escalated', 'escalation_count' => 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $_SESSION['admin_id'],
                'update_type' => 'escalation',
                'message' => 'Escalated by admin',
                'old_status' => 'in_progress',
                'new_status' => 'escalated'
            ]);
        }
    }
    header('Location: ' . APP_URL . '/admin/?page=utilities');
    exit;
}

$filter = '';
$params = [];
if (isset($_GET['status'])) {
    $filter = "WHERE ut.status = ?";
    $params[] = sanitize($_GET['status']);
}
$tickets = $db->fetchAll("SELECT ut.*, u.name as user_name, a.name as assigned_to_name, v.name as vendor_name FROM utility_tickets ut LEFT JOIN users u ON ut.user_id = u.id LEFT JOIN users a ON ut.assigned_to = a.id LEFT JOIN users v ON ut.vendor_id = v.id {$filter} ORDER BY ut.id DESC LIMIT 100", $params);
$authorities = $db->fetchAll("SELECT id, name, user_type FROM users WHERE user_type IN ('superadmin','admin','jiada_officer','regional_officer') ORDER BY name ASC");
$vendors = $db->fetchAll("SELECT id, name FROM users WHERE user_type = 'vendor' ORDER BY name ASC");

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-tools"></i> Utilities Management</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>ID</th><th>Ticket#</th><th>User</th><th>Service</th><th>Priority</th><th>Subject</th><th>Status</th><th>Assigned</th><th>Vendor</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><?php echo $t['id']; ?></td>
                        <td><?php echo e($t['ticket_number'] ?? $t['ticket_no'] ?? '-'); ?></td>
                        <td><?php echo e($t['user_name'] ?? '-'); ?></td>
                        <td><?php echo e($t['utility_type'] ?? $t['service_type'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo $t['priority'] === 'critical' ? 'danger' : ($t['priority'] === 'high' ? 'warning' : 'info'); ?>"><?php echo e($t['priority']); ?></span></td>
                        <td><?php echo e(substr($t['subject'], 0, 25)) . '...'; ?></td>
                        <td><span class="badge badge-<?php echo $t['status'] === 'resolved' ? 'success' : ($t['status'] === 'open' ? 'warning' : 'info'); ?>"><?php echo e($t['status']); ?></span></td>
                        <td><?php echo e($t['assigned_to_name'] ?? '-'); ?></td>
                        <td><?php echo e($t['vendor_name'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td>
                        <td style="min-width:260px;">
                            <form method="post" style="display:flex; flex-direction:column; gap:6px;">
                                <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                <select name="action" class="form-control form-control-sm">
                                    <option value="">Select action</option>
                                    <option value="assign_authority">Assign Authority</option>
                                    <option value="assign_vendor">Assign Vendor</option>
                                    <option value="resolve">Resolve</option>
                                    <option value="escalate">Escalate</option>
                                </select>
                                <select name="assigned_to" class="form-control form-control-sm">
                                    <option value="0">Authority (optional)</option>
                                    <?php foreach ($authorities as $a): ?>
                                        <option value="<?php echo $a['id']; ?>"><?php echo e($a['name'] . ' - ' . ucfirst(str_replace('_', ' ', $a['user_type']))); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="vendor_id" class="form-control form-control-sm">
                                    <option value="0">Vendor (optional)</option>
                                    <?php foreach ($vendors as $v): ?>
                                        <option value="<?php echo $v['id']; ?>"><?php echo e($v['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="resolution_notes" class="form-control form-control-sm" placeholder="Resolution notes">
                                <button type="submit" class="btn btn-sm btn-primary">Submit</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tickets)): ?><tr><td colspan="11" class="text-center">No tickets found</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
