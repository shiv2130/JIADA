<?php
$pageTitle = "Utility Services - Water, Power, Maintenance";
$currentPage = 'utilities';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-tools"></i> Utility Services</h2>
            <button class="btn btn-primary" onclick="showTicketForm()"><i class="fas fa-plus"></i> New Ticket</button>
        </div>
        <div class="card-body">
            <div id="ticketForm" style="display: none; margin-bottom: 30px;">
                <h3>Raise Utility Ticket</h3>
                <form id="utilityForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Service Type</label>
                            <select name="utility_type" class="form-control" required>
                                <option value="">Select Service</option>
                                <option value="water">Water Supply</option>
                                <option value="electricity">Electricity</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="sewage">Sewage</option>
                                <option value="road">Road Repair</option>
                                <option value="street_light">Street Light</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" class="form-control" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Plot ID (optional)</label>
                            <input type="number" name="plot_id" class="form-control" min="1" placeholder="If issue is tied to a plot">
                        </div>
                        <div class="form-group">
                            <label>Unit ID (optional)</label>
                            <input type="number" name="unit_id" class="form-control" min="1" placeholder="If issue is tied to a unit">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="Brief description of issue" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Detailed description of the issue" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Remarks / Images</label>
                        <input type="file" name="attachments[]" class="form-control" accept="image/*" multiple>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Ticket</button>
                    <button type="button" class="btn btn-outline" onclick="hideTicketForm()">Cancel</button>
                </form>
            </div>
            <div id="ticketsList"></div>
            <div id="ticketDetails" style="display:none; margin-top:20px;" class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0;">Ticket Details</h3>
                    <div>
                        <button class="btn btn-sm btn-outline" onclick="hideTicketDetails()">Close</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="detailsBody" class="alert alert-info">Loading...</div>
                    <div id="ticketActions" style="margin-top:10px; display:none;">
                        <button class="btn btn-outline btn-sm" onclick="escalateTicket()">Escalate to Higher Authority</button>
                    </div>
                    <div id="feedbackSection" style="display:none;">
                        <h4>Provide Feedback</h4>
                        <form id="feedbackForm">
                            <input type="hidden" name="ticket_id" id="feedbackTicketId">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Rating (1-5)</label>
                                    <select name="rating" class="form-control" required>
                                        <option value="">Select rating</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Feedback</label>
                                <textarea name="feedback" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">Submit Feedback</button>
                            <button type="button" class="btn btn-outline" onclick="escalateTicket()">Not Resolved - Escalate</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function showTicketForm() { $('#ticketForm').slideDown(); }
function hideTicketForm() { $('#ticketForm').slideUp(); }
function hideTicketDetails() { $('#ticketDetails').hide(); }

let currentTicketId = null;

$(document).ready(function() {
    $('#utilityForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const $form = $(form);
        const formData = new FormData(form);
        const subject = ($form.find('[name="subject"]').val() || '').trim();
        const description = ($form.find('[name="description"]').val() || '').trim();
        const utilityType = $form.find('[name="utility_type"]').val();
        const priority = $form.find('[name="priority"]').val();
        const attachmentInput = $form.find('[name="attachments[]"]')[0];
        const files = attachmentInput ? attachmentInput.files : [];

        if (!utilityType) { showAlert('Please select a service type.', 'danger'); return; }
        if (!priority) { showAlert('Please select a priority.', 'danger'); return; }
        if (subject.length < 5 || subject.length > 150) { showAlert('Subject must be between 5 and 150 characters.', 'danger'); return; }
        if (description.length < 10) { showAlert('Description must be at least 10 characters.', 'danger'); return; }
        if (files.length > 5) { showAlert('You can upload up to 5 images.', 'danger'); return; }
        const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        for (let i = 0; i < files.length; i++) {
            if (files[i].size > 5 * 1024 * 1024) { showAlert('Each image must be under 5 MB.', 'danger'); return; }
            if (!allowed.includes(files[i].type)) { showAlert('Only JPG, PNG, GIF, or WEBP images are allowed.', 'danger'); return; }
        }

        $.ajax({
            url: '<?php echo APP_URL; ?>/api/utilities/tickets',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if(res.success) { showAlert('Ticket raised successfully!', 'success'); hideTicketForm(); loadTickets(); }
                else { showAlert(res.message || 'Failed to create ticket', 'danger'); }
            },
            error: function(xhr) {
                const message = xhr?.responseJSON?.message || 'Network error. Please try again.';
                showAlert(message, 'danger');
            }
        });
    });

    $('#feedbackForm').on('submit', function(e) {
        e.preventDefault();
        if (!currentTicketId) return;
        const formData = $(this).serialize() + '&action=feedback&id=' + currentTicketId;
        $.post('<?php echo APP_URL; ?>/api/utilities/ticket', formData, function(res) {
            if (res.success) { showAlert('Feedback submitted. Ticket closed.', 'success'); loadTickets(); hideTicketDetails(); }
            else { showAlert(res.message || 'Failed to submit feedback', 'danger'); }
        });
    });

    loadTickets();
});

