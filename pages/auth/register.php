<?php
$pageTitle = "Register";
$currentPage = 'register';

ob_start();
?>
<div class="container" style="max-width:500px; padding-top:50px;">
    <div class="card">
        <div class="card-header" style="justify-content:center; flex-direction:column; text-align:center; padding:30px;">
            <h2>Create Account</h2>
            <p style="color:#95a5a6;">Register for JIADA Portal</p>
        </div>
        <div class="card-body" style="padding:30px;">
            <div id="registerMsg"></div>
            <form id="registerForm" class="form-inline" method="POST" action="<?php echo APP_URL; ?>/api/auth/register">
                <div class="form-group"><label>Name</label><input type="text" id="name" name="name" class="form-control" placeholder="Full Name" required></div>
                <div class="form-group"><label>Mobile</label><input type="tel" id="mobile" name="mobile" class="form-control" placeholder="10-digit mobile" required></div>
                <div class="form-group"><label>Email</label><input type="email" id="email" name="email" class="form-control" placeholder="Email address"></div>
                <div class="form-group"><label>User Type</label><select id="user_type" name="user_type" class="form-control"><option value="applicant">Applicant</option><option value="allottee">Allottee</option></select></div>
                <div class="form-group"><label>Company Name</label><input type="text" id="company_name" name="company_name" class="form-control" placeholder="Company/Proprietorship"></div>
                <div class="form-group"><label>Password</label><input type="password" id="password" name="password" class="form-control" placeholder="Min 8 characters" required minlength="8"></div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Register</button>
            </form>
            <div class="login-footer text-center mt-20">
                <a href="<?php echo APP_URL; ?>/login">Already have an account? Login</a>
            </div>
        </div>
    </div>
</div>
<script>
$('#registerForm').on('submit',function(e){e.preventDefault();var f=$(this).serializeArray();var d={};f.forEach(function(x){d[x.name]=x.value;});
$.post(this.action, d).done(function(r){if(r.success){alert('Registered!');window.location='<?php echo APP_URL; ?>/login';}else{alert(r.message);}}).fail(function(){alert('Network error');});});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>