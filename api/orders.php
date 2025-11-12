<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// For now, we'll assume authentication is handled elsewhere

if ($method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    $role = $_GET['role'] ?? null;
    $status = $_GET['status'] ?? null;
    
    if ($userId && $role) {
        if ($role === 'farmer') {
            // Get orders for farmer
            $sql = "SELECT o.*, u.username as buyer_name 
                    FROM orders o 
                    JOIN users u ON o.buyer_id = u.id 
                    WHERE o.farmer_id = ?";
            $params = [$userId];
            
            if ($status) {
                $sql .= " AND o.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY o.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
        } elseif ($role === 'buyer') {
            // Get orders for buyer
            $sql = "SELECT o.*, u.username as farmer_name 
                    FROM orders o 
                    JOIN users u ON o.farmer_id = u.id 
                    WHERE o.buyer_id = ?";
            $params = [$userId];
            
            if ($status) {
                $sql .= " AND o.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY o.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
        } else {
            // Admin - get all orders
            $sql = "SELECT o.*, 
                           buyer.username as buyer_name, 
                           farmer.username as farmer_name 
                    FROM orders o 
                    JOIN users buyer ON o.buyer_id = buyer.id 
                    JOIN users farmer ON o.farmer_id = farmer.id";
            $params = [];
            
            if ($status) {
                $sql .= " WHERE o.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY o.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get order items for each order
        foreach ($orders as &$order) {
            $stmt = $pdo->prepare("
                SELECT oi.*, p.name as product_name 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order['id']]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['ok' => true, 'orders' => $orders]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'User ID and role required']);
    }
    
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['buyer_id']) || !isset($input['items']) || !is_array($input['items'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Buyer ID and items array required']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Group items by farmer
        $ordersByFarmer = [];
        $totalAmount = 0;
        
        foreach ($input['items'] as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                throw new Exception('Invalid item format');
            }
            
            // Get product details
            $stmt = $pdo->prepare("SELECT farmer_id, price FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception("Product not found: " . $item['product_id']);
            }
            
            $farmerId = $product['farmer_id'];
            $itemTotal = $product['price'] * $item['quantity'];
            $totalAmount += $itemTotal;
            
            if (!isset($ordersByFarmer[$farmerId])) {
                $ordersByFarmer[$farmerId] = [
                    'items' => [],
                    'total' => 0
                ];
            }
            
            $ordersByFarmer[$farmerId]['items'][] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $product['price']
            ];
            $ordersByFarmer[$farmerId]['total'] += $itemTotal;
        }
        
        $createdOrders = [];
        
        // Create order for each farmer
        foreach ($ordersByFarmer as $farmerId => $orderData) {
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO orders (buyer_id, farmer_id, total, status) 
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$input['buyer_id'], $farmerId, $orderData['total']]);
            $orderId = $pdo->lastInsertId();
            
            // Add order items
            foreach ($orderData['items'] as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
            }
            
            $createdOrders[] = $orderId;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'ok' => true, 
            'msg' => 'Orders created successfully', 
            'order_ids' => $createdOrders,
            'total_amount' => $totalAmount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    
} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $_GET['id'] ?? null;
    
    if (!$orderId || !isset($input['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Order ID and status required']);
        exit;
    }
    
    $validStatuses = ['pending', 'completed', 'cancelled'];
    if (!in_array($input['status'], $validStatuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    if ($stmt->execute([$input['status'], $orderId])) {
        echo json_encode(['ok' => true, 'msg' => 'Order status updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Failed to update order status']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
