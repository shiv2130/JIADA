<?php
$pageTitle = "Settings - Admin";
$currentPage = 'settings';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'app_name' => sanitize($_POST['app_name'] ?? ''),
        'admin_email' => sanitize($_POST['admin_email'] ?? ''),
        'otp_expiry' => sanitize($_POST['otp_expiry'] ?? ''),
        'session_timeout' => sanitize($_POST['session_timeout'] ?? ''),
        'upload_max_mb' => sanitize($_POST['upload_max_mb'] ?? ''),
        'whatsapp_access_token' => trim($_POST['whatsapp_access_token'] ?? ''),
        'whatsapp_phone_number_id' => trim($_POST['whatsapp_phone_number_id'] ?? ''),
        'whatsapp_app_secret' => trim($_POST['whatsapp_app_secret'] ?? ''),
        'whatsapp_verify_signature' => isset($_POST['whatsapp_verify_signature']) ? '1' : '0'
    ];
    foreach ($settings as $key => $value) {
        $exists = $db->fetchOne("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            $db->update('system_settings', ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')], 'setting_key = ?', [$key]);
        } else {
            $db->insert('system_settings', [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => 'string',
                'description' => 'System setting',
                'updated_by' => $_SESSION['admin_id'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    $_SESSION['flash_success'] = 'Settings saved';
    header('Location: ' . APP_URL . '/admin/?page=settings');
    exit;
}

// Load settings
$get = function($key, $default = '') use ($db) {
    $row = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]);
    return $row['setting_value'] ?? $default;
};

// WhatsApp status + logs
$waPhoneId = $get('whatsapp_phone_number_id');
$waToken = $get('whatsapp_access_token');
$waSecret = $get('whatsapp_app_secret');
$waVerify = $get('whatsapp_verify_signature','0') === '1';
$waConfigured = ($waPhoneId && $waToken);
$lastInbound = $db->fetchOne("SELECT received_at FROM whatsapp_messages ORDER BY received_at DESC LIMIT 1")['received_at'] ?? null;
$lastOutbound = $db->fetchOne("SELECT created_at FROM whatsapp_interactions WHERE message_type = 'outbound' ORDER BY created_at DESC LIMIT 1")['created_at'] ?? null;
$waLogsInbound = $db->fetchAll("SELECT from_number, message_text, received_at FROM whatsapp_messages ORDER BY received_at DESC LIMIT 10");
$waLogsOutbound = $db->fetchAll("SELECT phone_number, message_content, status, created_at FROM whatsapp_interactions WHERE message_type = 'outbound' ORDER BY created_at DESC LIMIT 10");

ob_start();
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-cog"></i> System Settings</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group"><label>Application Name</label><input type="text" name="app_name" class="form-control" value="<?php echo e($get('app_name', 'JIADA Portal')); ?>"></div>
                <div class="form-group"><label>Admin Email</label><input type="email" name="admin_email" class="form-control" value="<?php echo e($get('admin_email', 'admin@jiada.in')); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>OTP Expiry (seconds)</label><input type="number" name="otp_expiry" class="form-control" value="<?php echo e($get('otp_expiry', '300')); ?>"></div>
                <div class="form-group"><label>Session Timeout (seconds)</label><input type="number" name="session_timeout" class="form-control" value="<?php echo e($get('session_timeout', '3600')); ?>"></div>
                <div class="form-group"><label>Max File Upload (MB)</label><input type="number" name="upload_max_mb" class="form-control" value="<?php echo e($get('upload_max_mb', '10')); ?>"></div>
            </div>
            <hr style="margin: 20px 0;">
            <h3>WhatsApp Cloud API</h3>
            <div class="form-group"><label>Phone Number ID</label><input type="text" name="whatsapp_phone_number_id" class="form-control" value="<?php echo e($get('whatsapp_phone_number_id')); ?>"></div>
            <div class="form-group"><label>Access Token</label><input type="text" name="whatsapp_access_token" class="form-control" value="<?php echo e($get('whatsapp_access_token')); ?>"></div>
            <div class="form-group"><label>App Secret</label><input type="password" name="whatsapp_app_secret" class="form-control" value="<?php echo e($get('whatsapp_app_secret')); ?>"></div>
            <div class="form-group">
                <label><input type="checkbox" name="whatsapp_verify_signature" <?php echo $get('whatsapp_verify_signature','0')==='1' ? 'checked' : ''; ?>> Verify Webhook Signature</label>
            </div>
            <div class="alert alert-info">
                Webhook URL: <strong><?php echo APP_URL; ?>/api/whatsapp/webhook</strong>
            </div>
            <div class="form-row">
                <div class="alert alert-<?php echo $waConfigured ? 'success' : 'warning'; ?>">
                    <strong>Status:</strong> <?php echo $waConfigured ? 'Configured' : 'Not Configured'; ?>
                </div>
                <div class="alert alert-<?php echo $waVerify ? 'success' : 'warning'; ?>">
                    <strong>Signature:</strong> <?php echo $waVerify ? 'Verification ON' : 'Verification OFF'; ?>
                </div>
                <div class="alert alert-info">
                    <strong>Last Inbound:</strong> <?php echo $lastInbound ? date('d/m/Y H:i', strtotime($lastInbound)) : '—'; ?>
                </div>
                <div class="alert alert-info">
                    <strong>Last Outbound:</strong> <?php echo $lastOutbound ? date('d/m/Y H:i', strtotime($lastOutbound)) : '—'; ?>
                </div>
            </div>
            <div class="card" style="margin-top: 15px;">
                <div class="card-header"><h3>Test WhatsApp Message</h3></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group"><label>Test Number (with country code)</label><input type="text" id="wa_test_number" class="form-control" placeholder="919999999999"></div>
                        <div class="form-group"><label>Message</label><input type="text" id="wa_test_message" class="form-control" placeholder="Test message from JIADA"></div>
                    </div>
                    <button type="button" class="btn btn-success" id="wa_test_btn"><i class="fab fa-whatsapp"></i> Send Test</button>
                </div>
            </div>
            <div class="card" style="margin-top: 15px;">
                <div class="card-header"><h3>Recent WhatsApp Logs</h3></div>
                <div class="card-body">
                    <h4>Inbound (Webhook)</h4>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>From</th><th>Message</th><th>Time</th></tr></thead>
                            <tbody>
                            <?php if (empty($waLogsInbound)): ?>
                                <tr><td colspan="3" class="text-center">No inbound messages</td></tr>
                            <?php else: foreach ($waLogsInbound as $m): ?>
                                <tr>
                                    <td><?php echo e($m['from_number']); ?></td>
                                    <td><?php echo e($m['message_text']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($m['received_at'])); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <h4 style="margin-top: 20px;">Outbound</h4>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>To</th><th>Message</th><th>Status</th><th>Time</th></tr></thead>
                            <tbody>
                            <?php if (empty($waLogsOutbound)): ?>
                                <tr><td colspan="4" class="text-center">No outbound messages</td></tr>
                            <?php else: foreach ($waLogsOutbound as $m): ?>
                                <tr>
                                    <td><?php echo e($m['phone_number']); ?></td>
                                    <td><?php echo e($m['message_content']); ?></td>
                                    <td><?php echo e($m['status']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>
<script>
$('#wa_test_btn').on('click', function() {
    const phone = $('#wa_test_number').val().trim();
    const msg = $('#wa_test_message').val().trim();
    if (!phone || !msg) { showAlert('Enter test number and message', 'warning'); return; }
    $.post('<?php echo APP_URL; ?>/api/whatsapp/message', {phone_number: phone, message: msg}, function(res) {
        if (res.success) { showAlert('Test message sent', 'success'); }
        else { showAlert(res.message || 'Send failed', 'danger'); }
    });
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
