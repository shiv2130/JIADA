<?php
/**
 * Master data cascade API.
 *
 * Feeds dependent admin dropdowns such as Region -> District -> Industrial Area.
 */
if (!defined('JIADA_APP')) {
    define('JIADA_APP', true);
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$db = getDB();

function cascadeTableExists($db, $table)
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

function cascadeColumns($db, $table)
{
    try {
        return array_map(static function ($row) {
            return $row['Field'];
        }, $db->fetchAll('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`'));
    } catch (Exception $e) {
        return [];
    }
}

function cascadePickColumn(array $columns, array $candidates)
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

try {
    $type = $_GET['type'] ?? '';
    $regionId = (int) ($_GET['region_id'] ?? 0);
    $districtId = (int) ($_GET['district_id'] ?? 0);

    switch ($type) {
        case 'regional_offices':
            $rows = $db->fetchAll(
                'SELECT id, office_name, office_code
                 FROM master_regional_offices
                 WHERE (? = 0 OR region_id = ?) AND is_active = 1
                 ORDER BY office_name',
                [$regionId, $regionId]
            );
            jsonResponse(['success' => true, 'data' => $rows]);

        case 'districts':
            if (cascadeTableExists($db, 'districts')) {
                $rows = $db->fetchAll(
                    "SELECT id, name AS district_name, code AS district_code
                     FROM districts
                     WHERE (? = 0 OR region_id = ?) AND (status = 'active' OR status IS NULL)
                     ORDER BY name",
                    [$regionId, $regionId]
                );
                if (!empty($rows)) {
                    jsonResponse(['success' => true, 'data' => $rows]);
                }
            }

            $rows = $db->fetchAll(
                'SELECT id, district_name, district_code
                 FROM master_districts
                 WHERE (? = 0 OR region_id = ?) AND is_active = 1
                 ORDER BY district_name',
                [$regionId, $regionId]
            );
            jsonResponse(['success' => true, 'data' => $rows]);

        case 'industrial_areas':
            $rows = $db->fetchAll(
                'SELECT id, area_name, area_code
                 FROM master_industrial_areas
                 WHERE (? = 0 OR district_id = ?) AND is_active = 1
                 ORDER BY area_name',
                [$districtId, $districtId]
            );
            jsonResponse(['success' => true, 'data' => $rows]);

        case 'blocks':
            if (!cascadeTableExists($db, 'tblBlock')) {
                jsonResponse(['success' => true, 'data' => []]);
            }

            $columns = cascadeColumns($db, 'tblBlock');
            $blockNameColumn = cascadePickColumn($columns, ['BlockName', 'block_name', 'block', 'name']);
            $blockCodeColumn = cascadePickColumn($columns, ['BlockCode', 'block_code', 'code', 'BlockID', 'id']);
            $districtRefColumn = cascadePickColumn($columns, ['DistrictID', 'district_id', 'DistID', 'dist_id', 'district_code', 'DistrictCode', 'district']);

            if (!$blockNameColumn || !$districtRefColumn) {
                jsonResponse(['success' => true, 'data' => []]);
            }

            $sql = 'SELECT `' . str_replace('`', '``', $blockNameColumn) . '` AS block_name';
            $sql .= $blockCodeColumn ? ', `' . str_replace('`', '``', $blockCodeColumn) . '` AS block_code' : ', NULL AS block_code';
            $sql .= ', `' . str_replace('`', '``', $districtRefColumn) . '` AS district_ref FROM `tblBlock`';
            $params = [];
            if ($districtId > 0) {
                $sql .= ' WHERE `' . str_replace('`', '``', $districtRefColumn) . '` = ?';
                $params[] = $districtId;
            }
            $sql .= ' ORDER BY `' . str_replace('`', '``', $blockNameColumn) . '` LIMIT 1000';

            jsonResponse(['success' => true, 'data' => $db->fetchAll($sql, $params)]);

        default:
            jsonResponse(['success' => false, 'message' => 'Invalid cascade type.'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
