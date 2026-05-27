<?php
/**
 * JIADA Admin Authentication & RBAC
 */
if (!defined('JIADA_APP')) die('Direct access not permitted');

class AdminAuth {
    
    /**
     * Check if user is logged in as admin user
     */
    public static function requireLogin() {
        if (!isset($_SESSION['admin_id'])) {
            header('Location: ' . APP_URL . '/admin/login');
            exit;
        }
        return $_SESSION;
    }
    
    /**
     * Check permission for module
     */
    public static function hasPermission($module, $action = 'view') {
        if (!isset($_SESSION['admin_id'])) return false;
        
        $role = $_SESSION['user_type'] ?? 'applicant';
        
        // SuperAdmin has all permissions
        if ($role === 'superadmin') return true;
        
        $db = getDB();
        $permMap = [
            'can_view' => ['view', 'list', 'show'],
            'can_create' => ['create', 'add', 'store'],
            'can_edit' => ['edit', 'update'],
            'can_delete' => ['delete', 'remove'],
            'can_approve' => ['approve', 'reject'],
            'can_export' => ['export', 'download'],
            'can_view_reports' => ['reports']
        ];
        
        foreach ($permMap as $field => $actions) {
            if (in_array($action, $actions)) {
                $perm = $db->fetchOne(
                    "SELECT {$field} FROM role_permissions WHERE role_type = ? AND module = ?",
                    [$role, $module]
                );
                return $perm && $perm[$field] == 1;
            }
        }
        return false;
    }
    
    /**
     * Get user's region restriction
     */
    public static function getUserRegion() {
        return $_SESSION['region_id'] ?? null;
    }
    
    /**
     * Apply region filter to query
     */
    public static function addRegionFilter($query, $params) {
        if (self::requireLogin() && $_SESSION['user_type'] === 'regional_officer' && $_SESSION['region_id']) {
            $query .= (stripos($query, ' where ') !== false ? ' AND ' : ' WHERE ') . 'region_id = ?';
            $params[] = $_SESSION['region_id'];
        }
        return [$query, $params];
    }
    
    /**
     * Set flash message
     */
    public static function setFlash($type, $message) {
        $_SESSION['flash_' . $type] = $message;
    }
    
    /**
     * Login user
     */
    public static function login($user) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_mobile'] = $user['mobile'];
        $_SESSION['region_id'] = $user['region_id'] ?? null;
        
        // Generate new JWT for mobile sync
        $tokenResult = JWT::generateToken($user['id'], $user['user_type'], ['name' => $user['name']]);
        $_SESSION['jwt_token'] = $tokenResult['token'];
        
        // Update login info
        $db = getDB();
        $db->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'last_login_ip' => $_SERVER['REMOTE_ADDR']
        ], 'id = ?', [$user['id']]);
        
        logActivity('Admin login: ' . $user['name'], $user['id']);
    }
    
    /**
     * Logout
     */
    public static function logout() {
        $userId = $_SESSION['admin_id'] ?? null;
        $token = $_SESSION['jwt_token'] ?? null;
        
        if ($token) JWT::blacklistToken($token);
        
        session_destroy();
        if ($userId) logActivity('Admin logout', $userId);
        
        header('Location: ' . APP_URL . '/admin/login');
        exit;
    }
}
