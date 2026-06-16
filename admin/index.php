
<?php
/**
 * JIADA Admin - Direct entry point (no URL rewrite needed)
 */
define('JIADA_APP', true);
require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Get the page requested (default: login)
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// Route admin pages
switch ($page) {
    case 'login':
        if (isset($_SESSION['admin_id'])) {
            header('Location: ' . APP_URL . '/admin/?page=dashboard');
            exit;
        }
        // Direct include without ob_start here
        $pageTitle = 'Admin Login';
        $error = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $db = getDB();
            $user = $db->fetchOne("SELECT * FROM users WHERE (email = ? OR mobile = ?) AND user_type IN ('superadmin', 'admin', 'jiada_officer', 'regional_officer', 'do', 'rdd', 'rd', 'counter_clerk', 'ieo')", [$email, $email]);
            
            if (!$user) {
                $error = 'Invalid credentials. User not found.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Your account is ' . str_replace('_', ' ', $user['status']) . '. Contact administrator.';
            } elseif (!password_verify($password, $user['password'])) {
                $error = 'Invalid password.';
            } else {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['region_id'] = $user['region_id'] ?? null;
                $db->update('users', ['last_login' => date('Y-m-d H:i:s'), 'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'], 'id = ?', [$user['id']]);
                header('Location: ' . APP_URL . '/admin/?page=dashboard');
                exit;
            }
        }
        
        // Output login directly
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $pageTitle; ?></title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: 'Inter', sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .login-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
                .login-card { width: 420px; background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
                .login-header { background: linear-gradient(135deg, #1a5276, #2980b9); color: #fff; padding: 40px 30px; text-align: center; }
                .login-header .logo { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
                .login-header h1 { font-size: 22px; margin-bottom: 5px; }
                .login-header p { font-size: 13px; opacity: 0.85; }
                .login-body { padding: 35px 30px; }
                .form-group { margin-bottom: 20px; }
                .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; }
                .input-group { position: relative; }
                .input-group i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #95a5a6; }
                .form-control { width: 100%; padding: 12px 14px 12px 40px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
                .form-control:focus { outline: none; border-color: #1a5276; }
                .btn { width: 100%; padding: 13px; background: #1a5276; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; }
                .btn:hover { background: #2980b9; }
                .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
                .alert-danger { background: #fce8e6; color: #c0392b; border-left: 4px solid #c0392b; }
                .alert-info { background: #e8f4fd; color: #2980b9; border-left: 4px solid #2980b9; }
                .login-footer { text-align: center; padding: 20px; border-top: 1px solid #f0f0f0; }
                .login-footer a { color: #1a5276; text-decoration: none; font-size: 13px; }
            </style>
        </head>
        <body>
        <div class="login-wrapper">
            <div class="login-card">
                <div class="login-header">
                    <div class="logo"><i class="fas fa-building" style="font-size:28px;"></i></div>
                    <h1>JIADA Admin Portal</h1>
                    <p>Internal Management System</p>
                </div>
                <div class="login-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Default: admin@jiada.in / admin123</div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Email Address or Mobile</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="email" class="form-control" placeholder="Enter email or mobile" required autofocus>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Sign In</button>
                    </form>
                    <div class="login-footer">
                        <a href="<?php echo APP_URL; ?>/?q=home"><i class="fas fa-arrow-left"></i> Back to Public Portal</a>
                    </div>
                </div>
            </div>
        </div>
        </body>
        </html>
        <?php
        exit;
        
    case 'dashboard':
        if (!isset($_SESSION['admin_id'])) {
            header('Location: ' . APP_URL . '/admin/?page=login');
            exit;
        }
        include __DIR__ . '/../pages/admin/dashboard.php';
        break;
        
    case 'users':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/users.php';
        break;
        
    case 'land-bank':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/land-bank.php';
        break;

    case 'regions':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/regions.php';
        break;

    case 'master-dashboard':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/master-dashboard.php';
        break;
        
    case 'applications':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/applications.php';
        break;
        
    case 'grievances':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/grievances.php';
        break;

    case 'grievance-crm':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/grievance-crm.php';
        break;
        
    case 'payments':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/payments.php';
        break;
        
    case 'utilities':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/utilities.php';
        break;
        
    case 'court-cases':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/court-cases.php';
        break;
        
    case 'bids':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/bids.php';
        break;
    
    case 'allotments':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/allotments.php';
        break;

    case 'possession':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/possession.php';
        break;

    case 'registry':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/registry.php';
        break;
        
    case 'lease':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/lease.php';
        break;
        
    case 'hrms':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/hrms.php';
        break;
        
    case 'surveys':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/surveys.php';
        break;
        
    case 'reports':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/reports.php';
        break;
        
    case 'audit-log':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/audit-log.php';
        break;
        
    case 'settings':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/settings.php';
        break;
        
    case 'mobile-app':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/mobile-app.php';
        break;
        
    case 'notifications':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/notifications.php';
        break;
        
    case 'profile':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/profile.php';
        break;
        
    case 'plug-play':
        if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }
        include __DIR__ . '/../pages/admin/plug-play.php';
        break;
        
    case 'logout':
        $token = $_SESSION['jwt_token'] ?? null;
        if ($token) { require_once __DIR__ . '/../core/JWT.php'; JWT::blacklistToken($token); }
        session_destroy();
        header('Location: ' . APP_URL . '/admin/?page=login');
        exit;
        
    default:
        if (!isset($_SESSION['admin_id']) && $page !== 'login') {
            header('Location: ' . APP_URL . '/admin/?page=login');
            exit;
        }
        include __DIR__ . '/../pages/admin/generic.php';
        break;
}
?>
