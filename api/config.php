<?php
// Security Configuration
if (!defined('FORCE_HTTPS')) define('FORCE_HTTPS', false); // Set to true in production
if (!defined('SECURE_COOKIES')) define('SECURE_COOKIES', false); // Set to true with HTTPS
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600); // 1 hour

// Helper to read env from multiple sources
if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val === false && isset($_ENV[$key])) $val = $_ENV[$key];
        if ($val === false && isset($_SERVER[$key])) $val = $_SERVER[$key];
        return ($val === false || $val === null || $val === '') ? $default : $val;
    }
}

// Base URL detection (supports override via BASE_URL env)
if (!defined('BASE_URL')) {
    $baseOverride = env('BASE_URL', null);
    if ($baseOverride !== null) {
        define('BASE_URL', rtrim($baseOverride, '/'));
    } else {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        define('BASE_URL', $base === '/' ? '' : $base);
    }
}

// Support DATABASE_URL (mysql://user:pass@host:port/dbname)
$dbUrl = env('DATABASE_URL');
if ($dbUrl && stripos($dbUrl, 'mysql://') === 0) {
    $parts = parse_url($dbUrl);
    if ($parts) {
        $db_host = $parts['host'] ?? 'localhost';
        $db_port = isset($parts['port']) ? (int)$parts['port'] : 3306;
        $db_user = $parts['user'] ?? 'root';
        $db_pass = $parts['pass'] ?? '';
        $db_name = isset($parts['path']) ? ltrim($parts['path'], '/') : 'farmlink';
    }
}

// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', isset($db_host) ? $db_host : env('db.fr-pari1.bengt.wasmernet.com', default: env('MYSQLHOST', 'localhost')));
if (!defined('DB_PORT')) define('DB_PORT', isset($db_port) ? $db_port : (int)env('10272', default: (int)env('MYSQLPORT', 3306)));
if (!defined('DB_NAME')) define('DB_NAME', isset($db_name) ? $db_name : env('farmlink', default: env('MYSQLDATABASE', 'farmlink')));
if (!defined('DB_USER')) define('DB_USER', isset($db_user) ? $db_user : env('4356d6577680800017f765d96a03', default: env('MYSQLUSER', 'root')));
if (!defined('DB_PASS')) define('DB_PASS', isset($db_pass) ? $db_pass : env(' 06914356-d657-77e8-8000-d341270f0fbe', default: env('MYSQLPASSWORD', '')));

// Create database connection
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
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
