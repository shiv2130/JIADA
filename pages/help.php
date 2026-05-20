<?php
$pageTitle = "Help & Support";
$currentPage = 'help';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="text-center mb-20">
        <h2><i class="fas fa-question-circle"></i> Help & Support</h2>
        <p style="color: var(--gray);">Find answers to common questions and get assistance</p>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <div>
            <div class="card" style="padding: 25px;">
                <h3><i class="fas fa-book"></i> User Guides</h3>
                <ul style="list-style: none; padding: 0;">
                    <li style="padding: 10px 0; border-bottom: 1px solid #eee;"><a href="#"><i class="fas fa-file-pdf" style="color: var(--danger);"></i> Registration Guide</a></li>
                    <li style="padding: 10px 0; border-bottom: 1px solid #eee;"><a href="#"><i class="fas fa-file-pdf" style="color: var(--danger);"></i> Land Application Process</a></li>
                    <li style="padding: 10px 0; border-bottom: 1px solid #eee;"><a href="#"><i class="fas fa-file-pdf" style="color: var(--danger);"></i> Payment Guide</a></li>
                    <li style="padding: 10px 0; border-bottom: 1px solid #eee;"><a href="#"><i class="fas fa-file-pdf" style="color: var(--danger);"></i> Utility Services Guide</a></li>
                    <li style="padding: 10px 0;"><a href="#"><i class="fas fa-file-pdf" style="color: var(--danger);"></i> Grievance Filing Guide</a></li>
                </ul>
            </div>
            
            <div class="card" style="padding: 25px; margin-top: 20px;">
                <h3><i class="fas fa-comments"></i> Contact Us</h3>
                <p><i class="fas fa-phone"></i> <strong>Phone:</strong> 0651-2220000</p>
                <p><i class="fas fa-envelope"></i> <strong>Email:</strong> support@jiada.in</p>
                <p><i class="fab fa-whatsapp"></i> <strong>WhatsApp:</strong> +91 9431000000</p>
                <p><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong> JIADA Bhawan, Dhurwa, Ranchi - 834004</p>
            </div>
        </div>
        
        <div>
            <div class="card" style="padding: 25px;">
                <h3><i class="fas fa-question"></i> Frequently Asked Questions</h3>
                <div class="faq-item" style="border-bottom: 1px solid #eee; padding: 15px 0;">
                    <strong>Q: How do I register on the portal?</strong>
                    <p style="color: var(--gray); margin-top: 5px;">A: Click on "Register" button, fill in your details including mobile number, verify via OTP, and set your password.</p>
                </div>
                <div class="faq-item" style="border-bottom: 1px solid #eee; padding: 15px 0;">
                    <strong>Q: How can I apply for land allotment?</strong>
                    <p style="color: var(--gray); margin-top: 5px;">A: Browse available plots in Land Bank, select your preferred plot, and submit application with required documents and processing fee.</p>
                </div>
                <div class="faq-item" style="border-bottom: 1px solid #eee; padding: 15px 0;">
                    <strong>Q: What payment methods are accepted?</strong>
                    <p style="color: var(--gray); margin-top: 5px;">A: We accept Net Banking, UPI, Credit/Debit Cards, and Digital Wallets through our secure payment gateway.</p>
                </div>
                <div class="faq-item" style="border-bottom: 1px solid #eee; padding: 15px 0;">
                    <strong>Q: How do I track my application?</strong>
                    <p style="color: var(--gray); margin-top: 5px;">A: Login to your account and go to "My Applications" section to view real-time status of all your applications.</p>
                </div>
                <div class="faq-item" style="padding: 15px 0;">
                    <strong>Q: How do I file a grievance?</strong>
                    <p style="color: var(--gray); margin-top: 5px;">A: Go to Grievances section, click "File Grievance", select category and department, provide details and submit. You'll receive SMS/email confirmation.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>