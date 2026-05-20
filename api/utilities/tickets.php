<?php
/**
 * JIADA Portal - Utility Tickets API
 * GET /api/utilities/tickets - List tickets
 * POST /api/utilities/tickets - Create ticket
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

$user = Middleware::requireAuth();
$method = getRequestMethod();

switch ($method) {
    case 'GET':
        listTickets($user);
        break;
    case 'POST':
        createTicket($user);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * List user's utility tickets
 */
function listTickets($user) {
    $db = getDB();
    
    // Filters
    $status = getGet('status', null);
    $utilityType = getGet('utility_type', null);
    $page = max(1, intval(getGet('page', 1)));
    $perPage = PER_PAGE;
    
    $where = ['user_id = ?'];
    $params = [$user['id']];
    
    if ($status) {
        $where[] = 'status = ?';
        $params[] = sanitize($status);
    }
    
    if ($utilityType) {
        $where[] = 'utility_type = ?';
        $params[] = sanitize($utilityType);
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get count
    $totalResult = $db->fetchOne("SELECT COUNT(*) as total FROM utility_tickets WHERE {$whereClause}", $params);
    $pagination = paginate($totalResult['total'], $perPage, $page);
    
    // Get tickets
    $query = "SELECT ut.*, u.name as assigned_to_name 
              FROM utility_tickets ut
              LEFT JOIN users u ON ut.assigned_to = u.id
              WHERE {$whereClause} 
              ORDER BY ut.created_at DESC 
              LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
    $tickets = $db->fetchAll($query, $params);
    
    jsonResponse([
        'success' => true,
        'data' => [
            'tickets' => $tickets,
            'pagination' => $pagination
        ]
    ]);
}

/**
 * Create new utility ticket
 */
function createTicket($user) {
    $input = getPost();
    $db = getDB();
    $columns = array_map(function($c) { return $c['Field']; }, $db->fetchAll("SHOW COLUMNS FROM utility_tickets"));
    
    // Validate required fields
    $required = ['utility_type', 'priority', 'subject', 'description'];
    $missing = validateRequired($input, $required);
    
    if (!empty($missing)) {
        jsonResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
    }
    
    // Validate utility type
    $validTypes = ['water', 'electricity', 'sewage', 'road', 'street_light', 'maintenance', 'other'];
    if (!in_array($input['utility_type'], $validTypes)) {
        jsonResponse(['success' => false, 'message' => 'Invalid utility type'], 400);
    }
    
    // Validate priority
    $validPriorities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($input['priority'], $validPriorities)) {
        jsonResponse(['success' => false, 'message' => 'Invalid priority'], 400);
    }
    
    // Validate text lengths
    if (strlen($input['subject']) < 5 || strlen($input['subject']) > 150) {
        jsonResponse(['success' => false, 'message' => 'Subject must be between 5 and 150 characters'], 400);
    }
    if (strlen($input['description']) < 10) {
        jsonResponse(['success' => false, 'message' => 'Description must be at least 10 characters'], 400);
    }

    // Validate numeric optional fields
    if (!empty($input['plot_id']) && intval($input['plot_id']) < 1) {
        jsonResponse(['success' => false, 'message' => 'Plot ID must be a positive number'], 400);
    }
    if (!empty($input['unit_id']) && intval($input['unit_id']) < 1) {
        jsonResponse(['success' => false, 'message' => 'Unit ID must be a positive number'], 400);
    }

    // Process attachments (images only, max 5)
    $attachments = [];
    if (!empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
        if (count($_FILES['attachments']['name']) > 5) {
            jsonResponse(['success' => false, 'message' => 'You can upload up to 5 images'], 400);
        }
        foreach ($_FILES['attachments']['name'] as $key => $filename) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $fileData = [
                'name' => $filename,
                'type' => $_FILES['attachments']['type'][$key],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                'error' => $_FILES['attachments']['error'][$key],
                'size' => $_FILES['attachments']['size'][$key]
            ];

            $validation = validateFileUpload($fileData, ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024);
            if (!$validation['success']) {
                jsonResponse(['success' => false, 'message' => 'Attachment error: ' . ($validation['message'] ?? 'Invalid file')], 400);
            }

            $fileResult = uploadFile($fileData, UPLOADS_PATH . '/utility_tickets');
            if (!$fileResult['success']) {
                jsonResponse(['success' => false, 'message' => 'Attachment upload failed: ' . ($fileResult['message'] ?? 'Unknown error')], 400);
            }
            $attachments[] = $fileResult['filename'];
        }
    }
    
    $ticketNumber = generateReferenceNumber('UTL');
    
    // Calculate SLA deadline based on priority
    $slaHours = ['low' => 72, 'medium' => 48, 'high' => 24, 'critical' => 4];
    $slaDeadline = date(DATETIME_FORMAT, strtotime('+' . ($slaHours[$input['priority']] ?? 48) . ' hours'));
    
    $db->beginTransaction();
    
    try {
        $userRecord = $db->fetchOne("SELECT region_id FROM users WHERE id = ?", [$user['id']]);
        $regionId = $userRecord['region_id'] ?? null;

        $insertData = [
            'user_id' => $user['id'],
            'plot_id' => !empty($input['plot_id']) ? intval($input['plot_id']) : null,
            'unit_id' => !empty($input['unit_id']) ? intval($input['unit_id']) : null,
            'utility_type' => sanitize($input['utility_type']),
            'priority' => sanitize($input['priority']),
            'subject' => sanitize($input['subject']),
            'description' => sanitize($input['description']),
            'attachments' => json_encode($attachments),
            'status' => 'open',
            'sla_deadline' => $slaDeadline
        ];
        if (in_array('ticket_number', $columns)) {
            $insertData['ticket_number'] = $ticketNumber;
        } elseif (in_array('ticket_no', $columns)) {
            $insertData['ticket_no'] = $ticketNumber;
        }
        if (in_array('region_id', $columns)) {
            $insertData['region_id'] = $regionId;
        }
        if (in_array('service_type', $columns)) {
            $insertData['service_type'] = $insertData['utility_type'];
            unset($insertData['utility_type']);
        }
        if (!in_array('unit_id', $columns)) {
            unset($insertData['unit_id']);
        }
        $ticketId = $db->insert('utility_tickets', $insertData);
        
        // Create initial ticket update
        $db->insert('ticket_updates', [
            'ticket_id' => $ticketId,
            'user_id' => $user['id'],
            'update_type' => 'status_change',
            'message' => 'Ticket created',
            'new_status' => 'open'
        ]);
        
        // Find and notify relevant authority (region-based if possible)
        $authority = null;
        if (!empty($regionId)) {
            $authority = $db->fetchOne(
                "SELECT id, name, mobile, email FROM users 
                 WHERE user_type IN ('regional_officer','jiada_officer') AND status = 'active' AND region_id = ?
                 ORDER BY FIELD(user_type,'regional_officer','jiada_officer') ASC LIMIT 1",
                [$regionId]
            );
        }
        if (!$authority) {
            $authority = $db->fetchOne(
                "SELECT id, name, mobile, email FROM users 
                 WHERE user_type IN ('regional_officer','jiada_officer','admin','superadmin') AND status = 'active'
                 ORDER BY FIELD(user_type,'regional_officer','jiada_officer','admin','superadmin') ASC LIMIT 1"
            );
        }

        // Find vendor if available
        $vendor = $db->fetchOne(
            "SELECT id, name, mobile, email FROM users 
             WHERE user_type = 'vendor' AND status = 'active' ORDER BY id ASC LIMIT 1"
        );

        if ($authority || $vendor) {
            $update = [
                'assigned_to' => $authority['id'] ?? null,
                'vendor_id' => $vendor['id'] ?? null,
                'status' => $authority ? 'assigned' : 'open'
            ];
            $db->update('utility_tickets', $update, 'id = ?', [$ticketId]);
            
            $db->insert('ticket_updates', [
                'ticket_id' => $ticketId,
                'user_id' => $user['id'],
                'update_type' => 'assignment',
                'message' => 'Ticket assigned to authority/vendor',
                'old_status' => 'open',
                'new_status' => $authority ? 'assigned' : 'open'
            ]);
        }
        
        if ($authority) {
            Notification::createInApp($authority['id'],
                "New Utility Ticket - {$ticketNumber}",
                "A new {$input['utility_type']} ticket has been assigned to you. Priority: {$input['priority']}",
                $input['priority'] === 'critical' ? Notification::PRIORITY_URGENT : Notification::PRIORITY_HIGH,
                ['ticket_id' => $ticketId, 'ticket_number' => $ticketNumber]
            );
            if (in_array($input['priority'], ['critical', 'high'])) {
                Notification::sendSMS($authority['id'],
                    "Urgent utility ticket {$ticketNumber} assigned. {$input['subject']}"
                );
            }
        }

        if ($vendor) {
            Notification::createInApp($vendor['id'],
                "Utility Ticket Work Order - {$ticketNumber}",
                "Work order assigned. Service: {$input['utility_type']} | Priority: {$input['priority']}",
                Notification::PRIORITY_HIGH,
                ['ticket_id' => $ticketId, 'ticket_number' => $ticketNumber]
            );
        }
        
        // Send confirmation to user
        Notification::send($user['id'], Notification::TYPE_EMAIL,
            "Utility Ticket Created - {$ticketNumber}",
            "Your {$input['utility_type']} service request has been created. Ticket: {$ticketNumber}. Priority: {$input['priority']}",
            Notification::PRIORITY_MEDIUM,
            ['ticket_number' => $ticketNumber, 'utility_type' => $input['utility_type']]
        );
        
        $db->commit();
        
        logActivity("Utility ticket created: {$ticketNumber}", $user['id']);
        
        jsonResponse([
            'success' => true,
            'message' => 'Utility ticket created successfully',
            'data' => [
            'ticket_id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'priority' => $input['priority'],
            'sla_deadline' => $slaDeadline,
            'assigned' => $authority ? true : false
        ]
    ]);
        
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}
