<?php
if (!defined('JIADA_APP')) define('JIADA_APP', true);
require_once __DIR__ . '/../../config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$userRole = $_SESSION['user_type'];
$userName = $_SESSION['user_name'] ?? 'Admin';
$currentPage = $_GET['page'] ?? '';
$pageTitle = ucfirst(str_replace('/', ' ', trim($currentPage, '/'))) . ' - Admin';
$unreadNotif = 0;

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-tools"></i> <?php echo strtoupper($pageTitle); ?></h3>
        <button class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add New</button>
    </div>
    <div class="card-body">
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> This module is under development. Core features like Dashboard, Users, Applications, Payments, Grievances, and Utilities are fully functional.</div>
        <p>Module: <strong><?php echo e($currentPage); ?></strong></p>
        <p>User Role: <strong><?php echo ucfirst($userRole); ?></strong></p>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>