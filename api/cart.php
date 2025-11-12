<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// For now, we'll assume authentication is handled elsewhere

if ($method === 'GET') {
    $buyerId = $_GET['buyer_id'] ?? null;
    
    if (!$buyerId) {
        http_response_code(400);
        echo json_encode(['error' => 'Buyer ID required']);
        exit;
    }
    
    // Get cart items with product details
    $stmt = $pdo->prepare("
        SELECT c.*, p.name as product_name, p.price, p.unit, p.image, u.username as farmer_name
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        JOIN users u ON p.farmer_id = u.id 
        WHERE c.buyer_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$buyerId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total
    $total = 0;
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    echo json_encode([
        'ok' => true, 
        'cart' => $cartItems,
        'total' => $total,
        'item_count' => count($cartItems)
    ]);
    
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['buyer_id']) || !isset($input['product_id']) || !isset($input['quantity'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Buyer ID, product ID, and quantity required']);
        exit;
    }
    
    // Check if product exists and get price
    $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
    $stmt->execute([$input['product_id']]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // Check if item already in cart
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE buyer_id = ? AND product_id = ?");
    $stmt->execute([$input['buyer_id'], $input['product_id']]);
    $existingItem = $stmt->fetch();
    
    if ($existingItem) {
        // Update quantity
        $newQuantity = $existingItem['quantity'] + $input['quantity'];
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $existingItem['id']]);
    } else {
        // Add new item
        $stmt = $pdo->prepare("INSERT INTO cart (buyer_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$input['buyer_id'], $input['product_id'], $input['quantity']]);
    }
    
    // Get updated cart
    $stmt = $pdo->prepare("
        SELECT c.*, p.name as product_name, p.price, p.unit, p.image, u.username as farmer_name
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        JOIN users u ON p.farmer_id = u.id 
        WHERE c.buyer_id = ?
    ");
    $stmt->execute([$input['buyer_id']]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true, 
        'msg' => 'Item added to cart',
        'cart' => $cartItems
    ]);
    
} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cartItemId = $_GET['id'] ?? null;
    
    if (!$cartItemId || !isset($input['quantity'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart item ID and quantity required']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    if ($stmt->execute([$input['quantity'], $cartItemId])) {
        echo json_encode(['ok' => true, 'msg' => 'Cart item updated']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Failed to update cart item']);
    }
    
} elseif ($method === 'DELETE') {
    $cartItemId = $_GET['id'] ?? null;
    $buyerId = $_GET['buyer_id'] ?? null;
    
    if ($cartItemId) {
        // Delete specific cart item
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
        if ($stmt->execute([$cartItemId])) {
            echo json_encode(['ok' => true, 'msg' => 'Item removed from cart']);
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'Failed to remove item']);
        }
    } elseif ($buyerId) {
        // Clear entire cart for buyer
        $stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ?");
        if ($stmt->execute([$buyerId])) {
            echo json_encode(['ok' => true, 'msg' => 'Cart cleared']);
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'Failed to clear cart']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Cart item ID or buyer ID required']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
