<?php
/**
 * Advanced Search API
 * Handles advanced product search with multiple filters
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Set base path for includes
$basePath = dirname(dirname(__DIR__));
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

try {
    $pdo = getDBConnection();
    
    // Get search parameters
    $keyword = trim($_GET['q'] ?? '');
    $category = $_GET['category'] ?? '';
    $minPrice = floatval($_GET['min_price'] ?? 0);
    $maxPrice = floatval($_GET['max_price'] ?? 999999);
    $isOrganic = isset($_GET['organic']) ? filter_var($_GET['organic'], FILTER_VALIDATE_BOOLEAN) : null;
    $inStock = isset($_GET['in_stock']) ? filter_var($_GET['in_stock'], FILTER_VALIDATE_BOOLEAN) : true;
    $location = $_GET['location'] ?? '';
    $radius = floatval($_GET['radius'] ?? 50); // km
    $sortBy = $_GET['sort'] ?? 'relevance';
    $limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
    $offset = max(0, intval($_GET['offset'] ?? 0));
    
    // Build WHERE clause
    $whereConditions = ["p.status = 'active'"];
    $params = [];
    
    // Keyword search
    if ($keyword) {
        $whereConditions[] = "(
            p.name LIKE ? OR 
            p.description LIKE ? OR 
            p.keywords LIKE ? OR 
            p.category LIKE ?
        )";
        $keywordParam = '%' . $keyword . '%';
        $params = array_merge($params, [$keywordParam, $keywordParam, $keywordParam, $keywordParam]);
    }
    
    // Category filter
    if ($category) {
        $whereConditions[] = "p.category = ?";
        $params[] = $category;
    }
    
    // Price range filter
    if ($minPrice > 0) {
        $whereConditions[] = "p.price >= ?";
        $params[] = $minPrice;
    }
    if ($maxPrice < 999999) {
        $whereConditions[] = "p.price <= ?";
        $params[] = $maxPrice;
    }
    
    // Organic filter
    if ($isOrganic !== null) {
        $whereConditions[] = "p.is_organic = ?";
        $params[] = $isOrganic ? 1 : 0;
    }
    
    // Stock filter
    if ($inStock) {
        $whereConditions[] = "p.current_stock > 0";
    }
    
    // Location-based search
    $distanceSelect = "0 as distance";
    $distanceOrder = "";
    
    if ($location && SessionManager::isLoggedIn()) {
        $user = $_SESSION['user'];
        if ($user['latitude'] && $user['longitude']) {
            $distanceSelect = "
                (6371 * acos(
                    cos(radians(?)) * 
                    cos(radians(u.latitude)) * 
                    cos(radians(u.longitude) - radians(?)) + 
                    sin(radians(?)) * 
                    sin(radians(u.latitude))
                )) as distance
            ";
            $params = array_merge([$user['latitude'], $user['longitude'], $user['latitude']], $params);
            
            if ($radius > 0) {
                $whereConditions[] = "
                    (6371 * acos(
                        cos(radians(?)) * 
                        cos(radians(u.latitude)) * 
                        cos(radians(u.longitude) - radians(?)) + 
                        sin(radians(?)) * 
                        sin(radians(u.latitude))
                    )) <= ?
                ";
                $params = array_merge($params, [$user['latitude'], $user['longitude'], $user['latitude'], $radius]);
            }
        }
    }
    
    // Build ORDER BY clause
    $orderBy = "p.created_at DESC";
    switch ($sortBy) {
        case 'price_low':
            $orderBy = "p.price ASC";
            break;
        case 'price_high':
            $orderBy = "p.price DESC";
            break;
        case 'rating':
            $orderBy = "p.average_rating DESC, p.total_reviews DESC";
            break;
        case 'newest':
            $orderBy = "p.created_at DESC";
            break;
        case 'popular':
            $orderBy = "p.total_sales DESC, p.total_reviews DESC";
            break;
        case 'distance':
            if ($location && SessionManager::isLoggedIn()) {
                $orderBy = "distance ASC";
            }
            break;
        case 'relevance':
        default:
            if ($keyword) {
                $orderBy = "
                    CASE 
                        WHEN p.name LIKE ? THEN 1
                        WHEN p.category LIKE ? THEN 2
                        WHEN p.description LIKE ? THEN 3
                        ELSE 4
                    END,
                    p.average_rating DESC,
                    p.total_sales DESC
                ";
                $keywordParam = '%' . $keyword . '%';
                $params = array_merge($params, [$keywordParam, $keywordParam, $keywordParam]);
            } else {
                $orderBy = "p.average_rating DESC, p.total_sales DESC";
            }
            break;
    }
    
    // Build the main query
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT 
            p.*,
            u.username as farmer_name,
            u.farm_name,
            u.city as farmer_city,
            u.province as farmer_province,
            u.profile_picture as farmer_avatar,
            u.average_rating as farmer_rating,
            {$distanceSelect}
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        WHERE {$whereClause}
        ORDER BY {$orderBy}
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(*) as total
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        WHERE {$whereClause}
    ";
    
    $countParams = array_slice($params, 0, -2); // Remove limit and offset
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $totalCount = $stmt->fetch()['total'];
    
    // Format products
    $formattedProducts = array_map(function($product) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        $normalize = function($v, $type = 'products') use ($base) {
            if (empty($v)) return '';
            $v = trim($v);
            if (strpos($v, 'http') === 0) return $v;
            if (strpos($v, $base . '/') === 0 || strpos($v, '/FARMLINK/') === 0) return $v;
            if (strpos($v, 'uploads/') === 0) return $base . '/' . $v;
            if (strpos($v, '/') === 0) return $base . $v;
            return $base . '/uploads/' . $type . '/' . basename($v);
        };
        // Handle image path
        $imagePath = $normalize($product['image'] ?? '', 'products');
        
        // Handle farmer avatar
        $farmerAvatar = $normalize($product['farmer_avatar'] ?? '', 'profiles');
        
        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'category' => $product['category'],
            'description' => $product['description'],
            'price' => floatval($product['price']),
            'unit' => $product['unit'],
            'current_stock' => floatval($product['current_stock']),
            'minimum_order' => floatval($product['minimum_order']),
            'maximum_order' => $product['maximum_order'] ? floatval($product['maximum_order']) : null,
            'is_organic' => (bool)$product['is_organic'],
            'harvest_date' => $product['harvest_date'],
            'expiry_date' => $product['expiry_date'],
            'image' => $imagePath ?: ($base . '/assets/img/product-placeholder.svg'),
            'average_rating' => floatval($product['average_rating']),
            'total_reviews' => intval($product['total_reviews']),
            'total_sales' => intval($product['total_sales']),
            'is_featured' => (bool)$product['is_featured'],
            'distance' => $product['distance'] ? round(floatval($product['distance']), 1) : null,
            'farmer' => [
                'id' => $product['farmer_id'],
                'username' => $product['farmer_name'],
                'farm_name' => $product['farm_name'],
                'location' => trim(($product['farmer_city'] ?? '') . ' ' . ($product['farmer_province'] ?? '')),
                'avatar' => $farmerAvatar ?: ($base . '/assets/img/default-avatar.png'),
                'rating' => floatval($product['farmer_rating'])
            ],
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ];
    }, $products);
    
    // Get available categories for filters
    $stmt = $pdo->prepare("
        SELECT DISTINCT category, COUNT(*) as count
        FROM products 
        WHERE status = 'active' AND current_stock > 0
        GROUP BY category 
        ORDER BY count DESC, category ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get price range
    $stmt = $pdo->prepare("
        SELECT MIN(price) as min_price, MAX(price) as max_price
        FROM products 
        WHERE status = 'active' AND current_stock > 0
    ");
    $stmt->execute();
    $priceRange = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'products' => $formattedProducts,
        'pagination' => [
            'total' => intval($totalCount),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount,
            'total_pages' => ceil($totalCount / $limit),
            'current_page' => floor($offset / $limit) + 1
        ],
        'filters' => [
            'categories' => $categories,
            'price_range' => [
                'min' => floatval($priceRange['min_price'] ?? 0),
                'max' => floatval($priceRange['max_price'] ?? 1000)
            ]
        ],
        'search_params' => [
            'keyword' => $keyword,
            'category' => $category,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'is_organic' => $isOrganic,
            'in_stock' => $inStock,
            'location' => $location,
            'radius' => $radius,
            'sort_by' => $sortBy
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Advanced search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
?>
