<?php
// Single Window SSO callback placeholder
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';

$pageTitle = "Single Window Login";
$currentPage = 'login';

ob_start();
?>
<div class="container" style="padding-top: 30px; max-width: 720px;">
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-shield-alt"></i> Single Window Login</h2></div>
        <div class="card-body">
            <div class="alert alert-info">
                Single Window authentication callback received. Integration is pending token verification and user provisioning.
            </div>
            <p>When you provide the Single Window SSO specs (token fields, signature, user payload), we will complete the automatic login and user mapping.</p>
            <a class="btn btn-primary" href="<?php echo APP_URL; ?>/login">Go to Login</a>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
