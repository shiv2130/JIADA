<?php
$pageTitle = "Admin Dashboard";
$currentPage = 'dashboard';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$userName = $_SESSION['user_name'] ?? 'Admin';
$userType = strtolower(trim((string)($_SESSION['user_type'] ?? 'admin')));
$regionId = !empty($_SESSION['region_id']) ? (int) $_SESSION['region_id'] : 0;
$regionWhere = $regionId ? " WHERE region_id = $regionId" : '';
$regionAnd = $regionId ? " AND region_id = $regionId" : '';
$isCounterClerk = in_array($userType, ['counter_clerk', 'cc'], true);
$isIeo = in_array($userType, ['ieo', 'industrial_extension_officer'], true);
$isRd = in_array($userType, ['rd', 'regional_director', 'authority'], true);
$userRole = $userType;
$userRegion = $regionId ? 'Region ' . $regionId : 'All Regions';

// Dashboard stats
$stats = [
    'total_users' => $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE user_type IN ('applicant','allottee','vendor')$regionAnd")['c'] ?? 0,
    'total_plots' => $db->fetchOne("SELECT COUNT(*) as c FROM land_banks$regionWhere")['c'] ?? 0,
    'available_plots' => $db->fetchOne("SELECT COUNT(*) as c FROM land_banks WHERE status = 'available'$regionAnd")['c'] ?? 0,
    'total_applications' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications$regionWhere")['c'] ?? 0,
    'pending_applications' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications WHERE status IN ('pending','submitted','under_scrutiny')$regionAnd")['c'] ?? 0,
    'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE status IN ('paid','success')$regionAnd")['t'] ?? 0,
    'open_grievances' => $db->fetchOne("SELECT COUNT(*) as c FROM grievances WHERE status NOT IN ('resolved','closed')$regionAnd")['c'] ?? 0,
    'open_tickets' => $db->fetchOne("SELECT COUNT(*) as c FROM utility_tickets WHERE status NOT IN ('closed','resolved')$regionAnd")['c'] ?? 0,
    'court_cases' => $db->fetchOne("SELECT COUNT(*) as c FROM court_cases WHERE case_status IN ('pending','in_progress')")['c'] ?? 0,
];

// KPI data
$appByStatus = $db->fetchAll("SELECT status, COUNT(*) as c FROM land_applications$regionWhere GROUP BY status");
$grievByStatus = $db->fetchAll("SELECT status, COUNT(*) as c FROM grievances$regionWhere GROUP BY status");
$utilByStatus = $db->fetchAll("SELECT status, COUNT(*) as c FROM utility_tickets$regionWhere GROUP BY status");
$revByMonth = $db->fetchAll("SELECT DATE_FORMAT(created_at,'%Y-%m') as m, SUM(amount) as t FROM payments WHERE status IN ('paid','success')$regionAnd AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY m ORDER BY m ASC");

// Recent data
$recentApps = $db->fetchAll("SELECT la.*, u.name as user_name, lb.plot_number FROM land_applications la LEFT JOIN users u ON la.user_id = u.id LEFT JOIN land_banks lb ON la.plot_id = lb.id ORDER BY la.id DESC LIMIT 10");
$recentGrievances = $db->fetchAll("SELECT g.*, u.name as user_name FROM grievances g LEFT JOIN users u ON g.user_id = u.id ORDER BY g.id DESC LIMIT 5");
$recentPayments = $db->fetchAll("SELECT p.*, u.name as user_name FROM payments p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC LIMIT 10");

