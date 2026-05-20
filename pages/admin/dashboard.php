<?php
$pageTitle = "Admin Dashboard";
$currentPage = 'dashboard';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$userName = $_SESSION['user_name'] ?? 'Admin';
$userType = $_SESSION['user_type'] ?? 'admin';
$regionId = $_SESSION['region_id'] ?? null;
$regionWhere = $regionId ? " AND region_id = $regionId" : '';

// Dashboard stats
$stats = [
    'total_users' => $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE user_type IN ('applicant','allottee','vendor')$regionWhere")['c'] ?? 0,
    'total_plots' => $db->fetchOne("SELECT COUNT(*) as c FROM land_banks$regionWhere")['c'] ?? 0,
    'available_plots' => $db->fetchOne("SELECT COUNT(*) as c FROM land_banks WHERE status = 'available'$regionWhere")['c'] ?? 0,
    'total_applications' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications$regionWhere")['c'] ?? 0,
    'pending_applications' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications WHERE status IN ('pending','submitted','under_scrutiny')$regionWhere")['c'] ?? 0,
    'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE status IN ('paid','success')$regionWhere")['t'] ?? 0,
    'open_grievances' => $db->fetchOne("SELECT COUNT(*) as c FROM grievances WHERE status NOT IN ('resolved','closed')$regionWhere")['c'] ?? 0,
    'open_tickets' => $db->fetchOne("SELECT COUNT(*) as c FROM utility_tickets WHERE status NOT IN ('closed','resolved')$regionWhere")['c'] ?? 0,
    'court_cases' => $db->fetchOne("SELECT COUNT(*) as c FROM court_cases WHERE case_status IN ('pending','in_progress')")['c'] ?? 0,
];

// KPI data
$appByStatus = $db->fetchAll("SELECT status, COUNT(*) as c FROM land_applications$regionWhere GROUP BY status");
$grievByStatus = $db->fetchAll("SELECT status, COUNT(*) as c FROM grievances$regionWhere GROUP BY status");
$utilByStatus = $db->fetchAll("SELECT status, COUNT(*) as c FROM utility_tickets$regionWhere GROUP BY status");
$revByMonth = $db->fetchAll("SELECT DATE_FORMAT(created_at,'%Y-%m') as m, SUM(amount) as t FROM payments WHERE status IN ('paid','success')$regionWhere AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY m ORDER BY m ASC");

// Recent data
$recentApps = $db->fetchAll("SELECT la.*, u.name as user_name, lb.plot_number FROM land_applications la LEFT JOIN users u ON la.user_id = u.id LEFT JOIN land_banks lb ON la.plot_id = lb.id ORDER BY la.id DESC LIMIT 10");
$recentGrievances = $db->fetchAll("SELECT g.*, u.name as user_name FROM grievances g LEFT JOIN users u ON g.user_id = u.id ORDER BY g.id DESC LIMIT 5");
$recentPayments = $db->fetchAll("SELECT p.*, u.name as user_name FROM payments p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC LIMIT 10");

ob_start();
?>
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
?>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
