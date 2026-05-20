<?php
/**
 * Survey Verify API - accept field verification updates
 * POST /api/survey/verify
 */
if (!defined('JIADA_APP')) { die('Direct access not permitted'); }

$user = Middleware::requireAuth();
$db = getDB();

if (getRequestMethod() !== 'POST') {
    jsonResponse(['success'=>false,'message'=>'Method not allowed'],405);
}

$parcelId = intval($_POST['parcel_id'] ?? 0);
$geojson = $_POST['geojson'] ?? '';
$notes = sanitize($_POST['notes'] ?? '');
$status = sanitize($_POST['status'] ?? 'verified');

if (!$parcelId || !$geojson) {
    jsonResponse(['success'=>false,'message'=>'Parcel and geojson are required'],400);
}

$photos = [];
if (!empty($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
    foreach ($_FILES['photos']['name'] as $key => $filename) {
        if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_NO_FILE) continue;
        $fileData = [
            'name' => $filename,
            'type' => $_FILES['photos']['type'][$key],
            'tmp_name' => $_FILES['photos']['tmp_name'][$key],
            'error' => $_FILES['photos']['error'][$key],
            'size' => $_FILES['photos']['size'][$key]
        ];
        $val = validateFileUpload($fileData, ['jpg','jpeg','png'], 10 * 1024 * 1024);
        if (!$val['success']) { jsonResponse(['success'=>false,'message'=>'Photo upload error: '.$val['message']],400); }
        $upload = uploadFile($fileData, UPLOADS_PATH . '/survey');
        if (!$upload['success']) { jsonResponse(['success'=>false,'message'=>'Failed to upload photo'],400); }
        $photos[] = $upload['filename'];
    }
}

$db->insert('survey_submissions', [
    'parcel_id' => $parcelId,
    'geojson' => $geojson,
    'status' => $status,
    'notes' => $notes,
    'photos' => json_encode($photos),
    'user_id' => $user['id'],
    'collected_at' => date('Y-m-d H:i:s')
]);

// Optional: mark land_banks verified_status if column exists
$cols = array_map(function($c){ return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM land_banks"));
if (in_array('verified_status', $cols)) {
    $db->update('land_banks', ['verified_status'=>$status, 'updated_at'=>date('Y-m-d H:i:s')], 'id = ?', [$parcelId]);
}

jsonResponse(['success'=>true,'message'=>'Survey submission recorded','photos'=>$photos]);
