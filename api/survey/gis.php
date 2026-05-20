<?php
/**
 * Land Survey GIS Config API
 * GET /api/survey/gis
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

jsonResponse([
    'success' => true,
    'data' => [
        'tile_server' => GIS_TILE_SERVER,
        'default_center' => GIS_DEFAULT_CENTER,
        'default_zoom' => GIS_DEFAULT_ZOOM
    ]
]);
