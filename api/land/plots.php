<?php
/**
 * JIADA Portal - Land Plots API
 * GET /api/land/plots - List available plots
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

// Get request method
$method = getRequestMethod();

switch ($method) {
    case 'GET':
        getPlots();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Get list of land plots
 */
function getPlots() {
    $db = getDB();
    
    // Optional filters
    $status = getGet('status', 'available');
    $plotType = getGet('plot_type', null);
    $zone = getGet('zone', null);
    $minArea = getGet('min_area', null);
    $maxArea = getGet('max_area', null);
    $page = max(1, intval(getGet('page', 1)));
    $requestedPerPage = intval(getGet('per_page', PER_PAGE));
    $perPage = $requestedPerPage > 0 ? min($requestedPerPage, 500) : PER_PAGE;
    
    // Build query
    $where = [];
    $params = [];
    
    if ($status) {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    
    if ($plotType) {
        $where[] = 'plot_type = ?';
        $params[] = $plotType;
    }
    
    if ($zone) {
        $where[] = 'zone = ?';
        $params[] = sanitize($zone);
    }
    
    if ($minArea) {
        $where[] = 'area_sqm >= ?';
        $params[] = floatval($minArea);
    }
    
    if ($maxArea) {
        $where[] = 'area_sqm <= ?';
        $params[] = floatval($maxArea);
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM land_banks {$whereClause}";
    $totalResult = $db->fetchOne($countQuery, $params);
    $total = $totalResult['total'] ?? 0;
    
    // Pagination
    $pagination = paginate($total, $perPage, $page);
    
    // Get plots
    $query = "SELECT * FROM land_banks {$whereClause} ORDER BY plot_number ASC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
    $plots = $db->fetchAll($query, $params);
    
    // Parse JSON fields
    foreach ($plots as &$plot) {
        if (!empty($plot['boundary_coordinates'])) {
            $plot['boundary_coordinates'] = json_decode($plot['boundary_coordinates'], true);
        }
        if (!empty($plot['utilities_available'])) {
            $plot['utilities_available'] = json_decode($plot['utilities_available'], true);
        }
        $plot['area_acres'] = round($plot['area_sqm'] / 4046.86, 4);
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'plots' => $plots,
            'pagination' => $pagination
        ]
    ]);
}
