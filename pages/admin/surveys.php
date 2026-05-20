<?php
$pageTitle = "Land Survey - Admin";
$currentPage = 'surveys';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

ob_start();
?>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-satellite-dish"></i> Land Survey Management</h3></div>
    <div class="card-body">
        <div id="map" style="height: 400px; margin-bottom: 20px;"></div>
        <p class="text-center">GIS-based land survey data will be displayed here. Integrate with mobile app for field data collection.</p>
    </div>
</div>
<script>
$(document).ready(function() {
    const map = L.map('map').setView([23.3441, 85.3096], 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>