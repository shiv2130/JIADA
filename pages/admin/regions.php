<?php
$pageTitle = "Region & District Master - Admin";
$currentPage = 'regions';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ' . APP_URL . '/admin/?page=login'); exit; }

$db = getDB();
$showAddRegion = (($_GET['show'] ?? '') === 'add-region');
$showAddDistrict = (($_GET['show'] ?? '') === 'add-district');
$editRegionId = !empty($_GET['edit_region']) ? (int) $_GET['edit_region'] : 0;
$editDistrictId = !empty($_GET['edit_district']) ? (int) $_GET['edit_district'] : 0;
$flashError = '';
$flashSuccess = '';
$editingRegion = null;
$editingDistrict = null;

function syncRegionDistricts($db, $regionId) {
    $rows = $db->fetchAll(
        "SELECT name FROM districts WHERE region_id = ? AND status = 'active' ORDER BY name ASC",
        [$regionId]
    );
    $names = array_map(function($row) {
        return $row['name'];
    }, $rows);

    $db->update('regions', [
        'districts' => json_encode($names)
    ], 'id = ?', [$regionId]);
}

function cleanCode($value) {
    return strtoupper(preg_replace('/[^A-Z0-9_]+/', '', trim((string) $value)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_region' || $action === 'update_region') {
            $regionId = (int) ($_POST['region_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $code = cleanCode($_POST['code'] ?? '');
            $headquarters = trim((string) ($_POST['headquarters'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $status = trim((string) ($_POST['status'] ?? 'active'));

            if ($name === '' || $code === '' || $headquarters === '') {
                throw new Exception('Region name, code, and headquarters are required.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid region email address.');
            }

            $duplicateParams = [$code];
            $duplicateSql = "SELECT id FROM regions WHERE code = ?";
            if ($action === 'update_region') {
                $duplicateSql .= " AND id != ?";
                $duplicateParams[] = $regionId;
            }
            $duplicate = $db->fetchOne($duplicateSql . " LIMIT 1", $duplicateParams);
            if ($duplicate) {
                throw new Exception('Another region already uses this code.');
            }

            $payload = [
                'name' => $name,
                'code' => $code,
                'headquarters' => $headquarters,
                'address' => $address !== '' ? $address : null,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            ];

            if ($action === 'add_region') {
                $payload['districts'] = json_encode([]);
                $db->insert('regions', $payload);
                header('Location: ' . APP_URL . '/admin/?page=regions&success=region_added');
                exit;
            }

            if ($regionId <= 0) {
                throw new Exception('Invalid region selected for update.');
            }
            $db->update('regions', $payload, 'id = ?', [$regionId]);
            syncRegionDistricts($db, $regionId);
            header('Location: ' . APP_URL . '/admin/?page=regions&success=region_updated');
            exit;
        }

        if ($action === 'add_district' || $action === 'update_district') {
            $districtId = (int) ($_POST['district_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $code = cleanCode($_POST['code'] ?? '');
            $regionId = (int) ($_POST['region_id'] ?? 0);
            $status = trim((string) ($_POST['status'] ?? 'active'));

            if ($name === '' || $regionId <= 0) {
                throw new Exception('District name and region are required.');
            }

            $region = $db->fetchOne("SELECT id FROM regions WHERE id = ? LIMIT 1", [$regionId]);
            if (!$region) {
                throw new Exception('Selected region does not exist.');
            }

            $duplicateParams = [$name, $regionId];
            $duplicateSql = "SELECT id FROM districts WHERE LOWER(name) = LOWER(?) AND region_id = ?";
            if ($action === 'update_district') {
                $duplicateSql .= " AND id != ?";
                $duplicateParams[] = $districtId;
            }
            $duplicate = $db->fetchOne($duplicateSql . " LIMIT 1", $duplicateParams);
            if ($duplicate) {
                throw new Exception('This district already exists in the selected region.');
            }

            $payload = [
                'name' => $name,
                'code' => $code !== '' ? $code : null,
                'region_id' => $regionId,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            ];

            if ($action === 'add_district') {
                if (empty($payload['code'])) {
                    $payload['code'] = substr(cleanCode($name), 0, 3);
                }
                $db->insert('districts', $payload);
                syncRegionDistricts($db, $regionId);
                header('Location: ' . APP_URL . '/admin/?page=regions&success=district_added');
                exit;
            }

            if ($districtId <= 0) {
                throw new Exception('Invalid district selected for update.');
            }
            $oldDistrict = $db->fetchOne("SELECT region_id FROM districts WHERE id = ? LIMIT 1", [$districtId]);
            $oldRegionId = (int) ($oldDistrict['region_id'] ?? 0);
            $db->update('districts', $payload, 'id = ?', [$districtId]);
            syncRegionDistricts($db, $regionId);
            if ($oldRegionId && $oldRegionId !== $regionId) {
                syncRegionDistricts($db, $oldRegionId);
            }
            header('Location: ' . APP_URL . '/admin/?page=regions&success=district_updated');
            exit;
        }

        if ($action === 'delete_district') {
            $districtId = (int) ($_POST['district_id'] ?? 0);
            if ($districtId <= 0) {
                throw new Exception('Invalid district selected for deletion.');
            }
            $district = $db->fetchOne("SELECT region_id FROM districts WHERE id = ? LIMIT 1", [$districtId]);
            if (!$district) {
                throw new Exception('District not found.');
            }
            $regionId = (int) $district['region_id'];
            $db->delete('districts', 'id = ?', [$districtId]);
            syncRegionDistricts($db, $regionId);
            header('Location: ' . APP_URL . '/admin/?page=regions&success=district_deleted');
            exit;
        }

        if ($action === 'delete_region') {
            $regionId = (int) ($_POST['region_id'] ?? 0);
            if ($regionId <= 0) {
                throw new Exception('Invalid region selected for deletion.');
            }
            $districtCount = (int) ($db->fetchOne("SELECT COUNT(*) AS c FROM districts WHERE region_id = ?", [$regionId])['c'] ?? 0);
            $userCount = (int) ($db->fetchOne("SELECT COUNT(*) AS c FROM users WHERE region_id = ?", [$regionId])['c'] ?? 0);
            $plotCount = (int) ($db->fetchOne("SELECT COUNT(*) AS c FROM land_banks WHERE region_id = ?", [$regionId])['c'] ?? 0);
            if ($districtCount || $userCount || $plotCount) {
                throw new Exception('Region cannot be deleted while districts, users, or plots are mapped to it. Move or delete those records first.');
            }
            $db->delete('regions', 'id = ?', [$regionId]);
            header('Location: ' . APP_URL . '/admin/?page=regions&success=region_deleted');
            exit;
        }
    } catch (Exception $e) {
        $flashError = $e->getMessage();
        $showAddRegion = $action === 'add_region';
        $showAddDistrict = $action === 'add_district';
        $editRegionId = $action === 'update_region' ? (int) ($_POST['region_id'] ?? 0) : $editRegionId;
        $editDistrictId = $action === 'update_district' ? (int) ($_POST['district_id'] ?? 0) : $editDistrictId;
    }
}

$successMessages = [
    'region_added' => 'Region added successfully.',
    'region_updated' => 'Region updated successfully.',
    'region_deleted' => 'Region deleted successfully.',
    'district_added' => 'District added successfully.',
    'district_updated' => 'District updated successfully.',
    'district_deleted' => 'District deleted successfully.',
];
if (isset($_GET['success'], $successMessages[$_GET['success']])) {
    $flashSuccess = $successMessages[$_GET['success']];
}

$regions = $db->fetchAll(
    "SELECT r.*,
            (SELECT COUNT(*) FROM districts d WHERE d.region_id = r.id) AS district_count,
            (SELECT COUNT(*) FROM users u WHERE u.region_id = r.id) AS user_count,
            (SELECT COUNT(*) FROM land_banks lb WHERE lb.region_id = r.id) AS plot_count
     FROM regions r
     ORDER BY r.name ASC"
);
$districts = $db->fetchAll(
    "SELECT d.*, r.name AS region_name
     FROM districts d
     INNER JOIN regions r ON r.id = d.region_id
     ORDER BY r.name ASC, d.name ASC"
);

if ($editRegionId > 0) {
    $editingRegion = $db->fetchOne("SELECT * FROM regions WHERE id = ? LIMIT 1", [$editRegionId]);
    if (!$editingRegion && !$flashError) {
        $flashError = 'Region not found for editing.';
    }
}
if ($editDistrictId > 0) {
    $editingDistrict = $db->fetchOne("SELECT * FROM districts WHERE id = ? LIMIT 1", [$editDistrictId]);
    if (!$editingDistrict && !$flashError) {
        $flashError = 'District not found for editing.';
    }
}

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-map-signs"></i> Region & District Master</h3>
        <div style="display:flex; gap:8px;">
            <a class="btn btn-outline btn-sm" href="<?php echo APP_URL; ?>/admin/?page=regions&show=add-district"><i class="fas fa-plus"></i> Add District</a>
            <a class="btn btn-primary btn-sm" href="<?php echo APP_URL; ?>/admin/?page=regions&show=add-region"><i class="fas fa-plus"></i> Add Region</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" style="margin-bottom:20px;"><?php echo e($flashSuccess); ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-danger" style="margin-bottom:20px;"><?php echo e($flashError); ?></div>
        <?php endif; ?>

        <?php if ($showAddRegion || $editingRegion): ?>
            <?php $regionForm = $editingRegion ?: []; ?>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h4><?php echo $editingRegion ? 'Edit Region' : 'Add Region'; ?></h4></div>
                <div class="card-body">
                    <form method="POST" action="<?php echo APP_URL; ?>/admin/?page=regions<?php echo $editingRegion ? '&edit_region=' . (int) $editingRegion['id'] : '&show=add-region'; ?>">
                        <input type="hidden" name="action" value="<?php echo $editingRegion ? 'update_region' : 'add_region'; ?>">
                        <?php if ($editingRegion): ?><input type="hidden" name="region_id" value="<?php echo (int) $editingRegion['id']; ?>"><?php endif; ?>
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:15px;">
                            <div class="form-group">
                                <label>Region Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo e($_POST['name'] ?? ($regionForm['name'] ?? '')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Region Code</label>
                                <input type="text" name="code" class="form-control" value="<?php echo e($_POST['code'] ?? ($regionForm['code'] ?? '')); ?>" placeholder="RANCHI" required>
                            </div>
                            <div class="form-group">
                                <label>Headquarters</label>
                                <input type="text" name="headquarters" class="form-control" value="<?php echo e($_POST['headquarters'] ?? ($regionForm['headquarters'] ?? '')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <?php $regionStatus = $_POST['status'] ?? ($regionForm['status'] ?? 'active'); ?>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo $regionStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $regionStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo e($_POST['phone'] ?? ($regionForm['phone'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo e($_POST['email'] ?? ($regionForm['email'] ?? '')); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo e($_POST['address'] ?? ($regionForm['address'] ?? '')); ?></textarea>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary" type="submit"><?php echo $editingRegion ? 'Update Region' : 'Save Region'; ?></button>
                            <a class="btn btn-outline" href="<?php echo APP_URL; ?>/admin/?page=regions">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showAddDistrict || $editingDistrict): ?>
            <?php $districtForm = $editingDistrict ?: []; ?>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h4><?php echo $editingDistrict ? 'Edit District' : 'Add District'; ?></h4></div>
                <div class="card-body">
                    <form method="POST" action="<?php echo APP_URL; ?>/admin/?page=regions<?php echo $editingDistrict ? '&edit_district=' . (int) $editingDistrict['id'] : '&show=add-district'; ?>">
                        <input type="hidden" name="action" value="<?php echo $editingDistrict ? 'update_district' : 'add_district'; ?>">
                        <?php if ($editingDistrict): ?><input type="hidden" name="district_id" value="<?php echo (int) $editingDistrict['id']; ?>"><?php endif; ?>
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:15px;">
                            <div class="form-group">
                                <label>District Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo e($_POST['name'] ?? ($districtForm['name'] ?? '')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>District Code</label>
                                <input type="text" name="code" class="form-control" value="<?php echo e($_POST['code'] ?? ($districtForm['code'] ?? '')); ?>" placeholder="RAN">
                            </div>
                            <div class="form-group">
                                <label>Region</label>
                                <?php $selectedRegionId = (int) ($_POST['region_id'] ?? ($districtForm['region_id'] ?? 0)); ?>
                                <select name="region_id" class="form-control" required>
                                    <option value="">Select region</option>
                                    <?php foreach ($regions as $region): ?>
                                        <option value="<?php echo (int) $region['id']; ?>" <?php echo $selectedRegionId === (int) $region['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($region['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <?php $districtStatus = $_POST['status'] ?? ($districtForm['status'] ?? 'active'); ?>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo $districtStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $districtStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary" type="submit"><?php echo $editingDistrict ? 'Update District' : 'Save District'; ?></button>
                            <a class="btn btn-outline" href="<?php echo APP_URL; ?>/admin/?page=regions">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><h4><i class="fas fa-map"></i> Regions</h4></div>
            <div class="card-body" style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Region</th>
                            <th>Code</th>
                            <th>HQ</th>
                            <th>Districts</th>
                            <th>Users</th>
                            <th>Plots</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regions as $region): ?>
                            <tr>
                                <td><?php echo (int) $region['id']; ?></td>
                                <td><?php echo e($region['name']); ?></td>
                                <td><span class="badge badge-info"><?php echo e($region['code']); ?></span></td>
                                <td><?php echo e($region['headquarters']); ?></td>
                                <td><?php echo (int) $region['district_count']; ?></td>
                                <td><?php echo (int) $region['user_count']; ?></td>
                                <td><?php echo (int) $region['plot_count']; ?></td>
                                <td><span class="badge badge-<?php echo ($region['status'] ?? '') === 'active' ? 'success' : 'warning'; ?>"><?php echo e($region['status']); ?></span></td>
                                <td>
                                    <a class="btn btn-sm btn-outline" href="<?php echo APP_URL; ?>/admin/?page=regions&edit_region=<?php echo (int) $region['id']; ?>">Edit</a>
                                    <form method="POST" action="<?php echo APP_URL; ?>/admin/?page=regions" style="display:inline;" onsubmit="return confirm('Delete region <?php echo e(addslashes($region['name'])); ?>?');">
                                        <input type="hidden" name="action" value="delete_region">
                                        <input type="hidden" name="region_id" value="<?php echo (int) $region['id']; ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($regions)): ?>
                            <tr><td colspan="9" class="text-center">No regions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4><i class="fas fa-map-marker-alt"></i> Districts</h4></div>
            <div class="card-body" style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>District</th>
                            <th>Code</th>
                            <th>Region</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($districts as $district): ?>
                            <tr>
                                <td><?php echo (int) $district['id']; ?></td>
                                <td><?php echo e($district['name']); ?></td>
                                <td><?php echo e($district['code'] ?: '-'); ?></td>
                                <td><?php echo e($district['region_name']); ?></td>
                                <td><span class="badge badge-<?php echo ($district['status'] ?? '') === 'active' ? 'success' : 'warning'; ?>"><?php echo e($district['status']); ?></span></td>
                                <td><?php echo !empty($district['updated_at']) ? date('d/m/Y', strtotime($district['updated_at'])) : '-'; ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline" href="<?php echo APP_URL; ?>/admin/?page=regions&edit_district=<?php echo (int) $district['id']; ?>">Edit</a>
                                    <form method="POST" action="<?php echo APP_URL; ?>/admin/?page=regions" style="display:inline;" onsubmit="return confirm('Delete district <?php echo e(addslashes($district['name'])); ?>?');">
                                        <input type="hidden" name="action" value="delete_district">
                                        <input type="hidden" name="district_id" value="<?php echo (int) $district['id']; ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($districts)): ?>
                            <tr><td colspan="7" class="text-center">No districts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include TEMPLATES_PATH . '/admin_layout.php';
?>
