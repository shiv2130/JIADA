<?php
/**
 * JIADA Portal - JWT Authentication
 * JSON Web Token implementation for user authentication
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class JWT {
    
    /**
     * Encode JWT token
     */
    public static function encode($payload) {
        $header = [
            'alg' => JWT_ALGORITHM,
            'typ' => 'JWT'
        ];
        
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = self::sign("{$headerEncoded}.{$payloadEncoded}");
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }
    
    /**
     * Decode and verify JWT token
     */
    public static function decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return ['success' => false, 'message' => 'Invalid token format'];
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        // Verify signature
        $payload = self::base64UrlDecode($payloadEncoded);
        $expectedSignature = self::sign("{$headerEncoded}.{$payloadEncoded}");
        $expectedSignatureEncoded = self::base64UrlEncode($expectedSignature);
        
        if (!hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
            return ['success' => false, 'message' => 'Invalid token signature'];
        }
        
        // Parse payload
        $data = json_decode($payload, true);
        
        if (!$data) {
            return ['success' => false, 'message' => 'Invalid token payload'];
        }
        
        // Check expiration
        if (isset($data['exp']) && time() >= $data['exp']) {
            return ['success' => false, 'message' => 'Token expired'];
        }
        
        // Check issued at
        if (isset($data['iat']) && $data['iat'] > time()) {
            return ['success' => false, 'message' => 'Token issued in the future'];
        }
        
        return ['success' => true, 'data' => $data];
    }
    
    /**
     * Generate token for user
     */
    public static function generateToken($userId, $userType = 'user', $additionalData = []) {
        $payload = [
            'iss' => APP_URL,
            'aud' => APP_URL,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRE,
            'sub' => $userId,
            'userType' => $userType
        ];
        
        $payload = array_merge($payload, $additionalData);
        
        $token = self::encode($payload);
        
        return [
            'success' => true,
            'token' => $token,
            'expires_in' => JWT_EXPIRE,
            'expires_at' => date(DATETIME_FORMAT, time() + JWT_EXPIRE)
        ];
    }
    
    /**
     * Generate refresh token
     */
    public static function generateRefreshToken($userId) {
        $payload = [
            'iss' => APP_URL,
            'aud' => APP_URL,
            'iat' => time(),
            'exp' => time() + JWT_REFRESH_EXPIRE,
            'sub' => $userId,
            'type' => 'refresh'
        ];
        
        return [
            'success' => true,
            'token' => self::encode($payload),
            'expires_in' => JWT_REFRESH_EXPIRE
        ];
    }
    
    /**
     * Get authorization token from headers
     */
    public static function getAuthorizationToken() {
        $headers = getallheaders();
        
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                if (preg_match('/Bearer\s(\S+)/i', $value, $matches)) {
                    return $matches[1];
                }
                return $value;
            }
        }
        
        // Fallback for Apache/FastCGI
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                return $matches[1];
            }
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        return null;
    }
    
    /**
     * Authenticate request using Bearer token
     */
    public static function authenticate() {
        $token = self::getAuthorizationToken();
        
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Authorization token required',
                'code' => 401
            ];
        }
        
        $decoded = self::decode($token);
        
        if (!$decoded['success']) {
            return [
                'success' => false,
                'message' => $decoded['message'],
                'code' => 401
            ];
        }
        
        $data = $decoded['data'];
        
        // Check if token is in blacklist (for logout)
        $db = getDB();
        $blacklisted = $db->fetchOne(
            "SELECT id FROM token_blacklist WHERE token_hash = ? AND expires_at > NOW()",
            [hash('sha256', $token)]
        );
        
        if ($blacklisted) {
            return [
                'success' => false,
                'message' => 'Token has been revoked',
                'code' => 401
            ];
        }
        
        // Get user details
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE id = ? AND (status IS NULL OR status NOT IN ('inactive', 'blocked'))",
            [$data['sub']]
        );
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found or inactive',
                'code' => 401
            ];
        }
        
        return [
            'success' => true,
            'user' => $user,
            'token_data' => $data
        ];
    }
    
    /**
     * Blacklist token (for logout)
     */
    public static function blacklistToken($token) {
        $decoded = self::decode($token);
        
        if (!$decoded['success']) {
            return false;
        }
        
        $db = getDB();
        $expiresAt = $decoded['data']['exp'];
        
        try {
            $db->insert('token_blacklist', [
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', $expiresAt)
            ]);
            return true;
        } catch (Exception $e) {
            logError("Token blacklist failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current authenticated user
     */
    public static function getCurrentUser() {
        $auth = self::authenticate();
        
        if ($auth['success']) {
            return $auth['user'];
        }
        
        return null;
    }
    
    /**
     * Verify token without returning user
     */
    public static function verifyToken($token = null) {
        $token = $token ?? self::getAuthorizationToken();
        
        if (!$token) {
            return ['success' => false, 'message' => 'No token provided'];
        }
        
        return self::decode($token);
    }
    
    /**
     * HMAC SHA256 signing
     */
    private static function sign($input) {
        return hash_hmac('sha256', $input, JWT_SECRET, true);
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
