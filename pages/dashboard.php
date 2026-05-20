<?php
$pageTitle = "Dashboard";
$currentPage = 'dashboard';

// Authentication required
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . baseUrl('/login'));
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Fetch dashboard stats
$stats = [];

// Total applications
$stats['total_applications'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM land_applications WHERE user_id = ?",
    [$userId]
)['count'] ?? 0;

// Pending applications
$stats['pending_applications'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM land_applications WHERE user_id = ? AND status IN ('draft', 'submitted', 'under_scrutiny')",
    [$userId]
)['count'] ?? 0;

// Approved applications
$stats['approved_applications'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM land_applications WHERE user_id = ? AND status = 'approved'",
    [$userId]
)['count'] ?? 0;

// Active leases
$stats['active_leases'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM lease_records WHERE user_id = ? AND status = 'active'",
    [$userId]
)['count'] ?? 0;

// Outstanding dues
$stats['outstanding_dues'] = $db->fetchOne(
    "SELECT COALESCE(SUM(due_amount - COALESCE(paid_amount, 0)), 0) as total FROM dues_outstanding WHERE user_id = ? AND status IN ('pending', 'partial', 'overdue')",
    [$userId]
)['total'] ?? 0;

// Unread notifications
$stats['unread_notifications'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
    [$userId]
)['count'] ?? 0;

// Open grievances
$stats['open_grievances'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM grievances WHERE user_id = ? AND status NOT IN ('resolved', 'closed')",
    [$userId]
)['count'] ?? 0;

// Open utility tickets
$stats['open_tickets'] = $db->fetchOne(
    "SELECT COUNT(*) as count FROM utility_tickets WHERE user_id = ? AND status NOT IN ('resolved', 'closed')",
    [$userId]
)['count'] ?? 0;

// Recent applications
$recentApplications = $db->fetchAll(
    "SELECT * FROM land_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$userId]
);

// Recent notifications
$recentNotifications = $db->fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$userId]
);

ob_start();
?>

