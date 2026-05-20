<?php
$pageTitle = "Lease Renewal - JIADA Portal";
$currentPage = 'lease-renewal';
ob_start();
?>
<div class="container" style="max-width: 800px; padding-top: 30px;">
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-sync-alt"></i> Lease Renewal</h2></div>
        <div class="card-body">
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> Renew your lease agreement before expiry. Apply 3 months in advance.</div>
            <form id="renewalForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Lease Agreement No.</label><input type="text" name="lease_no" class="form-control" required></div>
                    <div class="form-group"><label>Renewal Period (years)</label><select name="renewal_period" class="form-control" required><option value="5">5 Years</option><option value="10">10 Years</option><option value="15">15 Years</option><option value="20">20 Years</option><option value="30">30 Years</option></select></div>
                </div>
                <div class="form-group"><label>Reason for Renewal</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
                <div class="form-group"><label>Supporting Documents</label><input type="file" name="documents[]" class="form-control" multiple></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Renewal Application</button>
            </form>
        </div>
    </div>
</div>
<script>
$('#renewalForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: '<?php echo APP_URL; ?>/api/renewal/apply',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                showAlert('Renewal application submitted. Ref: ' + res.data.application_no, 'success');
                $('#renewalForm')[0].reset();
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
