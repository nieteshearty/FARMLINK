<?php
/**
 * Submit Review API
 * Handles review submission for completed orders
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Set base path for includes
$basePath = dirname(dirname(__DIR__));
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = $_SESSION['user'];

// Only buyers can submit reviews
if ($user['role'] !== 'buyer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only buyers can submit reviews']);
    exit;
}

$orderId = intval($_POST['order_id'] ?? 0);
$productId = intval($_POST['product_id'] ?? 0);
$overallRating = intval($_POST['overall_rating'] ?? 0);
$qualityRating = intval($_POST['quality_rating'] ?? 0);
$deliveryRating = intval($_POST['delivery_rating'] ?? 0);
$communicationRating = intval($_POST['communication_rating'] ?? 0);
$reviewText = trim($_POST['review_text'] ?? '');

// Validate input
if (!$orderId || !$productId || $overallRating < 1 || $overallRating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit;
}

// Validate optional ratings
$ratings = [$qualityRating, $deliveryRating, $communicationRating];
foreach ($ratings as $rating) {
    if ($rating && ($rating < 1 || $rating > 5)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ratings must be between 1 and 5']);
        exit;
    }
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Verify order exists and belongs to user
    $stmt = $pdo->prepare("
        SELECT o.*, oi.product_id, p.farmer_id, p.name as product_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.id = ? AND o.buyer_id = ? AND oi.product_id = ? AND o.status = 'completed'
    ");
    $stmt->execute([$orderId, $user['id'], $productId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Order not found or not eligible for review');
    }
    
    // Check if review already exists
    $stmt = $pdo->prepare("
        SELECT id FROM reviews 
        WHERE order_id = ? AND product_id = ? AND buyer_id = ?
    ");
    $stmt->execute([$orderId, $productId, $user['id']]);
    
    if ($stmt->fetch()) {
        throw new Exception('Review already submitted for this product');
    }
    
    // Handle review images
    $reviewImages = [];
    if (isset($_FILES['review_images'])) {
        $uploadDir = $basePath . '/uploads/reviews/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $maxFiles = 5;
        
        $files = $_FILES['review_images'];
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        
        for ($i = 0; $i < min($fileCount, $maxFiles); $i++) {
            $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $fileTmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            
            if ($fileError === UPLOAD_ERR_OK) {
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if (in_array($fileExtension, $allowedTypes)) {
                    $newFileName = uniqid() . '.' . $fileExtension;
                    $uploadPath = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmp, $uploadPath)) {
                        $reviewImages[] = '/FARMLINK/uploads/reviews/' . $newFileName;
                    }
                }
            }
        }
    }
    
    // Insert review
    $stmt = $pdo->prepare("
        INSERT INTO reviews (
            order_id, product_id, buyer_id, farmer_id, 
            overall_rating, quality_rating, delivery_rating, communication_rating,
            review_text, review_images
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $orderId,
        $productId,
        $user['id'],
        $order['farmer_id'],
        $overallRating,
        $qualityRating ?: null,
        $deliveryRating ?: null,
        $communicationRating ?: null,
        $reviewText,
        !empty($reviewImages) ? json_encode($reviewImages) : null
    ]);
    
    $reviewId = $pdo->lastInsertId();
    
    // Update product rating
    $stmt = $pdo->prepare("
        UPDATE products SET 
            average_rating = (
                SELECT AVG(overall_rating) 
                FROM reviews 
                WHERE product_id = ?
            ),
            total_reviews = (
                SELECT COUNT(*) 
                FROM reviews 
                WHERE product_id = ?
            )
        WHERE id = ?
    ");
    $stmt->execute([$productId, $productId, $productId]);
    
    // Update farmer rating
    $stmt = $pdo->prepare("
        UPDATE users SET 
            average_rating = (
                SELECT AVG(overall_rating) 
                FROM reviews 
                WHERE farmer_id = ?
            ),
            total_reviews = (
                SELECT COUNT(*) 
                FROM reviews 
                WHERE farmer_id = ?
            )
        WHERE id = ?
    ");
    $stmt->execute([$order['farmer_id'], $order['farmer_id'], $order['farmer_id']]);
    
    // Create notification for farmer
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, data, action_url) 
        VALUES (?, 'review', ?, ?, ?, ?)
    ");
    
    $notificationData = json_encode([
        'review_id' => $reviewId,
        'product_id' => $productId,
        'product_name' => $order['product_name'],
        'buyer_name' => $user['username'],
        'rating' => $overallRating
    ]);
    
    $stmt->execute([
        $order['farmer_id'],
        'New Review Received',
        $user['username'] . ' left a ' . $overallRating . '-star review for ' . $order['product_name'],
        $notificationData,
        '/FARMLINK/pages/farmer/farmer-products.php?product=' . $productId
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'review_id' => $reviewId,
        'message' => 'Review submitted successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Review submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
