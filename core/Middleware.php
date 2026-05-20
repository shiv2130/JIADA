<?php
/**
 * JIADA Portal - Middleware
 * Request authentication and authorization
 */

if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

class Middleware {
    
    /**
     * Require authentication
     */
    public static function requireAuth() {
        $auth = JWT::authenticate();
        
        if (!$auth['success']) {
            jsonResponse([
                'success' => false,
                'message' => $auth['message'],
                'code' => 'UNAUTHORIZED'
            ], $auth['code'] ?? 401);
        }
        
        return $auth['user'];
    }
    
    /**
     * Require specific user type
     */
    public static function requireUserType($types) {
        $user = self::requireAuth();
        
        if (!is_array($types)) {
            $types = [$types];
        }
        
        if (!in_array($user['user_type'], $types)) {
            jsonResponse([
                'success' => false,
                'message' => 'Insufficient permissions',
                'code' => 'FORBIDDEN'
            ], 403);
        }
        
        return $user;
    }
    
    /**
     * Require admin
     */
    public static function requireAdmin() {
        return self::requireUserType(['admin', 'authority']);
    }
    
    /**
     * Require API key (for webhooks)
     */
    public static function requireApiKey() {
        $headers = getallheaders();
        $apiKey = $_GET['api_key'] ?? ($headers['X-API-Key'] ?? null);
        
        if (!$apiKey) {
            jsonResponse([
                'success' => false,
                'message' => 'API key required',
                'code' => 'UNAUTHORIZED'
            ], 401);
        }
        
        $db = getDB();
        $validKey = $db->fetchOne(
            "SELECT id, name FROM api_keys WHERE key_value = ? AND is_active = 1",
            [$apiKey]
        );
        
        if (!$validKey) {
            jsonResponse([
                'success' => false,
                'message' => 'Invalid API key',
                'code' => 'UNAUTHORIZED'
            ], 401);
        }
        
        return $validKey;
    }
    
    /**
     * Rate limiter
     */
    public static function rateLimit($maxRequests = 60, $timeWindow = 60) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = "rate_limit:{$ip}";
        
        try {
            $redis = new Redis();
            $redis->connect(REDIS_HOST, REDIS_PORT);
            
            if (REDIS_PASSWORD) {
                $redis->auth(REDIS_PASSWORD);
            }
            
            $current = $redis->incr($key);
            
            if ($current === 1) {
                $redis->expire($key, $timeWindow);
            }
            
            $redis->close();
            
            if ($current > $maxRequests) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                    'code' => 'RATE_LIMITED'
                ], 429);
            }
            
        } catch (Exception $e) {
            // Fallback if Redis is unavailable
            logError("Rate limiter Redis error: " . $e->getMessage());
        }
    }
    
    /**
     * Validate CSRF token for non-API requests
     */
    public static function verifyCsrf() {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            return true; // API uses JWT auth
        }
        
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token || !verifyCSRFToken($token)) {
            jsonResponse([
                'success' => false,
                'message' => 'CSRF token validation failed',
                'code' => 'CSRF_FAILED'
            ], 403);
        }
        
        return true;
    }
    
    /**
     * Log API request
     */
    public static function logApiRequest() {
        $db = getDB();
        
        try {
            $db->insert('api_logs', [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'method' => $_SERVER['REQUEST_METHOD'],
                'endpoint' => $_SERVER['REQUEST_URI'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'request_data' => json_encode($_POST),
                'response_status' => http_response_code()
            ]);
        } catch (Exception $e) {
            // Silent fail for logging
        }
    }
    
    /**
     * Check module permission
     */
    public static function checkPermission($permissionSlug) {
        $user = self::requireAuth();
        
        $db = getDB();
        $hasPermission = $db->fetchOne(
            "SELECT p.id FROM permissions p
             INNER JOIN role_permissions rp ON p.id = rp.permission_id
             INNER JOIN user_roles ur ON rp.role_id = ur.role_id
             WHERE ur.user_id = ? AND p.slug = ? AND p.is_active = 1",
            [$user['id'], $permissionSlug]
        );
        
        if (!$hasPermission) {
            jsonResponse([
                'success' => false,
                'message' => 'You do not have permission to access this resource',
                'code' => 'FORBIDDEN'
            ], 403);
        }
        
        return true;
    }
}