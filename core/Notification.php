<?php
/**
 * JIADA Portal - Notification Service
 * Multi-channel notification system (Email, SMS, In-App, Push)
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class Notification {
    
    const TYPE_EMAIL = 'email';
    const TYPE_SMS = 'sms';
    const TYPE_IN_APP = 'in_app';
    const TYPE_PUSH = 'push';
    const TYPE_WHATSAPP = 'whatsapp';
    
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';
    
    /**
     * Send notification
     */
    public static function send($userId, $type, $subject, $message, $priority = self::PRIORITY_MEDIUM, $data = []) {
        $db = getDB();
        
        try {
            $notificationId = $db->insert('notifications', [
                'user_id' => $userId,
                'type' => $type,
                'subject' => $subject,
                'message' => $message,
                'priority' => $priority,
                'data' => json_encode($data),
                'status' => 'pending',
                'created_at' => date(DATETIME_FORMAT)
            ]);
            
            // Queue for processing
            self::queueNotification($notificationId, $type, $userId, $subject, $message, $data);
            
            return ['success' => true, 'notification_id' => $notificationId];
            
        } catch (Exception $e) {
            logError("Notification send failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send bulk notifications
     */
    public static function sendBulk($userIds, $type, $subject, $message, $priority = self::PRIORITY_MEDIUM, $data = []) {
        $results = [];
        
        foreach ($userIds as $userId) {
            $results[$userId] = self::send($userId, $type, $subject, $message, $priority, $data);
        }
        
        return $results;
    }
    
    /**
     * Send email notification
     */
    public static function sendEmail($userId, $subject, $message, $template = null, $data = []) {
        $db = getDB();
        
        // Get user email
        $user = $db->fetchOne("SELECT email, name FROM users WHERE id = ?", [$userId]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Use template if provided
        if ($template) {
            $message = self::renderTemplate($template, array_merge(['message' => $message, 'user' => $user], $data));
        }
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
        
        try {
            $sent = mail($user['email'], $subject, $message, $headers);
            
            // Log notification
            $db->insert('notification_logs', [
                'notification_id' => 0,
                'user_id' => $userId,
                'channel' => 'email',
                'recipient' => $user['email'],
                'subject' => $subject,
                'message' => $message,
                'status' => $sent ? 'sent' : 'failed',
                'sent_at' => date(DATETIME_FORMAT)
            ]);
            
            return ['success' => (bool)$sent];
            
        } catch (Exception $e) {
            logError("Email notification failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send SMS notification
     */
    public static function sendSMS($userId, $message) {
        $db = getDB();
        
        // Get user mobile
        $user = $db->fetchOne("SELECT mobile FROM users WHERE id = ?", [$userId]);
        
        if (!$user || !$user['mobile']) {
            return ['success' => false, 'message' => 'Mobile number not available'];
        }
        
        $mobile = preg_replace('/[^0-9]/', '', $user['mobile']);
        $senderId = SMS_SENDER_ID;
        
        try {
            $smsData = [
                'sender_id' => $senderId,
                'message' => "JIADA: {$message}",
                'mobile' => $mobile,
                'api_key' => SMS_API_KEY
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => SMS_API_URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($smsData),
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $success = ($httpCode >= 200 && $httpCode < 300);
            
            // Log notification
            $db->insert('notification_logs', [
                'notification_id' => 0,
                'user_id' => $userId,
                'channel' => 'sms',
                'recipient' => $mobile,
                'message' => $message,
                'status' => $success ? 'sent' : 'failed',
                'sent_at' => date(DATETIME_FORMAT)
            ]);
            
            return ['success' => $success];
            
        } catch (Exception $e) {
            logError("SMS notification failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send WhatsApp notification
     */
    public static function sendWhatsApp($userId, $message, $templateName = null, $templateData = []) {
        $db = getDB();
        
        $user = $db->fetchOne("SELECT mobile FROM users WHERE id = ?", [$userId]);
        
        if (!$user || !$user['mobile']) {
            return ['success' => false, 'message' => 'Mobile number not available'];
        }
        
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/[^0-9]/', '', $user['mobile']),
                'type' => 'text',
                'text' => ['body' => "JIADA Portal: {$message}"]
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => WHATSAPP_API_URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . WHATSAPP_API_KEY,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $success = ($httpCode >= 200 && $httpCode < 300);
            
            // Log notification
            $db->insert('notification_logs', [
                'notification_id' => 0,
                'user_id' => $userId,
                'channel' => 'whatsapp',
                'recipient' => $user['mobile'],
                'message' => $message,
                'status' => $success ? 'sent' : 'failed',
                'sent_at' => date(DATETIME_FORMAT)
            ]);
            
            return ['success' => $success];
            
        } catch (Exception $e) {
            logError("WhatsApp notification failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Create in-app notification
     */
    public static function createInApp($userId, $subject, $message, $priority = self::PRIORITY_MEDIUM, $data = []) {
        $db = getDB();
        
        try {
            $notificationId = $db->insert('notifications', [
                'user_id' => $userId,
                'type' => self::TYPE_IN_APP,
                'subject' => $subject,
                'message' => $message,
                'priority' => $priority,
                'data' => json_encode($data),
                'status' => 'sent',
                'created_at' => date(DATETIME_FORMAT)
            ]);
            
            return ['success' => true, 'notification_id' => $notificationId];
            
        } catch (Exception $e) {
            logError("In-app notification failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get user notifications
     */
    public static function getUserNotifications($userId, $limit = 20, $offset = 0, $unreadOnly = false) {
        $db = getDB();
        
        $where = "user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $where .= " AND is_read = 0";
        }
        
        return $db->fetchAll(
            "SELECT * FROM notifications WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
    }
    
    /**
     * Get unread notification count
     */
    public static function getUnreadCount($userId) {
        $db = getDB();
        $result = $db->fetchOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        return $result['count'] ?? 0;
    }
    
    /**
     * Mark notification as read
     */
    public static function markAsRead($notificationId, $userId = null) {
        $db = getDB();
        
        $where = "id = ?";
        $params = [$notificationId];
        
        if ($userId) {
            $where .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        return $db->update('notifications',
            ['is_read' => 1, 'read_at' => date(DATETIME_FORMAT)],
            $where,
            array_combine(array_map(function($i) { return "p{$i}"; }, range(0, count($params)-1)), $params)
        );
    }
    
    /**
     * Mark all notifications as read
     */
    public static function markAllAsRead($userId) {
        $db = getDB();
        return $db->query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }
    
    /**
     * Queue notification for processing
     */
    private static function queueNotification($notificationId, $type, $userId, $subject, $message, $data) {
        try {
            if (!class_exists('Redis')) {
                logError("Redis extension not available; skipping notification queue");
                return;
            }
            $redis = new Redis();
            $redis->connect(REDIS_HOST, REDIS_PORT);
            
            if (REDIS_PASSWORD) {
                $redis->auth(REDIS_PASSWORD);
            }
            
            $redis->rPush('notification_queue', json_encode([
                'notification_id' => $notificationId,
                'type' => $type,
                'user_id' => $userId,
                'subject' => $subject,
                'message' => $message,
                'data' => $data
            ]));
            
            $redis->close();
            
        } catch (Throwable $e) {
            logError("Redis queue failed: " . $e->getMessage());
        }
    }
    
    /**
     * Render email template
     */
    private static function renderTemplate($template, $data) {
        ob_start();
        extract($data);
        include TEMPLATES_PATH . '/emails/' . $template . '.php';
        return ob_get_clean();
    }
    
    /**
     * Process notification queue
     */
    public static function processQueue() {
        try {
            if (!class_exists('Redis')) {
                logError("Redis extension not available; queue processing skipped");
                return;
            }
            $redis = new Redis();
            $redis->connect(REDIS_HOST, REDIS_PORT);
            
            if (REDIS_PASSWORD) {
                $redis->auth(REDIS_PASSWORD);
            }
            
            while ($item = $redis->lPop('notification_queue')) {
                $notification = json_decode($item, true);
                
                if ($notification) {
                    // Get user preferences
                    $db = getDB();
                    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$notification['user_id']]);
                    
                    if ($user) {
                        $preferences = json_decode($user['notification_preferences'] ?? '{}', true);
                        
                        // Send based on preferences
                        if (($preferences['email'] ?? true)) {
                            self::sendEmail($notification['user_id'], $notification['subject'], $notification['message']);
                        }
                        
                        if (($preferences['sms'] ?? true) && $notification['priority'] !== self::PRIORITY_LOW) {
                            self::sendSMS($notification['user_id'], $notification['message']);
                        }
                        
                        if (($preferences['whatsapp'] ?? false)) {
                            self::sendWhatsApp($notification['user_id'], $notification['message']);
                        }
                    }
                }
            }
            
            $redis->close();
            
        } catch (Throwable $e) {
            logError("Queue processing failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send payment receipt notification
     */
    public static function sendPaymentReceipt($userId, $paymentData) {
        $subject = "Payment Receipt - JIADA Portal";
        $message = "Your payment of " . formatCurrency($paymentData['amount']) . " for " . $paymentData['purpose'] . " has been received successfully. Transaction ID: " . $paymentData['transaction_id'];
        
        return self::send($userId, self::TYPE_EMAIL, $subject, $message, self::PRIORITY_HIGH, $paymentData);
    }
    
    /**
     * Send application status notification
     */
    public static function sendApplicationStatus($userId, $applicationType, $status, $referenceNumber) {
        $subject = "Application Status Update - JIADA Portal";
        $message = "Your {$applicationType} application (Ref: {$referenceNumber}) status has been updated to: {$status}";
        
        return self::send($userId, self::TYPE_EMAIL, $subject, $message, self::PRIORITY_MEDIUM, [
            'application_type' => $applicationType,
            'status' => $status,
            'reference_number' => $referenceNumber
        ]);
    }
    
    /**
     * Send grievance update notification
     */
    public static function sendGrievanceUpdate($userId, $grievanceId, $status, $message) {
        $subject = "Grievance Update - JIADA Portal";
        
        return self::send($userId, self::TYPE_EMAIL, $subject, $message, self::PRIORITY_MEDIUM, [
            'grievance_id' => $grievanceId,
            'status' => $status
        ]);
    }
    
    /**
     * Send reminder notification
     */
    public static function sendReminder($userId, $reminderType, $details) {
        $subject = "Reminder - JIADA Portal";
        $message = $details['message'] ?? "This is a reminder for your {$reminderType}";
        
        return self::send($userId, self::TYPE_EMAIL, $subject, $message, self::PRIORITY_MEDIUM, $details);
    }
}
