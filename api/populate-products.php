<?php
/**
 * FARMLINK - Populate Products Script
 * Adds farmer's products to the database
 */

// Set base path for includes
$basePath = dirname(__DIR__);

try {
    // Include required files
    require $basePath . '/api/config.php';
    
    $pdo = getDBConnection();
    
    // First, let's create a farmer user if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'farmer' LIMIT 1");
    $stmt->execute();
    $farmer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $farmerId = 1; // Default farmer ID
    if (!$farmer) {
        // Create a default farmer user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, farm_name, role, phone_number, location, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'local_farmer',
            'farmer@farmlink.com',
            password_hash('farmer123', PASSWORD_DEFAULT),
            'Local Farm',
            'farmer',
            '09123456789',
            'Local Farm Address',
            'active'
        ]);
        $farmerId = $pdo->lastInsertId();
    } else {
        $farmerId = $farmer['id'];
    }
    
    // Clear existing products for this farmer
    $stmt = $pdo->prepare("DELETE FROM products WHERE farmer_id = ?");
    $stmt->execute([$farmerId]);
    
    // Define the farmer's products with their actual images
    $products = [
        [
            'name' => 'Petchay',
            'category' => 'Vegetables',
            'quantity' => 100.00,
            'price' => 30.00,
            'unit' => 'pieces',
            'description' => 'Pet-chay (also spelled petsay) most commonly refers to bok choy. Fresh, crispy leaves perfect for stir-fry and soups.',
            'image' => '/FARMLINK/assets/img/products/petchay.png'
        ],
        [
            'name' => 'Onion',
            'category' => 'Vegetables',
            'quantity' => 100.00,
            'price' => 200.00,
            'unit' => 'kg',
            'description' => 'The onion (scientific name: Allium cepa) is a bulbous vegetable. Fresh, pungent onions perfect for cooking.',
            'image' => '/FARMLINK/assets/img/products/onion.png'
        ],
        [
            'name' => 'Garlic',
            'category' => 'Vegetables',
            'quantity' => 100.00,
            'price' => 100.00,
            'unit' => 'kg',
            'description' => 'Garlic (scientific name: Allium sativum) is a species in the onion genus. Fresh, aromatic garlic cloves.',
            'image' => '/FARMLINK/assets/img/products/garlic.png'
        ],
        [
            'name' => 'Luya',
            'category' => 'Vegetables',
            'quantity' => 100.00,
            'price' => 10.00,
            'unit' => 'pieces',
            'description' => 'Luya (scientific name: Zingiber officinale) is the rhizome of the flowering plant. Fresh ginger root with spicy flavor.',
            'image' => '/FARMLINK/assets/img/products/luya.png'
        ],
        [
            'name' => 'Mani',
            'category' => 'Grains',
            'quantity' => 100.00,
            'price' => 20.00,
            'unit' => 'lbs',
            'description' => 'Despite their name, peanuts are not true nuts. Botanically, they are legumes. Fresh, crunchy peanuts.',
            'image' => '/FARMLINK/assets/img/products/mani.png'
        ],
        [
            'name' => 'Papaya',
            'category' => 'Vegetables',
            'quantity' => 99.00,
            'price' => 120.00,
            'unit' => 'kg',
            'description' => 'Fresh, sweet papaya fruit. Rich in vitamins and perfect for healthy snacks and smoothies.',
            'image' => '/FARMLINK/assets/img/products/papaya.png'
        ],
        [
            'name' => 'Cauliflower',
            'category' => 'Vegetables',
            'quantity' => 99.00,
            'price' => 250.00,
            'unit' => 'kg',
            'description' => 'Cauliflower is one of several vegetables cultivated from the species Brassica oleracea. Fresh, white florets perfect for cooking.',
            'image' => '/FARMLINK/assets/img/products/cauliflower.png'
        ]
    ];
    
    // Insert products into database
    $stmt = $pdo->prepare("
        INSERT INTO products (
            farmer_id, name, category, quantity, price, unit, description, image, 
            current_stock, status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ");
    
    $insertedCount = 0;
    foreach ($products as $product) {
        $stmt->execute([
            $farmerId,
            $product['name'],
            $product['category'],
            $product['quantity'],
            $product['price'],
            $product['unit'],
            $product['description'],
            $product['image'],
            $product['quantity'] // current_stock same as quantity
        ]);
        $insertedCount++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully inserted {$insertedCount} products for farmer ID {$farmerId}",
        'farmer_id' => $farmerId,
        'products_count' => $insertedCount
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error populating products: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>
