<?php
$pageTitle = "Home";
$currentPage = 'home';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Fetch available plots count & latest grievance status for logged-in user
$plotsCount = 0;
$plugPlayCount = 0;
$latestGrievance = null;
try {
    $db = getDB();
    $totalPlots = $db->fetchOne("SELECT COUNT(*) as total FROM land_banks WHERE status = 'available'");
    $plotsCount = $totalPlots['total'] ?? 0;
    $totalUnits = $db->fetchOne("SELECT COUNT(*) as total FROM plug_play_units WHERE status = 'available'");
    $plugPlayCount = $totalUnits['total'] ?? 0;
    if (!empty($_SESSION['user_id'])) {
        $latestGrievance = $db->fetchOne("SELECT grievance_number, grievance_no, status, updated_at FROM grievances WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1", [$_SESSION['user_id']]);
    }
} catch (Exception $e) {
    // Silent fail for homepage stats
}

ob_start();
?>

<?php if ($latestGrievance): ?>
<section style="background:#f7f9fb; padding:20px 0;">
    <div class="container">
        <div class="alert alert-info" style="display:flex; justify-content:space-between; align-items:center; margin:0;">
            <div>
                <strong>Latest Grievance:</strong>
                <?php echo e($latestGrievance['grievance_number'] ?? $latestGrievance['grievance_no'] ?? ''); ?> -
                Status: <span class="badge badge-<?php echo $latestGrievance['status']==='resolved'?'success':($latestGrievance['status']==='escalated'?'danger':'secondary'); ?>"><?php echo e($latestGrievance['status']); ?></span>
            </div>
            <a class="btn btn-sm btn-outline" href="<?php echo baseUrl('/grievances'); ?>">View</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Hero Section -->
