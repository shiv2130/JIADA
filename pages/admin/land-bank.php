<?php
$pageTitle = "Land Bank Management - Admin";
$currentPage = 'land-bank';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_notification') {
            $title = sanitize($_POST['notification_title'] ?? '');
            $publishDate = $_POST['publish_date'] ?? date('Y-m-d');
            $startDate = $_POST['application_start_date'] ?? date('Y-m-d');
            $endDate = $_POST['application_end_date'] ?? date('Y-m-d');
            $plotIds = $_POST['plot_ids'] ?? [];
            if (!$title || !$publishDate || !$startDate || !$endDate || empty($plotIds)) {
                throw new Exception('Title, dates and at least one plot are required');
            }
            $notifNo = generateReferenceNumber('NOTIF');
            $notificationId = $db->insert('la_notifications', [
                'notification_no' => $notifNo,
                'title' => $title,
                'publish_date' => $publishDate,
                'application_start_date' => $startDate,
                'application_end_date' => $endDate,
                'status' => 'draft',
                'created_by' => intval($_SESSION['admin_id']),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            foreach ($plotIds as $plotId) {
                $plotId = intval($plotId);
                if (!$plotId) continue;
                $plot = $db->fetchOne("SELECT base_price_per_sqm, area_sqm FROM land_banks WHERE id = ?", [$plotId]);
                $floorPrice = floatval($plot['base_price_per_sqm'] ?? 0) * floatval($plot['area_sqm'] ?? 0);
                $db->insert('la_notification_plots', [
                    'notification_id' => $notificationId,
                    'plot_id' => $plotId,
                    'floor_price' => $floorPrice,
                    'is_withdrawn' => 0
                ]);
            }
            $_SESSION['flash_success'] = 'Notification created as draft';
        }
        if ($action === 'publish_notification') {
            $id = intval($_POST['notification_id'] ?? 0);
            $db->update('la_notifications', ['status' => 'published'], 'id = ?', [$id]);
            $_SESSION['flash_success'] = 'Notification published';
        }
        if ($action === 'close_notification') {
            $id = intval($_POST['notification_id'] ?? 0);
            $db->update('la_notifications', ['status' => 'closed'], 'id = ?', [$id]);
            $_SESSION['flash_success'] = 'Notification closed';
        }
        if ($action === 'add_corrigendum') {
            $notificationId = intval($_POST['notification_id'] ?? 0);
            $description = sanitize($_POST['description'] ?? '');
            $corrDate = $_POST['corrigendum_date'] ?? date('Y-m-d');
            if (!$notificationId || !$description) throw new Exception('Notification and description required');
            $corrNo = generateReferenceNumber('CORR');
            $db->insert('la_notification_corrigenda', [
                'notification_id' => $notificationId,
                'corrigendum_no' => $corrNo,
                'corrigendum_date' => $corrDate,
                'description' => $description,
                'created_by' => intval($_SESSION['admin_id'])
            ]);
            $_SESSION['flash_success'] = 'Corrigendum added';
        }
        if ($action === 'withdraw_plot') {
            $notificationPlotId = intval($_POST['notification_plot_id'] ?? 0);
            $reason = sanitize($_POST['withdraw_reason'] ?? '');
            if (!$notificationPlotId || !$reason) throw new Exception('Plot and reason required');
            $db->update('la_notification_plots', ['is_withdrawn' => 1, 'withdrawn_reason' => $reason], 'id = ?', [$notificationPlotId]);
            $_SESSION['flash_success'] = 'Plot withdrawn from notification';
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: ' . APP_URL . '/admin/?page=land-bank');
    exit;
}

$plots = $db->fetchAll("SELECT lb.*, r.name as region_name FROM land_banks lb LEFT JOIN regions r ON lb.region_id = r.id ORDER BY lb.id DESC LIMIT 100");
$notifications = $db->fetchAll("SELECT n.*, (SELECT COUNT(*) FROM la_notification_plots np WHERE np.notification_id = n.id) AS total_plots FROM la_notifications n ORDER BY n.id DESC LIMIT 50");
$notificationPlots = $db->fetchAll("SELECT np.*, n.notification_no, lb.plot_number FROM la_notification_plots np INNER JOIN la_notifications n ON n.id = np.notification_id INNER JOIN land_banks lb ON lb.id = np.plot_id ORDER BY np.id DESC LIMIT 100");
$corrigenda = $db->fetchAll("SELECT c.*, n.notification_no FROM la_notification_corrigenda c INNER JOIN la_notifications n ON n.id = c.notification_id ORDER BY c.id DESC LIMIT 50");

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-map-marked-alt"></i> Land Bank Management</h3>
        <button class="btn btn-primary btn-sm" onclick="alert('Add plot form')"><i class="fas fa-plus"></i> Add Plot</button>
    </div>
    <div class="card-body">
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><strong>Create Plot Notification</strong></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="create_notification">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Notification Title</label>
                            <input type="text" name="notification_title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Publish Date</label>
                            <input type="date" name="publish_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Application Start</label>
                            <input type="date" name="application_start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Application End</label>
                            <input type="date" name="application_end_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Select Available Plots</label>
                        <select name="plot_ids[]" class="form-control" multiple size="6" required>
                            <?php foreach ($plots as $p): if (($p['status'] ?? '') !== 'available') continue; ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo e(($p['plot_number'] ?? '-') . ' | ' . number_format(floatval($p['area_sqm'] ?? 0), 2) . ' sqm'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Create Draft Notification</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><strong>Notifications</strong></div>
            <div class="card-body table-responsive">
                <table class="table">
                    <thead><tr><th>ID</th><th>No</th><th>Title</th><th>Publish</th><th>Window</th><th>Status</th><th>Plots</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($notifications as $n): ?>
                        <tr>
                            <td><?php echo $n['id']; ?></td>
                            <td><?php echo e($n['notification_no']); ?></td>
                            <td><?php echo e($n['title']); ?></td>
                            <td><?php echo e($n['publish_date']); ?></td>
                            <td><?php echo e($n['application_start_date'] . ' to ' . $n['application_end_date']); ?></td>
                            <td><span class="badge badge-<?php echo $n['status'] === 'published' ? 'success' : ($n['status'] === 'closed' ? 'secondary' : 'warning'); ?>"><?php echo e($n['status']); ?></span></td>
                            <td><?php echo intval($n['total_plots']); ?></td>
                            <td>
                                <form method="post" style="display:inline-flex; gap:6px;">
                                    <input type="hidden" name="notification_id" value="<?php echo $n['id']; ?>">
                                    <?php if (($n['status'] ?? '') !== 'published'): ?>
                                        <button class="btn btn-sm btn-success" name="action" value="publish_notification" type="submit">Publish</button>
                                    <?php endif; ?>
                                    <?php if (($n['status'] ?? '') === 'published'): ?>
                                        <button class="btn btn-sm btn-warning" name="action" value="close_notification" type="submit">Close</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($notifications)): ?><tr><td colspan="8" class="text-center">No notifications yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-row" style="margin-bottom:16px;">
            <div class="card">
                <div class="card-header"><strong>Add Corrigendum</strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_corrigendum">
                        <div class="form-group">
                            <label>Notification</label>
                            <select class="form-control" name="notification_id" required>
                                <option value="">Select</option>
                                <?php foreach ($notifications as $n): ?>
                                    <option value="<?php echo $n['id']; ?>"><?php echo e($n['notification_no'] . ' - ' . $n['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Corrigendum Date</label>
                            <input type="date" name="corrigendum_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit">Add Corrigendum</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><strong>Withdraw Plot From Notification</strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="withdraw_plot">
                        <div class="form-group">
                            <label>Notification Plot Entry</label>
                            <select class="form-control" name="notification_plot_id" required>
                                <option value="">Select</option>
                                <?php foreach ($notificationPlots as $np): ?>
                                    <option value="<?php echo $np['id']; ?>">
                                        <?php echo e($np['notification_no'] . ' | ' . $np['plot_number'] . ' | withdrawn=' . intval($np['is_withdrawn'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Speaking Order / Reason</label>
                            <textarea name="withdraw_reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <button class="btn btn-danger" type="submit">Withdraw Plot</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><strong>Corrigendum Register</strong></div>
            <div class="card-body table-responsive">
                <table class="table">
                    <thead><tr><th>ID</th><th>Corrigendum No</th><th>Notification</th><th>Date</th><th>Description</th></tr></thead>
                    <tbody>
                    <?php foreach ($corrigenda as $c): ?>
                        <tr>
                            <td><?php echo $c['id']; ?></td>
                            <td><?php echo e($c['corrigendum_no']); ?></td>
                            <td><?php echo e($c['notification_no']); ?></td>
                            <td><?php echo e($c['corrigendum_date']); ?></td>
                            <td><?php echo e($c['description']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($corrigenda)): ?><tr><td colspan="5" class="text-center">No corrigenda yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <h4 style="margin-bottom:8px;">Plot Inventory</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Plot#</th><th>Type</th><th>Area (sqm)</th><th>Zone</th><th>Region</th><th>Status</th><th>Price/sqm</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($plots as $p): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td><?php echo e($p['plot_number']); ?></td>
                        <td><?php echo e($p['plot_type']); ?></td>
                        <td><?php echo number_format($p['area_sqm'], 2); ?></td>
                        <td><?php echo e($p['zone']); ?></td>
                        <td><?php echo e($p['region_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo $p['status'] === 'available' ? 'success' : ($p['status'] === 'allotted' ? 'info' : 'warning'); ?>"><?php echo e($p['status']); ?></span></td>
                        <td>₹<?php echo number_format($p['base_price_per_sqm'], 2); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="alert('Edit plot <?php echo $p['id']; ?>')">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="alert('Delete plot <?php echo $p['id']; ?>')">Delete</button>
                        </td>
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
