<?php
$pageTitle = "Surrender of Allotted Plot - JIADA Portal";
$currentPage = 'lease-surrender';
ob_start();
?>
<div class="container" style="max-width: 800px; padding-top: 30px;">
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-undo"></i> Surrender of Allotted Plot</h2></div>
        <div class="card-body">
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> Surrender your allotted plot. Refund: 75% (within 1yr), 65% (within 2yrs), 55% (within 3yrs). Minimum 3 months notice required.</div>
            <form id="surrenderForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Lease Agreement No.</label><input type="text" name="lease_no" class="form-control" required></div>
                    <div class="form-group"><label>Plot No.</label><input type="text" name="plot_no" class="form-control" required></div>
                </div>
                <div class="form-group"><label>Reason for Surrender</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
                <div class="form-group"><label>Notice Date</label><input type="date" name="notice_date" class="form-control" required></div>
                <div class="form-group"><label>Supporting Documents (PDF/JPG/PNG up to 5MB, max 3 files)</label><input type="file" name="documents[]" class="form-control" multiple></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Surrender Application</button>
            </form>
        </div>
    </div>
</div>
<script>
$('#surrenderForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const reason = $('[name="reason"]').val().trim();
    const notice = $('[name="notice_date"]').val();
    const files = $('[name="documents[]"]')[0].files;
    if (reason.length < 10) { showAlert('Reason must be at least 10 characters.', 'danger'); return; }
    if (!notice) { showAlert('Notice date is required.', 'danger'); return; }
    if (files.length > 3) { showAlert('You can upload up to 3 files.', 'danger'); return; }
    const allowed = ['application/pdf','image/jpeg','image/png'];
    for (let i = 0; i < files.length; i++) {
        if (files[i].size > 5 * 1024 * 1024) { showAlert('Each file must be under 5 MB.', 'danger'); return; }
        if (!allowed.includes(files[i].type)) { showAlert('Only PDF, JPG or PNG files are allowed.', 'danger'); return; }
    }
    $.ajax({
        url: '<?php echo APP_URL; ?>/api/lease/surrender',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                showAlert('Surrender application submitted. Refund: ' + res.data.refund_percent + '%', 'success');
                $('#surrenderForm')[0].reset();
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
