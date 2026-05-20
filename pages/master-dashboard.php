<?php
$pageTitle = "Master Dashboard";
$currentPage = 'master-dashboard';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . baseUrl('/login'));
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Global stats
$stats = [
    'plots' => $db->fetchOne("SELECT COUNT(*) as c FROM land_banks")['c'] ?? 0,
    'available_plots' => $db->fetchOne("SELECT COUNT(*) as c FROM land_banks WHERE status='available'")['c'] ?? 0,
    'applications' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications")['c'] ?? 0,
    'grievances' => $db->fetchOne("SELECT COUNT(*) as c FROM grievances")['c'] ?? 0,
    'tickets' => $db->fetchOne("SELECT COUNT(*) as c FROM utility_tickets")['c'] ?? 0,
    'payments' => $db->fetchOne("SELECT COUNT(*) as c FROM payments")['c'] ?? 0,
    'court_cases' => $db->fetchOne("SELECT COUNT(*) as c FROM court_cases")['c'] ?? 0,
    'leases' => $db->fetchOne("SELECT COUNT(*) as c FROM lease_records")['c'] ?? 0,
];

// User stats
$my = [
    'applications' => $db->fetchOne("SELECT COUNT(*) as c FROM land_applications WHERE user_id = ?", [$userId])['c'] ?? 0,
    'grievances' => $db->fetchOne("SELECT COUNT(*) as c FROM grievances WHERE user_id = ?", [$userId])['c'] ?? 0,
    'tickets' => $db->fetchOne("SELECT COUNT(*) as c FROM utility_tickets WHERE user_id = ?", [$userId])['c'] ?? 0,
    'payments_due' => $db->fetchOne("SELECT COALESCE(SUM(amount - paid_amount),0) as c FROM payments WHERE user_id = ? AND status != 'paid'", [$userId])['c'] ?? 0,
];

ob_start();
?>
<div class="container">
    <div class="card mb-20" style="background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%); color: white;">
        <div class="card-body" style="padding: 28px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2>Master Dashboard</h2>
                <p style="opacity: 0.9;">Unified view of Land, Utilities, Payments, Grievances, Leases, and Compliance</p>
            </div>
            <a href="<?php echo baseUrl('/land-bank'); ?>" class="btn" style="background: white; color: #1a5276;">Apply Land</a>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-map"></i></div><div class="stat-info"><h4><?php echo $stats['available_plots']; ?></h4><span>Available Plots</span></div></div>
        <div class="stat-card"><div class="stat-icon green"><i class="fas fa-file-alt"></i></div><div class="stat-info"><h4><?php echo $stats['applications']; ?></h4><span>Total Applications</span></div></div>
        <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-comment-dots"></i></div><div class="stat-info"><h4><?php echo $stats['grievances']; ?></h4><span>Grievances</span></div></div>
        <div class="stat-card"><div class="stat-icon red"><i class="fas fa-tools"></i></div><div class="stat-info"><h4><?php echo $stats['tickets']; ?></h4><span>Utility Tickets</span></div></div>
    </div>
    
    <h3 class="mt-20">My Activity</h3>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-file"></i></div><div class="stat-info"><h4><?php echo $my['applications']; ?></h4><span>My Applications</span></div></div>
        <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-info"><h4><?php echo $my['grievances']; ?></h4><span>My Grievances</span></div></div>
        <div class="stat-card"><div class="stat-icon green"><i class="fas fa-wrench"></i></div><div class="stat-info"><h4><?php echo $my['tickets']; ?></h4><span>My Tickets</span></div></div>
        <div class="stat-card"><div class="stat-icon red"><i class="fas fa-rupee-sign"></i></div><div class="stat-info"><h4>₹<?php echo number_format($my['payments_due']); ?></h4><span>Payments Due</span></div></div>
    </div>
    
    <h3 class="mt-20">Quick Access</h3>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 15px;">
        <a class="btn btn-outline" href="<?php echo baseUrl('/land-bank'); ?>">Land Bank</a>
        <a class="btn btn-outline" href="<?php echo baseUrl('/plug-play'); ?>">Plug & Play</a>
        <a class="btn btn-outline" href="<?php echo baseUrl('/utilities'); ?>">Utilities</a>
        <a class="btn btn-outline" href="<?php echo baseUrl('/grievances'); ?>">Grievances</a>
        <a class="btn btn-outline" href="<?php echo baseUrl('/payments'); ?>">Payments</a>
        <a class="btn btn-outline" href="<?php echo baseUrl('/services'); ?>">Lease Services</a>
        <a class="btn btn-outline" href="<?php echo baseUrl('/survey'); ?>">Land Survey</a>
        <a class="btn btn-outline" href="<?php echo baseUrl('/whatsapp-bot'); ?>">WhatsApp Bot</a>
        <a class="btn btn-outline" href="<?php echo baseUrl('/brap'); ?>">BRAP</a>
        <a class="btn btn-outline" href="<?php echo baseUrl('/mobile-app'); ?>">Mobile App</a>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
