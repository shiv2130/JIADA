<?php
$pageTitle = "Merge/Demerge/Amalgamate - JIADA Portal";
$currentPage = 'lease-merge';
ob_start();
?>
<div class="container" style="max-width: 800px; padding-top: 30px;">
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-object-group"></i> Merge / Demerge / Amalgamate</h2></div>
        <div class="card-body">
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> Merge or demerge adjacent plots. Premium: 15% (MSME) or 25% (others) of land value.</div>
            <form id="mergeForm">
                <div class="form-group"><label>Operation Type</label><select name="operation" class="form-control" required><option value="merge">Merge</option><option value="demerge">Demerge</option><option value="amalgamate">Amalgamate</option></select></div>
                <div class="form-group"><label>Primary Lease No.</label><input type="text" name="primary_lease_no" class="form-control" required></div>
                <div class="form-group"><label>Secondary Lease Nos (comma separated)</label><input type="text" name="secondary_lease_nos" class="form-control" placeholder="e.g., L-1002, L-1003"></div>
                <div class="form-group"><label>Plot IDs (comma separated)</label><input type="text" name="plot_ids" class="form-control" placeholder="e.g., 12, 13" required></div>
                <div class="form-group"><label>Reason</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
                <div class="form-group"><label>Are you MSME?</label><select name="is_msme" class="form-control"><option value="0">No</option><option value="1">Yes (MSME)</option></select></div>
                <div class="form-group"><label>NOCs & Supporting Documents (PDF/JPG/PNG, max 5 files, 5MB each)</label><input type="file" name="documents[]" class="form-control" multiple required></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Application</button>
            </form>
        </div>
    </div>
</div>
<script>
$('#mergeForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const files = $('[name="documents[]"]')[0].files;
    if (files.length > 5) { showAlert('You can upload up to 5 documents.', 'danger'); return; }
    const allowed = ['application/pdf','image/jpeg','image/png'];
    for (let i = 0; i < files.length; i++) {
        if (files[i].size > 5 * 1024 * 1024) { showAlert('Each file must be under 5 MB.', 'danger'); return; }
        if (!allowed.includes(files[i].type)) { showAlert('Only PDF, JPG or PNG files are allowed.', 'danger'); return; }
    }
    $.ajax({
        url: '<?php echo APP_URL; ?>/api/lease/modify',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                const premium = res.data && res.data.calculated_premium ? ' | Premium: ₹' + parseFloat(res.data.calculated_premium).toLocaleString() : '';
                showAlert('Request submitted successfully' + premium, 'success');
                $('#mergeForm')[0].reset();
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
