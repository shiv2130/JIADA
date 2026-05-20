<?php
$pageTitle = "Reports - Admin";
$currentPage = 'reports';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$totalRevenue = $db->fetchOne("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE status IN ('paid','success')")['t'] ?? 0;
$totalUsers = $db->fetchOne("SELECT COUNT(*) as c FROM users")['c'] ?? 0;
$totalPlots = $db->fetchOne("SELECT COUNT(*) as c FROM land_banks")['c'] ?? 0;
$totalApps = $db->fetchOne("SELECT COUNT(*) as c FROM land_applications")['c'] ?? 0;
$singleVsMulti = $db->fetchAll("SELECT plot_id, COUNT(*) c FROM land_applications GROUP BY plot_id");
$objectionPending = $db->fetchOne("SELECT COUNT(*) c FROM la_objections WHERE status IN ('open','reopened')")['c'] ?? 0;
$timelineBreach = 0;
$breachTableExists = $db->fetchOne("SELECT 1 ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'la_timeline_breaches' LIMIT 1");
if ($breachTableExists) {
    $timelineBreach = $db->fetchOne("SELECT COUNT(*) c FROM la_timeline_breaches WHERE status = 'open'")['c'] ?? 0;
}
$premiumDue = $db->fetchOne("SELECT COUNT(*) c FROM la_allotments WHERE status IN ('provisional','premium_pending') AND payment_due_date < CURDATE()")['c'] ?? 0;
$possessionPending = $db->fetchOne("SELECT COUNT(*) c FROM la_possessions WHERE status = 'pending'")['c'] ?? 0;
$registryPending = $db->fetchOne("SELECT COUNT(*) c FROM la_registry_process WHERE status IN ('pending_cte','pending_construction_permit','registry_initiated')")['c'] ?? 0;
$nonEntertained = $db->fetchOne("SELECT COUNT(*) c FROM land_applications WHERE non_entertained_flag = 1")['c'] ?? 0;

$singleCount = 0;
$multiCount = 0;
foreach ($singleVsMulti as $r) {
    if (intval($r['c']) <= 1) $singleCount++;
    else $multiCount++;
}

$statusRows = $db->fetchAll("SELECT status, COUNT(*) c FROM land_applications GROUP BY status ORDER BY c DESC");
$hasApplicantProfiles = $db->fetchOne("SELECT 1 ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'applicant_profiles' LIMIT 1");
if ($hasApplicantProfiles) {
    $districtRows = $db->fetchAll("SELECT COALESCE(district,'N/A') district, COUNT(*) c FROM applicant_profiles GROUP BY district ORDER BY c DESC LIMIT 15");
} else {
    $districtRows = $db->fetchAll("SELECT COALESCE(district,'N/A') district, COUNT(*) c FROM users WHERE user_type IN ('applicant','allottee','vendor') GROUP BY district ORDER BY c DESC LIMIT 15");
}
$paymentRows = $db->fetchAll("SELECT payment_type, COALESCE(SUM(amount),0) amount FROM payments WHERE status IN ('paid','success') GROUP BY payment_type ORDER BY amount DESC");
$bidRows = $db->fetchAll("SELECT b.bid_number, lb.plot_number, b.status, b.base_price, b.emd_amount FROM bids b LEFT JOIN land_banks lb ON lb.id = b.plot_id ORDER BY b.id DESC LIMIT 20");

ob_start();
?>
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 20px; text-align: center; background: #e3f2fd;"><h3>₹<?php echo number_format($totalRevenue, 2); ?></h3><p>Total Revenue</p></div>
    <div class="card" style="padding: 20px; text-align: center; background: #e8f5e9;"><h3><?php echo $totalUsers; ?></h3><p>Total Users</p></div>
    <div class="card" style="padding: 20px; text-align: center; background: #fff3e0;"><h3><?php echo $totalPlots; ?></h3><p>Total Plots</p></div>
    <div class="card" style="padding: 20px; text-align: center; background: #f3e5f5;"><h3><?php echo $totalApps; ?></h3><p>Total Applications</p></div>
</div>

