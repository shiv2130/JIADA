<?php
$pageTitle = "Plug & Play Application";
$currentPage = 'plug-play';

$unitId = intval($_GET['unit'] ?? 0);
ob_start();
?>
<div class="container" style="padding-top: 30px; max-width: 900px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-industry"></i> Plug & Play Unit Application</h2>
            <a href="<?php echo baseUrl('/plug-play'); ?>" class="btn btn-outline btn-sm">Back to Units</a>
        </div>
        <div class="card-body">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="alert alert-warning"><i class="fas fa-lock"></i> Please login to submit an application.</div>
                <a class="btn btn-primary" href="<?php echo baseUrl('/login'); ?>">Login</a>
            <?php else: ?>
                <div id="unitInfo" class="alert alert-info">Loading unit details...</div>
                <form id="plugPlayForm">
                    <input type="hidden" name="unit_id" id="unitIdField" value="<?php echo $unitId; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Company Name <span class="required">*</span></label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Industry Type</label>
                            <input type="text" name="industry_type" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Proposed Investment (₹)</label>
                        <input type="number" name="investment_amount" class="form-control" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Upload Documents (max 5 files: PDF/JPG/PNG, 5MB each)</label>
                        <input type="file" name="documents[]" class="form-control" multiple>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Application</button>
                </form>
                <div id="rentBox" class="card mt-20" style="display:none; padding: 20px;">
                    <h3>Six Months Rent Due</h3>
                    <p id="rentText"></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const unitId = parseInt($('#unitIdField').val(), 10);
    if (unitId) {
        $.get('<?php echo APP_URL; ?>/api/plugplay/units', {status: '', limit: 1, page: 1}, function(res) {
            if (res.success) {
                const unit = (res.data || []).find(u => parseInt(u.id, 10) === unitId);
                if (unit) {
                    $('#unitInfo').html(`<strong>Unit:</strong> ${unit.unit_no} | <strong>Area:</strong> ${unit.area_sqft} sqft | <strong>Rent:</strong> ₹${parseInt(unit.monthly_rent).toLocaleString()}/month`);
                } else {
                    $('#unitInfo').removeClass('alert-info').addClass('alert-warning').text('Unit not found.');
                }
            }
        });
    } else {
        $('#unitInfo').removeClass('alert-info').addClass('alert-warning').text('No unit selected. Please choose a unit from Plug & Play.');
    }

    $('#plugPlayForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const unitIdVal = parseInt($('#unitIdField').val(), 10);
        const company = $('[name=\"company_name\"]').val().trim();
        const industry = $('[name=\"industry_type\"]').val().trim();
        const investment = parseFloat($('[name=\"investment_amount\"]').val() || '0');
        const files = $('[name=\"documents[]\"]')[0].files;

        if (!unitIdVal || unitIdVal < 1) { showAlert('Select a valid unit from Plug & Play.', 'danger'); return; }
        if (company.length < 3 || company.length > 150) { showAlert('Company name must be 3-150 characters.', 'danger'); return; }
        if (industry.length > 100) { showAlert('Industry type is too long.', 'danger'); return; }
        if (investment < 0) { showAlert('Investment amount cannot be negative.', 'danger'); return; }
        if (files.length > 5) { showAlert('You can upload up to 5 files.', 'danger'); return; }
        const allowed = ['application/pdf','image/jpeg','image/png'];
        for (let i = 0; i < files.length; i++) {
            if (files[i].size > 5 * 1024 * 1024) { showAlert('Each file must be under 5 MB.', 'danger'); return; }
            if (!allowed.includes(files[i].type)) { showAlert('Only PDF, JPG or PNG files are allowed.', 'danger'); return; }
        }

        $.ajax({
            url: '<?php echo APP_URL; ?>/api/plugplay/apply',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    showAlert('Application submitted successfully. Reference: ' + res.data.application_no, 'success');
                    $('#rentText').text('Six months rent due: ₹' + (res.data.six_month_rent_due || 0).toLocaleString());
                    $('#rentBox').show();
                } else {
                    showAlert(res.message || 'Submission failed', 'danger');
                }
            },
            error: function() { showAlert('Network error. Please try again.', 'danger'); }
        });
    });
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
