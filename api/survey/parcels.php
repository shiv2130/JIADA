<?php
/**
 * Survey Parcels API - serve land parcels as GeoJSON
 * GET /api/survey/parcels
 */
if (!defined('JIADA_APP')) { die('Direct access not permitted'); }

$user = Middleware::requireAuth();
$db = getDB();

$region = getGet('region_id', null);
$limit = min(500, max(1, intval(getGet('limit', 200))));

$where = "1=1";
$params = [];
if ($region) { $where .= " AND region_id = ?"; $params[] = intval($region); }

$parcels = $db->fetchAll("SELECT id, plot_no, area_sqmt, ST_AsText(geometry) as wkt, region_id FROM land_banks WHERE $where LIMIT ?", array_merge($params, [$limit]));

$features = [];
foreach ($parcels as $p) {
    if (empty($p['wkt'])) continue;
    $geom = wktToGeoJson($p['wkt']);
    if (!$geom) continue;
    $features[] = [
        'type' => 'Feature',
        'geometry' => $geom,
        'properties' => [
            'id' => $p['id'],
            'plot_no' => $p['plot_no'],
            'area_sqmt' => $p['area_sqmt'],
            'region_id' => $p['region_id']
        ]
    ];
}

jsonResponse(['type'=>'FeatureCollection','features'=>$features]);

function wktToGeoJson($wkt) {
    // Basic WKT POLYGON/MULTIPOLYGON to GeoJSON converter (limited)
    $wkt = trim($wkt);
    if (stripos($wkt, 'POLYGON') === 0) {
        $coords = substr($wkt, strpos($wkt, '((') + 2, -2);
        $pairs = array_map('trim', explode(',', $coords));
        $ring = [];
        foreach ($pairs as $pair) {
            [$x,$y] = array_map('floatval', preg_split('/\s+/', trim($pair)));
            $ring[] = [(float)$x,(float)$y];
        }
        return ['type'=>'Polygon','coordinates'=>[$ring]];
    }
    if (stripos($wkt, 'MULTIPOLYGON') === 0) {
        // Minimal parse for MULTIPOLYGON (((x y,...)),((...)))
        $body = substr($wkt, strpos($wkt, '(((') + 3, -3);
        $polys = explode(')),((', $body);
        $multi = [];
        foreach ($polys as $poly) {
            $pairs = array_map('trim', explode(',', $poly));
            $ring = [];
            foreach ($pairs as $pair) {
                [$x,$y] = array_map('floatval', preg_split('/\s+/', trim($pair)));
                $ring[] = [(float)$x,(float)$y];
            }
            $multi[] = [$ring];
        }
        return ['type'=>'MultiPolygon','coordinates'=>$multi];
    }
    return null;
}
