<?php
$pageTitle = "Plug & Play Units - Ready Factory Spaces";
$currentPage = 'plug-play';

ob_start();
?>
<div class="container" style="padding-top: 30px;">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-building"></i> Plug & Play Industrial Units</h2>
            <select id="typeFilter" class="form-control" style="width: 150px;">
                <option value="">All Types</option>
                <option value="flatted">Flatted Factory</option>
                <option value="shed">Industrial Shed</option>
                <option value="warehouse">Warehouse</option>
            </select>
        </div>
        <div class="card-body">
            <div id="unitsList"></div>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    function loadUnits() {
        $.get('<?php echo APP_URL; ?>/api/plugplay/units', {type: $('#typeFilter').val()}, function(res) {
            if(res.success) {
                let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">';
                res.data.forEach(unit => {
                    html += `<div class="card" style="padding: 25px;">
                        <h4>${unit.unit_no}</h4>
                        <p><i class="fas fa-ruler-combined"></i> ${unit.area_sqft} sqft</p>
                        <p><i class="fas fa-industry"></i> ${unit.unit_type}</p>
                        <p><i class="fas fa-map-marker-alt"></i> ${unit.location || 'JIADA Industrial Area'}</p>
                        <p><strong>Rent:</strong> ₹${parseInt(unit.monthly_rent).toLocaleString()}/month</p>
                        <p><span class="badge badge-${unit.status === 'available' ? 'success' : 'danger'}">${unit.status}</span></p>
                        ${unit.status === 'available' ? `<a href="<?php echo APP_URL; ?>/plugplay-apply?unit=${unit.id}" class="btn btn-primary btn-sm">Apply Now</a>` : ''}
                    </div>`;
                });
                html += '</div>';
                if(res.data.length === 0) html = '<p class="text-center">No units available at the moment.</p>';
                $('#unitsList').html(html);
            }
        });
    }
    loadUnits();
    $('#typeFilter').on('change', loadUnits);
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>