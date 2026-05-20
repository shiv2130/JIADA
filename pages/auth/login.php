<?php
$pageTitle = "Login";
$currentPage = 'login';
ob_start();
?>
<div class="container" style="max-width: 450px; padding-top: 60px;">
    <div class="card">
        <div class="card-header" style="justify-content: center; flex-direction: column; text-align: center; padding: 30px;">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fas fa-user" style="font-size: 30px; color: var(--white);"></i>
            </div>
            <h2 style="margin: 0;">Welcome Back</h2>
            <p style="color: var(--gray); margin-top: 5px;">Sign in to your JIADA account</p>
        </div>
        <div class="card-body" style="padding: 30px;">
            <div id="alertContainer"></div>
            
            <form id="loginForm">
                <div class="form-group">
                    <label for="mobile"><i class="fas fa-mobile-alt"></i> Mobile Number <span class="required">*</span></label>
                    <input type="tel" id="mobile" name="mobile" class="form-control" placeholder="Enter 10-digit mobile" pattern="[6-9][0-9]{9}" required>
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email (Optional)</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Enter email address">
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter password">
                        <button type="button" onclick="togglePassword()" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--gray);">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group" id="otpGroup" style="display: none;">
                    <label for="otp"><i class="fas fa-key"></i> OTP</label>
                    <input type="text" id="otp" name="otp" class="form-control" placeholder="Enter 6-digit OTP" maxlength="6" pattern="[0-9]{6}">
                    <small style="color: var(--gray); display: block; margin-top: 5px;">
                        <span id="otpTimer"></span>
                        <a href="#" id="resendOtp" style="color: var(--primary);">Resend OTP</a>
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--light);">
                <p style="color: var(--gray); margin-bottom: 10px;">Single Window System Users</p>
                <a href="<?php echo baseUrl('/sso/redirect'); ?>" class="btn btn-outline btn-sm">
                    <i class="fas fa-shield-alt"></i> Login via Single Window
                </a>
            </div>
        </div>
        <div class="card-footer text-center">
            <p>Don't have an account? <a href="<?php echo baseUrl('/register'); ?>" style="color: var(--primary); font-weight: 600;">Register Here</a></p>
            <p><a href="<?php echo baseUrl('/forgot-password'); ?>" style="color: var(--secondary);">Forgot Password?</a></p>
        </div>
    </div>
</div>

<script>
let otpId = null;
let loginMethod = 'password';

function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Handle form submission
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const mobile = document.getElementById('mobile').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const otp = document.getElementById('otp').value.trim();

    if (loginMethod === 'password' && !password) {
        showAlert('Please enter your password', 'warning');
        return;
    }

    if (loginMethod === 'otp' && !otp) {
        showAlert('Please enter the OTP', 'warning');
        return;
    }
    
    const formData = {
        mobile: mobile,
        email: email
    };
    
    if (loginMethod === 'password') {
        formData.password = password;
    } else {
        formData.otp = otp;
        formData.otp_id = otpId;
    }
    
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
    
    try {
        const response = await fetch(APP_CONFIG.api.baseUrl + '/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await response.json().catch(() => null);

        if (!result) {
            showAlert('Unexpected server response. Please try again.', 'danger');
            return;
        }
        
        if (result.success) {
            if (result.requires_otp) {
                loginMethod = 'otp';
                otpId = result.otp_id;
                document.getElementById('passwordGroup').style.display = 'none';
                document.getElementById('otpGroup').style.display = 'block';
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Verify OTP';
                btn.disabled = false;
                showAlert('OTP sent to your mobile/email', 'info');
                startOtpTimer(result.expires_in);
                return;
            }

            if (result.data?.token) {
                sessionStorage.setItem('jwt_token', result.data.token);
                sessionStorage.setItem('user', JSON.stringify(result.data.user));
                showAlert('Login successful! Redirecting...', 'success');
                setTimeout(() => window.location.href = APP_CONFIG.baseUrl + '/dashboard', 1500);
            } else {
                showAlert('Login response was incomplete. Please try again.', 'danger');
            }
        } else {
            showAlert(result.message || 'Login failed. Please check your credentials.', 'danger');
        }
    } catch (error) {
        showAlert('Connection error. Please try again.', 'danger');
    }
    
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
});

document.getElementById('mobile').addEventListener('input', function() {
    if (loginMethod === 'otp') {
        loginMethod = 'password';
        otpId = null;
        document.getElementById('passwordGroup').style.display = 'block';
        document.getElementById('otpGroup').style.display = 'none';
        document.getElementById('otp').value = '';
        document.getElementById('loginBtn').innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
    }
});

function startOtpTimer(seconds) {
    let remaining = seconds;
    const timer = setInterval(() => {
        const min = Math.floor(remaining / 60);
        const sec = remaining % 60;
        document.getElementById('otpTimer').textContent = `OTP expires in ${min}:${sec.toString().padStart(2, '0')}`;
        remaining--;
        if (remaining < 0) {
            clearInterval(timer);
            document.getElementById('otpTimer').textContent = 'OTP expired. Please resend.';
        }
    }, 1000);
}

document.getElementById('resendOtp')?.addEventListener('click', async function(e) {
    e.preventDefault();
    const mobile = document.getElementById('mobile').value;
    
    if (!mobile) {
        showAlert('Please enter your mobile number first', 'warning');
        return;
    }
    
    try {
        const response = await fetch(APP_CONFIG.api.baseUrl + '/auth/send-otp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mobile, purpose: 'login' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            otpId = result.otp_id;
            startOtpTimer(result.expires_in);
            showAlert('New OTP sent successfully', 'success');
        } else {
            showAlert(result.message, 'danger');
        }
    } catch (error) {
        showAlert('Error sending OTP', 'danger');
    }
});

function showAlert(message, type) {
    const html = `<div class="alert alert-${type}">${message}</div>`;
    document.getElementById('alertContainer').innerHTML = html;
}
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
