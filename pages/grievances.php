<?php
$pageTitle = "Grievance Redressal - File & Track Complaints";
$currentPage = 'grievances';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-headset"></i> Grievance Redressal System</h2>
            <button class="btn btn-danger" onclick="showGrievanceForm()"><i class="fas fa-plus"></i> File Grievance</button>
        </div>
        <div class="card-body">
            <div id="grievanceForm" style="display: none; margin-bottom: 30px;">
                <h3>File New Grievance</h3>
                <form id="grievanceFormEl">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="service">Service</option>
                                <option value="billing">Billing</option>
                                <option value="allotment">Allotment</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="corruption">Corruption</option>
                                <option value="harassment">Harassment</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department" class="form-control" required>
                                <option value="">Select Department</option>
                                <option value="Land & Revenue">Land & Revenue</option>
                                <option value="Infrastructure">Infrastructure</option>
                                <option value="Finance">Finance</option>
                                <option value="Administration">Administration</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="Brief description" required>
                    </div>
                    <div class="form-group">
                        <label>Detailed Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Explain your grievance in detail" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Attachments</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                    </div>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-paper-plane"></i> Submit Grievance</button>
                    <button type="button" class="btn btn-outline" onclick="hideGrievanceForm()">Cancel</button>
                </form>
            </div>
            <div id="grievancesList"></div>
        </div>
    </div>
</div>
<script>
function showGrievanceForm() { $('#grievanceForm').slideDown(); }
function hideGrievanceForm() { $('#grievanceForm').slideUp(); }

$(document).ready(function() {
    $('#grievanceFormEl').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const category = $('[name=\"category\"]').val();
        const department = $('[name=\"department\"]').val();
        const subject = $('[name=\"subject\"]').val().trim();
        const description = $('[name=\"description\"]').val().trim();
        const files = $('[name=\"attachments[]\"]')[0].files;

        if (!category) { alert('Please select a category.'); return; }
        if (!department) { alert('Please select a department.'); return; }
        if (subject.length < 5 || subject.length > 150) { alert('Subject must be 5-150 characters.'); return; }
        if (description.length < 20) { alert('Description must be at least 20 characters.'); return; }
        if (files.length > 3) { alert('You can upload up to 3 attachments.'); return; }
        const allowed = ['application/pdf','image/jpeg','image/png'];
        for (let i = 0; i < files.length; i++) {
            if (files[i].size > 5 * 1024 * 1024) { alert('Each attachment must be under 5 MB.'); return; }
            if (!allowed.includes(files[i].type)) { alert('Only PDF, JPG or PNG files are allowed.'); return; }
        }

        $.ajax({
            url: '<?php echo APP_URL; ?>/api/grievances/submit',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if(res.success) { alert('Grievance filed successfully!'); hideGrievanceForm(); loadGrievances(); }
                else { alert(res.message); }
            },
            error: function() { alert('Network error. Please try again.'); }
        });
    });
    
    function loadGrievances() {
        $.get('<?php echo APP_URL; ?>/api/grievances/list', function(res) {
            if(res.success) {
                let html = '<h3>My Grievances</h3>';
                if(res.data.length === 0) { html += '<p>No grievances filed.</p>'; }
                else {
                    html += '<div class="table-responsive"><table class="table"><thead><tr><th>Grievance#</th><th>Category</th><th>Subject</th><th>Status</th><th>Responses</th><th>Date</th></tr></thead><tbody>';
                    res.data.forEach(g => {
                        html += `<tr><td>${g.grievance_number || g.grievance_no}</td><td>${g.category}</td><td>${g.subject}</td>
                            <td><span class="badge badge-${g.status==='resolved'?'success':g.status==='in_progress'?'info':'secondary'}">${g.status}</span></td>
                            <td>${g.response_count || 0}</td>
                            <td>${new Date(g.created_at).toLocaleDateString()}</td></tr>`;
                    });
                    html += '</tbody></table></div>';
                }
                $('#grievancesList').html(html);
            }
        });
    }
    loadGrievances();
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
