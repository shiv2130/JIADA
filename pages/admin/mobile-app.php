<?php
$pageTitle = "Mobile App - Admin";
$currentPage = 'mobile-app';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

ob_start();
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-mobile-alt"></i> Mobile App Management</h3></div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="card" style="padding: 20px; text-align: center;">
                <i class="fab fa-android fa-3x" style="color: #3ddc84;"></i>
                <h4>Android App</h4>
                <p>React Native WebView App</p>
                <button class="btn btn-success">Download APK</button>
            </div>
            <div class="card" style="padding: 20px; text-align: center;">
                <i class="fab fa-apple fa-3x" style="color: #555;"></i>
                <h4>iOS App</h4>
                <p>Coming Soon</p>
                <button class="btn btn-secondary" disabled>Not Available</button>
            </div>
        </div>
        <div style="margin-top: 20px;">
            <h4>Push Notifications</h4>
            <p>Send push notifications to all mobile app users.</p>
            <button class="btn btn-primary">Send Notification</button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>