<section class="hero" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: var(--white); padding: 80px 0;">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;">
            <div>
                <h1 style="font-size: 48px; font-weight: 700; margin-bottom: 20px; line-height: 1.2;">Jharkhand Industrial<br>Area Development Authority</h1>
                <p style="font-size: 18px; margin-bottom: 30px; opacity: 0.9;">Single Window Portal for Industrial Land Allotment, Plug & Play Units, and Business Services</p>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="<?php echo baseUrl('/land-bank'); ?>" class="btn btn-lg" style="background: var(--white); color: var(--primary);">
                        <i class="fas fa-search"></i> Explore Land Bank
                    </a>
                    <a href="<?php echo baseUrl('/register'); ?>" class="btn btn-lg btn-secondary">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                </div>
            </div>
            <div style="text-align: center;">
                <i class="fas fa-city" style="font-size: 200px; opacity: 0.3;"></i>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section style="padding: 30px 0 0 0; background: var(--white);">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-map-marked-alt"></i></div>
                <div class="stat-info"><h4><?php echo $plotsCount; ?>+</h4><span>Available Plots</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-building"></i></div>
                <div class="stat-info"><h4><?php echo $plugPlayCount; ?></h4><span>Plug & Play Units</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-industry"></i></div>
                <div class="stat-info"><h4>500+</h4><span>Industries Allotted</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-handshake"></i></div>
                <div class="stat-info"><h4>₹2500Cr+</h4><span>Investment Facilitated</span></div>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section style="padding: 30px 0 0 0; background: #f5f7fa;">
    <div class="container">
        <div class="text-center mb-20">
            <h2 style="font-size: 32px; font-weight: 700; margin-bottom: 10px;">Our Services</h2>
            <p style="color: var(--gray);">Comprehensive industrial infrastructure and support services</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-top: 40px;">
            <!-- Service Cards -->
            <div class="card" style="padding: 30px; text-align: center; transition: var(--transition);" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(26, 82, 118, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-map fa-2x" style="color: var(--primary);"></i>
                </div>
                <h3 style="margin-bottom: 10px;">Land Allotment</h3>
                <p style="color: var(--gray); font-size: 14px; margin-bottom: 20px;">Apply for industrial land plots through transparent bidding process</p>
                <a href="<?php echo baseUrl('/land-bank'); ?>" class="btn btn-primary btn-sm">View Plots</a>
            </div>
            
            <div class="card" style="padding: 30px; text-align: center; transition: var(--transition);" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(39, 174, 96, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-building fa-2x" style="color: var(--success);"></i>
                </div>
                <h3 style="margin-bottom: 10px;">Plug & Play Units</h3>
                <p style="color: var(--gray); font-size: 14px; margin-bottom: 20px;">Ready-to-use flatted factory units and industrial sheds</p>
                <a href="<?php echo baseUrl('/plug-play'); ?>" class="btn btn-success btn-sm">Explore Units</a>
            </div>
            
            <div class="card" style="padding: 30px; text-align: center; transition: var(--transition);" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(230, 126, 34, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-exchange-alt fa-2x" style="color: var(--secondary);"></i>
                </div>
                <h3 style="margin-bottom: 10px;">Lease Services</h3>
                <p style="color: var(--gray); font-size: 14px; margin-bottom: 20px;">Transfer, mortgage, surrender and renewal of lease rights</p>
                <a href="<?php echo baseUrl('/services'); ?>" class="btn btn-secondary btn-sm">Learn More</a>
            </div>
            
            <div class="card" style="padding: 30px; text-align: center; transition: var(--transition);" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(52, 152, 219, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-tools fa-2x" style="color: var(--info);"></i>
                </div>
                <h3 style="margin-bottom: 10px;">Utility Services</h3>
                <p style="color: var(--gray); font-size: 14px; margin-bottom: 20px;">Water, electricity, and maintenance request management</p>
                <a href="<?php echo baseUrl('/utilities'); ?>" class="btn btn-sm" style="background: var(--info); color: var(--white);">Raise Ticket</a>
            </div>
            
            <div class="card" style="padding: 30px; text-align: center; transition: var(--transition);" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(231, 76, 60, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-headset fa-2x" style="color: var(--danger);"></i>
                </div>
                <h3 style="margin-bottom: 10px;">Grievance Redressal</h3>
                <p style="color: var(--gray); font-size: 14px; margin-bottom: 20px;">File and track your complaints with resolution tracking</p>
                <a href="<?php echo baseUrl('/grievances'); ?>" class="btn btn-sm" style="background: var(--danger); color: var(--white);">File Grievance</a>
            </div>
            
            <div class="card" style="padding: 30px; text-align: center; transition: var(--transition);" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(149, 165, 166, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-balance-scale fa-2x" style="color: var(--gray);"></i>
                </div>
                <h3 style="margin-bottom: 10px;">Legal Services</h3>
                <p style="color: var(--gray); font-size: 14px; margin-bottom: 20px;">Court case tracking and legal compliance management</p>
                <a href="<?php echo baseUrl('/court-cases'); ?>" class="btn btn-outline btn-sm">View Cases</a>
            </div>
        </div>
    </div>
</section>

<!-- GIS Map Preview Section -->
<section style="padding: 30px 0; background: var(--white);">
    <div class="container">
        <div class="text-center mb-20">
            <h2 style="font-size: 32px; font-weight: 700; margin-bottom: 10px;">Interactive Land Bank Map</h2>
            <p style="color: var(--gray); margin-bottom: 30px;">Browse available industrial plots across Jharkhand with real-time GIS data</p>
        </div>
        
        <div class="card">
            <div id="map"></div>
        </div>
        <div class="text-center mt-20">
            <a href="<?php echo baseUrl('/land-bank'); ?>" class="btn btn-primary btn-lg"><i class="fas fa-map-marked-alt"></i> View All Plots</a>
        </div>
    </div>
</section>

<!-- Quick Links -->
<section style="padding: 30px 0; background: var(--primary); color: var(--white);">
    <div class="container">
        <div class="text-center mb-20">
            <h2 style="font-size: 32px; font-weight: 700; margin-bottom: 10px;">Quick Access</h2>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px;">
            <a href="<?php echo baseUrl('/applications'); ?>" style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: var(--border-radius); text-decoration: none; color: var(--white); display: flex; align-items: center; gap: 15px; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-file-alt fa-2x"></i>
                <div><strong>My Applications</strong><br><small>Track status</small></div>
            </a>
            <a href="<?php echo baseUrl('/payments'); ?>" style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: var(--border-radius); text-decoration: none; color: var(--white); display: flex; align-items: center; gap: 15px; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-credit-card fa-2x"></i>
                <div><strong>Online Payments</strong><br><small>Pay fees & dues</small></div>
            </a>
            <a href="<?php echo baseUrl('/certificates'); ?>" style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: var(--border-radius); text-decoration: none; color: var(--white); display: flex; align-items: center; gap: 15px; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-certificate fa-2x"></i>
                <div><strong>Certificates</strong><br><small>Download docs</small></div>
            </a>
            <a href="<?php echo baseUrl('/help'); ?>" style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: var(--border-radius); text-decoration: none; color: var(--white); display: flex; align-items: center; gap: 15px; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <i class="fas fa-question-circle fa-2x"></i>
                <div><strong>Help & Support</strong><br><small>FAQs & Guides</small></div>
            </a>
        </div>
    </div>
</section>

<script>
// Initialize GIS Map
document.addEventListener('DOMContentLoaded', function() {
    const map = L.map('map').setView([23.3441, 85.3096], 8);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Sample markers for demo
    L.marker([23.3441, 85.3096]).addTo(map)
        .bindPopup('<strong>Ranchi Industrial Area</strong><br>Available plots: 25');
    L.marker([23.4200, 85.3200]).addTo(map)
        .bindPopup('<strong>Hehal Industrial Zone</strong><br>Available plots: 12');
    L.marker([23.2800, 85.2500]).addTo(map)
        .bindPopup('<strong>Kanke Industrial Estate</strong><br>Available plots: 8');
});
</script>

<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
