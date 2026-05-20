<?php
$pageTitle = "Forgot Password - JIADA Portal";
$currentPage = 'forgot-password';
ob_start();
?>
<div class="container" style="max-width: 500px; padding-top: 50px;">
    <div class="card">
        <div class="card-header" style="text-align: center; padding: 30px;">
            <h2><i class="fas fa-key"></i> Forgot Password</h2>
            <p style="color: var(--gray);">Reset your account password</p>
        </div>
        <div class="card-body" style="padding: 30px;">
            <form id="forgotForm">
                <div class="form-group">
                    <label>Email or Mobile Number</label>
                    <input type="text" name="email" class="form-control" placeholder="Enter registered email or mobile" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
            </form>
            <div class="text-center mt-20">
                <a href="<?php echo APP_URL; ?>/login"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</div>
<script>
$('#forgotForm').on('submit', function(e) {
    e.preventDefault();
    alert('Password reset link has been sent to your registered email/mobile.');
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>