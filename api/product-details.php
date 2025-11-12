<?php
// Set base path for includes
$basePath = dirname(__DIR__);

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get product ID from request
$productId = $_GET['id'] ?? null;

if (!$productId || !is_numeric($productId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get detailed product information with farmer details
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username as farmer_name,
            u.email as farmer_email,
            u.profile_picture as farmer_avatar,
            u.created_at as farmer_joined,
            u.farm_name as farm_name,
            u.location as farm_address,
            u.company as company_name,
            COUNT(DISTINCT o.id) as total_orders
        FROM products p 
        LEFT JOIN users u ON p.farmer_id = u.id 
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE p.id = ?
        GROUP BY p.id
    ");
    
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // Get recent reviews for this product (if reviews table exists)
    $reviews = [];
    $avgRating = 0;
    $reviewCount = 0;
    
    try {
        // Check if reviews table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'reviews'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                SELECT 
                    r.*,
                    u.username as reviewer_name,
                    u.profile_picture as reviewer_avatar
                FROM reviews r
                LEFT JOIN users u ON r.buyer_id = u.id
                WHERE r.product_id = ?
                ORDER BY r.created_at DESC
                LIMIT 5
            ");
            
            $stmt->execute([$productId]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get average rating and count
            $stmt = $pdo->prepare("
                SELECT AVG(overall_rating) as avg_rating, COUNT(*) as review_count
                FROM reviews 
                WHERE product_id = ?
            ");
            $stmt->execute([$productId]);
            $ratingData = $stmt->fetch(PDO::FETCH_ASSOC);
            $avgRating = round($ratingData['avg_rating'] ?? 0, 1);
            $reviewCount = $ratingData['review_count'] ?? 0;
        }
    } catch (Exception $e) {
        // Reviews table doesn't exist or error occurred, continue without reviews
        error_log("Reviews query error: " . $e->getMessage());
    }
    
    // Get product sales history (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(o.created_at) as sale_date,
            SUM(oi.quantity) as quantity_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.product_id = ? 
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND o.status != 'cancelled'
        GROUP BY DATE(o.created_at)
        ORDER BY sale_date DESC
        LIMIT 10
    ");
    
    $stmt->execute([$productId]);
    $salesHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate additional metrics
    $totalSold = array_sum(array_column($salesHistory, 'quantity_sold'));
    $totalRevenue = array_sum(array_column($salesHistory, 'revenue'));
    
    // Format the response
    $response = [
        'product' => $product,
        'reviews' => $reviews,
        'sales_history' => $salesHistory,
        'metrics' => [
            'total_sold_30_days' => $totalSold,
            'revenue_30_days' => $totalRevenue,
            'avg_rating' => $avgRating,
            'review_count' => $reviewCount,
            'total_orders' => $product['total_orders'] ?? 0
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Product details API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
