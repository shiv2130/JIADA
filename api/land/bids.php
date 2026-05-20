<?php
/**
 * JIADA Portal - Land Bids API
 * GET /api/land/bids?plot_id=#
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

if (getRequestMethod() !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$db = getDB();
$plotId = intval($_GET['plot_id'] ?? 0);
$status = $_GET['status'] ?? 'active';

$where = "1=1";
$params = [];
if ($plotId) {
    $where .= " AND plot_id = ?";
    $params[] = $plotId;
}
if ($status) {
    $where .= " AND status = ?";
    $params[] = $status;
}

$bids = $db->fetchAll(
    "SELECT * FROM bids WHERE $where ORDER BY bid_end_date ASC",
    $params
);

jsonResponse(['success' => true, 'data' => $bids]);
