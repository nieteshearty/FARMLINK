<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// For now, we'll assume authentication is handled elsewhere
// In production, implement proper authentication middleware

if ($method === 'GET') {
    $farmerId = $_GET['farmer_id'] ?? null;
    
    if ($farmerId) {
        // Get products for specific farmer
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as farmer_name 
            FROM products p 
            JOIN users u ON p.farmer_id = u.id 
            WHERE p.farmer_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$farmerId]);
    } else {
        // Get all products
        $stmt = $pdo->query("
            SELECT p.*, u.username as farmer_name 
            FROM products p 
            JOIN users u ON p.farmer_id = u.id 
            ORDER BY p.created_at DESC
        ");
    }
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'products' => $products]);
    
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['farmer_id', 'name', 'quantity', 'price'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            exit;
        }
    }
    
    // Insert product
    $productData = [
        'farmer_id' => $input['farmer_id'],
        'name' => $input['name'],
        'category' => $input['category'] ?? '',
        'quantity' => $input['quantity'],
        'price' => $input['price'],
        'unit' => $input['unit'] ?? 'kg',
        'description' => $input['description'] ?? '',
        'image' => $input['image'] ?? ''
    ];
    
    $columns = implode(', ', array_keys($productData));
    $placeholders = implode(', ', array_fill(0, count($productData), '?'));
    $values = array_values($productData);
    
    $stmt = $pdo->prepare("INSERT INTO products ($columns) VALUES ($placeholders)");
    if ($stmt->execute($values)) {
        $productId = $pdo->lastInsertId();
        
        // Get created product with farmer info
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as farmer_name 
            FROM products p 
            JOIN users u ON p.farmer_id = u.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $newProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['ok' => true, 'msg' => 'Product created successfully', 'product' => $newProduct]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Failed to create product']);
    }
    
} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $_GET['id'] ?? null;
    
    if (!$productId) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID required']);
        exit;
    }
    
    // Check if product exists and belongs to farmer
    $stmt = $pdo->prepare("SELECT farmer_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // In production, verify that current user owns this product
    
    // Update product
    $updatableFields = ['name', 'category', 'quantity', 'price', 'unit', 'description', 'image'];
    $updates = [];
    $values = [];
    
    foreach ($updatableFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $values[] = $input[$field];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $values[] = $productId;
    $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($values)) {
        // Get updated product
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as farmer_name 
            FROM products p 
            JOIN users u ON p.farmer_id = u.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $updatedProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['ok' => true, 'msg' => 'Product updated successfully', 'product' => $updatedProduct]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Failed to update product']);
    }
    
} elseif ($method === 'DELETE') {
    $productId = $_GET['id'] ?? null;
    
    if (!$productId) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID required']);
        exit;
    }
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT farmer_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // In production, verify that current user owns this product
    
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$productId])) {
        echo json_encode(['ok' => true, 'msg' => 'Product deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Failed to delete product']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
