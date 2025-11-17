<?php
/**
 * FARMLINK - Get Featured Products API
 * Returns featured products for the landing page
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Set base path for includes
$basePath = dirname(__DIR__);

try {
    // Include required files
    require $basePath . '/api/config.php';
    
    $pdo = getDBConnection();
    
    // Get featured products with farmer information
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.description,
            p.price,
            p.unit,
            p.quantity,
            p.category,
            p.image,
            p.status,
            COALESCE(u.farm_name, u.username) as farmer_name,
            u.profile_picture as farmer_image
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        WHERE p.status = 'active' 
        AND u.role = 'farmer'
        ORDER BY p.created_at DESC, p.quantity DESC
        LIMIT 20
    ");
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process products for display
    $featuredProducts = [];
    foreach ($products as $product) {
        // Handle image path
        $base = defined('BASE_URL') ? BASE_URL : '';
        $imagePath = $base . '/assets/img/product-placeholder.svg'; // Default
        if (!empty($product['image'])) {
            $image = trim($product['image']);
            
            // Handle different image path formats
            if (strpos($image, 'http') === 0) {
                // Full URL
                $imagePath = $image;
            } elseif (strpos($image, $base . '/') === 0) {
                // Already has BASE_URL prefix
                $imagePath = $image;
            } elseif (strpos($image, 'uploads/') === 0) {
                // Relative path from project root
                $imagePath = $base . '/' . $image;
            } elseif (strpos($image, '/uploads/') === 0) {
                // Absolute path from server root
                $imagePath = $base . $image;
            } elseif (preg_match('#^/[^/]+/(uploads/.*)$#', $image, $matches)) {
                // Legacy prefix like /FOLDER/uploads/...
                $imagePath = $base . '/' . $matches[1];
            } else {
                // Just filename, assume it's in products folder
                $imagePath = $base . '/uploads/products/' . basename($image);
            }
        }
        
        // Normalize farmer avatar
        $farmerImage = $base . '/assets/img/default-avatar.png';
        if (!empty($product['farmer_image'])) {
            $avatar = trim($product['farmer_image']);
            if (strpos($avatar, 'http') === 0) {
                $farmerImage = $avatar;
            } elseif (strpos($avatar, $base . '/') === 0) {
                $farmerImage = $avatar;
            } elseif (strpos($avatar, 'uploads/') === 0) {
                $farmerImage = $base . '/' . $avatar;
            } elseif (strpos($avatar, '/uploads/') === 0) {
                $farmerImage = $base . $avatar;
            } elseif (preg_match('#^/[^/]+/(uploads/.*)$#', $avatar, $matchAvatar)) {
                $farmerImage = $base . '/' . $matchAvatar[1];
            } else {
                $farmerImage = $base . '/uploads/profiles/' . basename($avatar);
            }
        }

        // Determine category badge
        $category = ucfirst($product['category'] ?? 'Fresh');
        
        // Create featured product
        $featuredProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $product['description'] ?? 'Fresh agricultural product from local farmers',
            'price' => $product['price'],
            'unit' => $product['unit'] ?? 'kg',
            'quantity' => $product['quantity'],
            'category' => $category,
            'image_url' => $imagePath,
            'farmer_name' => $product['farmer_name'] ?? 'Local Farmer',
            'farmer_image' => $farmerImage,
            'status' => $product['status']
        ];
    }
    
    // If no products found, show real farmer products from the system
    if (empty($featuredProducts)) {
        $featuredProducts = [
            [
                'id' => 'petchay_001',
                'name' => 'Petchay',
                'description' => 'Pet-chay (also spelled petsay) most commonly refers to bok choy. Fresh, crispy leaves perfect for stir-fry and soups.',
                'price' => '30.00',
                'unit' => 'pieces',
                'quantity' => 100,
                'category' => 'Vegetables',
                'image_url' => 'https://images.unsplash.com/photo-1515543237350-b3eea1ec8082?w=400&h=300&fit=crop&auto=format',
                'farmer_name' => 'Local Farmer',
                'farmer_image' => $base . '/assets/img/default-avatar.png',
                'status' => 'active'
            ],
            [
                'id' => 'onion_001',
                'name' => 'Onion',
                'description' => 'The onion (scientific name: Allium cepa) is a bulbous vegetable. Fresh, pungent onions perfect for cooking.',
                'price' => '200.00',
                'unit' => 'kg',
                'quantity' => 100,
                'category' => 'Vegetables',
                'image_url' => 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?w=400&h=300&fit=crop&auto=format',
                'farmer_name' => 'Local Farmer',
                'farmer_image' => $base . '/assets/img/default-avatar.png',
                'status' => 'active'
            ],
            [
                'id' => 'garlic_001',
                'name' => 'Garlic',
                'description' => 'Garlic (scientific name: Allium sativum) is a species in the onion genus. Fresh, aromatic garlic cloves.',
                'price' => '100.00',
                'unit' => 'kg',
                'quantity' => 100,
                'category' => 'Vegetables',
                'image_url' => 'https://images.unsplash.com/photo-1553978297-833d24758027?w=400&h=300&fit=crop&auto=format',
                'farmer_name' => 'Local Farmer',
                'farmer_image' => $base . '/assets/img/default-avatar.png',
                'status' => 'active'
            ],
            [
                'id' => 'luya_001',
                'name' => 'Luya (Ginger)',
                'description' => 'Luya (scientific name: Zingiber officinale) is the rhizome of the flowering plant. Fresh ginger root with spicy flavor.',
                'price' => '10.00',
                'unit' => 'pieces',
                'quantity' => 100,
                'category' => 'Vegetables',
                'image_url' => 'https://images.unsplash.com/photo-1599909533730-4c8e6a3f6e0e?w=400&h=300&fit=crop&auto=format',
                'farmer_name' => 'Local Farmer',
                'farmer_image' => $base . '/assets/img/default-avatar.png',
                'status' => 'active'
            ],
            [
                'id' => 'mani_001',
                'name' => 'Mani (Peanuts)',
                'description' => 'Despite their name, peanuts are not true nuts. Botanically, they are legumes. Fresh, crunchy peanuts.',
                'price' => '20.00',
                'unit' => 'lbs',
                'quantity' => 100,
                'category' => 'Grains',
                'image_url' => 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=400&h=300&fit=crop&auto=format',
                'farmer_name' => 'Local Farmer',
                'farmer_image' => '/FARMLINK/assets/img/default-avatar.png',
                'status' => 'active'
            ],
            [
                'id' => 'papaya_001',
                'name' => 'Papaya',
                'description' => 'Fresh, sweet papaya fruit. Rich in vitamins and perfect for healthy snacks and smoothies.',
                'price' => '120.00',
                'unit' => 'kg',
                'quantity' => 99,
                'category' => 'Fruits',
                'image_url' => 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?w=400&h=300&fit=crop&auto=format',
                'farmer_name' => 'Local Farmer',
                'farmer_image' => '/FARMLINK/assets/img/default-avatar.png',
                'status' => 'active'
            ],
            [
                'id' => 'cauliflower_001',
                'name' => 'Cauliflower',
                'description' => 'Cauliflower is one of several vegetables cultivated from the species Brassica oleracea. Fresh, white florets perfect for cooking.',
                'price' => '250.00',
                'unit' => 'kg',
                'quantity' => 99,
                'category' => 'Vegetables',
                'image_url' => 'https://images.unsplash.com/photo-1568584711271-946d4d46b7d5?w=400&h=300&fit=crop&auto=format',
                'farmer_name' => 'Local Farmer',
                'farmer_image' => $base . '/assets/img/default-avatar.png',
                'status' => 'active'
            ]
        ];
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'products' => $featuredProducts,
        'total' => count($featuredProducts),
        'message' => 'Featured products loaded successfully'
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Featured products API error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'products' => [],
        'total' => 0,
        'message' => 'Unable to load featured products',
        'error' => $e->getMessage()
    ]);
}
?>
