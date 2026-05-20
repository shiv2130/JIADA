<?php
$pageTitle = "Land Bank - Available Industrial Plots";
$currentPage = 'land-bank';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-map-marked-alt"></i> Land Bank - Available Industrial Plots</h2>
            <div style="display: flex; gap: 10px;">
                <select id="zoneFilter" class="form-control" style="width: 170px;">
                    <option value="">All Zones</option>
                    <option value="Ranchi">Ranchi</option>
                    <option value="Jamshedpur">Jamshedpur</option>
                    <option value="Dhanbad">Dhanbad</option>
                    <option value="Bokaro">Bokaro</option>
                </select>
                <select id="statusFilter" class="form-control" style="width: 150px;">
                    <option value="available">Available</option>
                    <option value="">All Status</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="alert alert-info" style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                    <span><i class="fas fa-info-circle"></i> Access land allotment via Jharkhand Single Window or login to continue.</span>
                    <div style="display:flex; gap:10px;">
                        <a class="btn btn-sm btn-primary" href="<?php echo APP_URL; ?>/sso/redirect">Single Window Login</a>
                        <a class="btn btn-sm btn-outline" href="<?php echo APP_URL; ?>/login">Portal Login</a>
                    </div>
                </div>
            <?php endif; ?>
            <div id="map" style="height: 400px; margin-bottom: 30px;"></div>
            <div id="plotsList"></div>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    const map = L.map('map').setView([23.3441, 85.3096], 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    let markers = [];
    
    function loadPlots() {
        $.get('<?php echo APP_URL; ?>/api/land/plots', {
            status: $('#statusFilter').val(),
            zone: $('#zoneFilter').val()
        }, function(res) {
            if(res.success) {
                markers.forEach(m => map.removeLayer(m));
                markers = [];
                let plots = res.data.plots || res.data || [];
                let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">';
                plots.forEach(plot => {
                    const lat = plot.location_lat || plot.latitude;
                    const lng = plot.location_lng || plot.longitude;
                    if(lat && lng) {
                        const marker = L.marker([lat, lng]).addTo(map)
                            .bindPopup(`<strong>${plot.plot_number}</strong><br>Area: ${plot.area_sqm} sqm<br>Zone: ${plot.zone || '-'}`);
                        markers.push(marker);
                    }
                    html += `<div class="card" style="padding: 20px;">
                        <h4>${plot.plot_number || plot.plot_no || 'Plot'}</h4>
                        <p><i class="fas fa-map"></i> ${plot.area_sqm || plot.area_sqft || '-'} ${plot.area_sqm ? 'sqm' : 'sqft'} | ${plot.zone || '-'}</p>
                        <p><i class="fas fa-industry"></i> ${plot.plot_type || plot.industry_type || 'Any'}</p>
                        <p><span class="badge badge-${plot.status === 'available' ? 'success' : 'danger'}">${plot.status}</span></p>
                        ${plot.status === 'available' ? `<a href="<?php echo APP_URL; ?>/land-apply?plot=${plot.id}" class="btn btn-primary btn-sm">Apply Now</a>` : ''}
                    </div>`;
                });
                html += '</div>';
                if(plots.length === 0) html = '<p class="text-center">No plots available.</p>';
                $('#plotsList').html(html);
            }
        });
    }
    
    loadPlots();
    $('#zoneFilter, #statusFilter').on('change', loadPlots);
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
