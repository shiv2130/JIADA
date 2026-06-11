<?php
$pageTitle = "Land Bank Management - Admin";
$currentPage = 'land-bank';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$showAddPlotForm = (($_GET['show'] ?? '') === 'add');
$editPlotId = intval($_GET['edit'] ?? 0);
$editingPlot = null;

function countBoundaryPoints($boundaryCoordinates) {
    if (trim($boundaryCoordinates) === '') {
        return 0;
    }

    $decoded = json_decode($boundaryCoordinates, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Boundary coordinates must be valid GeoJSON-style JSON.');
    }

    if (isset($decoded['type'], $decoded['coordinates']) && $decoded['type'] === 'Polygon') {
        $decoded = $decoded['coordinates'];
    } elseif (isset($decoded['type'], $decoded['geometry']['type'], $decoded['geometry']['coordinates']) && $decoded['type'] === 'Feature' && $decoded['geometry']['type'] === 'Polygon') {
        $decoded = $decoded['geometry']['coordinates'];
    }

    if (isset($decoded[0][0]) && is_array($decoded[0][0])) {
        $ring = $decoded[0];
    } else {
        $ring = $decoded;
    }

    if (!is_array($ring)) {
        throw new Exception('Boundary coordinates must contain polygon points.');
    }

    $uniquePoints = [];
    foreach ($ring as $point) {
        if (!is_array($point)) {
            continue;
        }

        if (isset($point[0], $point[1])) {
            $lng = floatval($point[0]);
            $lat = floatval($point[1]);
        } elseif (isset($point['lat'], $point['lng'])) {
            $lat = floatval($point['lat']);
            $lng = floatval($point['lng']);
        } elseif (isset($point['latitude'], $point['longitude'])) {
            $lat = floatval($point['latitude']);
            $lng = floatval($point['longitude']);
        } else {
            continue;
        }

        $uniquePoints[number_format($lat, 6, '.', '') . ',' . number_format($lng, 6, '.', '')] = true;
    }

    return count($uniquePoints);
}

function formatUtilitiesForForm($value) {
    if (!$value) {
        return '';
    }

    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return implode(', ', array_map('strval', $decoded));
    }

    return $value;
}

