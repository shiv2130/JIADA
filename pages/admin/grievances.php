<?php
$pageTitle = "Grievances Management - Admin";
$currentPage = 'grievances';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/admin/?page=login');
    exit;
}

$db = getDB();

$responsesTable = $db->fetchOne(
    "SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'grievance_responses' LIMIT 1"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $grievanceId = intval($_POST['grievance_id'] ?? 0);
    $note = trim((string) ($_POST['note'] ?? ''));

    if (!$grievanceId || !$action) {
        $_SESSION['flash_error'] = 'Invalid grievance action.';
        header('Location: ' . APP_URL . '/admin/?page=grievances');
        exit;
    }

    $grievance = $db->fetchOne("SELECT * FROM grievances WHERE id = ?", [$grievanceId]);
    if (!$grievance) {
        $_SESSION['flash_error'] = 'Grievance not found.';
        header('Location: ' . APP_URL . '/admin/?page=grievances');
        exit;
    }

    try {
        $db->beginTransaction();

        if ($note !== '' && $responsesTable) {
            $db->insert('grievance_responses', [
                'grievance_id' => $grievanceId,
                'user_id' => $_SESSION['admin_id'],
                'response' => sanitize($note),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        if ($action === 'send_reminder') {
            $recipientId = $grievance['secondment_officer_id'] ?? $grievance['user_id'];
            if ($recipientId) {
                $db->insert('notifications', [
                    'user_id' => $recipientId,
                    'type' => 'in_app',
                    'subject' => 'Grievance Reminder',
                    'message' => 'Reminder: grievance ' . ($grievance['grievance_number'] ?? $grievance['grievance_no'] ?? '') . ' is pending action.',
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            $db->commit();
            $_SESSION['flash_success'] = 'Reminder sent successfully.';
        } else {
            $update = ['updated_at' => date('Y-m-d H:i:s')];
            switch ($action) {
                case 'in_progress':
                    $update['status'] = 'under_review';
                    break;
                case 'escalate':
                    $update['status'] = 'escalated';
                    if (array_key_exists('escalated', $grievance)) {
                        $update['escalated'] = 1;
                    }
                    break;
                case 'resolve':
                    $update['status'] = 'resolved';
                    if (array_key_exists('resolved_at', $grievance)) {
                        $update['resolved_at'] = date('Y-m-d H:i:s');
                    }
                    break;
                case 'close':
                    $update['status'] = 'closed';
                    if (array_key_exists('closed_at', $grievance)) {
                        $update['closed_at'] = date('Y-m-d H:i:s');
                    }
                    break;
                default:
                    throw new Exception('Unsupported grievance action.');
            }

            $db->update('grievances', $update, 'id = ?', [$grievanceId]);
            $db->commit();
            $_SESSION['flash_success'] = 'Grievance updated successfully.';
        }
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['flash_error'] = 'Unable to update grievance: ' . $e->getMessage();
    }

    header('Location: ' . APP_URL . '/admin/?page=grievances&view=' . $grievanceId);
    exit;
}

$selectedId = intval($_GET['view'] ?? 0);

$grevs = $db->fetchAll(
    "SELECT g.*, u.name AS user_name, d.name AS dept_name
     FROM grievances g
     LEFT JOIN users u ON g.user_id = u.id
     LEFT JOIN departments d ON g.department_id = d.id
     ORDER BY g.id DESC
     LIMIT 100"
);

$selectedGrievance = null;
$selectedResponses = [];
if ($selectedId) {
    foreach ($grevs as $grievance) {
        if ((int) $grievance['id'] === $selectedId) {
            $selectedGrievance = $grievance;
            break;
        }
    }

    if ($selectedGrievance && $responsesTable) {
        $selectedResponses = $db->fetchAll(
            "SELECT gr.*, u.name AS responder_name
             FROM grievance_responses gr
             LEFT JOIN users u ON gr.user_id = u.id
             WHERE gr.grievance_id = ?
             ORDER BY gr.created_at DESC",
            [$selectedId]
        );
    }
}

function grievanceStatusBadge($status) {
    $status = (string) $status;
    if (in_array($status, ['resolved', 'closed'], true)) {
        return 'success';
    }
    if (in_array($status, ['submitted', 'assigned'], true)) {
        return 'info';
    }
    if (in_array($status, ['escalated'], true)) {
        return 'danger';
    }
    return 'warning';
}

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-headset"></i> Grievances Management</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?php echo e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger"><?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>

        <?php if ($selectedGrievance): ?>
            <?php
            $attachments = json_decode((string) ($selectedGrievance['attachments'] ?? '[]'), true);
            if (!is_array($attachments)) {
                $attachments = [];
            }
            $departmentName = $selectedGrievance['dept_name'] ?: ($selectedGrievance['department'] ?? '-');
            ?>
            <div class="card" id="grievance-action" style="margin-bottom: 20px;">
                <div class="card-header">
                    <h4 style="margin: 0;">Grievance Details - <?php echo e($selectedGrievance['grievance_number'] ?? $selectedGrievance['grievance_no'] ?? ('ID-' . $selectedGrievance['id'])); ?></h4>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:20px;">
                        <div><strong>User</strong><div><?php echo e($selectedGrievance['user_name'] ?? '-'); ?></div></div>
                        <div><strong>Category</strong><div><?php echo e($selectedGrievance['category'] ?? '-'); ?></div></div>
                        <div><strong>Department</strong><div><?php echo e($departmentName ?: '-'); ?></div></div>
                        <div><strong>Status</strong><div><span class="badge badge-<?php echo grievanceStatusBadge($selectedGrievance['status'] ?? ''); ?>"><?php echo e($selectedGrievance['status'] ?? '-'); ?></span></div></div>
                        <div><strong>Created</strong><div><?php echo !empty($selectedGrievance['created_at']) ? date('d/m/Y H:i', strtotime($selectedGrievance['created_at'])) : '-'; ?></div></div>
                        <div><strong>SLA Deadline</strong><div><?php echo !empty($selectedGrievance['sla_deadline']) ? date('d/m/Y H:i', strtotime($selectedGrievance['sla_deadline'])) : '-'; ?></div></div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <strong>Subject</strong>
                        <div><?php echo e($selectedGrievance['subject'] ?? '-'); ?></div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <strong>Description</strong>
                        <div style="white-space:pre-wrap;"><?php echo e($selectedGrievance['description'] ?? '-'); ?></div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <strong>Attachments</strong>
                        <?php if (empty($attachments)): ?>
                            <div style="color:var(--gray);">No attachments uploaded.</div>
                        <?php else: ?>
                            <ul style="margin:8px 0 0 18px;">
                                <?php foreach ($attachments as $file): ?>
                                    <li>
                                        <a href="<?php echo APP_URL . '/uploads/grievances/' . rawurlencode($file); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo e($file); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <form method="POST" style="border-top:1px solid #e5e7eb;padding-top:20px;">
                        <input type="hidden" name="grievance_id" value="<?php echo (int) $selectedGrievance['id']; ?>">
                        <div style="display:grid;grid-template-columns:minmax(220px,280px) 1fr auto;gap:12px;align-items:start;">
                            <select name="action" class="form-control">
                                <option value="in_progress">Mark In Progress</option>
                                <option value="escalate">Escalate to Upper Level</option>
                                <option value="resolve">Resolve</option>
                                <option value="close">Close</option>
                                <option value="send_reminder">Send Reminder</option>
                            </select>
                            <textarea name="note" class="form-control" rows="3" placeholder="Add response / forwarding note"></textarea>
                            <button class="btn btn-primary" type="submit">Update Grievance</button>
                        </div>
                    </form>

                    <div style="margin-top:24px;">
                        <strong>Response History</strong>
                        <?php if (empty($selectedResponses)): ?>
                            <div style="color:var(--gray);margin-top:8px;">No responses added yet.</div>
                        <?php else: ?>
                            <div style="margin-top:10px;display:flex;flex-direction:column;gap:12px;">
                                <?php foreach ($selectedResponses as $response): ?>
                                    <div style="padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc;">
                                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                            <strong><?php echo e($response['responder_name'] ?? 'Officer'); ?></strong>
                                            <span style="color:var(--gray);font-size:0.9rem;"><?php echo !empty($response['created_at']) ? date('d/m/Y H:i', strtotime($response['created_at'])) : '-'; ?></span>
                                        </div>
                                        <div style="margin-top:8px;white-space:pre-wrap;"><?php echo e($response['response'] ?? ''); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Grev#</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Department</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grevs as $g): ?>
                        <?php
                        $grievanceNumber = $g['grievance_number'] ?? $g['grievance_no'] ?? ('ID-' . $g['id']);
                        $departmentName = $g['dept_name'] ?: ($g['department'] ?? '-');
                        $subject = (string) ($g['subject'] ?? '');
                        $subjectPreview = mb_strlen($subject) > 35 ? mb_substr($subject, 0, 35) . '...' : $subject;
                        ?>
                        <tr>
                            <td><?php echo (int) $g['id']; ?></td>
                            <td><?php echo e($grievanceNumber); ?></td>
                            <td><?php echo e($g['user_name'] ?? '-'); ?></td>
                            <td><?php echo e($g['category'] ?? '-'); ?></td>
                            <td><?php echo e($departmentName ?: '-'); ?></td>
                            <td><?php echo e($subjectPreview ?: '-'); ?></td>
                            <td><span class="badge badge-<?php echo grievanceStatusBadge($g['status'] ?? ''); ?>"><?php echo e($g['status'] ?? '-'); ?></span></td>
                            <td><?php echo !empty($g['created_at']) ? date('d/m/Y', strtotime($g['created_at'])) : '-'; ?></td>
                            <td style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a class="btn btn-sm btn-primary" href="<?php echo APP_URL; ?>/admin/?page=grievances&view=<?php echo (int) $g['id']; ?>#grievance-action">Respond</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($grevs)): ?>
                        <tr><td colspan="9" class="text-center">No grievances found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
