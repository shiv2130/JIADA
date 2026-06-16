<?php
$pageTitle = 'Master Sheets Dashboard';
$currentPage = 'master-dashboard';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once CORE_PATH . '/MasterSheetsService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/admin/?page=login');
    exit;
}

$db = getDB();
MasterSheetsService::ensureSchema($db);

$activeTab = $_GET['tab'] ?? 'overview';
if (in_array($activeTab, ['plots', 'map'], true)) {
    header('Location: ' . APP_URL . '/admin/?page=land-bank');
    exit;
}

function masterTableExists($db, $table)
{
    try {
        return (bool) $db->fetchOne(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
            [$table]
        );
    } catch (Exception $e) {
        return false;
    }
}

function masterColumns($db, $table)
{
    try {
        return array_map(static function ($row) {
            return $row['Field'];
        }, $db->fetchAll('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`'));
    } catch (Exception $e) {
        return [];
    }
}

function masterPickColumn(array $columns, array $candidates)
{
    $lookup = [];
    foreach ($columns as $column) {
        $lookup[strtolower($column)] = $column;
    }
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);
        if (isset($lookup[$key])) {
            return $lookup[$key];
        }
    }
    return null;
}

function masterValue(array $row, array $candidates, $default = '')
{
    foreach ($candidates as $candidate) {
        foreach ($row as $key => $value) {
            if (strtolower((string) $key) === strtolower((string) $candidate)) {
                return $value;
            }
        }
    }
    return $default;
}

function masterCleanCode($value)
{
    return strtoupper(preg_replace('/[^A-Z0-9_]+/', '', trim((string) $value)));
}

function masterQuoteIdentifier($identifier)
{
    return '`' . str_replace('`', '``', (string) $identifier) . '`';
}

function masterColumnsMeta($db, $table)
{
    try {
        return $db->fetchAll('SHOW COLUMNS FROM ' . masterQuoteIdentifier($table));
    } catch (Exception $e) {
        return [];
    }
}

function masterPrimaryColumn(array $columnsMeta)
{
    foreach ($columnsMeta as $column) {
        if (($column['Key'] ?? '') === 'PRI') {
            return $column['Field'];
        }
    }
    return $columnsMeta[0]['Field'] ?? null;
}

function masterColumnIsAutoIncrement(array $columnsMeta, $columnName)
{
    foreach ($columnsMeta as $column) {
        if (($column['Field'] ?? '') === $columnName) {
            return stripos((string) ($column['Extra'] ?? ''), 'auto_increment') !== false;
        }
    }
    return false;
}

function masterBlockColumnMap($db)
{
    if (!masterTableExists($db, 'tblBlock')) {
        return ['available' => false];
    }

    $columnsMeta = masterColumnsMeta($db, 'tblBlock');
    $columns = array_map(static function ($row) {
        return $row['Field'];
    }, $columnsMeta);

    return [
        'available' => true,
        'columns' => $columns,
        'meta' => $columnsMeta,
        'primary' => masterPrimaryColumn($columnsMeta),
        'name' => masterPickColumn($columns, ['BlockName', 'block_name', 'block', 'name']),
        'code' => masterPickColumn($columns, ['BlockCode', 'block_code', 'code']),
        'district_ref' => masterPickColumn($columns, ['DistrictID', 'district_id', 'DistID', 'dist_id', 'district_code', 'DistrictCode', 'district']),
    ];
}

function masterBlockWhereByPrimary(array $blockMap, $value)
{
    if (empty($blockMap['primary'])) {
        throw new Exception('tblBlock does not have an identifiable primary column.');
    }
    return masterQuoteIdentifier($blockMap['primary']) . ' = ?';
}

function masterSyncBlockDistrictRef($db, $blockName, $districtId)
{
    if (!$blockName || !$districtId) {
        return;
    }
    try {
        $db->query('UPDATE land_banks SET district_id = ? WHERE LOWER(block) = LOWER(?) AND (district_id IS NULL OR district_id = 0)', [(int) $districtId, $blockName]);
    } catch (Exception $e) {
        // Land bank backfill is best-effort; block CRUD should not fail because of older plot rows.
    }
}

function masterSyncRegionDistricts($db, $regionId)
{
    if (!masterTableExists($db, 'districts')) {
        return;
    }

    $rows = $db->fetchAll(
        "SELECT name FROM districts WHERE region_id = ? AND status = 'active' ORDER BY name ASC",
        [(int) $regionId]
    );
    $names = array_map(static function ($row) {
        return $row['name'];
    }, $rows);
    $db->update('regions', ['districts' => json_encode($names)], 'id = ?', [(int) $regionId]);
}

function masterLoadDistricts($db, &$sourceTable = 'master_districts')
{
    if (masterTableExists($db, 'districts')) {
        $rows = $db->fetchAll(
            "SELECT d.id, d.region_id, d.name AS district_name, d.code AS district_code,
                    d.status, r.name AS region_name, 'districts' AS source_table
             FROM districts d
             LEFT JOIN regions r ON r.id = d.region_id
             WHERE d.status = 'active' OR d.status IS NULL
             ORDER BY r.name, d.name"
        );
        if (!empty($rows)) {
            $sourceTable = 'districts';
            return $rows;
        }
    }

    $sourceTable = 'master_districts';
    return $db->fetchAll(
        "SELECT d.id, d.region_id, d.district_name, d.district_code,
                IF(d.is_active = 1, 'active', 'inactive') AS status,
                r.name AS region_name, 'master_districts' AS source_table
         FROM master_districts d
         LEFT JOIN regions r ON r.id = d.region_id
         WHERE d.is_active = 1
         ORDER BY r.name, d.district_name"
    );
}

