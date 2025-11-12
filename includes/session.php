<?php
// Session management and authentication
require_once __DIR__ . '/../api/config.php';

// Secure session configuration
if (session_status() == PHP_SESSION_NONE) {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
    
    if (SECURE_COOKIES && isHTTPS()) {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
    }
    
    // Regenerate session ID periodically for security
    session_start();
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

class SessionManager {
    
    public static function isLoggedIn() {
        return isset($_SESSION['user']) && !empty($_SESSION['user']);
    }
    
    public static function getUser() {
        return self::isLoggedIn() ? $_SESSION['user'] : null;
    }
    
    public static function getUserRole() {
        $user = self::getUser();
        return $user ? $user['role'] : null;
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public static function requireRole($role) {
        self::requireLogin();
        $userRole = self::getUserRole();
        
        // Super admin has access to everything
        if ($userRole === 'super_admin') {
            return self::getUser();
        }
        
        // Normal role check for other users
        if ($userRole !== $role) {
            header('Location: /FARMLINK/pages/auth/unauthorized.php');
            exit;
        }
        return self::getUser();
    }
    
    public static function hasAccess($requiredRole) {
        $userRole = self::getUserRole();
        
        // Super admin has all access
        if ($userRole === 'super_admin') {
            return true;
        }
        
        // Define role hierarchy (admin removed - only super_admin, farmer, buyer)
        $hierarchy = [
            'farmer' => ['farmer'],
            'buyer' => ['buyer']
        ];
        
        return in_array($requiredRole, $hierarchy[$userRole] ?? []);
    }
    
    public static function logout() {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    public static function login($user) {
        $_SESSION['user'] = $user;
        
        // Log activity
        self::logActivity($user['id'], 'login', 'User logged in');
    }
    
    public static function logActivity($userId, $type, $message) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, type, message) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $type, $message]);
        } catch (Exception $e) {
            // Log error but don't break the flow
            error_log("Activity log error: " . $e->getMessage());
        }
    }
}

?>
