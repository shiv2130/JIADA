<?php
$pageTitle = "Land Survey GIS";
$currentPage = 'survey';
ob_start();
?>
<div class="container" style="padding-top:20px;">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-map"></i> Land Survey Map</h2></div>
    <div class="card-body">
      <div id="map" style="height:600px; width:100%;"></div>
    </div>
  </div>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php if (isset($_SESSION['user_id'])): ?>
<script>
const map = L.map('map').setView([23.36, 85.33], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19}).addTo(map);

function loadParcels(){
  $.get('<?php echo APP_URL; ?>/api/survey/parcels', function(data){
    if(!data || !data.features){ showAlert('No parcels found','warning'); return; }
    L.geoJSON(data, {
      style:{color:'#2c7be5', weight:1, fillOpacity:0.2},
      onEachFeature:(f,l)=>{
        l.bindPopup(`<strong>Plot:</strong> ${f.properties.plot_no}<br>Area: ${f.properties.area_sqmt} sqm<br>Region: ${f.properties.region_id||'-'}`);
      }
    }).addTo(map);
  });
}
loadParcels();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/layout.php';
?>
