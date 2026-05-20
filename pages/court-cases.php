 <?php
$pageTitle = "Court Cases";
$currentPage = 'court-cases';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-balance-scale"></i> Court Cases</h2>
            <p style="color: var(--gray);">Legal case tracking and compliance management</p>
        </div>
        <div class="card-body">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="alert alert-warning"><i class="fas fa-lock"></i> Please login to view or manage court cases.</div>
            <?php else: ?>
                <div class="form-row">
                    <div class="form-group"><input type="text" id="caseSearch" class="form-control" placeholder="Search by CNR/subject/petitioner"></div>
                    <div class="form-group"><button class="btn btn-outline" id="caseSearchBtn"><i class="fas fa-search"></i> Search</button></div>
                </div>
                <div id="caseList"></div>
                <hr style="margin: 30px 0;">
                <h3>Add / Update Case</h3>
                <form id="caseForm">
                    <div class="form-row">
                        <div class="form-group"><label>Case Number</label><input type="text" name="case_number" class="form-control" required></div>
                        <div class="form-group"><label>CNR Number</label><input type="text" name="cnr_number" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Court Name</label><input type="text" name="court_name" class="form-control" required></div>
                        <div class="form-group"><label>Case Type</label><input type="text" name="case_type" class="form-control" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" required></div>
                        <div class="form-group"><label>Next Hearing</label><input type="date" name="next_hearing_date" class="form-control"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Petitioner</label><input type="text" name="petitioner_name" class="form-control"></div>
                        <div class="form-group"><label>Respondent</label><input type="text" name="respondent_name" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Status</label><select name="case_status" class="form-control"><option value="pending">Pending</option><option value="in_progress">In Progress</option><option value="disposed">Disposed</option></select></div>
                    <div class="form-group"><label>Last Hearing Order</label><textarea name="last_hearing_order" class="form-control" rows="3"></textarea></div>
                    <button type="submit" class="btn btn-primary">Save Case</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if (isset($_SESSION['user_id'])): ?>
<script>
function loadCases() {
    $.get('<?php echo APP_URL; ?>/api/court-cases', {search: $('#caseSearch').val()}, function(res) {
        if (res.success) {
            let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Case#</th><th>CNR</th><th>Subject</th><th>Status</th><th>Next Hearing</th></tr></thead><tbody>';
            if (res.data.length === 0) {
                html += '<tr><td colspan="5" class="text-center">No cases found</td></tr>';
            } else {
                res.data.forEach(c => {
                    html += `<tr><td>${c.case_number}</td><td>${c.cnr_number || '-'}</td><td>${c.subject}</td><td>${c.case_status}</td><td>${c.next_hearing_date || '-'}</td></tr>`;
                });
            }
            html += '</tbody></table></div>';
            $('#caseList').html(html);
        } else {
            showAlert(res.message || 'Unable to load cases', 'danger');
        }
    });
}

$('#caseSearchBtn').on('click', function() { loadCases(); });
$('#caseForm').on('submit', function(e) {
    e.preventDefault();
    $.post('<?php echo APP_URL; ?>/api/court-case/add', $(this).serialize(), function(res) {
        if (res.success) {
            showAlert('Case saved', 'success');
            $('#caseForm')[0].reset();
            loadCases();
        } else {
            showAlert(res.message || 'Save failed', 'danger');
        }
    });
});

loadCases();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
