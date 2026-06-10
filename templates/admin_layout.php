<?php
$role = strtolower(trim((string)($userRole ?? ($_SESSION['user_type'] ?? 'admin'))));
$userRole = $role;
$userName = $userName ?? ($_SESSION['user_name'] ?? 'Admin User');
$userRegion = $userRegion ?? (!empty($_SESSION['region_id']) ? 'Region ' . $_SESSION['region_id'] : 'All Regions');
$roleLabels = [
    'superadmin' => 'Super Admin',
    'admin' => 'Admin',
    'jiada_officer' => 'JIADA Officer',
    'regional_officer' => 'Development Officer / RDD',
    'counter_clerk' => 'Counter Clerk',
    'cc' => 'Counter Clerk',
    'ieo' => 'Industrial Extension Officer',
    'industrial_extension_officer' => 'Industrial Extension Officer',
    'do' => 'Development Officer',
    'rdd' => 'Regional Deputy Director',
    'rd' => 'Regional Director',
    'regional_director' => 'Regional Director',
];
$displayRole = $roleLabels[$role] ?? ucwords(str_replace('_', ' ', $role));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle . ' - ') : ''; ?>JIADA Admin Portal</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #1a5276; --primary-light: #2980b9; --sidebar-width: 260px;
            --secondary: #e67e22; --success: #27ae60; --danger: #e74c3c;
            --warning: #f39c12; --info: #3498db; --dark: #2c3e50; --light: #ecf0f1;
            --white: #ffffff; --gray: #95a5a6; --border: #e0e0e0;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: var(--dark); }
        
        /* Admin Layout */
        .admin-wrapper { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width); background: var(--dark); color: var(--white);
            position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 1000;
            transition: transform 0.3s ease;
        }
        .sidebar-header {
            padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; gap: 12px;
        }
        .sidebar-header .logo-icon {
            width: 40px; height: 40px; background: var(--primary);
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
        }
        .sidebar-header h3 { font-size: 16px; font-weight: 600; }
        .sidebar-header small { font-size: 11px; color: var(--gray); display: block; }
        
        /* User Profile in Sidebar */
        .sidebar-user {
            padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; gap: 12px;
        }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: var(--primary-light);
            display: flex; align-items: center; justify-content: center; font-weight: 600;
        }
        .user-info h4 { font-size: 14px; margin-bottom: 2px; }
        .user-info span { font-size: 11px; color: var(--gray); }
        
        /* Navigation */
        .nav-section { padding: 15px 0; }
        .nav-section-title {
            padding: 0 20px; font-size: 11px; text-transform: uppercase;
            color: var(--gray); margin-bottom: 8px; letter-spacing: 1px;
        }
        .nav-item {
            display: flex; align-items: center; padding: 10px 20px;
            color: rgba(255,255,255,0.7); text-decoration: none;
            transition: all 0.2s; font-size: 14px; gap: 12px;
        }
        .nav-item:hover { background: rgba(255,255,255,0.1); color: var(--white); }
        .nav-item.active { background: var(--primary); color: var(--white); border-right: 3px solid var(--secondary); }
        .nav-item i { width: 20px; text-align: center; }
        .nav-item .badge {
            margin-left: auto; background: var(--danger); color: var(--white);
            padding: 2px 8px; border-radius: 10px; font-size: 11px;
        }
        .nav-item.has-submenu::after {
            content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            margin-left: auto; transition: transform 0.2s;
        }
        .nav-item.has-submenu.expanded::after { transform: rotate(180deg); }
        .submenu { background: rgba(0,0,0,0.2); display: none; }
        .submenu.show { display: block; }
        .submenu .nav-item { padding: 8px 20px 8px 52px; font-size: 13px; }
        
        /* Main Content */
        .main-content {
            flex: 1; margin-left: var(--sidebar-width); transition: margin-left 0.3s;
        }
        
        /* Top Header */
        .top-header {
            background: var(--white); padding: 15px 25px; display: flex;
            align-items: center; justify-content: space-between;
            box-shadow: var(--shadow); position: sticky; top: 0; z-index: 99;
        }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .header-right { display: flex; align-items: center; gap: 15px; }
        .menu-toggle { display: none; background: none; border: none; font-size: 20px; cursor: pointer; }
        .page-title h2 { font-size: 18px; font-weight: 600; }
        .page-title small { color: var(--gray); font-size: 12px; }
        
        .header-btn {
            width: 40px; height: 40px; border-radius: 50%; border: none;
            background: var(--light); cursor: pointer; position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        .header-btn .count {
            position: absolute; top: -2px; right: -2px; width: 18px; height: 18px;
            background: var(--danger); color: var(--white); border-radius: 50%;
            font-size: 10px; display: flex; align-items: center; justify-content: center;
        }
        
        /* Page Content */
        .page-content { padding: 25px; }
        
        /* Stats Cards */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card {
            background: var(--white); border-radius: 12px; padding: 20px;
            box-shadow: var(--shadow); display: flex; align-items: center; gap: 15px;
        }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .stat-icon.blue { background: rgba(52,152,219,0.1); color: var(--info); }
        .stat-icon.green { background: rgba(39,174,96,0.1); color: var(--success); }
        .stat-icon.orange { background: rgba(230,126,34,0.1); color: var(--secondary); }
        .stat-icon.red { background: rgba(231,76,60,0.1); color: var(--danger); }
        .stat-icon.purple { background: rgba(142,68,173,0.1); color: #8e44ad; }
        .stat-info h3 { font-size: 24px; font-weight: 700; line-height: 1.2; }
        .stat-info p { color: var(--gray); font-size: 13px; }
        
        /* Cards */
        .card { background: var(--white); border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 20px; overflow: hidden; }
        .card-header { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { font-size: 16px; font-weight: 600; }
        .card-body { padding: 22px; }
        
        /* Buttons */
        .btn {
            padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer;
            font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s; text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: var(--white); }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-success { background: var(--success); color: var(--white); }
        .btn-danger { background: var(--danger); color: var(--white); }
        .btn-warning { background: var(--warning); color: var(--white); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--dark); }
        .btn-outline:hover { background: var(--light); }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        
        /* Tables */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--light); font-weight: 600; font-size: 13px; text-transform: uppercase; }
        tr:hover { background: #f8f9fa; }
        
        /* Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .badge-success { background: rgba(39,174,96,0.1); color: var(--success); }
        .badge-warning { background: rgba(243,156,18,0.1); color: var(--warning); }
        .badge-danger { background: rgba(231,76,60,0.1); color: var(--danger); }
        .badge-info { background: rgba(52,152,219,0.1); color: var(--info); }
        .badge-secondary { background: rgba(149,165,166,0.1); color: var(--gray); }
        
        /* Forms */
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,82,118,0.1); }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        
        /* Alerts */
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: rgba(39,174,96,0.1); color: var(--success); border-left: 4px solid var(--success); }
        .alert-danger { background: rgba(231,76,60,0.1); color: var(--danger); border-left: 4px solid var(--danger); }
        .alert-warning { background: rgba(243,156,18,0.1); color: var(--warning); border-left: 4px solid var(--warning); }
        .alert-info { background: rgba(52,152,219,0.1); color: var(--info); border-left: 4px solid var(--info); }
        
        /* Loading */
        .loading-overlay { display: none; position: fixed; top:0;left:0;right:0;bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
        .loading-overlay.show { display: flex; }
        .spinner { width: 40px; height: 40px; border: 4px solid var(--white); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Mobile */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .stats-row { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
        
        /* Dashboard Grid */
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
        @media (max-width: 500px) { .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loading"><div class="spinner"></div></div>
    
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-building" style="color: white;"></i></div>
                <div><h3>JIADA Portal</h3><small><?php echo e($displayRole); ?></small></div>
            </div>
            
            <div class="sidebar-user">
                <div class="user-avatar"><?php echo strtoupper(substr($userName ?? 'A', 0, 2)); ?></div>
                <div class="user-info">
                    <h4><?php echo e($userName); ?></h4>
                    <span><?php echo e($userRegion); ?></span>
                </div>
            </div>
            
            <nav class="nav-section">
                <?php
                // Get user role and permissions
                $isSuperAdmin = $role === 'superadmin';
                $isAdmin = in_array($role, ['superadmin', 'admin'], true);
                $isCounterClerk = in_array($role, ['counter_clerk', 'cc'], true);
                $isIeo = in_array($role, ['ieo', 'industrial_extension_officer'], true);
                $isRd = in_array($role, ['rd', 'regional_director', 'authority'], true);
                $isOfficer = in_array($role, ['superadmin', 'admin', 'jiada_officer', 'regional_officer', 'counter_clerk', 'cc', 'ieo', 'industrial_extension_officer', 'do', 'rdd', 'rd', 'regional_director'], true);
                $isApplicant = $role === 'applicant';
                
                $navItems = [];
                
                // Dashboard - All roles
                $navItems[] = ['section' => 'MAIN', 'items' => [
                    ['url' => '/admin/dashboard', 'icon' => 'dashboard', 'title' => $isCounterClerk ? 'Scrutiny Desk' : ($isIeo ? 'IEO Desk' : ($isRd ? 'RD Desk' : 'Dashboard')), 'key' => 'dashboard']
                ]];

                if ($isCounterClerk) {
                    $navItems[] = ['section' => 'COUNTER CLERK', 'items' => [
                        ['url' => '/admin/?page=applications', 'icon' => 'file-alt', 'title' => 'Applications Queue', 'key' => 'applications'],
                        ['url' => '/admin/?page=notifications', 'icon' => 'bell', 'title' => 'Notifications', 'key' => 'notifications'],
                        ['url' => '/admin/?page=profile', 'icon' => 'user-circle', 'title' => 'My Profile', 'key' => 'profile'],
                    ]];
                    $navItems[] = ['section' => 'MASTER MANAGEMENT', 'items' => [
                        ['url' => '/admin/?page=regions', 'icon' => 'map-signs', 'title' => 'Regions & Districts', 'key' => 'regions'],
                        ['url' => '/admin/?page=land-bank', 'icon' => 'map', 'title' => 'Land Bank', 'key' => 'land-bank'],
                    ]];
                } elseif ($isIeo) {
                    $navItems[] = ['section' => 'INDUSTRIAL EXTENSION OFFICER', 'items' => [
                        ['url' => '/admin/?page=applications', 'icon' => 'file-alt', 'title' => 'Applications Queue', 'key' => 'applications'],
                        ['url' => '/admin/?page=notifications', 'icon' => 'bell', 'title' => 'Notifications', 'key' => 'notifications'],
                        ['url' => '/admin/?page=profile', 'icon' => 'user-circle', 'title' => 'My Profile', 'key' => 'profile'],
                    ]];
                    $navItems[] = ['section' => 'MASTER MANAGEMENT', 'items' => [
                        ['url' => '/admin/?page=regions', 'icon' => 'map-signs', 'title' => 'Regions & Districts', 'key' => 'regions'],
                        ['url' => '/admin/?page=land-bank', 'icon' => 'map', 'title' => 'Land Bank', 'key' => 'land-bank'],
                    ]];
                } elseif ($isRd) {
                    $navItems[] = ['section' => 'REGIONAL DIRECTOR', 'items' => [
                        ['url' => '/admin/?page=applications', 'icon' => 'gavel', 'title' => 'Decision Queue', 'key' => 'applications'],
                        ['url' => '/admin/?page=allotments', 'icon' => 'file-signature', 'title' => 'Allotment Decisions', 'key' => 'allotments'],
                        ['url' => '/admin/?page=bids', 'icon' => 'gavel', 'title' => 'Bidding', 'key' => 'bids'],
                        ['url' => '/admin/?page=notifications', 'icon' => 'bell', 'title' => 'Notifications', 'key' => 'notifications'],
                        ['url' => '/admin/?page=profile', 'icon' => 'user-circle', 'title' => 'My Profile', 'key' => 'profile'],
                    ]];
                    $navItems[] = ['section' => 'MASTER MANAGEMENT', 'items' => [
                        ['url' => '/admin/?page=regions', 'icon' => 'map-signs', 'title' => 'Regions & Districts', 'key' => 'regions'],
                        ['url' => '/admin/?page=land-bank', 'icon' => 'map', 'title' => 'Land Bank', 'key' => 'land-bank'],
                    ]];
                } elseif ($isOfficer) {
                    $navItems[] = ['section' => 'MASTER MANAGEMENT', 'items' => [
                        ['url' => '/admin/?page=users', 'icon' => 'users', 'title' => 'User Management', 'key' => 'users'],
                        ['url' => '/admin/?page=regions', 'icon' => 'map-signs', 'title' => 'Regions & Districts', 'key' => 'regions'],
                        ['url' => '/admin/?page=land-bank', 'icon' => 'map', 'title' => 'Land Bank', 'key' => 'land-bank'],
                        ['url' => '/admin/?page=plug-play', 'icon' => 'building', 'title' => 'Plug & Play Units', 'key' => 'plug-play'],
                    ]];

                    $navItems[] = ['section' => 'APPLICATION WORKFLOW', 'items' => [
                        ['url' => '/admin/?page=applications', 'icon' => 'file-alt', 'title' => 'Applications', 'key' => 'applications'],
                        ['url' => '/admin/?page=allotments', 'icon' => 'file-signature', 'title' => 'Allotments', 'key' => 'allotments'],
                        ['url' => '/admin/?page=possession', 'icon' => 'key', 'title' => 'Possession', 'key' => 'possession'],
                        ['url' => '/admin/?page=registry', 'icon' => 'book', 'title' => 'Registry', 'key' => 'registry'],
                        ['url' => '/admin/?page=payments', 'icon' => 'rupee-sign', 'title' => 'Payments & Revenue', 'key' => 'payments'],
                        ['url' => '/admin/?page=bids', 'icon' => 'gavel', 'title' => 'Bidding', 'key' => 'bids'],
                    ]];
                    
                    $navItems[] = ['section' => 'SERVICES', 'items' => [
                        ['url' => '/admin/?page=lease', 'icon' => 'file-contract', 'title' => 'Lease Management', 'key' => 'lease'],
                        ['url' => '/admin/?page=grievances', 'icon' => 'comment-dots', 'title' => 'Grievances', 'key' => 'grievances'],
                        ['url' => '/admin/?page=grievance-crm', 'icon' => 'headset', 'title' => 'Grievance CRM', 'key' => 'grievance-crm'],
                        ['url' => '/admin/?page=utilities', 'icon' => 'tools', 'title' => 'Utility Tickets', 'key' => 'utilities'],
                        ['url' => '/admin/?page=court-cases', 'icon' => 'balance-scale', 'title' => 'Court Cases', 'key' => 'court-cases'],
                    ]];
                    
                    if ($isAdmin) {
                        $navItems[] = ['section' => 'INTERNAL', 'items' => [
                            ['url' => '/admin/?page=hrms', 'icon' => 'id-card', 'title' => 'HRMS & Payroll', 'key' => 'hrms'],
                            ['url' => '/admin/?page=surveys', 'icon' => 'satellite-dish', 'title' => 'Land Survey', 'key' => 'surveys'],
                        ]];
                    }
                    
                    $navItems[] = ['section' => 'REPORTS & SETTINGS', 'items' => [
                        ['url' => '/admin/?page=reports', 'icon' => 'chart-bar', 'title' => 'Reports', 'key' => 'reports'],
                        ['url' => '/admin/?page=audit-log', 'icon' => 'history', 'title' => 'Audit Log', 'key' => 'audit-log'],
                        ['url' => '/admin/?page=notifications', 'icon' => 'bell', 'title' => 'Notifications', 'key' => 'notifications'],
                        ['url' => '/admin/?page=settings', 'icon' => 'cog', 'title' => 'Settings', 'key' => 'settings'],
                        ['url' => '/admin/?page=mobile-app', 'icon' => 'mobile-alt', 'title' => 'Mobile App', 'key' => 'mobile-app'],
                        ['url' => '/admin/?page=profile', 'icon' => 'user-circle', 'title' => 'My Profile', 'key' => 'profile'],
                    ]];
                }
                
                foreach ($navItems as $section): ?>
                <div class="nav-section-title"><?php echo $section['section']; ?></div>
                <?php foreach ($section['items'] as $item):
                    $active = trim(strstr($currentPage ?? '', '?', true), '/') === trim($item['url'], '/') || ($currentPage ?? '') === $item['url']; ?>
                <a href="<?php echo htmlspecialchars(APP_URL . $item['url']); ?>" class="nav-item <?php echo $active ? 'active' : ''; ?>" data-key="<?php echo $item['key']; ?>">
                    <i class="fas fa-<?php echo $item['icon']; ?>"></i>
                    <span><?php echo $item['title']; ?></span>
                    <?php if (!empty($item['badge'])): ?><span class="badge"><?php echo $item['badge']; ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
                <?php endforeach; ?>
                
                <div style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 10px; padding-top: 10px;">
                    <a href="<?php echo APP_URL; ?>/" class="nav-item"><i class="fas fa-home"></i> Public Portal</a>
                    <a href="<?php echo APP_URL; ?>/admin/logout" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="top-header">
                <div class="header-left">
                    <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
                    <div class="page-title"><h2><?php echo $pageTitle ?? 'Dashboard'; ?></h2></div>
                </div>
                <div class="header-right">
                    <a href="<?php echo APP_URL; ?>/admin/notifications" class="header-btn">
                        <i class="fas fa-bell"></i>
                        <?php if (($unreadNotif ?? 0) > 0): ?><span class="count"><?php echo $unreadNotif; ?></span><?php endif; ?>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/profile" class="header-btn">
                        <i class="fas fa-user-circle"></i>
                    </a>
                </div>
            </div>
            <div class="page-content">
                <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
                <?php elseif (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
                <?php endif; ?>
                <?php echo $content ?? ''; ?>
            </div>
        </main>
    </div>
    
    <script>
    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !e.target.closest('.menu-toggle')) {
                sidebar.classList.remove('open');
            }
        }
    });
    
    // Global functions
    function showLoading() { document.getElementById('loading').classList.add('show'); }
    function hideLoading() { document.getElementById('loading').classList.remove('show'); }
    function showAlert(msg, type='info') {
        let html = `<div class="alert alert-${type}"><i class="fas fa-${type==='success'?'check-circle':type==='danger'?'exclamation-circle':'info-circle'}"></i> ${msg}</div>`;
        let el = document.querySelector('.page-content');
        if (el) el.insertAdjacentHTML('afterbegin', html);
        setTimeout(() => { let a = document.querySelector('.alert'); if(a) a.remove(); }, 4000);
    }
    async function apiCall(endpoint, method='GET', data=null) {
        showLoading();
        try {
            let r = await fetch('<?php echo APP_URL; ?>/api' + endpoint, {
                method, headers: {'Content-Type': 'application/json'},
                body: data ? JSON.stringify(data) : null
            });
            hideLoading(); return await r.json();
        } catch(e) { hideLoading(); return {success:false, message:'Error'}; }
    }
    </script>
    <?php echo $scripts ?? ''; ?>
</body>
</html>
