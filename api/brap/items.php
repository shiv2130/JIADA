<?php
/**
 * BRAP Items API - list/create/update BRAP compliance tasks
 * GET /api/brap/items
 * POST /api/brap/items
 */
if (!defined('JIADA_APP')) { die('Direct access not permitted'); }

$user = Middleware::requireAuth();
$db = getDB();
$method = getRequestMethod();

if ($method === 'GET') {
    $status = getGet('status', null);
    $owner = getGet('owner_role', null);
    $q = getGet('q', null);
    $where = "1=1";
    $params = [];
    if ($status) { $where .= " AND status = ?"; $params[] = sanitize($status); }
    if ($owner)  { $where .= " AND owner_role = ?"; $params[] = sanitize($owner); }
    if ($q)      { $where .= " AND (title LIKE ? OR category LIKE ? OR evidence_url LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%"; }
    $items = $db->fetchAll("SELECT * FROM brap_items WHERE $where ORDER BY due_date ASC LIMIT 500", $params);
    jsonResponse(['success'=>true,'data'=>$items]);
}

if ($method === 'POST') {
    $input = getPost();
    $required = ['title','category','owner_role','due_date'];
    $missing = validateRequired($input, $required);
    if ($missing) { jsonResponse(['success'=>false,'message'=>'Missing: '.implode(', ',$missing)],400); }
    $data = [
        'title' => sanitize($input['title']),
        'category' => sanitize($input['category']),
        'owner_role' => sanitize($input['owner_role']),
        'due_date' => sanitize($input['due_date']),
        'status' => sanitize($input['status'] ?? 'pending'),
        'evidence_url' => sanitize($input['evidence_url'] ?? ''),
        'last_reviewed_at' => date('Y-m-d H:i:s')
    ];
    if (!empty($input['id'])) {
        $db->update('brap_items', $data, 'id = ?', [intval($input['id'])]);
        jsonResponse(['success'=>true,'message'=>'BRAP item updated']);
    } else {
        $id = $db->insert('brap_items', $data);
        jsonResponse(['success'=>true,'message'=>'BRAP item created','id'=>$id]);
    }
}

jsonResponse(['success'=>false,'message'=>'Method not allowed'],405);
