<?php
$pageTitle = "Transfer of Lease Hold Rights - JIADA Portal";
$currentPage = 'lease-transfer';
ob_start();
?>
<div class="container" style="max-width: 800px; padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exchange-alt"></i> Transfer of Lease Hold Rights</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Transfer your lease hold rights to another party. Premium: 15% (MSME) or 25% (others) of prevailing land premium.
            </div>
            <form id="transferForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Lease Agreement No.</label><input type="text" name="lease_no" class="form-control" required></div>
                    <div class="form-group"><label>Transferee Name</label><input type="text" name="to_user_name" class="form-control" required></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Transferee Mobile</label><input type="tel" name="to_user_mobile" class="form-control" required></div>
                    <div class="form-group"><label>Transferee Email</label><input type="email" name="to_user_email" class="form-control"></div>
                </div>
                <div class="form-group"><label>Transfer Reason</label><textarea name="transfer_reason" class="form-control" rows="3" required></textarea></div>
                <div class="form-group"><label>Consideration Amount (₹)</label><input type="number" name="consideration_amount" class="form-control" required></div>
                <div class="form-group"><label>Is MSME?</label><select name="is_msme" class="form-control"><option value="0">No</option><option value="1">Yes</option></select></div>
                <div class="form-group"><label>Supporting Documents (PDF/JPG/PNG, max 5 files, 5MB each)</label><input type="file" name="documents[]" class="form-control" multiple></div>
                <div class="form-group" id="premiumPreview" style="display:none;">
                    <div class="alert alert-secondary">Estimated premium: <strong id="premiumValue">₹0</strong></div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Application</button>
            </form>
        </div>
    </div>
</div>
<script>
$('#transferForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const leaseNo = $('[name="lease_no"]').val().trim();
    const name = $('[name="to_user_name"]').val().trim();
    const mobile = $('[name="to_user_mobile"]').val().trim();
    const email = $('[name="to_user_email"]').val().trim();
    const reason = $('[name="transfer_reason"]').val().trim();
    const consideration = parseFloat($('[name="consideration_amount"]').val() || '0');
    const isMsme = $('[name="is_msme"]').val() === '1';
    const files = $('[name="documents[]"]')[0].files;
    const allowed = ['application/pdf','image/jpeg','image/png'];

    if (!leaseNo) { showAlert('Lease number is required.', 'danger'); return; }
    if (name.length < 3 || name.length > 150) { showAlert('Transferee name must be 3-150 characters.', 'danger'); return; }
    if (!/^[0-9]{8,15}$/.test(mobile)) { showAlert('Enter a valid mobile (8-15 digits).', 'danger'); return; }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showAlert('Enter a valid email.', 'danger'); return; }
    if (reason.length < 10) { showAlert('Reason must be at least 10 characters.', 'danger'); return; }
    if (consideration <= 0) { showAlert('Consideration must be greater than zero.', 'danger'); return; }
    if (files.length > 5) { showAlert('You can upload up to 5 documents.', 'danger'); return; }
    for (let i = 0; i < files.length; i++) {
        if (files[i].size > 5 * 1024 * 1024) { showAlert('Each document must be under 5 MB.', 'danger'); return; }
        if (!allowed.includes(files[i].type)) { showAlert('Only PDF, JPG or PNG files are allowed.', 'danger'); return; }
    }

    // Premium preview (server recalculates using lease premium if available)
    const premiumPercent = isMsme ? 15 : 25;
    const estimatedPremium = (consideration * premiumPercent) / 100;
    $('#premiumValue').text('₹' + estimatedPremium.toLocaleString());
    $('#premiumPreview').show();

    $.ajax({
        url: '<?php echo APP_URL; ?>/api/lease/transfer',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                const premium = res.data && res.data.premium ? res.data.premium : estimatedPremium;
                showAlert('Transfer application submitted. Premium: ₹' + premium.toLocaleString(), 'success');
                $('#transferForm')[0].reset();
                $('#premiumPreview').hide();
            } else {
                showAlert(res.message || 'Submission failed', 'danger');
            }
        },
        error: function() { showAlert('Network error. Please try again.', 'danger'); }
    });
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
