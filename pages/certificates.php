<?php
$pageTitle = "Certificates & Documents";
$currentPage = 'certificates';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-certificate"></i> Certificates & Documents</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                <div class="card" style="padding: 25px; text-align: center;">
                    <i class="fas fa-file-contract fa-3x" style="color: var(--primary); margin-bottom: 15px;"></i>
                    <h4>Allotment Letters</h4>
                    <p style="color: var(--gray);">Download your land/unit allotment letters</p>
                    <a href="<?php echo APP_URL; ?>/api/certificates/allotment" class="btn btn-primary btn-sm">Download</a>
                </div>
                <div class="card" style="padding: 25px; text-align: center;">
                    <i class="fas fa-receipt fa-3x" style="color: var(--success); margin-bottom: 15px;"></i>
                    <h4>Payment Receipts</h4>
                    <p style="color: var(--gray);">Download payment receipts and invoices</p>
                    <a href="<?php echo APP_URL; ?>/payments" class="btn btn-success btn-sm">View Payments</a>
                </div>
                <div class="card" style="padding: 25px; text-align: center;">
                    <i class="fas fa-id-card fa-3x" style="color: var(--info); margin-bottom: 15px;"></i>
                    <h4>Lease Agreements</h4>
                    <p style="color: var(--gray);">Download signed lease agreements</p>
                    <a href="<?php echo APP_URL; ?>/api/certificates/lease" class="btn btn-sm" style="background: var(--info); color: white;">Download</a>
                </div>
                <div class="card" style="padding: 25px; text-align: center;">
                    <i class="fas fa-stamp fa-3x" style="color: var(--secondary); margin-bottom: 15px;"></i>
                    <h4>NOC Certificates</h4>
                    <p style="color: var(--gray);">Download No Objection Certificates</p>
                    <a href="<?php echo APP_URL; ?>/api/certificates/noc" class="btn btn-secondary btn-sm">Download</a>
                </div>
                <div class="card" style="padding: 25px; text-align: center;">
                    <i class="fas fa-building fa-3x" style="color: #9b59b6; margin-bottom: 15px;"></i>
                    <h4>Constitution Certificate</h4>
                    <p style="color: var(--gray);">Download unit constitution documents</p>
                    <a href="<?php echo APP_URL; ?>/api/certificates/constitution" class="btn btn-sm" style="background: #9b59b6; color: white;">Download</a>
                </div>
                <div class="card" style="padding: 25px; text-align: center;">
                    <i class="fas fa-chart-pie fa-3x" style="color: #f1c40f; margin-bottom: 15px;"></i>
                    <h4>Shareholding Certificate</h4>
                    <p style="color: var(--gray);">Download shareholding pattern documents</p>
                    <a href="<?php echo APP_URL; ?>/api/certificates/shareholding" class="btn btn-sm" style="background: #f1c40f; color: #333;">Download</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>