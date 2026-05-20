<?php
$pageTitle = "Contact Us - JIADA Portal";
$currentPage = 'contact';
ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="text-center mb-20">
        <h2><i class="fas fa-phone-alt"></i> Contact Us</h2>
        <p style="color: var(--gray);">Get in touch with JIADA for any queries or support</p>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <div class="card" style="padding: 30px;">
            <h3><i class="fas fa-map-marker-alt"></i> Office Address</h3>
            <p><strong>Jharkhand Industrial Area Development Authority</strong><br>
            JIADA Bhawan, Dhurwa,<br>
            Ranchi, Jharkhand - 834004</p>
            <h3><i class="fas fa-phone"></i> Phone</h3>
            <p>0651-2220000<br>0651-2220001</p>
            <h3><i class="fas fa-envelope"></i> Email</h3>
            <p>info@jiada.in<br>support@jiada.in</p>
            <h3><i class="fab fa-whatsapp"></i> WhatsApp</h3>
            <p>+91 9431000000</p>
        </div>
        <div class="card" style="padding: 30px;">
            <h3><i class="fas fa-paper-plane"></i> Send us a Message</h3>
            <form id="contactForm">
                <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" required></div>
                <div class="form-group"><label>Message</label><textarea name="message" class="form-control" rows="4" required></textarea></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Message</button>
            </form>
        </div>
    </div>
</div>
<script>
$('#contactForm').on('submit', function(e) {
    e.preventDefault();
    alert('Thank you for contacting us. We will get back to you soon.');
    this.reset();
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>