if ($isCounterClerk) {
    $ccStats = [
        'new_submissions' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications WHERE status = 'submitted'$regionAnd")['c'] ?? 0,
        'assigned_to_me' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications WHERE assigned_to = ? AND status IN ('submitted','under_scrutiny')", [$_SESSION['admin_id']])['c'] ?? 0,
        'open_objections' => $db->fetchOne("SELECT COUNT(*) as c FROM la_objections WHERE raised_by = ? AND status = 'open'", [$_SESSION['admin_id']])['c'] ?? 0,
        'ready_to_forward' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications WHERE assigned_to = ? AND status = 'under_scrutiny'", [$_SESSION['admin_id']])['c'] ?? 0,
        'overdue' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications WHERE status IN ('submitted','under_scrutiny')$regionAnd AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)")['c'] ?? 0,
    ];

    $ccQueue = $db->fetchAll(
        "SELECT la.id, la.application_number, la.status, la.created_at, la.scrutiny_remarks, u.name as user_name, lb.plot_number
         FROM land_applications la
         LEFT JOIN users u ON la.user_id = u.id
         LEFT JOIN land_banks lb ON la.plot_id = lb.id
         WHERE (
             la.assigned_to = ?
             OR (
                 COALESCE(la.region_id, lb.region_id) = ?
                 AND (la.assigned_to IS NULL OR la.status = 'submitted')
             )
         )
         ORDER BY CASE WHEN la.status = 'submitted' THEN 0 ELSE 1 END, la.created_at ASC
         LIMIT 12",
        [$_SESSION['admin_id'], $regionId]
    );

    $ccObjections = $db->fetchAll(
        "SELECT o.*, la.application_number, u.name as user_name
         FROM la_objections o
         INNER JOIN land_applications la ON la.id = o.application_id
         LEFT JOIN users u ON u.id = la.user_id
         WHERE o.raised_by = ?
         ORDER BY o.created_at DESC
         LIMIT 8",
        [$_SESSION['admin_id']]
    );
} elseif ($isIeo) {
    $ieoStats = [
        'forwarded_from_cc' => $db->fetchOne(
            "SELECT COUNT(*) as c
             FROM land_applications
             WHERE status = 'under_scrutiny'$regionAnd
             AND (assigned_to = ? OR scrutiny_remarks LIKE ? OR scrutiny_remarks LIKE ?)",
            [$_SESSION['admin_id'], '%Forwarded to IEO%', '%IEO%']
        )['c'] ?? 0,
        'assigned_to_me' => $db->fetchOne(
            "SELECT COUNT(*) as c
             FROM land_applications
             WHERE assigned_to = ? AND status = 'under_scrutiny'",
            [$_SESSION['admin_id']]
        )['c'] ?? 0,
        'open_objections' => $db->fetchOne(
            "SELECT COUNT(*) as c FROM la_objections WHERE raised_by = ? AND status = 'open'",
            [$_SESSION['admin_id']]
        )['c'] ?? 0,
        'ready_to_forward' => $db->fetchOne(
            "SELECT COUNT(*) as c
             FROM land_applications
             WHERE status = 'under_scrutiny'$regionAnd
             AND (assigned_to = ? OR scrutiny_remarks LIKE ? OR scrutiny_remarks LIKE ?)",
            [$_SESSION['admin_id'], '%Forwarded to IEO%', '%IEO%']
        )['c'] ?? 0,
        'overdue' => $db->fetchOne(
            "SELECT COUNT(*) as c
             FROM land_applications
             WHERE status = 'under_scrutiny'$regionAnd
             AND (assigned_to = ? OR scrutiny_remarks LIKE ? OR scrutiny_remarks LIKE ?)
             AND created_at < DATE_SUB(NOW(), INTERVAL 5 DAY)",
            [$_SESSION['admin_id'], '%Forwarded to IEO%', '%IEO%']
        )['c'] ?? 0,
    ];

    $ieoQueue = $db->fetchAll(
        "SELECT la.id, la.application_number, la.status, la.created_at, la.scrutiny_remarks, u.name as user_name, lb.plot_number
         FROM land_applications la
         LEFT JOIN users u ON la.user_id = u.id
         LEFT JOIN land_banks lb ON la.plot_id = lb.id
         WHERE (
                la.assigned_to = ?
                OR (
                    la.status = 'under_scrutiny'
                    AND COALESCE(la.region_id, lb.region_id) = ?
                    AND (
                        la.scrutiny_remarks LIKE ?
                        OR la.scrutiny_remarks LIKE ?
                    )
                )
           )
         ORDER BY CASE WHEN la.assigned_to = ? THEN 0 ELSE 1 END, la.created_at ASC
         LIMIT 12",
        [$_SESSION['admin_id'], $regionId, '%Forwarded to IEO%', '%IEO%', $_SESSION['admin_id']]
    );

    $ieoObjections = $db->fetchAll(
        "SELECT o.*, la.application_number, u.name as user_name
         FROM la_objections o
         INNER JOIN land_applications la ON la.id = o.application_id
         LEFT JOIN users u ON u.id = la.user_id
         WHERE o.raised_by = ?
         ORDER BY o.created_at DESC
         LIMIT 8",
        [$_SESSION['admin_id']]
    );
} elseif ($isRd) {
    $rdStats = [
        'forwarded_from_do' => $db->fetchOne(
            "SELECT COUNT(*) as c
             FROM land_applications
             WHERE status = 'under_scrutiny'$regionAnd
             AND (assigned_to = ? OR scrutiny_remarks LIKE ? OR scrutiny_remarks LIKE ?)",
            [$_SESSION['admin_id'], '%Forwarded to RD%', '%RD%']
        )['c'] ?? 0,
        'assigned_to_me' => $db->fetchOne(
            "SELECT COUNT(*) as c
             FROM land_applications
             WHERE assigned_to = ? AND status = 'under_scrutiny'",
            [$_SESSION['admin_id']]
        )['c'] ?? 0,
        'approvals_today' => $db->fetchOne(
            "SELECT COUNT(*) as c
             FROM land_applications
             WHERE approved_by = ? AND DATE(approved_at) = CURDATE()",
            [$_SESSION['admin_id']]
        )['c'] ?? 0,
        'ready_for_decision' => $db->fetchOne(
            "SELECT COUNT(*) as c
             FROM land_applications
             WHERE status = 'under_scrutiny'$regionAnd
             AND (assigned_to = ? OR scrutiny_remarks LIKE ? OR scrutiny_remarks LIKE ?)",
            [$_SESSION['admin_id'], '%Forwarded to RD%', '%RD%']
        )['c'] ?? 0,
        'overdue' => $db->fetchOne(
            "SELECT COUNT(*) as c
             FROM land_applications
             WHERE status = 'under_scrutiny'$regionAnd
             AND (assigned_to = ? OR scrutiny_remarks LIKE ? OR scrutiny_remarks LIKE ?)
             AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$_SESSION['admin_id'], '%Forwarded to RD%', '%RD%']
        )['c'] ?? 0,
    ];

    $rdQueue = $db->fetchAll(
        "SELECT la.id, la.application_number, la.status, la.created_at, la.scrutiny_remarks, la.approved_at, u.name as user_name, lb.plot_number
         FROM land_applications la
         LEFT JOIN users u ON la.user_id = u.id
         LEFT JOIN land_banks lb ON la.plot_id = lb.id
         WHERE (
                la.assigned_to = ?
                OR (
                    la.status = 'under_scrutiny'
                    AND COALESCE(la.region_id, lb.region_id) = ?
                    AND (
                        la.scrutiny_remarks LIKE ?
                        OR la.scrutiny_remarks LIKE ?
                    )
                )
           )
         ORDER BY CASE WHEN la.assigned_to = ? THEN 0 ELSE 1 END, la.created_at ASC
         LIMIT 12",
        [$_SESSION['admin_id'], $regionId, '%Forwarded to RD%', '%RD%', $_SESSION['admin_id']]
    );

    $rdRecentDecisions = $db->fetchAll(
        "SELECT la.application_number, la.status, la.approved_at, u.name as user_name
         FROM land_applications la
         LEFT JOIN users u ON la.user_id = u.id
         WHERE la.approved_by = ?
         ORDER BY la.approved_at DESC
         LIMIT 8",
        [$_SESSION['admin_id']]
    );
}

