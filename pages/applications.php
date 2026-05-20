<?php
$pageTitle = "My Applications";
$currentPage = 'applications';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-file-alt"></i> My Applications</h2>
            <div style="display: flex; gap: 10px;">
                <select id="typeFilter" class="form-control" style="width: 150px;">
                    <option value="all">All Types</option>
                    <option value="land">Land Allotment</option>
                    <option value="plugplay">Plug & Play</option>
                    <option value="lease">Lease Services</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div id="applicationsList"></div>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    function loadApplications() {
        $.get('<?php echo APP_URL; ?>/api/land/applications', function(res) {
            if(res.success) {
                let html = '';
                if(res.data.length === 0) { html = '<p class="text-center">No applications found.</p>'; }
                else {
                    html = '<div class="table-responsive"><table class="table"><thead><tr><th>Application#</th><th>Type</th><th>Plot/Unit</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>';
                    res.data.forEach(a => {
                        const appNo = a.application_number || a.application_no;
                        html += `<tr><td>${appNo}</td><td>Land Allotment</td><td>${a.plot_number || a.plot_no || '-'}</td>
                            <td><span class="badge badge-${a.status==='approved'?'success':a.status==='pending'?'warning':'danger'}">${a.status}</span></td>
                            <td>${new Date(a.created_at).toLocaleDateString()}</td>
                            <td><a href="<?php echo APP_URL; ?>/application-view?id=${a.id}" class="btn btn-sm btn-outline">View</a></td></tr>`;
                    });
                    html += '</tbody></table></div>';
                }
                $('#applicationsList').html(html);
            }
        });
    }
    loadApplications();
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