<div class="container">
    <!-- Welcome Section -->
    <div class="card mb-20" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: var(--white);">
        <div class="card-body" style="padding: 30px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin-bottom: 10px;">Welcome, <?php echo e($_SESSION['user_name'] ?? 'User'); ?>!</h2>
                <p style="opacity: 0.9;">Manage your industrial applications, leases, and services from one place.</p>
            </div>
            <div class="d-flex gap-10">
                <a href="<?php echo baseUrl('/land-bank'); ?>" class="btn" style="background: var(--white); color: var(--primary);">
                    <i class="fas fa-plus"></i> New Application
                </a>
                <a href="<?php echo baseUrl('/master-dashboard'); ?>" class="btn btn-outline">
                    <i class="fas fa-layer-group"></i> Master Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <h4><?php echo $stats['total_applications']; ?></h4>
                <span>Total Applications</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h4><?php echo $stats['pending_applications']; ?></h4>
                <span>Pending</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h4><?php echo $stats['approved_applications']; ?></h4>
                <span>Approved</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-rupee-sign"></i></div>
            <div class="stat-info">
                <h4><?php echo formatCurrency($stats['outstanding_dues']); ?></h4>
                <span>Outstanding Dues</span>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <h3 style="margin: 30px 0 15px; font-size: 20px;">Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <a href="<?php echo baseUrl('/land-bank'); ?>" class="btn btn-outline" style="flex-direction: column; padding: 20px; text-align: center;">
            <i class="fas fa-map" style="font-size: 24px; margin-bottom: 8px;"></i>
            <span>Land Bank</span>
        </a>
        <a href="<?php echo baseUrl('/utilities'); ?>" class="btn btn-outline" style="flex-direction: column; padding: 20px; text-align: center;">
            <i class="fas fa-tools" style="font-size: 24px; margin-bottom: 8px;"></i>
            <span>Utility Tickets</span>
        </a>
        <a href="<?php echo baseUrl('/grievances'); ?>" class="btn btn-outline" style="flex-direction: column; padding: 20px; text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 8px;"></i>
            <span>Grievances</span>
        </a>
        <a href="<?php echo baseUrl('/payments'); ?>" class="btn btn-outline" style="flex-direction: column; padding: 20px; text-align: center;">
            <i class="fas fa-credit-card" style="font-size: 24px; margin-bottom: 8px;"></i>
            <span>Payments</span>
        </a>
        <a href="<?php echo baseUrl('/lease-surrender'); ?>" class="btn btn-outline" style="flex-direction: column; padding: 20px; text-align: center;">
            <i class="fas fa-handshake" style="font-size: 24px; margin-bottom: 8px;"></i>
            <span>Surrender</span>
        </a>
        <a href="<?php echo baseUrl('/notifications'); ?>" class="btn btn-outline" style="flex-direction: column; padding: 20px; text-align: center;">
            <i class="fas fa-bell" style="font-size: 24px; margin-bottom: 8px;"></i>
            <span>Notifications<?php echo $stats['unread_notifications'] > 0 ? ' (' . $stats['unread_notifications'] . ')' : ''; ?></span>
        </a>
    </div>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- Recent Applications -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt" style="color: var(--primary);"></i> Recent Applications</h3>
                <a href="<?php echo baseUrl('/applications'); ?>" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (count($recentApplications) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Application No.</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentApplications as $app): ?>
                            <tr>
                                <td><strong><?php echo e($app['application_number']); ?></strong></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $app['application_type'])); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'secondary';
                                    if ($app['status'] === 'approved') $badgeClass = 'success';
                                    elseif (in_array($app['status'], ['submitted', 'under_scrutiny'])) $badgeClass = 'warning';
                                    elseif ($app['status'] === 'rejected') $badgeClass = 'danger';
                                    ?>
                                    <span class="badge badge-<?php echo $badgeClass; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($app['created_at']); ?></td>
                                <td>
                                    <a href="<?php echo baseUrl('/applications/' . $app['id']); ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--gray);">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p>No applications yet</p>
                    <a href="<?php echo baseUrl('/land-bank'); ?>" class="btn btn-primary btn-sm">Apply Now</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Notifications -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bell" style="color: var(--warning);"></i> Notifications</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (count($recentNotifications) > 0): ?>
                <?php foreach ($recentNotifications as $i => $notif): ?>
                <div style="padding: 15px; border-bottom: <?php echo $i < count($recentNotifications) - 1 ? '1px solid var(--light)' : 'none'; ?>; <?php echo !$notif['is_read'] ? 'background: #f0f8ff;' : ''; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <strong style="font-size: 14px;"><?php echo e($notif['subject']); ?></strong>
                            <p style="font-size: 12px; color: var(--gray); margin-top: 4px;">
                                <?php echo truncate($notif['message'], 60); ?>
                            </p>
                        </div>
                        <?php if (!$notif['is_read']): ?>
                        <span style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%; flex-shrink: 0;"></span>
                        <?php endif; ?>
                    </div>
                    <small style="color: var(--gray); font-size: 11px;">
                        <?php echo formatDate($notif['created_at'], 'd/m/Y h:i A'); ?>
                    </small>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--gray);">
                    <i class="fas fa-bell-slash" style="font-size: 36px; margin-bottom: 10px;"></i>
                    <p>No notifications</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Service Status Summary -->
    <h3 style="margin: 30px 0 15px; font-size: 20px;">Service Status</h3>
    <div class="stats-grid">
        <a href="<?php echo baseUrl('/lease'); ?>" class="stat-card" style="text-decoration: none; color: inherit;">
            <div class="stat-icon green"><i class="fas fa-file-contract"></i></div>
            <div class="stat-info">
                <h4><?php echo $stats['active_leases']; ?></h4>
                <span>Active Leases</span>
            </div>
        </a>
        <a href="<?php echo baseUrl('/utilities'); ?>" class="stat-card" style="text-decoration: none; color: inherit;">
            <div class="stat-icon blue"><i class="fas fa-wrench"></i></div>
            <div class="stat-info">
                <h4><?php echo $stats['open_tickets']; ?></h4>
                <span>Open Tickets</span>
            </div>
        </a>
        <a href="<?php echo baseUrl('/grievances'); ?>" class="stat-card" style="text-decoration: none; color: inherit;">
            <div class="stat-icon orange"><i class="fas fa-comment-dots"></i></div>
            <div class="stat-info">
                <h4><?php echo $stats['open_grievances']; ?></h4>
                <span>Open Grievances</span>
            </div>
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