function buildLandBankPlotPayload($db, $ignorePlotId = 0) {
    $plotNumber = trim($_POST['plot_number'] ?? '');
    $plotNo = trim($_POST['plot_no'] ?? '');
    $surveyNumber = trim($_POST['survey_number'] ?? '');
    $plotType = trim($_POST['plot_type'] ?? 'industrial');
    $areaSqm = floatval($_POST['area_sqm'] ?? 0);
    $areaSqftInput = trim($_POST['area_sqft'] ?? '');
    $areaSqft = $areaSqftInput !== '' ? floatval($areaSqftInput) : ($areaSqm > 0 ? round($areaSqm * 10.7639, 2) : 0);
    $zone = trim($_POST['zone'] ?? '');
    $industryType = trim($_POST['industry_type'] ?? '');
    $latitudeInput = trim($_POST['latitude'] ?? '');
    $longitudeInput = trim($_POST['longitude'] ?? '');
    $locationLatInput = trim($_POST['location_lat'] ?? '');
    $locationLngInput = trim($_POST['location_lng'] ?? '');
    $basePriceInput = trim($_POST['base_price'] ?? '');
    $basePricePerSqmInput = trim($_POST['base_price_per_sqm'] ?? '');
    $sector = trim($_POST['sector'] ?? '');
    $regionId = intval($_POST['region_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'available');
    $infrastructureType = trim($_POST['infrastructure_type'] ?? 'raw_land');
    $utilitiesAvailable = trim($_POST['utilities_available'] ?? '');
    $boundaryCoordinates = trim($_POST['boundary_coordinates'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($plotNumber === '' || $areaSqm <= 0 || $zone === '' || !$regionId) {
        throw new Exception('Plot number, area (sqm), zone, and region are required.');
    }

    $statusMap = [
        'under_maintenance' => 'under_development'
    ];
    $infrastructureMap = [
        'developed_land' => 'developed',
        'plug_and_play' => 'plug_play'
    ];
    $status = $statusMap[$status] ?? $status;
    $infrastructureType = $infrastructureMap[$infrastructureType] ?? $infrastructureType;

    if ($boundaryCoordinates !== '' && countBoundaryPoints($boundaryCoordinates) < 3) {
        throw new Exception('Boundary coordinates must contain at least 3 unique points to map the plot area.');
    }

    $duplicateSql = "SELECT id FROM land_banks WHERE plot_number = ?";
    $duplicateParams = [$plotNumber];
    if ($ignorePlotId > 0) {
        $duplicateSql .= " AND id != ?";
        $duplicateParams[] = $ignorePlotId;
    }
    $existingPlot = $db->fetchOne($duplicateSql, $duplicateParams);
    if ($existingPlot) {
        throw new Exception('A land bank entry with this plot number already exists.');
    }

    $basePricePerSqm = $basePricePerSqmInput !== '' ? floatval($basePricePerSqmInput) : null;
    $basePrice = $basePriceInput !== '' ? floatval($basePriceInput) : null;
    if ($basePrice === null && $basePricePerSqm !== null && $areaSqm > 0) {
        $basePrice = round($basePricePerSqm * $areaSqm, 2);
    }

    $utilitiesPayload = null;
    if ($utilitiesAvailable !== '') {
        $decodedUtilities = json_decode($utilitiesAvailable, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $utilitiesPayload = json_encode($decodedUtilities);
        } else {
            $utilitiesList = array_values(array_filter(array_map('trim', preg_split('/[,\\n]+/', $utilitiesAvailable))));
            $utilitiesPayload = json_encode($utilitiesList);
        }
    }

    return [
        'plot_no' => $plotNo !== '' ? $plotNo : null,
        'area_sqft' => $areaSqft > 0 ? $areaSqft : null,
        'plot_number' => $plotNumber,
        'survey_number' => $surveyNumber !== '' ? $surveyNumber : null,
        'plot_type' => $plotType !== '' ? $plotType : null,
        'area_sqm' => $areaSqm,
        'zone' => $zone,
        'industry_type' => $industryType !== '' ? $industryType : null,
        'latitude' => $latitudeInput !== '' ? floatval($latitudeInput) : null,
        'longitude' => $longitudeInput !== '' ? floatval($longitudeInput) : null,
        'base_price' => $basePrice,
        'sector' => $sector !== '' ? $sector : null,
        'region_id' => $regionId,
        'location_lat' => $locationLatInput !== '' ? floatval($locationLatInput) : null,
        'location_lng' => $locationLngInput !== '' ? floatval($locationLngInput) : null,
        'boundary_coordinates' => $boundaryCoordinates !== '' ? $boundaryCoordinates : null,
        'base_price_per_sqm' => $basePricePerSqm,
        'status' => $status !== '' ? $status : 'available',
        'infrastructure_type' => $infrastructureType !== '' ? $infrastructureType : null,
        'utilities_available' => $utilitiesPayload,
        'remarks' => $remarks !== '' ? $remarks : null,
    ];
}

function countPlotReferences($db, $plotId) {
    $checks = [
        "SELECT COUNT(*) AS c FROM land_applications WHERE plot_id = ?",
        "SELECT COUNT(*) AS c FROM la_notification_plots WHERE plot_id = ?",
        "SELECT COUNT(*) AS c FROM bids WHERE plot_id = ?"
    ];

    $total = 0;
    foreach ($checks as $sql) {
        try {
            $total += intval($db->fetchOne($sql, [$plotId])['c'] ?? 0);
        } catch (Exception $e) {
            continue;
        }
    }

    return $total;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_plot') {
            $payload = buildLandBankPlotPayload($db);
            $payload['created_at'] = date('Y-m-d H:i:s');
            $payload['updated_at'] = date('Y-m-d H:i:s');
            $db->insert('land_banks', $payload);

            $_SESSION['flash_success'] = 'Land bank plot added successfully.';
        }
        if ($action === 'update_plot') {
            $plotId = intval($_POST['plot_id'] ?? 0);
            if (!$plotId) {
                throw new Exception('Plot ID is required for update.');
            }

            $plot = $db->fetchOne("SELECT id FROM land_banks WHERE id = ?", [$plotId]);
            if (!$plot) {
                throw new Exception('Plot not found.');
            }

            $payload = buildLandBankPlotPayload($db, $plotId);
            $payload['updated_at'] = date('Y-m-d H:i:s');
            $db->update('land_banks', $payload, 'id = ?', [$plotId]);
            $_SESSION['flash_success'] = 'Land bank plot updated successfully.';
        }
        if ($action === 'delete_plot') {
            $plotId = intval($_POST['plot_id'] ?? 0);
            if (!$plotId) {
                throw new Exception('Plot ID is required for delete.');
            }

            $plot = $db->fetchOne("SELECT id FROM land_banks WHERE id = ?", [$plotId]);
            if (!$plot) {
                throw new Exception('Plot not found.');
            }

            if (countPlotReferences($db, $plotId) > 0) {
                throw new Exception('This plot is already linked with applications, notifications, or bids. Change its status instead of deleting it.');
            }

            $db->delete('land_banks', 'id = ?', [$plotId]);
            $_SESSION['flash_success'] = 'Land bank plot deleted successfully.';
        }
        if ($action === 'create_notification') {
            $title = sanitize($_POST['notification_title'] ?? '');
            $publishDate = $_POST['publish_date'] ?? date('Y-m-d');
            $startDate = $_POST['application_start_date'] ?? date('Y-m-d');
            $endDate = $_POST['application_end_date'] ?? date('Y-m-d');
            $plotIds = $_POST['plot_ids'] ?? [];
            if (!$title || !$publishDate || !$startDate || !$endDate || empty($plotIds)) {
                throw new Exception('Title, dates and at least one plot are required');
            }
            $notifNo = generateReferenceNumber('NOTIF');
            $notificationId = $db->insert('la_notifications', [
                'notification_no' => $notifNo,
                'title' => $title,
                'publish_date' => $publishDate,
                'application_start_date' => $startDate,
                'application_end_date' => $endDate,
                'status' => 'draft',
                'created_by' => intval($_SESSION['admin_id']),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            foreach ($plotIds as $plotId) {
                $plotId = intval($plotId);
                if (!$plotId) continue;
                $plot = $db->fetchOne("SELECT base_price_per_sqm, area_sqm FROM land_banks WHERE id = ?", [$plotId]);
                $floorPrice = floatval($plot['base_price_per_sqm'] ?? 0) * floatval($plot['area_sqm'] ?? 0);
                $db->insert('la_notification_plots', [
                    'notification_id' => $notificationId,
                    'plot_id' => $plotId,
                    'floor_price' => $floorPrice,
                    'is_withdrawn' => 0
                ]);
            }
            $_SESSION['flash_success'] = 'Notification created as draft';
        }
        if ($action === 'publish_notification') {
            $id = intval($_POST['notification_id'] ?? 0);
            $db->update('la_notifications', ['status' => 'published'], 'id = ?', [$id]);
            $_SESSION['flash_success'] = 'Notification published';
        }
        if ($action === 'close_notification') {
            $id = intval($_POST['notification_id'] ?? 0);
            $db->update('la_notifications', ['status' => 'closed'], 'id = ?', [$id]);
            $_SESSION['flash_success'] = 'Notification closed';
        }
        if ($action === 'add_corrigendum') {
            $notificationId = intval($_POST['notification_id'] ?? 0);
            $description = sanitize($_POST['description'] ?? '');
            $corrDate = $_POST['corrigendum_date'] ?? date('Y-m-d');
            if (!$notificationId || !$description) throw new Exception('Notification and description required');
            $corrNo = generateReferenceNumber('CORR');
            $db->insert('la_notification_corrigenda', [
                'notification_id' => $notificationId,
                'corrigendum_no' => $corrNo,
                'corrigendum_date' => $corrDate,
                'description' => $description,
                'created_by' => intval($_SESSION['admin_id'])
            ]);
            $_SESSION['flash_success'] = 'Corrigendum added';
        }
        if ($action === 'withdraw_plot') {
            $notificationPlotId = intval($_POST['notification_plot_id'] ?? 0);
            $reason = sanitize($_POST['withdraw_reason'] ?? '');
            if (!$notificationPlotId || !$reason) throw new Exception('Plot and reason required');
            $db->update('la_notification_plots', ['is_withdrawn' => 1, 'withdrawn_reason' => $reason], 'id = ?', [$notificationPlotId]);
            $_SESSION['flash_success'] = 'Plot withdrawn from notification';
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    header('Location: ' . APP_URL . '/admin/?page=land-bank');
    exit;
}

$regions = $db->fetchAll("SELECT id, name FROM regions ORDER BY name ASC");
$editingPlot = $editPlotId > 0 ? $db->fetchOne("SELECT * FROM land_banks WHERE id = ?", [$editPlotId]) : null;
if ($editPlotId > 0 && !$editingPlot) {
    $_SESSION['flash_error'] = 'Plot not found.';
    header('Location: ' . APP_URL . '/admin/?page=land-bank');
    exit;
}
$showPlotForm = $showAddPlotForm || $editingPlot;
$plotForm = $editingPlot ?: [];
$plots = $db->fetchAll("SELECT lb.*, r.name as region_name FROM land_banks lb LEFT JOIN regions r ON lb.region_id = r.id ORDER BY lb.id DESC LIMIT 100");
$notifications = $db->fetchAll("SELECT n.*, (SELECT COUNT(*) FROM la_notification_plots np WHERE np.notification_id = n.id) AS total_plots FROM la_notifications n ORDER BY n.id DESC LIMIT 50");
$notificationPlots = $db->fetchAll("SELECT np.*, n.notification_no, lb.plot_number FROM la_notification_plots np INNER JOIN la_notifications n ON n.id = np.notification_id INNER JOIN land_banks lb ON lb.id = np.plot_id ORDER BY np.id DESC LIMIT 100");
$corrigenda = $db->fetchAll("SELECT c.*, n.notification_no FROM la_notification_corrigenda c INNER JOIN la_notifications n ON n.id = c.notification_id ORDER BY c.id DESC LIMIT 50");

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-map-marked-alt"></i> Land Bank Management</h3>
        <a class="btn btn-primary btn-sm" href="<?php echo APP_URL; ?>/admin/?page=land-bank&show=add"><i class="fas fa-plus"></i> Add Plot</a>
    </div>
    <div class="card-body">
        <?php if ($showPlotForm): ?>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <strong><?php echo $editingPlot ? 'Edit Land Bank Plot' : 'Add Land Bank Plot'; ?></strong>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="<?php echo $editingPlot ? 'update_plot' : 'add_plot'; ?>">
                    <?php if ($editingPlot): ?>
                        <input type="hidden" name="plot_id" value="<?php echo intval($editingPlot['id']); ?>">
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Plot Number *</label>
                            <input type="text" name="plot_number" class="form-control" placeholder="PLOT-0101" value="<?php echo e($plotForm['plot_number'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Plot No</label>
                            <input type="text" name="plot_no" class="form-control" placeholder="Internal plot code" value="<?php echo e($plotForm['plot_no'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Survey Number</label>
                            <input type="text" name="survey_number" class="form-control" value="<?php echo e($plotForm['survey_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Plot Type</label>
                            <select name="plot_type" class="form-control">
                                <?php $selectedPlotType = $plotForm['plot_type'] ?? 'industrial'; ?>
                                <option value="industrial" <?php echo $selectedPlotType === 'industrial' ? 'selected' : ''; ?>>Industrial</option>
                                <option value="commercial" <?php echo $selectedPlotType === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                                <option value="flatted_factory" <?php echo $selectedPlotType === 'flatted_factory' ? 'selected' : ''; ?>>Flatted Factory</option>
                                <option value="warehouse" <?php echo $selectedPlotType === 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                                <option value="mixed_use" <?php echo $selectedPlotType === 'mixed_use' ? 'selected' : ''; ?>>Mixed Use</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Area (sqm) *</label>
                            <input type="number" name="area_sqm" class="form-control" step="0.01" min="0" value="<?php echo e($plotForm['area_sqm'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Area (sqft)</label>
                            <input type="number" name="area_sqft" class="form-control" step="0.01" min="0" value="<?php echo e($plotForm['area_sqft'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Zone *</label>
                            <input type="text" name="zone" class="form-control" placeholder="Ranchi / Jamshedpur / etc." value="<?php echo e($plotForm['zone'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Industry Type</label>
                            <input type="text" name="industry_type" class="form-control" placeholder="Manufacturing / IT-ITES / etc." value="<?php echo e($plotForm['industry_type'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Region *</label>
                            <select name="region_id" class="form-control" required>
                                <option value="">Select region</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?php echo intval($region['id']); ?>" <?php echo intval($plotForm['region_id'] ?? 0) === intval($region['id']) ? 'selected' : ''; ?>><?php echo e($region['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sector</label>
                            <input type="text" name="sector" class="form-control" value="<?php echo e($plotForm['sector'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <?php $selectedStatus = $plotForm['status'] ?? 'available'; ?>
                                <option value="available" <?php echo $selectedStatus === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="reserved" <?php echo $selectedStatus === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                                <option value="allotted" <?php echo $selectedStatus === 'allotted' ? 'selected' : ''; ?>>Allotted</option>
                                <option value="blocked" <?php echo $selectedStatus === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                <option value="under_development" <?php echo in_array($selectedStatus, ['under_development', 'under_maintenance'], true) ? 'selected' : ''; ?>>Under Development</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Infrastructure Type</label>
                            <select name="infrastructure_type" class="form-control">
                                <?php $selectedInfrastructure = $plotForm['infrastructure_type'] ?? 'raw_land'; ?>
                                <option value="raw_land" <?php echo $selectedInfrastructure === 'raw_land' ? 'selected' : ''; ?>>Raw Land</option>
                                <option value="developed" <?php echo in_array($selectedInfrastructure, ['developed', 'developed_land'], true) ? 'selected' : ''; ?>>Developed Land</option>
                                <option value="plug_play" <?php echo in_array($selectedInfrastructure, ['plug_play', 'plug_and_play'], true) ? 'selected' : ''; ?>>Plug &amp; Play</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Latitude</label>
                            <input type="number" name="latitude" class="form-control" step="0.000001" value="<?php echo e($plotForm['latitude'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Longitude</label>
                            <input type="number" name="longitude" class="form-control" step="0.000001" value="<?php echo e($plotForm['longitude'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Location Lat</label>
                            <input type="number" name="location_lat" class="form-control" step="0.000001" value="<?php echo e($plotForm['location_lat'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Location Lng</label>
                            <input type="number" name="location_lng" class="form-control" step="0.000001" value="<?php echo e($plotForm['location_lng'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Base Price</label>
                            <input type="number" name="base_price" class="form-control" step="0.01" min="0" value="<?php echo e($plotForm['base_price'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Base Price per sqm</label>
                            <input type="number" name="base_price_per_sqm" class="form-control" step="0.01" min="0" value="<?php echo e($plotForm['base_price_per_sqm'] ?? ''); ?>">
                        </div>
                        <div class="form-group" style="flex:2;">
                            <label>Utilities Available</label>
                            <input type="text" name="utilities_available" class="form-control" placeholder="Electricity, Water, Road access" value="<?php echo e(formatUtilitiesForForm($plotForm['utilities_available'] ?? '')); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:6px;">
                            <label style="margin:0;">Boundary Coordinates</label>
                            <button type="button" class="btn btn-sm btn-outline" id="openBoundaryPicker">
                                <i class="fas fa-draw-polygon"></i> Select on Map
                            </button>
                        </div>
                        <textarea id="boundaryCoordinates" name="boundary_coordinates" class="form-control" rows="3" placeholder='GeoJSON coordinates will appear here after drawing on map'><?php echo e($plotForm['boundary_coordinates'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3"><?php echo e($plotForm['remarks'] ?? ''); ?></textarea>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-primary" type="submit"><?php echo $editingPlot ? 'Update Plot' : 'Save Plot'; ?></button>
                        <a class="btn btn-outline" href="<?php echo APP_URL; ?>/admin/?page=land-bank">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div id="boundaryPickerModal" class="boundary-picker-modal" aria-hidden="true">
            <div class="boundary-picker-dialog">
                <div class="boundary-picker-header">
                    <div>
                        <strong>Select Plot Boundary</strong>
                        <small>Draw one polygon around the plot, then use the selected boundary.</small>
                    </div>
                    <button type="button" class="boundary-picker-close" id="closeBoundaryPicker" aria-label="Close boundary picker">&times;</button>
                </div>
                <div id="boundaryPickerMap"></div>
                <div class="boundary-picker-footer">
                    <span id="boundaryPickerStatus">Use the polygon tool to mark plot corners.</span>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn btn-outline" id="clearBoundaryPolygon">Clear</button>
                        <button type="button" class="btn btn-primary" id="useBoundaryPolygon">Use Boundary</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><strong>Create Plot Notification</strong></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="create_notification">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Notification Title</label>
                            <input type="text" name="notification_title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Publish Date</label>
                            <input type="date" name="publish_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Application Start</label>
                            <input type="date" name="application_start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Application End</label>
                            <input type="date" name="application_end_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Select Available Plots</label>
                        <select name="plot_ids[]" class="form-control" multiple size="6" required>
                            <?php foreach ($plots as $p): if (($p['status'] ?? '') !== 'available') continue; ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo e(($p['plot_number'] ?? '-') . ' | ' . number_format(floatval($p['area_sqm'] ?? 0), 2) . ' sqm'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Create Draft Notification</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><strong>Notifications</strong></div>
            <div class="card-body table-responsive">
                <table class="table">
                    <thead><tr><th>ID</th><th>No</th><th>Title</th><th>Publish</th><th>Window</th><th>Status</th><th>Plots</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($notifications as $n): ?>
                        <tr>
                            <td><?php echo $n['id']; ?></td>
                            <td><?php echo e($n['notification_no']); ?></td>
                            <td><?php echo e($n['title']); ?></td>
                            <td><?php echo e($n['publish_date']); ?></td>
                            <td><?php echo e($n['application_start_date'] . ' to ' . $n['application_end_date']); ?></td>
                            <td><span class="badge badge-<?php echo $n['status'] === 'published' ? 'success' : ($n['status'] === 'closed' ? 'secondary' : 'warning'); ?>"><?php echo e($n['status']); ?></span></td>
                            <td><?php echo intval($n['total_plots']); ?></td>
                            <td>
                                <form method="post" style="display:inline-flex; gap:6px;">
                                    <input type="hidden" name="notification_id" value="<?php echo $n['id']; ?>">
                                    <?php if (($n['status'] ?? '') !== 'published'): ?>
                                        <button class="btn btn-sm btn-success" name="action" value="publish_notification" type="submit">Publish</button>
                                    <?php endif; ?>
                                    <?php if (($n['status'] ?? '') === 'published'): ?>
                                        <button class="btn btn-sm btn-warning" name="action" value="close_notification" type="submit">Close</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($notifications)): ?><tr><td colspan="8" class="text-center">No notifications yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-row" style="margin-bottom:16px;">
            <div class="card">
                <div class="card-header"><strong>Add Corrigendum</strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_corrigendum">
                        <div class="form-group">
                            <label>Notification</label>
                            <select class="form-control" name="notification_id" required>
                                <option value="">Select</option>
                                <?php foreach ($notifications as $n): ?>
                                    <option value="<?php echo $n['id']; ?>"><?php echo e($n['notification_no'] . ' - ' . $n['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Corrigendum Date</label>
                            <input type="date" name="corrigendum_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit">Add Corrigendum</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><strong>Withdraw Plot From Notification</strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="withdraw_plot">
                        <div class="form-group">
                            <label>Notification Plot Entry</label>
                            <select class="form-control" name="notification_plot_id" required>
                                <option value="">Select</option>
                                <?php foreach ($notificationPlots as $np): ?>
                                    <option value="<?php echo $np['id']; ?>">
                                        <?php echo e($np['notification_no'] . ' | ' . $np['plot_number'] . ' | withdrawn=' . intval($np['is_withdrawn'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Speaking Order / Reason</label>
                            <textarea name="withdraw_reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <button class="btn btn-danger" type="submit">Withdraw Plot</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><strong>Corrigendum Register</strong></div>
            <div class="card-body table-responsive">
                <table class="table">
                    <thead><tr><th>ID</th><th>Corrigendum No</th><th>Notification</th><th>Date</th><th>Description</th></tr></thead>
                    <tbody>
                    <?php foreach ($corrigenda as $c): ?>
                        <tr>
                            <td><?php echo $c['id']; ?></td>
                            <td><?php echo e($c['corrigendum_no']); ?></td>
                            <td><?php echo e($c['notification_no']); ?></td>
                            <td><?php echo e($c['corrigendum_date']); ?></td>
                            <td><?php echo e($c['description']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($corrigenda)): ?><tr><td colspan="5" class="text-center">No corrigenda yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <h4 style="margin-bottom:8px;">Plot Inventory</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Plot#</th><th>Type</th><th>Area (sqm)</th><th>Zone</th><th>Region</th><th>Status</th><th>Price/sqm</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($plots as $p): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td><?php echo e($p['plot_number']); ?></td>
                        <td><?php echo e($p['plot_type']); ?></td>
                        <td><?php echo number_format($p['area_sqm'], 2); ?></td>
                        <td><?php echo e($p['zone']); ?></td>
                        <td><?php echo e($p['region_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo $p['status'] === 'available' ? 'success' : ($p['status'] === 'allotted' ? 'info' : 'warning'); ?>"><?php echo e($p['status']); ?></span></td>
                        <td>₹<?php echo number_format($p['base_price_per_sqm'], 2); ?></td>
                        <td>
                            <div style="display:inline-flex; gap:6px; align-items:center;">
                                <a class="btn btn-sm btn-outline" href="<?php echo APP_URL; ?>/admin/?page=land-bank&edit=<?php echo intval($p['id']); ?>">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete plot <?php echo e($p['plot_number']); ?>? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_plot">
                                    <input type="hidden" name="plot_id" value="<?php echo intval($p['id']); ?>">
                                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$scripts = ($scripts ?? '') . <<<'HTML'
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<style>
.boundary-picker-modal {
    position: fixed;
    inset: 0;
    z-index: 3000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.55);
}
.boundary-picker-modal.open { display: flex; }
.boundary-picker-dialog {
    width: min(1080px, 96vw);
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
}
.boundary-picker-header,
.boundary-picker-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
}
.boundary-picker-footer {
    border-top: 1px solid #e5e7eb;
    border-bottom: 0;
}
.boundary-picker-header small {
    display: block;
    margin-top: 2px;
    color: #64748b;
}
.boundary-picker-close {
    border: 0;
    background: transparent;
    color: #334155;
    cursor: pointer;
    font-size: 28px;
    line-height: 1;
}
#boundaryPickerMap {
    height: min(64vh, 620px);
    width: 100%;
}
#boundaryPickerStatus {
    color: #64748b;
    font-size: 13px;
}
</style>
<script>
(function () {
    const openButton = document.getElementById('openBoundaryPicker');
    const closeButton = document.getElementById('closeBoundaryPicker');
    const modal = document.getElementById('boundaryPickerModal');
    const textarea = document.getElementById('boundaryCoordinates');
    const clearButton = document.getElementById('clearBoundaryPolygon');
    const useButton = document.getElementById('useBoundaryPolygon');
    const status = document.getElementById('boundaryPickerStatus');

    if (!openButton || !modal || typeof L === 'undefined') {
        return;
    }

    let pickerMap = null;
    let drawnItems = null;
    let selectedCoordinates = null;

    function getInitialCenter() {
        const lat = parseFloat(document.querySelector('[name="latitude"]')?.value || document.querySelector('[name="location_lat"]')?.value || '');
        const lng = parseFloat(document.querySelector('[name="longitude"]')?.value || document.querySelector('[name="location_lng"]')?.value || '');
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            return [lat, lng];
        }
        return [23.3441, 85.3096];
    }

    function setStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function latLngsToGeoJsonCoordinates(latlngs) {
        const ring = latlngs.map((point) => [
            Number(point.lng.toFixed(6)),
            Number(point.lat.toFixed(6))
        ]);

        if (ring.length && (ring[0][0] !== ring[ring.length - 1][0] || ring[0][1] !== ring[ring.length - 1][1])) {
            ring.push([...ring[0]]);
        }

        return [ring];
    }

    function syncCoordinatesFromLayer(layer) {
        const latlngs = layer.getLatLngs()[0] || [];
        selectedCoordinates = latLngsToGeoJsonCoordinates(latlngs);
        setStatus(selectedCoordinates[0].length + ' boundary points selected.');
    }

    function normalizeExistingCoordinates(value) {
        if (!value) {
            return null;
        }

        try {
            let parsed = JSON.parse(value);
            if (parsed?.type === 'Feature' && parsed.geometry?.type === 'Polygon') {
                parsed = parsed.geometry.coordinates;
            } else if (parsed?.type === 'Polygon') {
                parsed = parsed.coordinates;
            }

            const ring = Array.isArray(parsed?.[0]?.[0]) ? parsed[0] : parsed;
            if (!Array.isArray(ring) || ring.length < 3) {
                return null;
            }

            const latlngs = ring
                .filter((point) => Array.isArray(point) && point.length >= 2)
                .map((point) => [Number(point[1]), Number(point[0])])
                .filter((point) => Number.isFinite(point[0]) && Number.isFinite(point[1]));

            return latlngs.length >= 3 ? latlngs : null;
        } catch (error) {
            return null;
        }
    }

    function loadExistingBoundary() {
        if (!textarea || !drawnItems || drawnItems.getLayers().length) {
            return false;
        }

        const latlngs = normalizeExistingCoordinates(textarea.value);
        if (!latlngs) {
            return false;
        }

        const polygon = L.polygon(latlngs, {
            color: '#1a5276',
            weight: 3,
            fillColor: '#3498db',
            fillOpacity: 0.18
        });
        drawnItems.addLayer(polygon);
        syncCoordinatesFromLayer(polygon);
        pickerMap.fitBounds(polygon.getBounds(), { padding: [24, 24] });
        return true;
    }

    function initMap() {
        if (pickerMap) {
            setTimeout(() => pickerMap.invalidateSize(), 120);
            return;
        }

        pickerMap = L.map('boundaryPickerMap').setView(getInitialCenter(), 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(pickerMap);

        drawnItems = new L.FeatureGroup();
        pickerMap.addLayer(drawnItems);

        const drawControl = new L.Control.Draw({
            draw: {
                polygon: {
                    allowIntersection: false,
                    showArea: true,
                    shapeOptions: {
                        color: '#1a5276',
                        weight: 3,
                        fillColor: '#3498db',
                        fillOpacity: 0.18
                    }
                },
                rectangle: false,
                circle: false,
                marker: false,
                polyline: false,
                circlemarker: false
            },
            edit: {
                featureGroup: drawnItems
            }
        });
        pickerMap.addControl(drawControl);

        pickerMap.on(L.Draw.Event.CREATED, function (event) {
            drawnItems.clearLayers();
            drawnItems.addLayer(event.layer);
            syncCoordinatesFromLayer(event.layer);
        });

        pickerMap.on(L.Draw.Event.EDITED, function (event) {
            event.layers.eachLayer(syncCoordinatesFromLayer);
        });

        pickerMap.on(L.Draw.Event.DELETED, function () {
            selectedCoordinates = null;
            setStatus('Boundary cleared. Draw a new polygon.');
        });
    }

    openButton.addEventListener('click', function () {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        initMap();
        if (!loadExistingBoundary()) {
            pickerMap.setView(getInitialCenter(), pickerMap.getZoom());
        }
        setTimeout(() => pickerMap.invalidateSize(), 120);
    });

    closeButton?.addEventListener('click', function () {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
    });

    clearButton?.addEventListener('click', function () {
        drawnItems?.clearLayers();
        selectedCoordinates = null;
        setStatus('Boundary cleared. Draw a new polygon.');
    });

    useButton?.addEventListener('click', function () {
        if (!selectedCoordinates) {
            setStatus('Draw a polygon before using the boundary.');
            return;
        }
        textarea.value = JSON.stringify(selectedCoordinates);
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    });
})();
</script>
HTML;
include TEMPLATES_PATH . '/admin_layout.php';
?>
