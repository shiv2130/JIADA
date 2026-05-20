<?php
$pageTitle = "Cancellation of Allotted Plot - JIADA Portal";
$currentPage = 'cancellation';
ob_start();
?>
<div class="container" style="max-width: 800px; padding-top: 30px;">
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-ban"></i> Cancellation of Allotted Plot</h2></div>
        <div class="card-body">
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> View cancellation notices and respond to authority queries. Max 2 clarification cycles allowed.</div>
            <div id="cancellationNotices">
                <h3>Active Cancellation Notices</h3>
                <div class="alert alert-info">Loading notices...</div>
            </div>
            <div style="margin-top: 30px;">
                <h3>Respond to Notice</h3>
                <form id="cancellationResponseForm">
                    <div class="form-group"><label>Notice Reference No.</label><input type="text" name="notice_number" class="form-control" required></div>
                    <div class="form-group"><label>Justification / Response</label><textarea name="response" class="form-control" rows="4" required></textarea></div>
                    <div class="form-group"><label>Supporting Documents</label><input type="file" name="documents[]" class="form-control" multiple></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Response</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function loadNotices() {
    $.get('<?php echo APP_URL; ?>/api/cancellation/list', function(res) {
        if (res.success) {
            let html = '<h3>Active Cancellation Notices</h3>';
            if (res.data.length === 0) {
                html += '<div class="alert alert-info">No active cancellation notices found.</div>';
            } else {
                html += '<div class="table-responsive"><table class="table"><thead><tr><th>Notice#</th><th>Lease No.</th><th>Status</th><th>Reason</th><th>Date</th></tr></thead><tbody>';
                res.data.forEach(n => {
                    html += `<tr><td>${n.notice_number}</td><td>${n.lease_no || '-'}</td><td>${n.status}</td><td>${n.reason_category}</td><td>${new Date(n.created_at).toLocaleDateString()}</td></tr>`;
                });
                html += '</tbody></table></div>';
            }
            $('#cancellationNotices').html(html);
        }
    });
}

$('#cancellationResponseForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: '<?php echo APP_URL; ?>/api/cancellation/respond',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                showAlert('Response submitted', 'success');
                $('#cancellationResponseForm')[0].reset();
                loadNotices();
            } else {
                showAlert(res.message || 'Submission failed', 'danger');
            }
        },
        error: function() { showAlert('Network error. Please try again.', 'danger'); }
    });
});

loadNotices();
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
