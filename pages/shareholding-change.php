<?php
$pageTitle = "Change in Shareholding - JIADA Portal";
$currentPage = 'shareholding-change';
ob_start();
?>
<div class="container" style="max-width: 800px; padding-top: 30px;">
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-chart-pie"></i> Change in Shareholding (CIS)</h2></div>
        <div class="card-body">
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> Update shareholding pattern with CA-certified documents.</div>
            <form id="shareholdingForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Lease No.</label><input type="text" name="lease_no" class="form-control" required></div>
                    <div class="form-group"><label>Change Type</label><select name="change_type" class="form-control" required><option value="transfer">Transfer</option><option value="addition">Addition</option><option value="removal">Removal</option><option value="restructuring">Restructuring</option></select></div>
                </div>
                <div class="form-group"><label>Old Shareholding (JSON or notes)</label><textarea name="old_shareholding" class="form-control" rows="3" placeholder='{"partner":"X","share":40}'></textarea></div>
                <div class="form-group"><label>New Shareholding (JSON or notes)</label><textarea name="new_shareholding" class="form-control" rows="3" placeholder='{"partner":"Y","share":60}'></textarea></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Blood Relative % (Before)</label><input type="number" name="blood_relative_before" class="form-control" step="0.01" required></div>
                    <div class="form-group"><label>Blood Relative % (After)</label><input type="number" name="blood_relative_after" class="form-control" step="0.01" required></div>
                </div>
                <div class="form-group"><label>CA-Certified Shareholding Documents</label><input type="file" name="documents[]" class="form-control" multiple required></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Application</button>
            </form>
        </div>
    </div>
</div>
<script>
$('#shareholdingForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: '<?php echo APP_URL; ?>/api/shareholding/change',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                showAlert('Application submitted. Ref: ' + res.data.application_no, 'success');
                $('#shareholdingForm')[0].reset();
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