<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 20px; text-align: center; background: #e0f7fa;"><h3><?php echo intval($singleCount); ?></h3><p>Single Applicant Plots</p></div>
    <div class="card" style="padding: 20px; text-align: center; background: #ffebee;"><h3><?php echo intval($multiCount); ?></h3><p>Multi Applicant Plots</p></div>
    <div class="card" style="padding: 20px; text-align: center; background: #fff8e1;"><h3><?php echo intval($objectionPending); ?></h3><p>Objections Pending</p></div>
    <div class="card" style="padding: 20px; text-align: center; background: #fce4ec;"><h3><?php echo intval($timelineBreach); ?></h3><p>Timeline Breaches</p></div>
</div>

<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 20px; text-align: center; background: #ede7f6;"><h3><?php echo intval($premiumDue); ?></h3><p>Premium Overdue</p></div>
    <div class="card" style="padding: 20px; text-align: center; background: #f1f8e9;"><h3><?php echo intval($possessionPending); ?></h3><p>Possession Pending</p></div>
    <div class="card" style="padding: 20px; text-align: center; background: #e8eaf6;"><h3><?php echo intval($registryPending); ?></h3><p>Registry Pending</p></div>
</div>
<div style="display: grid; grid-template-columns: repeat(1, 1fr); gap: 15px; margin-bottom: 20px;">
    <div class="card" style="padding: 20px; text-align: center; background: #fff3e0;"><h3><?php echo intval($nonEntertained); ?></h3><p>Non-Entertained Applications</p></div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Reports & Analytics</h3></div>
    <div class="card-body">
        <p>Live Land Allotment MIS reports from application, bidding, payment, allotment, possession, and registry stages.</p>
        <div style="margin-top: 20px; display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="downloadTableCsv('statusReportTable','application_status_report.csv')">Application Status CSV</button>
            <button class="btn btn-success" onclick="downloadTableCsv('paymentReportTable','payment_report.csv')">Payment Report CSV</button>
            <button class="btn btn-info" onclick="downloadTableCsv('bidReportTable','bidding_report.csv')">Bidding Report CSV</button>
            <button class="btn btn-warning" onclick="downloadTableCsv('districtReportTable','district_report.csv')">District Report CSV</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-list"></i> Application Status Report</h3></div>
    <div class="card-body table-responsive">
        <table class="table" id="statusReportTable">
            <thead><tr><th>Status</th><th>Count</th></tr></thead>
            <tbody>
            <?php foreach ($statusRows as $r): ?>
                <tr><td><?php echo e($r['status']); ?></td><td><?php echo intval($r['c']); ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($statusRows)): ?><tr><td colspan="2" class="text-center">No data</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-money-check"></i> Payment Head Report</h3></div>
    <div class="card-body table-responsive">
        <table class="table" id="paymentReportTable">
            <thead><tr><th>Payment Type</th><th>Amount</th></tr></thead>
            <tbody>
            <?php foreach ($paymentRows as $r): ?>
                <tr><td><?php echo e($r['payment_type']); ?></td><td><?php echo number_format(floatval($r['amount']), 2); ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($paymentRows)): ?><tr><td colspan="2" class="text-center">No data</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-gavel"></i> Bidding Report</h3></div>
    <div class="card-body table-responsive">
        <table class="table" id="bidReportTable">
            <thead><tr><th>Bid No</th><th>Plot</th><th>Status</th><th>Base Price</th><th>EMD</th></tr></thead>
            <tbody>
            <?php foreach ($bidRows as $r): ?>
                <tr>
                    <td><?php echo e($r['bid_number']); ?></td>
                    <td><?php echo e($r['plot_number'] ?? '-'); ?></td>
                    <td><?php echo e($r['status']); ?></td>
                    <td><?php echo number_format(floatval($r['base_price'] ?? 0), 2); ?></td>
                    <td><?php echo number_format(floatval($r['emd_amount'] ?? 0), 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($bidRows)): ?><tr><td colspan="5" class="text-center">No bids</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-map-marker-alt"></i> District-wise Applicant Report</h3></div>
    <div class="card-body table-responsive">
        <table class="table" id="districtReportTable">
            <thead><tr><th>District</th><th>Applicant Count</th></tr></thead>
            <tbody>
            <?php foreach ($districtRows as $r): ?>
                <tr><td><?php echo e($r['district']); ?></td><td><?php echo intval($r['c']); ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($districtRows)): ?><tr><td colspan="2" class="text-center">No data</td></tr><?php endif; ?>
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
?>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
