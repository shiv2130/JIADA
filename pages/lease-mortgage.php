<?php
$pageTitle = "Mortgage of Lease Hold Rights - JIADA Portal";
$currentPage = 'lease-mortgage';
ob_start();
?>
<div class="container" style="max-width: 800px; padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-hand-holding-usd"></i> Mortgage of Lease Hold Rights</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> Mortgage your lease hold rights for bank loans. Requires bank consent letter.</div>
            <form id="mortgageForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Lease Agreement No.</label><input type="text" name="lease_no" class="form-control" required></div>
                    <div class="form-group"><label>Bank Name</label><select name="bank_name" class="form-control" required><option value="">Select Bank</option><option>SBI</option><option>PNB</option><option>BOB</option><option>HDFC</option><option>ICICI</option><option>Axis Bank</option><option>Canara Bank</option></select></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Bank Branch</label><input type="text" name="bank_branch" class="form-control" required></div>
                    <div class="form-group"><label>Loan Amount (₹)</label><input type="number" name="loan_amount" class="form-control" required></div>
                </div>
                <div class="form-group"><label>Loan Tenure (months)</label><input type="number" name="loan_tenure" class="form-control" required></div>
                <div class="form-group"><label>Purpose</label><textarea name="purpose" class="form-control" rows="3" required></textarea></div>
                <div class="form-group"><label>Bank Consent Letter</label><input type="file" name="consent_letter" class="form-control" required></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Application</button>
            </form>
        </div>
    </div>
</div>
<script>
$('#mortgageForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    $.ajax({
        url: '<?php echo APP_URL; ?>/api/lease/mortgage',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            if (res.success) {
                showAlert('Mortgage application submitted. Ref: ' + res.data.application_no, 'success');
                $('#mortgageForm')[0].reset();
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
