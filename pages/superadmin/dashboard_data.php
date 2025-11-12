<?php
// Disable error display in production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/error.log');

// Set default timezone
date_default_timezone_set('UTC');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../api/config.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    // Check if user is logged in and is super admin
    $user = SessionManager::getUser();
    
    if (!$user || $user['role'] !== 'super_admin') {
        throw new Exception('Unauthorized access');
    }

    // Get database connection
    $pdo = Database::getConnection();
    
    // Get total users count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get farmers count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'farmer'");
    $totalFarmers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get buyers count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'buyer'");
    $totalBuyers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get products count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
    $totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total orders count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get admins count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $totalAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Prepare stats data
    $response['data'] = [
        'total_users' => (int)$totalUsers,
        'total_farmers' => (int)$totalFarmers,
        'total_buyers' => (int)$totalBuyers,
        'total_products' => (int)$totalProducts,
        'total_orders' => (int)$totalOrders,
        'total_admins' => (int)$totalAdmins
    ];
    
    $response['success'] = true;
    
} catch (Exception $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    $response['message'] = 'Error fetching dashboard data: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