function masterCountBoundaryPoints($boundaryCoordinates)
{
    if (trim((string) $boundaryCoordinates) === '') {
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

    $ring = (isset($decoded[0][0]) && is_array($decoded[0][0])) ? $decoded[0] : $decoded;
    if (!is_array($ring)) {
        throw new Exception('Boundary coordinates must contain polygon points.');
    }

    $uniquePoints = [];
    foreach ($ring as $point) {
        if (!is_array($point)) {
            continue;
        }

        if (isset($point[0], $point[1])) {
            $lng = (float) $point[0];
            $lat = (float) $point[1];
        } elseif (isset($point['lat'], $point['lng'])) {
            $lat = (float) $point['lat'];
            $lng = (float) $point['lng'];
        } elseif (isset($point['latitude'], $point['longitude'])) {
            $lat = (float) $point['latitude'];
            $lng = (float) $point['longitude'];
        } else {
            continue;
        }

        $uniquePoints[number_format($lat, 6, '.', '') . ',' . number_format($lng, 6, '.', '')] = true;
    }

    return count($uniquePoints);
}

$userRole = strtolower(trim((string) ($_SESSION['user_type'] ?? 'admin')));
$isAdmin = in_array($userRole, ['superadmin', 'admin'], true);
$activeTab = $_GET['tab'] ?? 'overview';
$regionId = !empty($_SESSION['region_id']) ? (int) $_SESSION['region_id'] : 0;
$regionWhere = $regionId ? " WHERE region_id = $regionId" : '';
$regionAnd = $regionId ? " AND region_id = $regionId" : '';
$currentFy = MasterSheetsService::currentFinancialYear();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'add_regional_office':
                $db->insert('master_regional_offices', [
                    'region_id' => (int) ($_POST['region_id'] ?? 0),
                    'office_name' => trim($_POST['office_name'] ?? ''),
                    'office_code' => trim($_POST['office_code'] ?? '') ?: null,
                    'address' => trim($_POST['address'] ?? '') ?: null,
                    'is_active' => 1,
                ]);
                $_SESSION['flash_success'] = 'Regional office added.';
                break;

            case 'add_district':
                $districtName = trim($_POST['district_name'] ?? '');
                $districtCode = masterCleanCode($_POST['district_code'] ?? '');
                $districtRegionId = (int) ($_POST['region_id'] ?? 0);

                if ($districtName === '' || $districtRegionId <= 0) {
                    throw new Exception('District name and region are required.');
                }

                if (masterTableExists($db, 'districts')) {
                    $duplicate = $db->fetchOne(
                        'SELECT id FROM districts WHERE LOWER(name) = LOWER(?) AND region_id = ? LIMIT 1',
                        [$districtName, $districtRegionId]
                    );
                    if ($duplicate) {
                        throw new Exception('This district already exists in the selected region.');
                    }
                    $db->insert('districts', [
                        'region_id' => $districtRegionId,
                        'name' => $districtName,
                        'code' => $districtCode !== '' ? $districtCode : substr(masterCleanCode($districtName), 0, 3),
                        'status' => 'active',
                    ]);
                    masterSyncRegionDistricts($db, $districtRegionId);
                } else {
                    $db->insert('master_districts', [
                        'region_id' => $districtRegionId,
                        'district_name' => $districtName,
                        'district_code' => $districtCode !== '' ? $districtCode : null,
                        'is_active' => 1,
                    ]);
                }
                $_SESSION['flash_success'] = 'District added.';
                break;

            case 'add_block':
            case 'update_block':
                $blockMap = masterBlockColumnMap($db);
                if (empty($blockMap['available'])) {
                    throw new Exception('tblBlock table is not available.');
                }
                if (empty($blockMap['name']) || empty($blockMap['district_ref'])) {
                    throw new Exception('tblBlock must have block name and district reference columns before it can be managed here.');
                }

                $blockName = trim($_POST['block_name'] ?? '');
                $blockCode = trim($_POST['block_code'] ?? '');
                $blockDistrictId = (int) ($_POST['district_id'] ?? 0);
                $blockId = trim($_POST['block_id'] ?? '');

                if ($blockName === '' || $blockDistrictId <= 0) {
                    throw new Exception('Block name and district are required.');
                }

                $blockPayload = [
                    $blockMap['name'] => $blockName,
                    $blockMap['district_ref'] => $blockDistrictId,
                ];

                if (!empty($blockMap['code']) && $blockCode !== '') {
                    $blockPayload[$blockMap['code']] = $blockCode;
                } elseif (($action === 'add_block') && !empty($blockMap['primary']) && !masterColumnIsAutoIncrement($blockMap['meta'], $blockMap['primary']) && $blockCode !== '') {
                    $blockPayload[$blockMap['primary']] = $blockCode;
                }

                if ($action === 'update_block') {
                    if ($blockId === '') {
                        throw new Exception('Select a block to edit.');
                    }
                    $setSql = implode(', ', array_map(static function ($column) {
                        return masterQuoteIdentifier($column) . ' = ?';
                    }, array_keys($blockPayload)));
                    $params = array_values($blockPayload);
                    $params[] = $blockId;
                    $db->query('UPDATE `tblBlock` SET ' . $setSql . ' WHERE ' . masterBlockWhereByPrimary($blockMap, $blockId), $params);
                    $_SESSION['flash_success'] = 'Block updated successfully.';
                } else {
                    $duplicate = $db->fetchOne(
                        'SELECT ' . masterQuoteIdentifier($blockMap['primary'] ?: $blockMap['name']) . ' FROM `tblBlock` WHERE LOWER(' . masterQuoteIdentifier($blockMap['name']) . ') = LOWER(?) AND ' . masterQuoteIdentifier($blockMap['district_ref']) . ' = ? LIMIT 1',
                        [$blockName, $blockDistrictId]
                    );
                    if ($duplicate) {
                        throw new Exception('This block already exists for the selected district.');
                    }
                    $columnsSql = implode(', ', array_map('masterQuoteIdentifier', array_keys($blockPayload)));
                    $placeholdersSql = implode(', ', array_fill(0, count($blockPayload), '?'));
                    $db->query('INSERT INTO `tblBlock` (' . $columnsSql . ') VALUES (' . $placeholdersSql . ')', array_values($blockPayload));
                    $_SESSION['flash_success'] = 'Block added successfully.';
                }

                masterSyncBlockDistrictRef($db, $blockName, $blockDistrictId);
                break;

            case 'delete_block':
                $blockMap = masterBlockColumnMap($db);
                if (empty($blockMap['available'])) {
                    throw new Exception('tblBlock table is not available.');
                }
                $blockId = trim($_POST['block_id'] ?? '');
                if ($blockId === '') {
                    throw new Exception('Select a block to delete.');
                }
                $db->delete('tblBlock', masterBlockWhereByPrimary($blockMap, $blockId), [$blockId]);
                $_SESSION['flash_success'] = 'Block deleted successfully.';
                break;

            case 'add_industrial_area':
                $db->insert('master_industrial_areas', [
                    'region_id' => (int) ($_POST['region_id'] ?? 0),
                    'district_id' => (int) ($_POST['district_id'] ?? 0),
                    'area_name' => trim($_POST['area_name'] ?? ''),
                    'area_code' => trim($_POST['area_code'] ?? '') ?: null,
                    'land_price_per_unit' => $_POST['land_price_per_unit'] !== '' ? (float) $_POST['land_price_per_unit'] : null,
                    'unit_type' => $_POST['unit_type'] ?? 'acre',
                    'is_active' => 1,
                ]);
                $_SESSION['flash_success'] = 'Industrial area added.';
                break;

            case 'add_sector':
                $db->insert('master_sectors', [
                    'sector_code' => strtolower(trim($_POST['sector_code'] ?? '')),
                    'sector_name' => trim($_POST['sector_name'] ?? ''),
                    'is_active' => 1,
                ]);
                $_SESSION['flash_success'] = 'Sector added.';
                break;

            case 'add_fee_rule':
                $db->insert('master_fee_rules', [
                    'financial_year' => trim($_POST['financial_year'] ?? $currentFy),
                    'land_category' => strtoupper(trim($_POST['land_category'] ?? 'C')),
                    'min_area' => (float) ($_POST['min_area'] ?? 0),
                    'max_area' => $_POST['max_area'] !== '' ? (float) $_POST['max_area'] : null,
                    'area_unit' => $_POST['area_unit'] ?? 'acre',
                    'processing_fee' => (float) ($_POST['processing_fee'] ?? 0),
                    'min_fee' => $_POST['min_fee'] !== '' ? (float) $_POST['min_fee'] : null,
                    'rate_per_unit' => $_POST['rate_per_unit'] !== '' ? (float) $_POST['rate_per_unit'] : null,
                    'remarks' => trim($_POST['remarks'] ?? '') ?: null,
                    'is_active' => 1,
                ]);
                $_SESSION['flash_success'] = 'Fee rule added.';
                break;

            case 'add_notification_rate':
                $db->insert('master_notification_rates', [
                    'notification_ref' => trim($_POST['notification_ref'] ?? ''),
                    'financial_year' => trim($_POST['financial_year'] ?? $currentFy),
                    'rate_per_sqm' => $_POST['rate_per_sqm'] !== '' ? (float) $_POST['rate_per_sqm'] : null,
                    'floor_price_multiplier' => $_POST['floor_price_multiplier'] !== '' ? (float) $_POST['floor_price_multiplier'] : 1,
                    'effective_from' => $_POST['effective_from'] ?: null,
                    'effective_to' => $_POST['effective_to'] ?: null,
                    'remarks' => trim($_POST['remarks'] ?? '') ?: null,
                    'is_active' => 1,
                ]);
                $_SESSION['flash_success'] = 'Notification rate added.';
                break;

            case 'add_plot':
            case 'save_plot':
                $plotId = (int) ($_POST['plot_id'] ?? 0);
                $vacancyStatus = $_POST['vacancy_status'] ?? 'vacant';

                if ($action === 'save_plot' && $vacancyStatus !== ($_POST['original_vacancy_status'] ?? $vacancyStatus) && !$isAdmin) {
                    throw new Exception('Only Admin can change vacant/allotted status.');
                }

                $areaSqm = (float) ($_POST['area_sqm'] ?? 0);
                $areaAcres = $areaSqm > 0 ? FeeCalculationService::sqmToAcre($areaSqm) : 0;
                $boundaryCoordinates = trim($_POST['boundary_coordinates'] ?? '');

                if ($boundaryCoordinates !== '' && masterCountBoundaryPoints($boundaryCoordinates) < 3) {
                    throw new Exception('Boundary coordinates must contain at least 3 unique points to map the plot area.');
                }

                $payload = [
                    'plot_number' => trim($_POST['plot_number'] ?? ''),
                    'plot_no' => trim($_POST['plot_no'] ?? '') ?: null,
                    'survey_number' => trim($_POST['survey_number'] ?? '') ?: null,
                    'region_id' => (int) ($_POST['region_id'] ?? 0),
                    'regional_office_id' => (int) ($_POST['regional_office_id'] ?? 0) ?: null,
                    'district_id' => (int) ($_POST['district_id'] ?? 0) ?: null,
                    'industrial_area_id' => (int) ($_POST['industrial_area_id'] ?? 0) ?: null,
                    'sector_id' => (int) ($_POST['sector_id'] ?? 0) ?: null,
                    'block' => trim($_POST['block'] ?? '') ?: null,
                    'circle' => trim($_POST['circle'] ?? '') ?: null,
                    'mouja' => trim($_POST['mouja'] ?? '') ?: null,
                    'khata' => trim($_POST['khata'] ?? '') ?: null,
                    'zone' => trim($_POST['zone'] ?? '') ?: null,
                    'sector' => trim($_POST['sector'] ?? '') ?: null,
                    'area_type' => trim($_POST['area_type'] ?? '') ?: null,
                    'plot_type' => trim($_POST['plot_type'] ?? 'industrial'),
                    'area_sqm' => $areaSqm,
                    'area_sqft' => $areaSqm > 0 ? round($areaSqm * 10.7639, 2) : null,
                    'area_acres' => $areaAcres > 0 ? round($areaAcres, 4) : null,
                    'latitude' => $_POST['latitude'] !== '' ? (float) $_POST['latitude'] : null,
                    'longitude' => $_POST['longitude'] !== '' ? (float) $_POST['longitude'] : null,
                    'location_lat' => $_POST['location_lat'] !== '' ? (float) $_POST['location_lat'] : ($_POST['latitude'] !== '' ? (float) $_POST['latitude'] : null),
                    'location_lng' => $_POST['location_lng'] !== '' ? (float) $_POST['location_lng'] : ($_POST['longitude'] !== '' ? (float) $_POST['longitude'] : null),
                    'boundary_coordinates' => $boundaryCoordinates !== '' ? $boundaryCoordinates : null,
                    'base_price_per_sqm' => $_POST['base_price_per_sqm'] !== '' ? (float) $_POST['base_price_per_sqm'] : null,
                    'base_price' => $_POST['base_price'] !== '' ? (float) $_POST['base_price'] : null,
                    'processing_fee' => $_POST['processing_fee'] !== '' ? (float) $_POST['processing_fee'] : null,
                    'floor_price' => $_POST['floor_price'] !== '' ? (float) $_POST['floor_price'] : null,
                    'other_cost' => $_POST['other_cost'] !== '' ? (float) $_POST['other_cost'] : null,
                    'notification_rate_id' => (int) ($_POST['notification_rate_id'] ?? 0) ?: null,
                    'financial_year' => trim($_POST['financial_year'] ?? $currentFy),
                    'vacancy_status' => $vacancyStatus,
                    'status' => MasterSheetsService::mapVacancyToStatus($vacancyStatus),
                    'infrastructure_type' => trim($_POST['infrastructure_type'] ?? 'raw_land'),
                    'remarks' => trim($_POST['remarks'] ?? '') ?: null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                if ($payload['plot_number'] === '' || $payload['region_id'] <= 0 || $areaSqm <= 0) {
                    throw new Exception('Plot number, region, and area (sqm) are required.');
                }

                if ($plotId > 0) {
                    $db->update('land_banks', $payload, 'id = ?', [$plotId]);
                    $_SESSION['flash_success'] = 'Plot updated successfully.';
                } else {
                    $existing = $db->fetchOne('SELECT id FROM land_banks WHERE plot_number = ?', [$payload['plot_number']]);
                    if ($existing) {
                        throw new Exception('Plot number already exists.');
                    }
                    $payload['created_at'] = date('Y-m-d H:i:s');
                    $db->insert('land_banks', $payload);
                    $_SESSION['flash_success'] = 'Plot added successfully.';
                }
                break;

            case 'calculate_fees':
                $plotId = (int) ($_POST['plot_id'] ?? 0);
                $plot = $plotId ? $db->fetchOne('SELECT * FROM land_banks WHERE id = ?', [$plotId]) : null;
                if (!$plot) {
                    throw new Exception('Select a plot to calculate fees.');
                }
                $category = strtoupper(trim($_POST['land_category'] ?? 'C'));
                $fees = MasterSheetsService::calculatePlotFees($db, $plot, $category);
                $db->update('land_banks', [
                    'processing_fee' => $fees['processing_fee'],
                    'floor_price' => $fees['floor_price'],
                    'financial_year' => $fees['financial_year'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$plotId]);
                $_SESSION['flash_success'] = 'Fees calculated: Processing ₹' . number_format($fees['processing_fee'], 2)
                    . ', Floor ₹' . number_format($fees['floor_price'], 2)
                    . ', Total ₹' . number_format($fees['total_fee'], 2);
                break;

            default:
                throw new Exception('Unknown action.');
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }

    $redirectTab = $_POST['redirect_tab'] ?? $activeTab;
    header('Location: ' . APP_URL . '/admin/?page=master-dashboard&tab=' . urlencode($redirectTab));
    exit;
}

$regions = $db->fetchAll('SELECT id, name, code FROM regions WHERE status = "active" OR status IS NULL ORDER BY name');
$regionalOffices = $db->fetchAll(
    'SELECT ro.*, r.name AS region_name FROM master_regional_offices ro LEFT JOIN regions r ON r.id = ro.region_id ORDER BY r.name, ro.office_name'
);
$districtSourceTable = 'master_districts';
$districts = masterLoadDistricts($db, $districtSourceTable);

if ($districtSourceTable === 'districts') {
    $industrialAreas = $db->fetchAll(
        'SELECT ia.*, r.name AS region_name, d.name AS district_name FROM master_industrial_areas ia
         LEFT JOIN regions r ON r.id = ia.region_id
         LEFT JOIN districts d ON d.id = ia.district_id
         ORDER BY r.name, d.name, ia.area_name'
    );
} else {
    $industrialAreas = $db->fetchAll(
        'SELECT ia.*, r.name AS region_name, d.district_name FROM master_industrial_areas ia
         LEFT JOIN regions r ON r.id = ia.region_id
         LEFT JOIN master_districts d ON d.id = ia.district_id
         ORDER BY r.name, d.district_name, ia.area_name'
    );
}
$sectors = $db->fetchAll('SELECT * FROM master_sectors ORDER BY sector_name');
$feeRules = $db->fetchAll('SELECT * FROM master_fee_rules ORDER BY financial_year DESC, land_category, min_area');
$notificationRates = $db->fetchAll('SELECT * FROM master_notification_rates ORDER BY financial_year DESC, notification_ref');
$blockRows = [];
$blockColumns = [];
$blockNormalized = [];
$editBlock = null;
$blockColumnMap = masterBlockColumnMap($db);
$blockTableAvailable = !empty($blockColumnMap['available']);
if ($blockTableAvailable) {
    $blockColumns = $blockColumnMap['columns'] ?? [];
    $blockNameColumn = $blockColumnMap['name'] ?? null;
    $blockCodeColumn = $blockColumnMap['code'] ?? null;
    $blockPrimaryColumn = $blockColumnMap['primary'] ?? null;
    $districtRefColumn = $blockColumnMap['district_ref'] ?? null;
    $orderColumn = $districtRefColumn ?: ($blockNameColumn ?: ($blockColumns[0] ?? null));
    $blockRows = $db->fetchAll('SELECT * FROM `tblBlock`' . ($orderColumn ? ' ORDER BY ' . masterQuoteIdentifier($orderColumn) : '') . ' LIMIT 1000');

    $districtLookup = [];
    foreach ($districts as $district) {
        $districtLookup[(string) ($district['id'] ?? '')] = $district;
        if (!empty($district['district_code'])) {
            $districtLookup[strtolower((string) $district['district_code'])] = $district;
        }
        if (!empty($district['district_name'])) {
            $districtLookup[strtolower((string) $district['district_name'])] = $district;
        }
    }
    foreach ($blockRows as $row) {
        $districtRef = $districtRefColumn ? ($row[$districtRefColumn] ?? '') : '';
        $matchedDistrict = $districtLookup[(string) $districtRef] ?? $districtLookup[strtolower((string) $districtRef)] ?? null;
        $normalizedBlock = [
            'block_id' => (string) ($blockPrimaryColumn ? ($row[$blockPrimaryColumn] ?? '') : ''),
            'block_name' => (string) ($blockNameColumn ? ($row[$blockNameColumn] ?? '') : ''),
            'block_code' => (string) ($blockCodeColumn ? ($row[$blockCodeColumn] ?? '') : ''),
            'district_ref' => (string) $districtRef,
            'district_id' => (int) ($matchedDistrict['id'] ?? 0),
            'district_name' => (string) ($matchedDistrict['district_name'] ?? ''),
            'region_name' => (string) ($matchedDistrict['region_name'] ?? ''),
        ];
        if (isset($_GET['edit_block']) && (string) $_GET['edit_block'] === $normalizedBlock['block_id']) {
            $editBlock = $normalizedBlock;
        }
        $blockNormalized[] = $normalizedBlock;
    }
}

$districtJoin = $districtSourceTable === 'districts'
    ? 'LEFT JOIN districts d ON d.id = lb.district_id'
    : 'LEFT JOIN master_districts d ON d.id = lb.district_id';
$districtNameSelect = $districtSourceTable === 'districts' ? 'd.name AS district_name' : 'd.district_name';
$plotSql = "SELECT lb.id, lb.plot_number, lb.plot_no, lb.area_sqm, lb.vacancy_status, lb.status,
            lb.latitude, lb.longitude, lb.location_lat, lb.location_lng,
            r.name AS region_name, $districtNameSelect, ia.area_name AS industrial_area_name
            FROM land_banks lb
            LEFT JOIN regions r ON r.id = lb.region_id
            $districtJoin
            LEFT JOIN master_industrial_areas ia ON ia.id = lb.industrial_area_id
            WHERE 1=1 $regionAnd
            ORDER BY lb.id DESC LIMIT 500";
$plots = $db->fetchAll($plotSql);

$stats = [
    'total' => $db->fetchOne("SELECT COUNT(*) AS c FROM land_banks WHERE 1=1 $regionAnd")['c'] ?? 0,
    'vacant' => $db->fetchOne("SELECT COUNT(*) AS c FROM land_banks WHERE (vacancy_status = 'vacant' OR status = 'available') $regionAnd")['c'] ?? 0,
    'allotted' => $db->fetchOne("SELECT COUNT(*) AS c FROM land_banks WHERE (vacancy_status = 'allotted' OR status IN ('allotted','reserved')) $regionAnd")['c'] ?? 0,
    'industrial_areas' => $db->fetchOne('SELECT COUNT(*) AS c FROM master_industrial_areas WHERE is_active = 1')['c'] ?? 0,
    'districts' => count($districts),
    'blocks' => count($blockRows),
];

$editPlotId = (int) ($_GET['edit_plot'] ?? 0);
$editPlot = $editPlotId ? $db->fetchOne('SELECT * FROM land_banks WHERE id = ?', [$editPlotId]) : null;
if ($editPlot && empty($editPlot['vacancy_status'])) {
    $editPlot['vacancy_status'] = MasterSheetsService::mapStatusToVacancy((string) ($editPlot['status'] ?? 'available'));
}

$mapCenter = GIS_DEFAULT_CENTER;
$mapZoom = GIS_DEFAULT_ZOOM;

ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<style>
.master-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.master-tabs a { padding:10px 16px; border-radius:8px; background:#fff; border:1px solid var(--border); text-decoration:none; color:var(--dark); font-weight:500; }
.master-tabs a.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.master-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:20px; }
.master-stat { background:#fff; border-radius:12px; padding:18px; box-shadow:var(--shadow); }
.master-stat h4 { font-size:13px; color:var(--gray); margin-bottom:8px; }
.master-stat .val { font-size:26px; font-weight:700; color:var(--primary); }
.master-split { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.master-form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; }
#masterMap { height:480px; border-radius:12px; border:1px solid var(--border); }
.boundary-picker-modal { display:none; position:fixed; inset:0; z-index:3000; background:rgba(15,23,42,.62); align-items:center; justify-content:center; padding:20px; }
.boundary-picker-modal.is-open { display:flex; }
.boundary-picker-dialog { width:min(1100px,96vw); max-height:92vh; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 24px 70px rgba(15,23,42,.35); }
.boundary-picker-header, .boundary-picker-footer { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid var(--border); }
.boundary-picker-footer { border-top:1px solid var(--border); border-bottom:0; }
.boundary-picker-body { padding:16px; }
#boundaryPickerMap { height:520px; border-radius:8px; border:1px solid var(--border); }
.boundary-picker-status { color:var(--gray); font-size:13px; }
.plot-row-selected { background:#e8f4fd !important; }
@media (max-width: 960px) { .master-split { grid-template-columns:1fr; } }
</style>

<div class="master-tabs">
    <a href="<?php echo APP_URL; ?>/admin/?page=master-dashboard&tab=overview" class="<?php echo $activeTab === 'overview' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i> Overview</a>
    <a href="<?php echo APP_URL; ?>/admin/?page=master-dashboard&tab=masters" class="<?php echo $activeTab === 'masters' ? 'active' : ''; ?>"><i class="fas fa-database"></i> Master Data</a>
    <a href="<?php echo APP_URL; ?>/admin/?page=master-dashboard&tab=blocks" class="<?php echo $activeTab === 'blocks' ? 'active' : ''; ?>"><i class="fas fa-border-all"></i> Blocks</a>
</div>

<?php if ($activeTab === 'overview'): ?>
<div class="master-grid">
    <div class="master-stat"><h4>Total Plots</h4><div class="val"><?php echo (int) $stats['total']; ?></div></div>
    <div class="master-stat"><h4>Vacant Plots</h4><div class="val" style="color:#27ae60;"><?php echo (int) $stats['vacant']; ?></div></div>
    <div class="master-stat"><h4>Allotted Plots</h4><div class="val" style="color:#e67e22;"><?php echo (int) $stats['allotted']; ?></div></div>
    <div class="master-stat"><h4>Districts</h4><div class="val"><?php echo (int) $stats['districts']; ?></div></div>
    <div class="master-stat"><h4>Blocks</h4><div class="val"><?php echo (int) $stats['blocks']; ?></div></div>
    <div class="master-stat"><h4>Industrial Areas</h4><div class="val"><?php echo (int) $stats['industrial_areas']; ?></div></div>
    <div class="master-stat"><h4>Financial Year</h4><div class="val" style="font-size:20px;"><?php echo e($currentFy); ?></div></div>
</div>
<div class="card">
    <div class="card-header"><h3><i class="fas fa-sitemap"></i> Hierarchy</h3></div>
    <div class="card-body">
        <p style="margin-bottom:12px;color:var(--gray);">Region → Regional Office → District → Industrial Area → Plot (Block, Circle, Mouja, Khata, Sector)</p>
        <div class="master-grid">
            <?php foreach ($regions as $r): ?>
            <div class="master-stat">
                <h4><?php echo e($r['code'] ?? $r['name']); ?></h4>
                <div style="font-size:13px;color:var(--gray);"><?php echo e($r['name']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($activeTab === 'masters'): ?>
<div class="master-split">
    <div class="card" id="regional-office">
        <div class="card-header"><h3>Regional Office</h3></div>
        <div class="card-body">
            <form method="post" class="master-form-grid">
                <input type="hidden" name="action" value="add_regional_office">
                <input type="hidden" name="redirect_tab" value="masters">
                <div class="form-group"><label>Region</label><select name="region_id" class="form-control" required><?php foreach ($regions as $r): ?><option value="<?php echo (int) $r['id']; ?>"><?php echo e($r['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Office Name</label><input name="office_name" class="form-control" required></div>
                <div class="form-group"><label>Code</label><input name="office_code" class="form-control"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Address</label><input name="address" class="form-control"></div>
                <button type="submit" class="btn btn-primary btn-sm">Add Office</button>
            </form>
            <table class="table" style="margin-top:16px;"><thead><tr><th>Region</th><th>Office</th><th>Code</th></tr></thead><tbody>
                <?php foreach ($regionalOffices as $ro): ?><tr><td><?php echo e($ro['region_name']); ?></td><td><?php echo e($ro['office_name']); ?></td><td><?php echo e($ro['office_code']); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
    <div class="card" id="district-master">
        <div class="card-header"><h3>District</h3></div>
        <div class="card-body">
            <form method="post" class="master-form-grid">
                <input type="hidden" name="action" value="add_district">
                <input type="hidden" name="redirect_tab" value="masters">
                <div class="form-group"><label>Region</label><select name="region_id" class="form-control" required><?php foreach ($regions as $r): ?><option value="<?php echo (int) $r['id']; ?>"><?php echo e($r['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>District Name</label><input name="district_name" class="form-control" required></div>
                <div class="form-group"><label>Code</label><input name="district_code" class="form-control"></div>
                <button type="submit" class="btn btn-primary btn-sm">Add District</button>
            </form>
            <table class="table" style="margin-top:16px;"><thead><tr><th>Region</th><th>District</th></tr></thead><tbody>
                <?php foreach ($districts as $d): ?><tr><td><?php echo e($d['region_name']); ?></td><td><?php echo e($d['district_name']); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
</div>

<div class="master-split" style="margin-top:20px;">
    <div class="card" id="industrial-area">
        <div class="card-header"><h3>Industrial Area</h3></div>
        <div class="card-body">
            <form method="post" class="master-form-grid">
                <input type="hidden" name="action" value="add_industrial_area">
                <input type="hidden" name="redirect_tab" value="masters">
                <div class="form-group"><label>Region</label><select name="region_id" id="ia_region" class="form-control" required><?php foreach ($regions as $r): ?><option value="<?php echo (int) $r['id']; ?>"><?php echo e($r['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>District</label><select name="district_id" id="ia_district" class="form-control" required></select></div>
                <div class="form-group"><label>Area Name</label><input name="area_name" class="form-control" required></div>
                <div class="form-group"><label>Code</label><input name="area_code" class="form-control"></div>
                <div class="form-group"><label>Price/Unit</label><input name="land_price_per_unit" type="number" step="0.01" class="form-control"></div>
                <div class="form-group"><label>Unit</label><select name="unit_type" class="form-control"><option value="acre">Acre</option><option value="hectare">Hectare</option><option value="sqm">Sqm</option><option value="sqft">Sqft</option></select></div>
                <button type="submit" class="btn btn-primary btn-sm">Add Industrial Area</button>
            </form>
            <table class="table" style="margin-top:16px;"><thead><tr><th>Region</th><th>District</th><th>Area</th><th>Rate</th></tr></thead><tbody>
                <?php foreach ($industrialAreas as $ia): ?><tr><td><?php echo e($ia['region_name']); ?></td><td><?php echo e($ia['district_name']); ?></td><td><?php echo e($ia['area_name']); ?></td><td><?php echo e($ia['land_price_per_unit']); ?> / <?php echo e($ia['unit_type']); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Sector Master</h3></div>
        <div class="card-body">
            <form method="post" class="master-form-grid">
                <input type="hidden" name="action" value="add_sector">
                <input type="hidden" name="redirect_tab" value="masters">
                <div class="form-group"><label>Code</label><input name="sector_code" class="form-control" placeholder="production" required></div>
                <div class="form-group"><label>Name</label><input name="sector_name" class="form-control" required></div>
                <button type="submit" class="btn btn-primary btn-sm">Add Sector</button>
            </form>
            <table class="table" style="margin-top:16px;"><thead><tr><th>Code</th><th>Name</th></tr></thead><tbody>
                <?php foreach ($sectors as $s): ?><tr><td><?php echo e($s['sector_code']); ?></td><td><?php echo e($s['sector_name']); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
</div>

<div class="master-split" style="margin-top:20px;">
    <div class="card">
        <div class="card-header"><h3>Fee Rules (FY wise)</h3></div>
        <div class="card-body">
            <form method="post" class="master-form-grid">
                <input type="hidden" name="action" value="add_fee_rule">
                <input type="hidden" name="redirect_tab" value="masters">
                <div class="form-group"><label>Financial Year</label><input name="financial_year" class="form-control" value="<?php echo e($currentFy); ?>" required></div>
                <div class="form-group"><label>Category</label><select name="land_category" class="form-control"><option value="A">A</option><option value="B">B</option><option value="C">C</option></select></div>
                <div class="form-group"><label>Min Area</label><input name="min_area" type="number" step="0.01" class="form-control" value="0"></div>
                <div class="form-group"><label>Max Area</label><input name="max_area" type="number" step="0.01" class="form-control" placeholder="blank = unlimited"></div>
                <div class="form-group"><label>Unit</label><select name="area_unit" class="form-control"><option value="acre">Acre</option><option value="hectare">Hectare</option></select></div>
                <div class="form-group"><label>Processing Fee</label><input name="processing_fee" type="number" step="0.01" class="form-control" required></div>
                <div class="form-group"><label>Min Fee</label><input name="min_fee" type="number" step="0.01" class="form-control"></div>
                <div class="form-group"><label>Rate/Unit</label><input name="rate_per_unit" type="number" step="0.01" class="form-control"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Remarks</label><input name="remarks" class="form-control"></div>
                <button type="submit" class="btn btn-primary btn-sm">Add Fee Rule</button>
            </form>
            <table class="table" style="margin-top:16px;"><thead><tr><th>FY</th><th>Cat</th><th>Range</th><th>Fee</th></tr></thead><tbody>
                <?php foreach ($feeRules as $fr): ?><tr><td><?php echo e($fr['financial_year']); ?></td><td><?php echo e($fr['land_category']); ?></td><td><?php echo e($fr['min_area']); ?> - <?php echo e($fr['max_area'] ?? '∞'); ?> <?php echo e($fr['area_unit']); ?></td><td>₹<?php echo number_format((float) $fr['processing_fee'], 2); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Notification Rates</h3></div>
        <div class="card-body">
            <form method="post" class="master-form-grid">
                <input type="hidden" name="action" value="add_notification_rate">
                <input type="hidden" name="redirect_tab" value="masters">
                <div class="form-group"><label>Notification Ref</label><input name="notification_ref" class="form-control" required></div>
                <div class="form-group"><label>Financial Year</label><input name="financial_year" class="form-control" value="<?php echo e($currentFy); ?>"></div>
                <div class="form-group"><label>Rate/sqm</label><input name="rate_per_sqm" type="number" step="0.01" class="form-control"></div>
                <div class="form-group"><label>Floor Multiplier</label><input name="floor_price_multiplier" type="number" step="0.0001" class="form-control" value="1"></div>
                <div class="form-group"><label>From</label><input name="effective_from" type="date" class="form-control"></div>
                <div class="form-group"><label>To</label><input name="effective_to" type="date" class="form-control"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Remarks</label><input name="remarks" class="form-control"></div>
                <button type="submit" class="btn btn-primary btn-sm">Add Rate</button>
            </form>
            <table class="table" style="margin-top:16px;"><thead><tr><th>Ref</th><th>FY</th><th>Rate/sqm</th><th>Mult.</th></tr></thead><tbody>
                <?php foreach ($notificationRates as $nr): ?><tr><td><?php echo e($nr['notification_ref']); ?></td><td><?php echo e($nr['financial_year']); ?></td><td><?php echo e($nr['rate_per_sqm']); ?></td><td><?php echo e($nr['floor_price_multiplier']); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
</div>

<div class="card" id="block-master" style="margin-top:20px;">
    <div class="card-header"><h3><i class="fas fa-border-all"></i> Block Master</h3></div>
    <div class="card-body">
        <?php if (!$blockTableAvailable): ?>
            <div class="alert alert-warning">tblBlock table is not available in this database.</div>
        <?php elseif (empty($blockRows)): ?>
            <div class="alert alert-info">tblBlock exists, but no blocks have been imported yet.</div>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Block</th><th>Block Code</th><th>District Ref</th><th>Matched District</th><th>Region</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($blockNormalized, 0, 50) as $block): ?>
                    <tr>
                        <td><?php echo e($block['block_name'] ?: '-'); ?></td>
                        <td><?php echo e($block['block_code'] ?: '-'); ?></td>
                        <td><?php echo e($block['district_ref'] ?: '-'); ?></td>
                        <td><?php echo e($block['district_name'] ?: 'Not matched'); ?></td>
                        <td><?php echo e($block['region_name'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($blockRows) > 50): ?><p style="color:var(--gray);font-size:13px;margin-top:10px;">Showing first 50 of <?php echo count($blockRows); ?> imported blocks.</p><?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($activeTab === 'blocks'): ?>
<div class="master-split">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-border-all"></i> Blocks From tblBlock</h3></div>
        <div class="card-body" style="padding:0; max-height:620px; overflow:auto;">
            <?php if (!$blockTableAvailable): ?>
                <div class="alert alert-warning">tblBlock table is not available in this database.</div>
            <?php elseif (empty($blockRows)): ?>
                <div class="alert alert-info">No block rows found in tblBlock. Use the form to add your first block.</div>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Block</th><th>Block Code</th><th>District Ref</th><th>Matched District</th><th>Region</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($blockNormalized as $block): ?>
                        <tr>
                            <td><?php echo e($block['block_name'] ?: '-'); ?></td>
                            <td><?php echo e($block['block_code'] ?: '-'); ?></td>
                            <td><?php echo e($block['district_ref'] ?: '-'); ?></td>
                            <td><?php echo e($block['district_name'] ?: 'Not matched'); ?></td>
                            <td><?php echo e($block['region_name'] ?: '-'); ?></td>
                            <td style="white-space:nowrap;">
                                <?php if ($block['block_id'] !== ''): ?>
                                <a href="<?php echo APP_URL; ?>/admin/?page=master-dashboard&tab=blocks&edit_block=<?php echo urlencode($block['block_id']); ?>" class="btn btn-sm btn-outline">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this block?');">
                                    <input type="hidden" name="action" value="delete_block">
                                    <input type="hidden" name="redirect_tab" value="blocks">
                                    <input type="hidden" name="block_id" value="<?php echo e($block['block_id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                                <?php else: ?>
                                <span style="color:var(--gray);">No key</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-edit"></i> <?php echo $editBlock ? 'Edit Block' : 'Add New Block'; ?></h3></div>
        <div class="card-body">
            <?php if (!$blockTableAvailable): ?>
                <div class="alert alert-warning">Create or import tblBlock first, then this form will become available.</div>
            <?php else: ?>
            <form method="post" class="master-form-grid">
                <input type="hidden" name="action" value="<?php echo $editBlock ? 'update_block' : 'add_block'; ?>">
                <input type="hidden" name="redirect_tab" value="blocks">
                <input type="hidden" name="block_id" value="<?php echo e($editBlock['block_id'] ?? ''); ?>">

                <div class="form-group"><label>Block Name *</label><input name="block_name" class="form-control" value="<?php echo e($editBlock['block_name'] ?? ''); ?>" required></div>
                <div class="form-group"><label>Block Code</label><input name="block_code" class="form-control" value="<?php echo e($editBlock['block_code'] ?? ''); ?>"></div>
                <div class="form-group"><label>District *</label>
                    <select name="district_id" class="form-control" required>
                        <option value="">Select</option>
                        <?php foreach ($districts as $district): ?>
                        <option value="<?php echo (int) $district['id']; ?>" <?php echo (int) ($editBlock['district_id'] ?? 0) === (int) $district['id'] ? 'selected' : ''; ?>><?php echo e(($district['region_name'] ?? '-') . ' - ' . ($district['district_name'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="align-self:end;">
                    <button type="submit" class="btn btn-primary"><?php echo $editBlock ? 'Update Block' : 'Add Block'; ?></button>
                    <?php if ($editBlock): ?><a href="<?php echo APP_URL; ?>/admin/?page=master-dashboard&tab=blocks" class="btn btn-outline">Cancel</a><?php endif; ?>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($activeTab === 'plots' || $activeTab === 'map'): ?>
<div class="master-split">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Pick From Table</h3>
            <a href="<?php echo APP_URL; ?>/admin/?page=master-dashboard&tab=plots" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Plot</a>
        </div>
        <div class="card-body" style="padding:0; max-height:520px; overflow:auto;">
            <table class="table" id="plotTable">
                <thead><tr><th>Plot#</th><th>Region</th><th>Area</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($plots as $p):
                    $vac = $p['vacancy_status'] ?? MasterSheetsService::mapStatusToVacancy((string) ($p['status'] ?? 'available'));
                ?>
                <tr data-plot-id="<?php echo (int) $p['id']; ?>" class="plot-pick-row">
                    <td><?php echo e($p['plot_number']); ?></td>
                    <td><?php echo e($p['region_name'] ?? '-'); ?></td>
                    <td><?php echo e($p['area_sqm']); ?> sqm</td>
                    <td><span class="badge badge-<?php echo $vac === 'vacant' ? 'success' : 'warning'; ?>"><?php echo e($vac); ?></span></td>
                    <td><a href="<?php echo APP_URL; ?>/admin/?page=master-dashboard&tab=plots&edit_plot=<?php echo (int) $p['id']; ?>" class="btn btn-sm btn-outline">Edit</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-edit"></i> Plot Information</h3></div>
        <div class="card-body">
            <form method="post" id="plotForm" class="master-form-grid">
                <input type="hidden" name="action" value="<?php echo $editPlot ? 'save_plot' : 'add_plot'; ?>">
                <input type="hidden" name="redirect_tab" value="<?php echo e($activeTab); ?>">
                <input type="hidden" name="plot_id" value="<?php echo (int) ($editPlot['id'] ?? 0); ?>">
                <input type="hidden" name="original_vacancy_status" value="<?php echo e($editPlot['vacancy_status'] ?? 'vacant'); ?>">

                <div class="form-group"><label>Plot Number *</label><input name="plot_number" class="form-control" value="<?php echo e($editPlot['plot_number'] ?? ''); ?>" required></div>
                <div class="form-group"><label>Plot No</label><input name="plot_no" class="form-control" value="<?php echo e($editPlot['plot_no'] ?? ''); ?>"></div>
                <div class="form-group"><label>Survey No</label><input name="survey_number" class="form-control" value="<?php echo e($editPlot['survey_number'] ?? ''); ?>"></div>

                <div class="form-group"><label>Region *</label>
                    <select name="region_id" id="plot_region" class="form-control" required>
                        <option value="">Select</option>
                        <?php foreach ($regions as $r): ?>
                        <option value="<?php echo (int) $r['id']; ?>" <?php echo (int) ($editPlot['region_id'] ?? 0) === (int) $r['id'] ? 'selected' : ''; ?>><?php echo e($r['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Regional Office</label><select name="regional_office_id" id="plot_regional_office" class="form-control"><option value="">Select</option></select></div>
                <div class="form-group"><label>District</label><select name="district_id" id="plot_district" class="form-control"><option value="">Select</option></select></div>
                <div class="form-group"><label>Industrial Area</label><select name="industrial_area_id" id="plot_industrial_area" class="form-control"><option value="">Select</option></select></div>

                <div class="form-group"><label>Block</label><select name="block" id="plot_block" class="form-control" data-current="<?php echo e($editPlot['block'] ?? ''); ?>"><option value="">Select district first</option></select></div>
                <div class="form-group"><label>Circle</label><input name="circle" class="form-control" value="<?php echo e($editPlot['circle'] ?? ''); ?>"></div>
                <div class="form-group"><label>Mouja</label><input name="mouja" class="form-control" value="<?php echo e($editPlot['mouja'] ?? ''); ?>"></div>
                <div class="form-group"><label>Khata</label><input name="khata" class="form-control" value="<?php echo e($editPlot['khata'] ?? ''); ?>"></div>
                <div class="form-group"><label>Sector</label><select name="sector_id" id="plot_sector" class="form-control"><option value="">Select</option><?php foreach ($sectors as $s): ?><option value="<?php echo (int) $s['id']; ?>" <?php echo (int) ($editPlot['sector_id'] ?? 0) === (int) $s['id'] ? 'selected' : ''; ?>><?php echo e($s['sector_name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Zone</label><input name="zone" class="form-control" value="<?php echo e($editPlot['zone'] ?? ''); ?>"></div>
                <div class="form-group"><label>Area Type</label><input name="area_type" class="form-control" value="<?php echo e($editPlot['area_type'] ?? ''); ?>" placeholder="e.g. Industrial"></div>

                <div class="form-group"><label>Plot Area (sqm) *</label><input name="area_sqm" id="plot_area_sqm" type="number" step="0.01" class="form-control" value="<?php echo e($editPlot['area_sqm'] ?? ''); ?>" required></div>
                <div class="form-group"><label>Plot Type</label><select name="plot_type" class="form-control"><?php foreach (['industrial','commercial','flatted_factory','warehouse','mixed_use'] as $pt): ?><option value="<?php echo $pt; ?>" <?php echo ($editPlot['plot_type'] ?? 'industrial') === $pt ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_',' ',$pt)); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Vacancy Status <?php if (!$isAdmin): ?><small>(Admin only to change)</small><?php endif; ?></label>
                    <select name="vacancy_status" class="form-control" <?php echo !$isAdmin && $editPlot ? 'disabled' : ''; ?>>
                        <option value="vacant" <?php echo ($editPlot['vacancy_status'] ?? 'vacant') === 'vacant' ? 'selected' : ''; ?>>Vacant</option>
                        <option value="allotted" <?php echo ($editPlot['vacancy_status'] ?? '') === 'allotted' ? 'selected' : ''; ?>>Allotted</option>
                    </select>
                    <?php if (!$isAdmin && $editPlot): ?><input type="hidden" name="vacancy_status" value="<?php echo e($editPlot['vacancy_status'] ?? 'vacant'); ?>"><?php endif; ?>
                </div>

                <div class="form-group"><label>Latitude (GPS)</label><input name="latitude" id="plot_lat" class="form-control" value="<?php echo e($editPlot['latitude'] ?? $editPlot['location_lat'] ?? ''); ?>"></div>
                <div class="form-group"><label>Longitude (GPS)</label><input name="longitude" id="plot_lng" class="form-control" value="<?php echo e($editPlot['longitude'] ?? $editPlot['location_lng'] ?? ''); ?>"></div>
                <input type="hidden" name="location_lat" id="plot_location_lat" value="<?php echo e($editPlot['location_lat'] ?? ''); ?>">
                <input type="hidden" name="location_lng" id="plot_location_lng" value="<?php echo e($editPlot['location_lng'] ?? ''); ?>">

                <div class="form-group" style="grid-column:1/-1;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:6px;">
                        <label style="margin:0;">Boundary Coordinates</label>
                        <button type="button" class="btn btn-sm btn-outline" id="openBoundaryPicker"><i class="fas fa-draw-polygon"></i> Select on Map</button>
                    </div>
                    <textarea id="boundaryCoordinates" name="boundary_coordinates" class="form-control" rows="3" placeholder="GeoJSON coordinates will appear here after drawing on map"><?php echo e($editPlot['boundary_coordinates'] ?? ''); ?></textarea>
                </div>

                <div class="form-group"><label>Base Price/sqm</label><input name="base_price_per_sqm" type="number" step="0.01" class="form-control" value="<?php echo e($editPlot['base_price_per_sqm'] ?? ''); ?>"></div>
                <div class="form-group"><label>Base Price</label><input name="base_price" type="number" step="0.01" class="form-control" value="<?php echo e($editPlot['base_price'] ?? ''); ?>"></div>
                <div class="form-group"><label>Financial Year</label><input name="financial_year" class="form-control" value="<?php echo e($editPlot['financial_year'] ?? $currentFy); ?>"></div>
                <div class="form-group"><label>Notification Rate</label><select name="notification_rate_id" class="form-control"><option value="">None</option><?php foreach ($notificationRates as $nr): ?><option value="<?php echo (int) $nr['id']; ?>" <?php echo (int) ($editPlot['notification_rate_id'] ?? 0) === (int) $nr['id'] ? 'selected' : ''; ?>><?php echo e($nr['notification_ref']); ?> (<?php echo e($nr['financial_year']); ?>)</option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Processing Fee</label><input name="processing_fee" type="number" step="0.01" class="form-control" value="<?php echo e($editPlot['processing_fee'] ?? ''); ?>"></div>
                <div class="form-group"><label>Floor Price</label><input name="floor_price" type="number" step="0.01" class="form-control" value="<?php echo e($editPlot['floor_price'] ?? ''); ?>"></div>
                <div class="form-group"><label>Other Cost</label><input name="other_cost" type="number" step="0.01" class="form-control" value="<?php echo e($editPlot['other_cost'] ?? ''); ?>"></div>
                <div class="form-group"><label>Infra Type</label><select name="infrastructure_type" class="form-control"><?php foreach (['raw_land','developed','plug_play'] as $it): ?><option value="<?php echo $it; ?>" <?php echo ($editPlot['infrastructure_type'] ?? 'raw_land') === $it ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_',' ',$it)); ?></option><?php endforeach; ?></select></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2"><?php echo e($editPlot['remarks'] ?? ''); ?></textarea></div>

                <div style="grid-column:1/-1;display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary"><?php echo $editPlot ? 'Update Plot' : 'Add Plot'; ?></button>
                </div>
            </form>
            <?php if ($editPlot): ?>
            <form method="post" style="margin-top:12px;display:flex;gap:10px;align-items:center;">
                <input type="hidden" name="action" value="calculate_fees">
                <input type="hidden" name="plot_id" value="<?php echo (int) $editPlot['id']; ?>">
                <input type="hidden" name="land_category" value="C">
                <input type="hidden" name="redirect_tab" value="<?php echo e($activeTab); ?>">
                <button type="submit" class="btn btn-outline">Calculate Fees (Processing + Floor + Other)</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="boundaryPickerModal" class="boundary-picker-modal" aria-hidden="true">
    <div class="boundary-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="boundaryPickerTitle">
        <div class="boundary-picker-header">
            <div>
                <h3 id="boundaryPickerTitle" style="margin:0;"><i class="fas fa-draw-polygon"></i> Select Plot Boundary</h3>
                <div class="boundary-picker-status" id="boundaryPickerStatus">Draw a polygon with at least 3 points.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline" id="closeBoundaryPicker">Close</button>
        </div>
        <div class="boundary-picker-body"><div id="boundaryPickerMap"></div></div>
        <div class="boundary-picker-footer">
            <div class="boundary-picker-status">Use polygon drawing tools on the map, then save coordinates back to this plot.</div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-outline" id="clearBoundaryPolygon">Clear</button>
                <button type="button" class="btn btn-primary" id="useBoundaryPolygon">Use Boundary</button>
            </div>
        </div>
    </div>
</div>

<?php if ($activeTab === 'map'): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3><i class="fas fa-map-marked-alt"></i> GIS Viewer — click map to set GPS for selected plot</h3></div>
    <div class="card-body">
        <div id="masterMap"></div>
        <p style="margin-top:10px;color:var(--gray);font-size:13px;">Markers show plot locations. Click a table row or marker to load plot details. Click the map to update coordinates in the form.</p>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
const APP_BASE = '<?php echo APP_URL; ?>';
const MAP_CENTER = <?php echo json_encode($mapCenter); ?>;
const MAP_ZOOM = <?php echo (int) $mapZoom; ?>;
const EDIT_REGION = <?php echo (int) ($editPlot['region_id'] ?? 0); ?>;
const EDIT_DISTRICT = <?php echo (int) ($editPlot['district_id'] ?? 0); ?>;
const EDIT_OFFICE = <?php echo (int) ($editPlot['regional_office_id'] ?? 0); ?>;
const EDIT_IA = <?php echo (int) ($editPlot['industrial_area_id'] ?? 0); ?>;
const BLOCKS_DATA = <?php echo json_encode($blockNormalized); ?>;
const PLOTS_GEO = <?php echo json_encode(array_map(function ($p) {
    $lat = $p['latitude'] ?? $p['location_lat'];
    $lng = $p['longitude'] ?? $p['location_lng'];
  return ['id' => (int) $p['id'], 'plot_number' => $p['plot_number'], 'lat' => $lat, 'lng' => $lng, 'area_sqm' => $p['area_sqm']];
}, $plots)); ?>;

async function loadCascade(type, params, selectEl, selectedId) {
    const qs = new URLSearchParams({ type, ...params });
    const res = await fetch(APP_BASE + '/api/master/cascade?' + qs.toString());
    const json = await res.json();
    if (!json.success) return;
    selectEl.innerHTML = '<option value="">Select</option>';
    json.data.forEach(row => {
        const opt = document.createElement('option');
        const id = row.id;
        let label = row.office_name || row.district_name || row.area_name || row.sector_name || row.notification_ref || id;
        opt.value = id;
        opt.textContent = label;
        if (selectedId && Number(selectedId) === Number(id)) opt.selected = true;
        selectEl.appendChild(opt);
    });
}

function bindRegionDistrictCascade(regionSel, districtSel, officeSel, iaSel, presetRegion, presetDistrict, presetOffice, presetIa) {
    if (!regionSel) return;
    const refresh = async () => {
        const regionId = regionSel.value;
        if (officeSel) await loadCascade('regional_offices', { region_id: regionId }, officeSel, presetOffice && regionId == presetRegion ? presetOffice : 0);
        if (districtSel) await loadCascade('districts', { region_id: regionId }, districtSel, presetDistrict && regionId == presetRegion ? presetDistrict : 0);
        if (iaSel && districtSel) {
            const districtId = districtSel.value;
            await loadCascade('industrial_areas', { district_id: districtId }, iaSel, presetIa && districtId == presetDistrict ? presetIa : 0);
        }
    };
    regionSel.addEventListener('change', () => { presetOffice = 0; presetDistrict = 0; presetIa = 0; refresh(); });
    if (districtSel) districtSel.addEventListener('change', async () => {
        presetIa = 0;
        if (iaSel) await loadCascade('industrial_areas', { district_id: districtSel.value }, iaSel, 0);
    });
    refresh();
}


function parseBoundaryCoordinates(raw) {
    if (!raw || !raw.trim()) return null;
    try {
        const parsed = JSON.parse(raw);
        if (parsed.type === 'Feature' && parsed.geometry && parsed.geometry.type === 'Polygon') return parsed.geometry.coordinates;
        if (parsed.type === 'Polygon' && parsed.coordinates) return parsed.coordinates;
        return parsed;
    } catch (error) {
        return null;
    }
}

function boundaryToLatLngs(coords) {
    const ring = Array.isArray(coords) && Array.isArray(coords[0]) && Array.isArray(coords[0][0]) ? coords[0] : coords;
    if (!Array.isArray(ring)) return [];
    return ring.map(point => {
        if (Array.isArray(point) && point.length >= 2) return [Number(point[1]), Number(point[0])];
        if (point && typeof point === 'object') return [Number(point.lat ?? point.latitude), Number(point.lng ?? point.longitude)];
        return null;
    }).filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1]));
}

function countUniqueBoundaryPoints(latLngs) {
    const unique = new Set();
    latLngs.forEach(point => unique.add(point[0].toFixed(6) + ',' + point[1].toFixed(6)));
    return unique.size;
}

function bindBoundaryPicker() {
    const openBtn = document.getElementById('openBoundaryPicker');
    const modal = document.getElementById('boundaryPickerModal');
    const closeBtn = document.getElementById('closeBoundaryPicker');
    const clearBtn = document.getElementById('clearBoundaryPolygon');
    const useBtn = document.getElementById('useBoundaryPolygon');
    const textarea = document.getElementById('boundaryCoordinates');
    const status = document.getElementById('boundaryPickerStatus');
    const latInput = document.getElementById('plot_lat');
    const lngInput = document.getElementById('plot_lng');

    if (!openBtn || !modal || !textarea) return;

    let pickerMap = null;
    let drawnItems = null;
    let selectedLayer = null;

    const setStatus = (message) => { if (status) status.textContent = message; };

    const ensureMap = () => {
        if (pickerMap) return;
        if (typeof L === 'undefined' || !L.Control || !L.Control.Draw) {
            setStatus('Leaflet Draw is not loaded. Check internet/CDN access.');
            return;
        }

        const lat = parseFloat(latInput?.value || MAP_CENTER[0]);
        const lng = parseFloat(lngInput?.value || MAP_CENTER[1]);
        pickerMap = L.map('boundaryPickerMap').setView([Number.isFinite(lat) ? lat : MAP_CENTER[0], Number.isFinite(lng) ? lng : MAP_CENTER[1]], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(pickerMap);

        drawnItems = new L.FeatureGroup();
        pickerMap.addLayer(drawnItems);
        pickerMap.addControl(new L.Control.Draw({
            edit: { featureGroup: drawnItems },
            draw: { polygon: true, rectangle: false, circle: false, circlemarker: false, marker: false, polyline: false }
        }));

        pickerMap.on(L.Draw.Event.CREATED, (event) => {
            drawnItems.clearLayers();
            selectedLayer = event.layer;
            drawnItems.addLayer(selectedLayer);
            setStatus('Boundary selected. Click Use Boundary to save it to the plot form.');
        });

        pickerMap.on(L.Draw.Event.EDITED, () => setStatus('Boundary updated. Click Use Boundary to save it.'));

        const existing = boundaryToLatLngs(parseBoundaryCoordinates(textarea.value));
        if (existing.length >= 3) {
            selectedLayer = L.polygon(existing);
            drawnItems.addLayer(selectedLayer);
            pickerMap.fitBounds(selectedLayer.getBounds().pad(0.25));
            setStatus('Existing boundary loaded. Edit it or click Use Boundary.');
        }
    };

    openBtn.addEventListener('click', () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        ensureMap();
        setTimeout(() => pickerMap && pickerMap.invalidateSize(), 150);
    });

    closeBtn?.addEventListener('click', () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    });

    clearBtn?.addEventListener('click', () => {
        if (drawnItems) drawnItems.clearLayers();
        selectedLayer = null;
        textarea.value = '';
        setStatus('Boundary cleared. Draw a new polygon with at least 3 points.');
    });

    useBtn?.addEventListener('click', () => {
        if (!selectedLayer && drawnItems) {
            drawnItems.eachLayer(layer => { selectedLayer = layer; });
        }
        if (!selectedLayer || !selectedLayer.getLatLngs) {
            setStatus('Draw a polygon before using the boundary.');
            return;
        }
        const ring = selectedLayer.getLatLngs()[0] || [];
        if (countUniqueBoundaryPoints(ring.map(point => [point.lat, point.lng])) < 3) {
            setStatus('Boundary must contain at least 3 unique points.');
            return;
        }
        const coordinates = ring.map(point => [Number(point.lng.toFixed(6)), Number(point.lat.toFixed(6))]);
        if (coordinates.length && (coordinates[0][0] !== coordinates[coordinates.length - 1][0] || coordinates[0][1] !== coordinates[coordinates.length - 1][1])) {
            coordinates.push(coordinates[0]);
        }
        textarea.value = JSON.stringify([coordinates]);
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    });
}

function refreshBlockOptions() {
    const districtSel = document.getElementById('plot_district');
    const blockSel = document.getElementById('plot_block');
    if (!districtSel || !blockSel) return;

    const districtId = Number(districtSel.value || 0);
    const current = blockSel.dataset.current || '';
    blockSel.innerHTML = '<option value="">Select</option>';

    const rows = BLOCKS_DATA.filter(row => Number(row.district_id || 0) === districtId);
    rows.forEach(row => {
        const value = row.block_name || row.block_code;
        if (!value) return;
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = row.block_name + (row.block_code && row.block_code !== row.block_name ? ' (' + row.block_code + ')' : '');
        if (current && current === value) opt.selected = true;
        blockSel.appendChild(opt);
    });

    if (current && !Array.from(blockSel.options).some(option => option.value === current)) {
        const opt = document.createElement('option');
        opt.value = current;
        opt.textContent = current + ' (saved value)';
        opt.selected = true;
        blockSel.appendChild(opt);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    bindBoundaryPicker();

    const iaRegion = document.getElementById('ia_region');
    const iaDistrict = document.getElementById('ia_district');
    if (iaRegion && iaDistrict) {
        bindRegionDistrictCascade(iaRegion, iaDistrict, null, null, iaRegion.value, 0, 0, 0);
    }

    const plotRegion = document.getElementById('plot_region');
    const plotDistrict = document.getElementById('plot_district');
    const plotOffice = document.getElementById('plot_regional_office');
    const plotIa = document.getElementById('plot_industrial_area');
	    if (plotRegion) {
	        bindRegionDistrictCascade(plotRegion, plotDistrict, plotOffice, plotIa, EDIT_REGION, EDIT_DISTRICT, EDIT_OFFICE, EDIT_IA);
	        if (plotDistrict) {
	            plotDistrict.addEventListener('change', refreshBlockOptions);
	            setTimeout(refreshBlockOptions, 350);
	        }
	    }

    document.querySelectorAll('.plot-pick-row').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('a')) return;
            const id = row.dataset.plotId;
            window.location.href = APP_BASE + '/admin/?page=master-dashboard&tab=plots&edit_plot=' + id;
        });
    });

    if (document.getElementById('masterMap')) {
        const map = L.map('masterMap').setView(MAP_CENTER, MAP_ZOOM);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
        const markers = [];
        let activeMarker = null;

        PLOTS_GEO.forEach(p => {
            if (!p.lat || !p.lng) return;
            const m = L.marker([p.lat, p.lng]).addTo(map).bindPopup('<strong>' + p.plot_number + '</strong><br>' + p.area_sqm + ' sqm');
            m.on('click', () => { window.location.href = APP_BASE + '/admin/?page=master-dashboard&tab=map&edit_plot=' + p.id; });
            markers.push(m);
        });
        if (markers.length) {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.2));
        }

        const latInput = document.getElementById('plot_lat');
        const lngInput = document.getElementById('plot_lng');
        const locLat = document.getElementById('plot_location_lat');
        const locLng = document.getElementById('plot_location_lng');

        if (latInput && lngInput && latInput.value && lngInput.value) {
            activeMarker = L.marker([parseFloat(latInput.value), parseFloat(lngInput.value)], { draggable: true }).addTo(map);
            activeMarker.on('dragend', () => {
                const pos = activeMarker.getLatLng();
                latInput.value = pos.lat.toFixed(6);
                lngInput.value = pos.lng.toFixed(6);
                if (locLat) locLat.value = pos.lat.toFixed(6);
                if (locLng) locLng.value = pos.lng.toFixed(6);
            });
            map.setView(activeMarker.getLatLng(), 14);
        }

        map.on('click', (e) => {
            if (!latInput || !lngInput) return;
            latInput.value = e.latlng.lat.toFixed(6);
            lngInput.value = e.latlng.lng.toFixed(6);
            if (locLat) locLat.value = e.latlng.lat.toFixed(6);
            if (locLng) locLng.value = e.latlng.lng.toFixed(6);
            if (activeMarker) map.removeLayer(activeMarker);
            activeMarker = L.marker(e.latlng, { draggable: true }).addTo(map);
            activeMarker.on('dragend', () => {
                const pos = activeMarker.getLatLng();
                latInput.value = pos.lat.toFixed(6);
                lngInput.value = pos.lng.toFixed(6);
                if (locLat) locLat.value = pos.lat.toFixed(6);
                if (locLng) locLng.value = pos.lng.toFixed(6);
            });
        });
    }
});
</script>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
