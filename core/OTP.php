<?php
/**
 * JIADA Portal - OTP Service
 * One-Time Password generation, validation, and delivery
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class OTP {
    
    /**
     * Generate and send OTP
     */
    public static function generateAndSend($mobile, $email, $purpose = 'login') {
        $db = getDB();
        
        // Check rate limiting
        $recentOTP = $db->fetchOne(
            "SELECT id FROM otp_requests 
             WHERE (mobile = ? OR email = ?) 
             AND purpose = ? 
             AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
             ORDER BY id DESC LIMIT 1",
            [$mobile, $email, $purpose, OTP_RESEND_COOLDOWN]
        );
        
        if ($recentOTP) {
            return ['success' => false, 'message' => 'Please wait before requesting new OTP'];
        }
        
        // Generate OTP
        $otp = generateOTP();
        $expiresAt = date(DATETIME_FORMAT, time() + OTP_EXPIRE_TIME);
        
        // Store OTP
        try {
            $otpId = $db->insert('otp_requests', [
                'mobile' => $mobile,
                'email' => $email,
                'otp_hash' => hash('sha256', $otp),
                'purpose' => $purpose,
                'expires_at' => $expiresAt,
                'attempts' => 0,
                'is_verified' => 0
            ]);
            
            // Send OTP via SMS
            $smsSent = self::sendSMS($mobile, $otp, $purpose);
            
            // Send OTP via Email
            $emailSent = self::sendEmail($email, $otp, $purpose);
            
            if ($smsSent['success'] || $emailSent['success']) {
                logActivity("OTP sent to {$mobile}/{$email} for {$purpose}");
                
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'otp_id' => $otpId,
                    'expires_in' => OTP_EXPIRE_TIME
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to send OTP'];
            
        } catch (Exception $e) {
            logError("OTP generation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Internal server error'];
        }
    }
    
    /**
     * Verify OTP
     */
    public static function verify($mobile, $email, $otp, $purpose = 'login') {
        $db = getDB();
        
        // Get latest unverified OTP for this user
        $otpRecord = $db->fetchOne(
            "SELECT * FROM otp_requests 
             WHERE (mobile = ? OR email = ?) 
             AND purpose = ? 
             AND is_verified = 0 
             AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1",
            [$mobile, $email, $purpose]
        );
        
        if (!$otpRecord) {
            return ['success' => false, 'message' => 'No valid OTP found or OTP expired'];
        }
        
        // Check max attempts
        if ($otpRecord['attempts'] >= OTP_MAX_ATTEMPTS) {
            return ['success' => false, 'message' => 'Maximum attempts exceeded. Please request new OTP'];
        }
        
        // Verify OTP hash
        $otpHash = hash('sha256', $otp);
        
        if ($otpHash === $otpRecord['otp_hash']) {
            // Mark as verified
            $db->update('otp_requests', 
                ['is_verified' => 1, 'verified_at' => date(DATETIME_FORMAT)],
                'id = :id',
                ['id' => $otpRecord['id']]
            );
            
            logActivity("OTP verified for {$mobile}/{$email}");
            
            return [
                'success' => true,
                'message' => 'OTP verified successfully',
                'otp_id' => $otpRecord['id']
            ];
        }
        
        // Increment attempts
        $db->update('otp_requests',
            ['attempts' => $otpRecord['attempts'] + 1],
            'id = :id',
            ['id' => $otpRecord['id']]
        );
        
        $remainingAttempts = OTP_MAX_ATTEMPTS - $otpRecord['attempts'] - 1;
        
        return [
            'success' => false, 
            'message' => 'Invalid OTP',
            'remaining_attempts' => $remainingAttempts
        ];
    }
    
    /**
     * Verify using OTP ID (for API)
     */
    public static function verifyById($otpId, $otp) {
        $db = getDB();
        
        $otpRecord = $db->fetchOne(
            "SELECT * FROM otp_requests 
             WHERE id = ? 
             AND is_verified = 0 
             AND expires_at > NOW()
             LIMIT 1",
            [$otpId]
        );
        
        if (!$otpRecord) {
            return ['success' => false, 'message' => 'No valid OTP found or OTP expired'];
        }
        
        if ($otpRecord['attempts'] >= OTP_MAX_ATTEMPTS) {
            return ['success' => false, 'message' => 'Maximum attempts exceeded'];
        }
        
        $otpHash = hash('sha256', $otp);
        
        if ($otpHash === $otpRecord['otp_hash']) {
            $db->update('otp_requests',
                ['is_verified' => 1, 'verified_at' => date(DATETIME_FORMAT)],
                'id = :id',
                ['id' => $otpRecord['id']]
            );
            
            return ['success' => true, 'message' => 'OTP verified'];
        }
        
        $db->update('otp_requests',
            ['attempts' => $otpRecord['attempts'] + 1],
            'id = :id',
            ['id' => $otpRecord['id']]
        );
        
        return ['success' => false, 'message' => 'Invalid OTP'];
    }
    
    /**
     * Send OTP via SMS
     */
    private static function sendSMS($mobile, $otp, $purpose) {
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        
        $message = "Your JIADA Portal OTP for {$purpose} is: {$otp}. Valid for " . (OTP_EXPIRE_TIME / 60) . " minutes. Do not share with anyone.";
        
        try {
            // Using custom SMS gateway
            $smsData = [
                'sender_id' => SMS_SENDER_ID,
                'message' => $message,
                'mobile' => $mobile,
                'api_key' => SMS_API_KEY
            ];
            
            // Example: Generic SMS API call
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
            
            logMessage("SMS sent to {$mobile}: {$httpCode}", 'info', 'otp');
            
            return ['success' => true, 'message' => 'SMS sent'];
            
        } catch (Exception $e) {
            logError("SMS send failed: " . $e->getMessage());
            
            // Log for development (remove in production)
            if (APP_DEBUG) {
                logMessage("DEBUG - OTP for {$mobile}: {$otp}", 'debug', 'otp');
            }
            
            return ['success' => false, 'message' => 'SMS send failed'];
        }
    }
    
    /**
     * Send OTP via Email
     */
    private static function sendEmail($email, $otp, $purpose) {
        $subject = "JIADA Portal - OTP for {$purpose}";
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .otp-code { font-size: 28px; font-weight: bold; color: #1a5276; 
                            text-align: center; padding: 20px; background: #eaf2f8; 
                            margin: 20px 0; border-radius: 5px; }
                .warning { color: #c0392b; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>JIADA Portal - OTP Verification</h2>
                <p>Your One-Time Password (OTP) for <strong>{$purpose}</strong> is:</p>
                <div class='otp-code'>{$otp}</div>
                <p>This OTP is valid for " . (OTP_EXPIRE_TIME / 60) . " minutes.</p>
                <p class='warning'>⚠️ Do not share this OTP with anyone. JIADA officials will never ask for your OTP.</p>
                <hr>
                <p>If you did not request this OTP, please ignore this email.</p>
                <p><small>Jharkhand Industrial Area Development Authority (JIADA)</small></p>
            </div>
        </body>
        </html>";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        
        try {
            $sent = mail($email, $subject, $body, $headers);
            
            logMessage("Email OTP sent to {$email}: " . ($sent ? 'success' : 'failed'), 'info', 'otp');
            
            return ['success' => (bool)$sent, 'message' => $sent ? 'Email sent' : 'Email failed'];
            
        } catch (Exception $e) {
            logError("Email send failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Email send failed'];
        }
    }
    
    /**
     * Clean up expired OTPs
     */
    public static function cleanupExpired() {
        $db = getDB();
        $db->query("DELETE FROM otp_requests WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }
    
    /**
     * Check if mobile/email is verified
     */
    public static function isVerified($mobile, $email, $purpose = 'login') {
        $db = getDB();
        
        $record = $db->fetchOne(
            "SELECT id FROM otp_requests 
             WHERE (mobile = ? OR email = ?) 
             AND purpose = ? 
             AND is_verified = 1 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY id DESC LIMIT 1",
            [$mobile, $email, $purpose]
        );
        
        return $record !== false;
    }
}