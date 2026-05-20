<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle . ' - ') : ''; ?>JIADA Portal</title>
    <meta name="description" content="Jharkhand Industrial Area Development Authority - Single Window Portal">
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo baseUrl('assets/images/favicon.ico'); ?>" type="image/x-icon">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS for GIS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Razorpay -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    
    <style>
        :root {
            --primary: #1a5276;
            --primary-light: #2980b9;
            --secondary: #e67e22;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
            --dark: #2c3e50;
            --light: #ecf0f1;
            --white: #ffffff;
            --gray: #95a5a6;
            --gray-light: #bdc3c7;
            --border-radius: 8px;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --shadow-lg: 0 5px 25px rgba(0,0,0,0.15);
            --transition: all 0.3s ease;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        /* Header */
        .header {
            background: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-top {
            background: var(--primary);
            color: var(--white);
            padding: 8px 0;
            font-size: 13px;
        }
        
        .header-top .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-top a { color: var(--white); text-decoration: none; }
        .header-top a:hover { opacity: 0.8; }
        
        .header-main { padding: 15px 0; }
        
        .header-main .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: var(--primary);
        }
        
        .logo img { height: 50px; }
        
        .logo-text h1 { font-size: 20px; font-weight: 700; line-height: 1.2; }
        .logo-text span { font-size: 12px; color: var(--gray); }
        
        .nav-menu { display: flex; list-style: none; gap: 5px; margin:0}
        .nav-menu a {
            color: var(--dark);
            text-decoration: none;
            padding: 10px 18px;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
        }
        .nav-menu a:hover, .nav-menu a.active {
            background: var(--primary);
            color: var(--white);
        }
        
        .header-actions { display: flex; align-items: center; gap: 15px; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-primary { background: var(--primary); color: var(--white); }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-secondary { background: var(--secondary); color: var(--white); }
        .btn-success { background: var(--success); color: var(--white); }
        .btn-danger { background: var(--danger); color: var(--white); }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        .btn-outline:hover { background: var(--primary); color: var(--white); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-lg { padding: 14px 28px; font-size: 16px; }
        
        /* Container */
        .container { max-width: 1280px; margin: 0 auto; padding: 0 20px; }
        
        /* Main Content */
        .main-content { min-height: calc(100vh - 200px); padding: 1px 0; }
        
        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 { font-size: 18px; font-weight: 600; }
        .card-body { padding: 25px; }
        .card-footer { padding: 15px 25px; background: var(--light); }
        
        /* Dashboard Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.blue { background: rgba(52, 152, 219, 0.1); color: var(--info); }
        .stat-icon.green { background: rgba(39, 174, 96, 0.1); color: var(--success); }
        .stat-icon.orange { background: rgba(230, 126, 34, 0.1); color: var(--secondary); }
        .stat-icon.red { background: rgba(231, 76, 60, 0.1); color: var(--danger); }
        
        .stat-info h4 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-info span { color: var(--gray); font-size: 14px; }
        
        /* Forms */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        .form-group label .required { color: var(--danger); }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 82, 118, 0.1);
        }
        
        textarea.form-control { min-height: 120px; resize: vertical; }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        /* Tables */
        .table-responsive { overflow-x: auto; }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light);
        }
        .table th {
            background: var(--light);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
        }
        .table tr:hover { background: #f8f9fa; }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
        .badge-warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
        .badge-danger { background: rgba(231, 76, 60, 0.1); color: var(--danger); }
        .badge-info { background: rgba(52, 152, 219, 0.1); color: var(--info); }
        .badge-secondary { background: rgba(149, 165, 166, 0.1); color: var(--gray); }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success { background: rgba(39, 174, 96, 0.1); color: var(--success); border-left: 4px solid var(--success); }
        .alert-danger { background: rgba(231, 76, 60, 0.1); color: var(--danger); border-left: 4px solid var(--danger); }
        .alert-warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); border-left: 4px solid var(--warning); }
        .alert-info { background: rgba(52, 152, 219, 0.1); color: var(--info); border-left: 4px solid var(--info); }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 15px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--dark);
        }
        .pagination a.active, .pagination span.current {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }
        
        /* GIS Map */
        #map { height: 500px; border-radius: var(--border-radius); }
        
        /* Footer */
        .footer {
            background: var(--dark);
            color: var(--white);
            padding: 40px 0 20px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .footer h4 { margin-bottom: 15px; font-size: 16px; }
        .footer ul { list-style: none; }
        .footer ul li { margin-bottom: 8px; }
        .footer ul a { color: var(--gray-light); text-decoration: none; }
        .footer ul a:hover { color: var(--white); }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 14px;
            color: var(--gray-light);
        }
        
        /* Loading */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .loading.active { display: flex; }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--white);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Mobile Menu Toggle */
        .menu-toggle { display: none; background: none; border: none; font-size: 24px; color: var(--dark); cursor: pointer; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--white);
                flex-direction: column;
                padding: 10px;
                box-shadow: var(--shadow);
            }
            .nav-menu.active { display: flex; }
            .header-actions .btn-text { display: none; }
            .stats-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
        
        /* Utility Classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-20 { margin-top: 20px; }
        .mb-20 { margin-bottom: 20px; }
        .d-flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .align-center { align-items: center; }
        .gap-10 { gap: 10px; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>
    
    <!-- Header -->
    <header class="header">
        <div class="header-top">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <div>
                    <a href="tel:+916512234567"><i class="fas fa-phone"></i> +91 651-223-4567</a>
                    <span style="margin: 0 15px;">|</span>
                    <a href="mailto:info@jiada.in"><i class="fas fa-envelope"></i> info@jiada.in</a>
                </div>
                <div>
                    <!-- <a href="<?php echo baseUrl('/whatsapp-bot'); ?>" id="whatsappLink"><i class="fab fa-whatsapp"></i> WhatsApp</a> -->
                    <!-- <span style="margin: 0 15px;">|</span> -->
                    <span id="currentDateTime"></span> | 
               
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="d-flex align-center gap-10">
                        <a href="<?php echo baseUrl('/notifications'); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-bell"></i>
                            <span class="badge badge-danger" id="notificationCount">0</span>
                        </a>
                        <a href="<?php echo baseUrl('/dashboard'); ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-user"></i> <span class="btn-text"><?php echo e($_SESSION['user_name'] ?? 'Dashboard'); ?></span>
                        </a>
                        <a href="<?php echo baseUrl('/logout'); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-sign-out-alt"></i> <span class="btn-text">Logout</span>
                        </a>
                    </div>
                    <?php else: ?>
                    <a href="<?php echo baseUrl('/login'); ?>" class="btn btn-outline btn-sm">Login</a>
                    <a href="<?php echo baseUrl('/register'); ?>" class="btn btn-primary btn-sm">Register</a>
                    <?php endif; ?> 
                </div>
            </div>
        </div>
        <div class="header-main">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <a href="<?php echo baseUrl('/'); ?>" class="logo">
                    <i class="fas fa-industry fa-2x" style="color: var(--primary);"></i>
                    <div class="logo-text">
                        <h1>JIADA Portal</h1>
                        <span>Jharkhand Industrial Area Development Authority</span>
                    </div>
                </a>
                
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <nav>
                    <ul class="nav-menu" id="navMenu">
                        <li><a href="<?php echo baseUrl('/'); ?>" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">Home</a></li>
                        <li><a href="<?php echo baseUrl('/land-bank'); ?>" class="<?php echo $currentPage === 'land-bank' ? 'active' : ''; ?>">Land Bank</a></li>
                        <li><a href="<?php echo baseUrl('/master-dashboard'); ?>" class="<?php echo $currentPage === 'master-dashboard' ? 'active' : ''; ?>">Master Dashboard</a></li>
                        <li><a href="<?php echo baseUrl('/applications'); ?>" class="<?php echo $currentPage === 'applications' ? 'active' : ''; ?>">Applications</a></li>
                        <li><a href="<?php echo baseUrl('/plug-play'); ?>" class="<?php echo $currentPage === 'plug-play' ? 'active' : ''; ?>">Plug & Play</a></li>
                        <li><a href="<?php echo baseUrl('/utilities'); ?>" class="<?php echo $currentPage === 'utilities' ? 'active' : ''; ?>">Utilities</a></li>
                        <li><a href="<?php echo baseUrl('/grievances'); ?>" class="<?php echo $currentPage === 'grievances' ? 'active' : ''; ?>">Grievances</a></li>
                        <li><a href="<?php echo baseUrl('/payments'); ?>" class="<?php echo $currentPage === 'payments' ? 'active' : ''; ?>">Payments</a></li>
                        <li><a href="<?php echo baseUrl('/services'); ?>" class="<?php echo $currentPage === 'services' ? 'active' : ''; ?>">Services</a></li>
                        <!-- <li><a href="<?php echo baseUrl('/whatsapp-bot'); ?>" class="<?php echo $currentPage === 'whatsapp-bot' ? 'active' : ''; ?>">WhatsApp</a></li> -->
                        <li><a href="<?php echo baseUrl('/contact'); ?>" class="<?php echo $currentPage === 'contact' ? 'active' : ''; ?>">Contact</a></li>
                    </ul>
                </nav>
                
                <!-- <div class="header-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="d-flex align-center gap-10">
                        <a href="<?php echo baseUrl('/notifications'); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-bell"></i>
                            <span class="badge badge-danger" id="notificationCount">0</span>
                        </a>
                        <a href="<?php echo baseUrl('/dashboard'); ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-user"></i> <span class="btn-text"><?php echo e($_SESSION['user_name'] ?? 'Dashboard'); ?></span>
                        </a>
                    </div>
                    <?php else: ?>
                    <a href="<?php echo baseUrl('/login'); ?>" class="btn btn-outline btn-sm">Login</a>
                    <a href="<?php echo baseUrl('/register'); ?>" class="btn btn-primary btn-sm">Register</a>
                    <?php endif; ?>
                </div> -->
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php echo $content ?? ''; ?>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h4><i class="fas fa-industry"></i> JIADA</h4>
                    <p>Jharkhand Industrial Area Development Authority - Your single window portal for industrial land allotment and services.</p>
                    <div class="d-flex gap-10 mt-20">
                        <a href="#"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#"><i class="fab fa-youtube fa-lg"></i></a>
                    </div>
                </div>
                <div>
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#">About JIADA</a></li>
                        <li><a href="#">Land Bank</a></li>
                        <li><a href="#">Plug & Play Units</a></li>
                        <li><a href="#">Tenders</a></li>
                        <li><a href="#">Notifications</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Services</h4>
                    <ul>
                        <li><a href="#">Land Allotment</a></li>
                        <li><a href="#">Lease Transfer</a></li>
                        <li><a href="#">Mortgage Rights</a></li>
                        <li><a href="#">Utility Services</a></li>
                        <li><a href="#">Grievance Redressal</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Contact Us</h4>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> Udyog Bhawan, Dhurwa, Ranchi - 834004</li>
                        <li><i class="fas fa-phone"></i> +91 651-223-4567</li>
                        <li><i class="fas fa-envelope"></i> info@jiada.in</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> JIADA, Government of Jharkhand. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

    <!-- JavaScript -->
    <script>
        // App Configuration
        const APP_CONFIG = {
            baseUrl: '<?php echo APP_URL; ?>',
            api: {
                baseUrl: '<?php echo APP_URL; ?>/api'
            },
            user: <?php echo isset($_SESSION['user_id']) ? json_encode(['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name'] ?? '', 'token' => $_SESSION['jwt_token'] ?? '']) : 'null'; ?>
        };
        
        // Utility Functions
        function showLoading() { document.getElementById('loading').classList.add('active'); }
        function hideLoading() { document.getElementById('loading').classList.remove('active'); }
        
        function showAlert(message, type = 'info') {
            const icons = { success: 'check-circle', danger: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
            const alertHtml = `<div class="alert alert-${type} alert-dismissible"><i class="fas fa-${icons[type]}"></i> ${message}<button type="button" onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;"><i class="fas fa-times"></i></button></div>`;
            const container = document.querySelector('.main-content .container') || document.querySelector('.main-content');
            container.insertAdjacentHTML('afterbegin', alertHtml);
            setTimeout(() => { const alert = container.querySelector('.alert'); if(alert) alert.remove(); }, 5000);
        }
        
        // API Helper
        async function apiCall(endpoint, method = 'GET', data = null) {
            showLoading();
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': APP_CONFIG.user?.token ? `Bearer ${APP_CONFIG.user.token}` : ''
                }
            };
            if (data && method !== 'GET') { options.body = JSON.stringify(data); }
            
            try {
                const response = await fetch(`${APP_CONFIG.api.baseUrl}${endpoint}`, options);
                const result = await response.json();
                hideLoading();
                return result;
            } catch (error) {
                hideLoading();
                showAlert('Network error. Please try again.', 'danger');
                return { success: false, message: 'Network error' };
            }
        }

        // Ensure jQuery requests carry auth token when available
        if (window.jQuery && APP_CONFIG.user?.token) {
            $.ajaxSetup({
                headers: { 'Authorization': `Bearer ${APP_CONFIG.user.token}` }
            });
        }
        
        // Mobile Menu
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('navMenu').classList.toggle('active');
        });
        
        // Date Time Display
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-IN', options);
        }
        updateDateTime();
        setInterval(updateDateTime, 60000);
        
        // Get notification count
        if (APP_CONFIG.user?.id) {
            apiCall('/notifications/unread-count').then(result => {
                if (result.data) {
                    const count = document.getElementById('notificationCount');
                    if (count) count.textContent = result.data.count || 0;
                }
            });
        }
    </script>
    
    <?php echo $scripts ?? ''; ?>
</body>
</html>
