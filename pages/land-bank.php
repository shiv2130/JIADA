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
                    <option value="Adityapur">Adityapur</option>
                    <option value="Santhal">Santhal Pragana</option>
                    <option value="Bokaro">Bokaro</option>
                </select>
                <select id="statusFilter" class="form-control" style="width: 150px;">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="reserved">Reserved</option>
                    <option value="allotted">Allotted</option>
                    <option value="under_maintenance">Under Maintenance</option>
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
    
    let mapLayers = [];

    function clearMapLayers() {
        mapLayers.forEach(layer => map.removeLayer(layer));
        mapLayers = [];
    }

    function getBoundaryRing(boundaryCoordinates) {
        if (!boundaryCoordinates) return [];

        let coordinates = boundaryCoordinates;
        if (coordinates.type === 'Polygon' && Array.isArray(coordinates.coordinates)) {
            coordinates = coordinates.coordinates;
        }
        if (coordinates.type === 'Feature' && coordinates.geometry && coordinates.geometry.type === 'Polygon') {
            coordinates = coordinates.geometry.coordinates;
        }

        let ring = [];
        if (Array.isArray(coordinates) && Array.isArray(coordinates[0]) && Array.isArray(coordinates[0][0])) {
            ring = coordinates[0];
        } else if (Array.isArray(coordinates)) {
            ring = coordinates;
        }

        return ring
            .map(point => {
                if (Array.isArray(point) && point.length >= 2) {
                    return { lng: parseFloat(point[0]), lat: parseFloat(point[1]) };
                }
                if (point && typeof point === 'object') {
                    const lat = parseFloat(point.lat ?? point.latitude);
                    const lng = parseFloat(point.lng ?? point.longitude);
                    return { lat, lng };
                }
                return null;
            })
            .filter(point => point && Number.isFinite(point.lat) && Number.isFinite(point.lng));
    }

    function hasAtLeastThreeBoundaryPoints(ring) {
        const uniquePoints = new Set(ring.map(point => `${point.lat.toFixed(6)},${point.lng.toFixed(6)}`));
        return uniquePoints.size >= 3;
    }

    function drawPlotBoundary(plot, boundsGroup) {
        const ring = getBoundaryRing(plot.boundary_coordinates);
        if (!hasAtLeastThreeBoundaryPoints(ring)) return false;

        const latLngs = ring.map(point => [point.lat, point.lng]);
        const polygon = L.polygon(latLngs, {
            color: '#1a5276',
            weight: 2,
            fillColor: '#2e86c1',
            fillOpacity: 0.28
        }).addTo(map).bindPopup(`<strong>${plot.plot_number}</strong><br>Area: ${plot.area_sqm} sqm<br>Zone: ${plot.zone || '-'}<br>Boundary points: ${new Set(ring.map(point => `${point.lat},${point.lng}`)).size}`);

        mapLayers.push(polygon);
        boundsGroup.addLayer(polygon);
        return true;
    }
    
    function loadPlots() {
        $.get('<?php echo APP_URL; ?>/api/land/plots', {
            per_page: 500,
            status: $('#statusFilter').val(),
            zone: $('#zoneFilter').val()
        }, function(res) {
            if(res.success) {
                clearMapLayers();
                let plots = res.data.plots || res.data || [];
                const boundsGroup = L.featureGroup();
                let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">';
                plots.forEach(plot => {
                    const lat = plot.location_lat || plot.latitude;
                    const lng = plot.location_lng || plot.longitude;
                    const boundaryDrawn = drawPlotBoundary(plot, boundsGroup);
                    if(!boundaryDrawn && lat && lng) {
                        const marker = L.marker([lat, lng]).addTo(map)
                            .bindPopup(`<strong>${plot.plot_number}</strong><br>Area: ${plot.area_sqm} sqm<br>Zone: ${plot.zone || '-'}`);
                        mapLayers.push(marker);
                        boundsGroup.addLayer(marker);
                    }
                    html += `<div class="card" style="padding: 20px;">
                        <h4>${plot.plot_number || plot.plot_no || 'Plot'}</h4>
                        <p><i class="fas fa-map"></i> ${plot.area_sqm || plot.area_sqft || '-'} ${plot.area_sqm ? 'sqm' : 'sqft'} | ${plot.zone || '-'}</p>
                        <p><i class="fas fa-industry"></i> ${plot.plot_type || plot.industry_type || 'Any'}</p>
                        <p><i class="fas fa-draw-polygon"></i> ${hasAtLeastThreeBoundaryPoints(getBoundaryRing(plot.boundary_coordinates)) ? 'Boundary mapped' : 'Point location only'}</p>
                        <p><span class="badge badge-${plot.status === 'available' ? 'success' : 'danger'}">${plot.status}</span></p>
                        ${plot.status === 'available' ? `<a href="<?php echo APP_URL; ?>/land-apply?plot=${plot.id}" class="btn btn-primary btn-sm">Apply Now</a>` : ''}
                    </div>`;
                });
                html += '</div>';
                if(plots.length === 0) html = '<p class="text-center">No plots available.</p>';
                $('#plotsList').html(html);
                if(boundsGroup.getLayers().length > 0) {
                    map.fitBounds(boundsGroup.getBounds(), { padding: [24, 24], maxZoom: 13 });
                }
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
