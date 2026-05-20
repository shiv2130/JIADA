<?php
$pageTitle = "Change of Constitution - JIADA Portal";
$currentPage = 'constitution-change';
ob_start();
?>
<div class="container" style="max-width: 800px; padding-top: 30px;">
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-users-cog"></i> Change of Constitution of Unit</h2></div>
        <div class="card-body">
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> Update unit constitution with CA-certified documents.</div>
            <form id="constitutionForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Lease No.</label><input type="text" name="lease_no" class="form-control" required></div>
                    <div class="form-group"><label>Change Type</label><select name="change_type" class="form-control" required><option value="proprietor_to_partnership">Proprietor to Partnership</option><option value="partnership_to_company">Partnership to Company</option><option value="merger">Merger</option><option value="conversion">Conversion</option><option value="name_change">Name Change</option><option value="other">Other</option></select></div>
                </div>
                <div class="form-group"><label>Current Constitution Type</label><select name="old_constitution" class="form-control" required><option>Proprietorship</option><option>Partnership</option><option>Pvt Ltd</option><option>Public Ltd</option><option>LLP</option></select></div>
                <div class="form-group"><label>New Constitution Type</label><select name="new_constitution" class="form-control" required><option>Proprietorship</option><option>Partnership</option><option>Pvt Ltd</option><option>Public Ltd</option><option>LLP</option></select></div>
                <div class="form-group"><label>Reason for Change</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
                <div class="form-group"><label>CA-Certified Documents</label><input type="file" name="documents[]" class="form-control" multiple required></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Application</button>
            </form>
        </div>
    </div>
</div>
<script>
$('#constitutionForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: '<?php echo APP_URL; ?>/api/constitution/change',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                showAlert('Application submitted. Ref: ' + res.data.application_no, 'success');
                $('#constitutionForm')[0].reset();
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
