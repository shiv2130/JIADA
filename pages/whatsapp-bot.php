<?php
$pageTitle = "WhatsApp Bot - JIADA Portal";
$currentPage = 'whatsapp-bot';
ob_start();
?>
<div class="container" style="max-width: 900px; padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fab fa-whatsapp"></i> JIADA WhatsApp Bot</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Use WhatsApp for service tracking, payments, and status updates. Supports Hindi & English.
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Menu Options</label>
                    <ul>
                        <li>Land Allotment Status</li>
                        <li>Payments & Dues</li>
                        <li>Lease Renewal</li>
                        <li>Utility Tickets</li>
                        <li>Grievances</li>
                    </ul>
                </div>
                <div class="form-group">
                    <label>Send Test Message (Internal)</label>
                    <form id="waForm">
                        <input type="text" name="phone_number" class="form-control" placeholder="WhatsApp Number (e.g., 919999999999)" required>
                        <textarea name="message" class="form-control" rows="3" placeholder="Message to send" required></textarea>
                        <button type="submit" class="btn btn-success"><i class="fab fa-whatsapp"></i> Send</button>
                    </form>
                </div>
            </div>
            <div class="mt-20">
                <h3>Webhook Integration</h3>
                <p>Configure your WhatsApp provider webhook to:</p>
                <code><?php echo APP_URL; ?>/api/whatsapp/webhook</code>
            </div>
        </div>
    </div>
</div>
<script>
$('#waForm').on('submit', function(e) {
    e.preventDefault();
    $.post('<?php echo APP_URL; ?>/api/whatsapp/message', $(this).serialize(), function(res) {
        if (res.success) {
            showAlert('Message queued successfully', 'success');
            $('#waForm')[0].reset();
        } else {
            showAlert(res.message || 'Unable to queue message', 'danger');
        }
    });
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
