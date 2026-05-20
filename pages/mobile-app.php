<?php
$pageTitle = "JIADA Mobile App";
$currentPage = 'mobile-app';
ob_start();
?>
<div class="container" style="padding-top: 30px; max-width: 900px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-mobile-alt"></i> JIADA Mobile App</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> The mobile app offers land allotment, payments, grievances, utility tickets, and certificate downloads on the go.
            </div>
            <h3>Features</h3>
            <ul>
                <li>Land & Plug & Play applications with document upload</li>
                <li>Status tracking and payment reminders</li>
                <li>Grievance and utility ticket management</li>
                <li>GIS-based land search and navigation</li>
                <li>Secure login with device PIN/biometric</li>
            </ul>
            <div class="mt-20">
                <button class="btn btn-primary"><i class="fab fa-android"></i> Android (Coming Soon)</button>
                <button class="btn btn-outline"><i class="fab fa-apple"></i> iOS (Optional)</button>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
