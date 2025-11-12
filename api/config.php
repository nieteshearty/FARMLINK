<?php
// Security Configuration
if (!defined('FORCE_HTTPS')) define('FORCE_HTTPS', false); // Set to true in production
if (!defined('SECURE_COOKIES')) define('SECURE_COOKIES', false); // Set to true with HTTPS
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600); // 1 hour

// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'farmlink');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// Create database connection
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
}

// Security Functions
if (!function_exists('forceHTTPS')) {
    function forceHTTPS() {
        if (FORCE_HTTPS && !isHTTPS()) {
            $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirectURL", true, 301);
            exit();
        }
    }
}

if (!function_exists('isHTTPS')) {
    function isHTTPS() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || $_SERVER['SERVER_PORT'] == 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    }
}

if (!function_exists('setSecurityHeaders')) {
    function setSecurityHeaders() {
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        if (isHTTPS()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdnjs.cloudflare.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdnjs.cloudflare.com https://fonts.googleapis.com; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; ";
        $csp .= "img-src 'self' data: https: http:; ";
        $csp .= "connect-src 'self' https://nominatim.openstreetmap.org; ";
        $csp .= "frame-src 'none';";
        header("Content-Security-Policy: $csp");
    }
}

// Apply security measures
forceHTTPS();
setSecurityHeaders();

// Only set headers for API requests
if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    // Enable CORS with security considerations
    $allowedOrigins = ['http://localhost', 'https://localhost'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowedOrigins) || strpos($origin, 'localhost') !== false) {
        header("Access-Control-Allow-Origin: $origin");
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}
?>