ob_start();
?>
<?php if ($isCounterClerk): ?>
<div style="margin-bottom: 20px;">
    <h2><i class="fas fa-clipboard-check"></i> Counter Clerk Desk</h2>
    <p style="color: var(--gray);">Role: Counter Clerk | <?php echo $regionId ? 'Region: ' . $regionId : 'All Regions'; ?> | Initial scrutiny, deficiency handling, and forwarding to IEO</p>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 18px; background: #eef6ff;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">New Submissions</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--info);"><?php echo (int) $ccStats['new_submissions']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #fff8e8;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Assigned to Me</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--warning);"><?php echo (int) $ccStats['assigned_to_me']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #fff1f0;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Open Objections</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--danger);"><?php echo (int) $ccStats['open_objections']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #eefaf2;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Ready to Forward</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--success);"><?php echo (int) $ccStats['ready_to_forward']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #fdf2e9;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Overdue Cases</div>
        <div style="font-size: 28px; font-weight: 700; color: #d35400;"><?php echo (int) $ccStats['overdue']; ?></div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-inbox"></i> Applications Requiring Action</h3>
            <a href="<?php echo APP_URL; ?>/admin/?page=applications" class="btn btn-sm btn-outline">Open Queue</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($ccQueue)): ?>
            <table class="table">
                <thead><tr><th>App#</th><th>Applicant</th><th>Plot</th><th>Status</th><th>Age</th></tr></thead>
                <tbody>
                    <?php foreach ($ccQueue as $row): ?>
                    <tr>
                        <td><?php echo e($row['application_number'] ?? '-'); ?></td>
                        <td><?php echo e($row['user_name'] ?? '-'); ?></td>
                        <td><?php echo e($row['plot_number'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo ($row['status'] ?? '') === 'submitted' ? 'warning' : 'info'; ?>"><?php echo e($row['status'] ?? '-'); ?></span></td>
                        <td><?php echo max(0, (int) floor((time() - strtotime($row['created_at'])) / 86400)); ?> days</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 30px; text-align: center; color: var(--gray);">No CC queue items right now</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-comment-dots"></i> Objection Follow-up</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($ccObjections)): ?>
            <table class="table">
                <thead><tr><th>App#</th><th>Applicant</th><th>Status</th><th>Due</th></tr></thead>
                <tbody>
                    <?php foreach ($ccObjections as $row): ?>
                    <tr>
                        <td><?php echo e($row['application_number'] ?? '-'); ?></td>
                        <td><?php echo e($row['user_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo ($row['status'] ?? '') === 'open' ? 'danger' : 'success'; ?>"><?php echo e($row['status'] ?? '-'); ?></span></td>
                        <td><?php echo !empty($row['due_date']) ? date('d/m/Y', strtotime($row['due_date'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 30px; text-align: center; color: var(--gray);">No objections raised by you yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php elseif ($isIeo): ?>
<div style="margin-bottom: 20px;">
    <h2><i class="fas fa-industry"></i> Industrial Extension Officer Desk</h2>
    <p style="color: var(--gray);">Role: Industrial Extension Officer | <?php echo $regionId ? 'Region: ' . $regionId : 'All Regions'; ?> | Technical scrutiny, clarifications, and forwarding to DO/RDD</p>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 18px; background: #eef6ff;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Forwarded from CC</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--info);"><?php echo (int) $ieoStats['forwarded_from_cc']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #fff8e8;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Assigned to Me</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--warning);"><?php echo (int) $ieoStats['assigned_to_me']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #fff1f0;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Open Objections</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--danger);"><?php echo (int) $ieoStats['open_objections']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #eefaf2;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Ready to Forward</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--success);"><?php echo (int) $ieoStats['ready_to_forward']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #fdf2e9;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Overdue Cases</div>
        <div style="font-size: 28px; font-weight: 700; color: #d35400;"><?php echo (int) $ieoStats['overdue']; ?></div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-inbox"></i> Applications Pending IEO Review</h3>
            <a href="<?php echo APP_URL; ?>/admin/?page=applications" class="btn btn-sm btn-outline">Open Queue</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($ieoQueue)): ?>
            <table class="table">
                <thead><tr><th>App#</th><th>Applicant</th><th>Plot</th><th>Status</th><th>Age</th></tr></thead>
                <tbody>
                    <?php foreach ($ieoQueue as $row): ?>
                    <tr>
                        <td><?php echo e($row['application_number'] ?? '-'); ?></td>
                        <td><?php echo e($row['user_name'] ?? '-'); ?></td>
                        <td><?php echo e($row['plot_number'] ?? '-'); ?></td>
                        <td><span class="badge badge-info"><?php echo e($row['status'] ?? '-'); ?></span></td>
                        <td><?php echo max(0, (int) floor((time() - strtotime($row['created_at'])) / 86400)); ?> days</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 30px; text-align: center; color: var(--gray);">No IEO queue items right now</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-comment-dots"></i> Clarification / Objection Follow-up</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($ieoObjections)): ?>
            <table class="table">
                <thead><tr><th>App#</th><th>Applicant</th><th>Status</th><th>Due</th></tr></thead>
                <tbody>
                    <?php foreach ($ieoObjections as $row): ?>
                    <tr>
                        <td><?php echo e($row['application_number'] ?? '-'); ?></td>
                        <td><?php echo e($row['user_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo ($row['status'] ?? '') === 'open' ? 'danger' : 'success'; ?>"><?php echo e($row['status'] ?? '-'); ?></span></td>
                        <td><?php echo !empty($row['due_date']) ? date('d/m/Y', strtotime($row['due_date'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 30px; text-align: center; color: var(--gray);">No objections raised by you yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php elseif ($isRd): ?>
<div style="margin-bottom: 20px;">
    <h2><i class="fas fa-balance-scale"></i> Regional Director Desk</h2>
    <p style="color: var(--gray);">Role: Regional Director | <?php echo $regionId ? 'Region: ' . $regionId : 'All Regions'; ?> | Final regional scrutiny, reversions, approvals, rejections, and bidding decisions</p>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 18px; background: #eef6ff;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Forwarded from DO/RDD</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--info);"><?php echo (int) $rdStats['forwarded_from_do']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #fff8e8;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Assigned to Me</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--warning);"><?php echo (int) $rdStats['assigned_to_me']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #eefaf2;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Ready for Decision</div>
        <div style="font-size: 28px; font-weight: 700; color: var(--success);"><?php echo (int) $rdStats['ready_for_decision']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #f3e5f5;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Approvals Today</div>
        <div style="font-size: 28px; font-weight: 700; color: #7b1fa2;"><?php echo (int) $rdStats['approvals_today']; ?></div>
    </div>
    <div class="card" style="padding: 18px; background: #fdf2e9;">
        <div style="font-size: 13px; color: var(--gray); margin-bottom: 8px;">Overdue Cases</div>
        <div style="font-size: 28px; font-weight: 700; color: #d35400;"><?php echo (int) $rdStats['overdue']; ?></div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-gavel"></i> Applications Pending RD Decision</h3>
            <a href="<?php echo APP_URL; ?>/admin/?page=applications" class="btn btn-sm btn-outline">Open Queue</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($rdQueue)): ?>
            <table class="table">
                <thead><tr><th>App#</th><th>Applicant</th><th>Plot</th><th>Status</th><th>Age</th></tr></thead>
                <tbody>
                    <?php foreach ($rdQueue as $row): ?>
                    <tr>
                        <td><?php echo e($row['application_number'] ?? '-'); ?></td>
                        <td><?php echo e($row['user_name'] ?? '-'); ?></td>
                        <td><?php echo e($row['plot_number'] ?? '-'); ?></td>
                        <td><span class="badge badge-info"><?php echo e($row['status'] ?? '-'); ?></span></td>
                        <td><?php echo max(0, (int) floor((time() - strtotime($row['created_at'])) / 86400)); ?> days</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 30px; text-align: center; color: var(--gray);">No RD queue items right now</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-check-circle"></i> Recent RD Decisions</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($rdRecentDecisions)): ?>
            <table class="table">
                <thead><tr><th>App#</th><th>Applicant</th><th>Status</th><th>Decision Time</th></tr></thead>
                <tbody>
                    <?php foreach ($rdRecentDecisions as $row): ?>
                    <tr>
                        <td><?php echo e($row['application_number'] ?? '-'); ?></td>
                        <td><?php echo e($row['user_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo ($row['status'] ?? '') === 'approved' ? 'success' : 'secondary'; ?>"><?php echo e($row['status'] ?? '-'); ?></span></td>
                        <td><?php echo !empty($row['approved_at']) ? date('d/m/Y H:i', strtotime($row['approved_at'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 30px; text-align: center; color: var(--gray);">No RD decisions recorded yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div style="margin-bottom: 20px;">
    <h2><i class="fas fa-tachometer-alt"></i> Welcome, <?php echo e($userName); ?>!</h2>
    <p style="color: var(--gray);">Role: <?php echo ucfirst($userType); ?> | <?php echo $regionId ? 'Region: ' . $regionId : 'All Regions'; ?></p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 20px; text-align: center; background: #e3f2fd;">
        <i class="fas fa-users fa-2x" style="color: #1976d2;"></i>
        <h3 style="margin: 10px 0;"><?php echo number_format($stats['total_users']); ?></h3>
        <p style="color: var(--gray);">Registered Users</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #e8f5e9;">
        <i class="fas fa-map fa-2x" style="color: #388e3c;"></i>
        <h3 style="margin: 10px 0;"><?php echo number_format($stats['total_plots']); ?></h3>
        <p style="color: var(--gray);">Total Plots</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #fff3e0;">
        <i class="fas fa-clock fa-2x" style="color: #f57c00;"></i>
        <h3 style="margin: 10px 0;"><?php echo number_format($stats['pending_applications']); ?></h3>
        <p style="color: var(--gray);">Pending Applications</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #f3e5f5;">
        <i class="fas fa-indian-rupee-sign fa-2x" style="color: #7b1fa2;"></i>
        <h3 style="margin: 10px 0;">₹<?php echo number_format($stats['total_revenue'], 2); ?></h3>
        <p style="color: var(--gray);">Revenue Collected</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #fce4ec;">
        <i class="fas fa-exclamation-circle fa-2x" style="color: #c62828;"></i>
        <h3 style="margin: 10px 0;"><?php echo $stats['open_grievances']; ?></h3>
        <p style="color: var(--gray);">Open Grievances</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #e0f7fa;">
        <i class="fas fa-tools fa-2x" style="color: #00838f;"></i>
        <h3 style="margin: 10px 0;"><?php echo $stats['open_tickets']; ?></h3>
        <p style="color: var(--gray);">Utility Tickets</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #fff8e1;">
        <i class="fas fa-balance-scale fa-2x" style="color: #f9a825;"></i>
        <h3 style="margin: 10px 0;"><?php echo $stats['court_cases']; ?></h3>
        <p style="color: var(--gray);">Active Court Cases</p>
    </div>
    <div class="card" style="padding: 20px; text-align: center; background: #efebe9;">
        <i class="fas fa-file-alt fa-2x" style="color: #4e342e;"></i>
        <h3 style="margin: 10px 0;"><?php echo number_format($stats['total_applications']); ?></h3>
        <p style="color: var(--gray);">Total Applications</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Recent Applications -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> Recent Applications</h3>
            <a href="<?php echo APP_URL; ?>/admin/?page=applications" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (count($recentApps) > 0): ?>
            <table class="table">
                <thead><tr><th>App#</th><th>User</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($recentApps as $a): ?>
                    <tr>
                        <td><?php echo e($a['application_number'] ?? $a['application_no'] ?? '-'); ?></td>
                        <td><?php echo e($a['user_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo $a['status'] === 'approved' ? 'success' : ($a['status'] === 'pending' ? 'warning' : 'danger'); ?>"><?php echo e($a['status']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($a['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 30px; text-align: center; color: var(--gray);">No applications yet</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Grievances -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-headset"></i> Recent Grievances</h3>
            <a href="<?php echo APP_URL; ?>/admin/?page=grievances" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (count($recentGrievances) > 0): ?>
            <table class="table">
                <thead><tr><th>Grev#</th><th>User</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($recentGrievances as $g): ?>
                    <tr>
                        <td><?php echo e($g['grievance_number'] ?? $g['grievance_no'] ?? '-'); ?></td>
                        <td><?php echo e($g['user_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo $g['status'] === 'resolved' ? 'success' : ($g['status'] === 'pending' ? 'warning' : 'info'); ?>"><?php echo e($g['status']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($g['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 30px; text-align: center; color: var(--gray);">No grievances</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-credit-card"></i> Recent Payments</h3>
        <a href="<?php echo APP_URL; ?>/admin/?page=payments" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (count($recentPayments) > 0): ?>
        <table class="table">
            <thead><tr><th>Receipt#</th><th>User</th><th>For</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($recentPayments as $p): ?>
                <tr>
                    <td><?php echo e($p['receipt_no'] ?? $p['payment_id'] ?? '-'); ?></td>
                    <td><?php echo e($p['user_name'] ?? '-'); ?></td>
                    <td><?php echo e($p['payment_for'] ?? $p['payment_type'] ?? '-'); ?></td>
                    <td>₹<?php echo number_format($p['amount'], 2); ?></td>
                    <td><span class="badge badge-<?php echo $p['status'] === 'paid' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'info'); ?>"><?php echo e($p['status']); ?></span></td>
                    <td><?php echo date('d/m/Y', strtotime($p['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="padding: 30px; text-align: center; color: var(--gray);">No payments recorded</div>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Charts -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3><i class="fas fa-chart-bar"></i> KPI Charts</h3></div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px,1fr)); gap: 20px;">
            <canvas id="appStatusChart" height="180"></canvas>
            <canvas id="grievStatusChart" height="180"></canvas>
            <canvas id="utilStatusChart" height="180"></canvas>
            <canvas id="revMonthChart" height="180"></canvas>
        </div>
    </div>
</div>

<?php
$scripts = '<script>
const appStatus = ' . json_encode($appByStatus) . ';
const grievStatus = ' . json_encode($grievByStatus) . ';
const utilStatus = ' . json_encode($utilByStatus) . ';
const revMonth = ' . json_encode($revByMonth) . ';

function toLabels(data){ return data.map(d => d.status || d.m); }
function toValues(data){ return data.map(d => Number(d.c || d.t || 0)); }

new Chart(document.getElementById("appStatusChart"), {
  type: "doughnut",
  data: { labels: toLabels(appStatus), datasets: [{ data: toValues(appStatus), backgroundColor: ["#3498db","#f39c12","#27ae60","#e74c3c","#9b59b6","#95a5a6"] }] },
  options: { plugins: { title: { display: true, text: "Applications by Status" } } }
});
new Chart(document.getElementById("grievStatusChart"), {
  type: "doughnut",
  data: { labels: toLabels(grievStatus), datasets: [{ data: toValues(grievStatus), backgroundColor: ["#e67e22","#e74c3c","#27ae60","#3498db","#9b59b6","#95a5a6"] }] },
  options: { plugins: { title: { display: true, text: "Grievances by Status" } } }
});
new Chart(document.getElementById("utilStatusChart"), {
  type: "doughnut",
  data: { labels: toLabels(utilStatus), datasets: [{ data: toValues(utilStatus), backgroundColor: ["#3498db","#f39c12","#27ae60","#e74c3c","#95a5a6"] }] },
  options: { plugins: { title: { display: true, text: "Utilities by Status" } } }
});
new Chart(document.getElementById("revMonthChart"), {
  type: "bar",
  data: { labels: toLabels(revMonth), datasets: [{ label: "Revenue", data: toValues(revMonth), backgroundColor: "#1a5276" }] },
  options: { plugins: { title: { display: true, text: "Revenue (Last 6 Months)" } }, scales: { y: { beginAtZero: true } } }
});
</script>';
endif;
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
