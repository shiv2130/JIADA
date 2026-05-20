<?php
$pageTitle = "Lease Services - Transfer, Mortgage, Surrender";
$currentPage = 'services';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="text-center mb-20">
        <h2><i class="fas fa-exchange-alt"></i> Lease Services</h2>
        <p style="color: var(--gray);">Manage your lease rights - Transfer, Mortgage, Surrender, and Renewal</p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px;">
        <div class="card" style="padding: 30px; text-align: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(26, 82, 118, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-exchange-alt fa-2x" style="color: var(--primary);"></i>
            </div>
            <h3>Transfer of Lease</h3>
            <p style="color: var(--gray);">Transfer your lease hold rights to another party. Premium: 15% (MSME) or 25% (others) of land value.</p>
            <a href="<?php echo APP_URL; ?>/lease-transfer" class="btn btn-primary">Apply for Transfer</a>
        </div>
        
        <div class="card" style="padding: 30px; text-align: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(39, 174, 96, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-hand-holding-usd fa-2x" style="color: var(--success);"></i>
            </div>
            <h3>Mortgage of Lease</h3>
            <p style="color: var(--gray);">Mortgage your lease hold rights for bank loans. Requires bank consent letter.</p>
            <a href="<?php echo APP_URL; ?>/lease-mortgage" class="btn btn-success">Apply for Mortgage</a>
        </div>
        
        <div class="card" style="padding: 30px; text-align: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(230, 126, 34, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-undo fa-2x" style="color: var(--secondary);"></i>
            </div>
            <h3>Surrender of Plot</h3>
            <p style="color: var(--gray);">Surrender your allotted plot. Refund: 75% (1yr), 65% (2yr), 55% (3yr) of premium.</p>
            <a href="<?php echo APP_URL; ?>/lease-surrender" class="btn btn-secondary">Apply for Surrender</a>
        </div>
        
        <div class="card" style="padding: 30px; text-align: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(52, 152, 219, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-sync-alt fa-2x" style="color: var(--info);"></i>
            </div>
            <h3>Lease Renewal</h3>
            <p style="color: var(--gray);">Renew your lease agreement before expiry. Apply 3 months in advance.</p>
            <a href="<?php echo APP_URL; ?>/lease-renewal" class="btn btn-sm" style="background: var(--info); color: white;">Apply for Renewal</a>
        </div>
        
        <div class="card" style="padding: 30px; text-align: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(155, 89, 182, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-object-group fa-2x" style="color: #9b59b6;"></i>
            </div>
            <h3>Merge / Demerge</h3>
            <p style="color: var(--gray);">Merge or demerge adjacent plots. Premium: 15% (MSME) or 25% (others).</p>
            <a href="<?php echo APP_URL; ?>/lease-merge" class="btn btn-sm" style="background: #9b59b6; color: white;">Apply for Merge/Demerge</a>
        </div>
        
        <div class="card" style="padding: 30px; text-align: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(231, 76, 60, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-users-cog fa-2x" style="color: var(--danger);"></i>
            </div>
            <h3>Change of Constitution</h3>
            <p style="color: var(--gray);">Update unit constitution with CA-certified documents.</p>
            <a href="<?php echo APP_URL; ?>/constitution-change" class="btn btn-sm" style="background: var(--danger); color: white;">Apply for Change</a>
        </div>
        
        <div class="card" style="padding: 30px; text-align: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(241, 196, 15, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-chart-pie fa-2x" style="color: #f1c40f;"></i>
            </div>
            <h3>Change in Shareholding</h3>
            <p style="color: var(--gray);">Update shareholding pattern with CA-certified documents.</p>
            <a href="<?php echo APP_URL; ?>/shareholding-change" class="btn btn-sm" style="background: #f1c40f; color: #333;">Apply for Change</a>
        </div>
        
        <div class="card" style="padding: 30px; text-align: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(149, 165, 166, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-ban fa-2x" style="color: var(--gray);"></i>
            </div>
            <h3>Cancellation</h3>
            <p style="color: var(--gray);">View cancellation notices and respond to authority queries.</p>
            <a href="<?php echo APP_URL; ?>/cancellation" class="btn btn-outline">View Status</a>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>