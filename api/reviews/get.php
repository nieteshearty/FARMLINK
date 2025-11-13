<?php
/**
 * Get Reviews API
 * Retrieves reviews for products or farmers
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Set base path for includes
$basePath = dirname(dirname(__DIR__));
require $basePath . '/api/config.php';

$productId = intval($_GET['product_id'] ?? 0);
$farmerId = intval($_GET['farmer_id'] ?? 0);
$limit = min(50, max(5, intval($_GET['limit'] ?? 10)));
$offset = max(0, intval($_GET['offset'] ?? 0));
$sortBy = $_GET['sort'] ?? 'newest'; // newest, oldest, rating_high, rating_low

if (!$productId && !$farmerId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Product ID or Farmer ID required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Build WHERE clause
    $whereClause = $productId ? "r.product_id = ?" : "r.farmer_id = ?";
    $param = $productId ?: $farmerId;
    
    // Build ORDER BY clause
    $orderBy = "r.created_at DESC";
    switch ($sortBy) {
        case 'oldest':
            $orderBy = "r.created_at ASC";
            break;
        case 'rating_high':
            $orderBy = "r.overall_rating DESC, r.created_at DESC";
            break;
        case 'rating_low':
            $orderBy = "r.overall_rating ASC, r.created_at DESC";
            break;
        case 'newest':
        default:
            $orderBy = "r.created_at DESC";
            break;
    }
    
    // Get reviews
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            b.username as buyer_name,
            b.profile_picture as buyer_avatar,
            p.name as product_name,
            p.image as product_image,
            f.username as farmer_name,
            f.farm_name,
            resp.response_text as farmer_response,
            resp.created_at as response_date
        FROM reviews r
        JOIN users b ON r.buyer_id = b.id
        JOIN products p ON r.product_id = p.id
        JOIN users f ON r.farmer_id = f.id
        LEFT JOIN review_responses resp ON r.id = resp.review_id
        WHERE {$whereClause}
        ORDER BY {$orderBy}
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$param, $limit, $offset]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM reviews r
        WHERE {$whereClause}
    ");
    $stmt->execute([$param]);
    $totalCount = $stmt->fetch()['total'];
    
    // Get rating summary
    $stmt = $pdo->prepare("
        SELECT 
            AVG(overall_rating) as average_rating,
            COUNT(*) as total_reviews,
            SUM(CASE WHEN overall_rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN overall_rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) as two_star,
            SUM(CASE WHEN overall_rating = 1 THEN 1 ELSE 0 END) as one_star,
            AVG(quality_rating) as avg_quality,
            AVG(delivery_rating) as avg_delivery,
            AVG(communication_rating) as avg_communication
        FROM reviews r
        WHERE {$whereClause}
    ");
    $stmt->execute([$param]);
    $ratingSummary = $stmt->fetch();
    
    // Format reviews
    $formattedReviews = array_map(function($review) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        $normalize = function($v, $type = 'profiles') use ($base) {
            if (empty($v)) return '';
            $v = trim($v);
            if (strpos($v, 'http') === 0) return $v;
            if (strpos($v, $base . '/') === 0 || strpos($v, '/FARMLINK/') === 0) return $v;
            if (strpos($v, 'uploads/') === 0) return $base . '/' . $v;
            if (strpos($v, '/') === 0) return $base . $v;
            return $base . '/uploads/' . $type . '/' . basename($v);
        };

        // Handle buyer avatar
        $buyerAvatar = $normalize($review['buyer_avatar'] ?? '', 'profiles');
        
        // Handle product image
        $productImage = $normalize($review['product_image'] ?? '', 'products');
        
        // Parse review images
        $reviewImages = [];
        if ($review['review_images']) {
            $reviewImages = json_decode($review['review_images'], true) ?: [];
        }
        
        return [
            'id' => $review['id'],
            'overall_rating' => intval($review['overall_rating']),
            'quality_rating' => $review['quality_rating'] ? intval($review['quality_rating']) : null,
            'delivery_rating' => $review['delivery_rating'] ? intval($review['delivery_rating']) : null,
            'communication_rating' => $review['communication_rating'] ? intval($review['communication_rating']) : null,
            'review_text' => $review['review_text'],
            'review_images' => $reviewImages,
            'is_verified' => (bool)$review['is_verified'],
            'is_featured' => (bool)$review['is_featured'],
            'created_at' => $review['created_at'],
            'formatted_date' => date('M j, Y', strtotime($review['created_at'])),
            'buyer' => [
                'id' => $review['buyer_id'],
                'username' => $review['buyer_name'],
                'avatar' => $buyerAvatar ?: ($base . '/assets/img/default-avatar.png')
            ],
            'product' => [
                'id' => $review['product_id'],
                'name' => $review['product_name'],
                'image' => $productImage ?: ($base . '/assets/img/product-placeholder.svg')
            ],
            'farmer' => [
                'id' => $review['farmer_id'],
                'username' => $review['farmer_name'],
                'farm_name' => $review['farm_name']
            ],
            'farmer_response' => $review['farmer_response'] ? [
                'text' => $review['farmer_response'],
                'date' => $review['response_date'],
                'formatted_date' => $review['response_date'] ? 
                    date('M j, Y', strtotime($review['response_date'])) : null
            ] : null
        ];
    }, $reviews);
    
    // Format rating summary
    $totalReviews = intval($ratingSummary['total_reviews']);
    $ratingBreakdown = [];
    
    if ($totalReviews > 0) {
        for ($i = 5; $i >= 1; $i--) {
            $count = intval($ratingSummary[$i === 5 ? 'five_star' : 
                                        ($i === 4 ? 'four_star' : 
                                        ($i === 3 ? 'three_star' : 
                                        ($i === 2 ? 'two_star' : 'one_star')))]);
            $ratingBreakdown[] = [
                'rating' => $i,
                'count' => $count,
                'percentage' => round(($count / $totalReviews) * 100, 1)
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $formattedReviews,
        'rating_summary' => [
            'average_rating' => round(floatval($ratingSummary['average_rating']), 1),
            'total_reviews' => $totalReviews,
            'rating_breakdown' => $ratingBreakdown,
            'category_averages' => [
                'quality' => $ratingSummary['avg_quality'] ? round(floatval($ratingSummary['avg_quality']), 1) : null,
                'delivery' => $ratingSummary['avg_delivery'] ? round(floatval($ratingSummary['avg_delivery']), 1) : null,
                'communication' => $ratingSummary['avg_communication'] ? round(floatval($ratingSummary['avg_communication']), 1) : null
            ]
        ],
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount,
            'total_pages' => ceil($totalCount / $limit),
            'current_page' => floor($offset / $limit) + 1
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get reviews error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve reviews']);
}
?>