function loadTickets() {
    $.get('<?php echo APP_URL; ?>/api/utilities/tickets', function(res) {
        if(res.success) {
            let html = '<h3>My Tickets</h3>';
            const tickets = res.data.tickets || res.data || [];
            if(tickets.length === 0) { html += '<p>No tickets raised.</p>'; }
            else {
                html += '<div class="table-responsive"><table class="table"><thead><tr><th>Ticket#</th><th>Service</th><th>Subject</th><th>Priority</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>';
                tickets.forEach(t => {
                    html += `<tr><td>${t.ticket_number || t.ticket_no}</td><td>${t.utility_type || t.service_type}</td><td>${t.subject}</td>
                    <td><span class="badge badge-${t.priority==='critical'?'danger':t.priority==='high'?'warning':'info'}">${t.priority}</span></td>
                    <td><span class="badge badge-${t.status==='resolved'?'success':t.status==='in_progress'?'info':t.status==='escalated'?'danger':'secondary'}">${t.status}</span></td>
                    <td>${new Date(t.created_at).toLocaleDateString()}</td>
                    <td><button class="btn btn-sm btn-outline" onclick="viewTicket(${t.id})">View</button></td></tr>`;
                });
                html += '</tbody></table></div>';
            }
            $('#ticketsList').html(html);
        }
    });
}

function viewTicket(id) {
    currentTicketId = id;
    $('#ticketDetails').show();
    $('#feedbackSection').hide();
    $('#ticketActions').hide();
    $('#detailsBody').removeClass('alert-warning').addClass('alert-info').text('Loading...');
    $.get('<?php echo APP_URL; ?>/api/utilities/ticket', {id: id}, function(res) {
        if (res.success) {
            const t = res.data;
            const assigned = t.assigned_to_name ? `Assigned to: ${t.assigned_to_name}` : 'Assigned to: Pending';
            const vendor = t.vendor_name ? `Vendor: ${t.vendor_name}` : 'Vendor: Pending';
            $('#detailsBody').html(`<strong>Ticket:</strong> ${t.ticket_number}<br>
                <strong>Service:</strong> ${t.utility_type}<br>
                <strong>Status:</strong> ${t.status}<br>
                <strong>Priority:</strong> ${t.priority}<br>
                <strong>${assigned}</strong><br>
                <strong>${vendor}</strong><br>
                <strong>Subject:</strong> ${t.subject}<br>
                <strong>Description:</strong> ${t.description}`);
            if (t.status === 'resolved') {
                $('#feedbackTicketId').val(id);
                $('#feedbackSection').show();
            }
            if (t.status !== 'closed') {
                $('#ticketActions').show();
            }
        } else {
            $('#detailsBody').removeClass('alert-info').addClass('alert-warning').text(res.message || 'Unable to load ticket');
        }
    });
}

function escalateTicket() {
    if (!currentTicketId) return;
    if (!confirm('Are you sure you want to escalate this ticket to higher officials?')) return;
    $.post('<?php echo APP_URL; ?>/api/utilities/ticket', {id: currentTicketId, action: 'escalate'}, function(res) {
        if (res.success) { showAlert('Ticket escalated to higher authority.', 'success'); loadTickets(); hideTicketDetails(); }
        else { showAlert(res.message || 'Escalation failed', 'danger'); }
    });
}
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
