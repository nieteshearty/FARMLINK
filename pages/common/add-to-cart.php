<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

// Handle both AJAX and form submissions
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Set content type for AJAX responses
if ($isAjax) {
    header('Content-Type: application/json');
}

// Require buyer role
try {
    $user = SessionManager::requireRole('buyer');
} catch (Exception $e) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    } else {
        header('Location: ../../auth/login.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = $_POST['product_id'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 1);
    
    // Validate input
    if (!$productId || $quantity <= 0) {
        $error = "Invalid product or quantity.";
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        } else {
            $_SESSION['error'] = $error;
            $redirect = $_SERVER['HTTP_REFERER'] ?? '../buyer/buyer-market.php';
            header('Location: ' . $redirect);
            exit;
        }
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if product exists and has enough quantity
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            $error = "Product not found.";
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            } else {
                $_SESSION['error'] = $error;
                $redirect = $_SERVER['HTTP_REFERER'] ?? '../buyer/buyer-market.php';
                header('Location: ' . $redirect);
                exit;
            }
        }
        
        // Check if product is expired
        if ($product['expires_at']) {
            $expiresDate = new DateTime($product['expires_at']);
            $now = new DateTime();
            if ($now > $expiresDate) {
                $error = "This product has expired and cannot be added to cart.";
                if ($isAjax) {
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit;
                } else {
                    $_SESSION['error'] = $error;
                    $redirect = $_SERVER['HTTP_REFERER'] ?? '../buyer/buyer-market.php';
                    header('Location: ' . $redirect);
                    exit;
                }
            }
        }
        
        if ($product['quantity'] < $quantity) {
            $error = "Not enough quantity available. Only {$product['quantity']} {$product['unit']} left.";
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            } else {
                $_SESSION['error'] = $error;
                $redirect = $_SERVER['HTTP_REFERER'] ?? '../buyer/buyer-market.php';
                header('Location: ' . $redirect);
                exit;
            }
        }
        
        // Check if item already in cart
        $stmt = $pdo->prepare("SELECT * FROM cart WHERE buyer_id = ? AND product_id = ?");
        $stmt->execute([$user['id'], $productId]);
        $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingItem) {
            // Update quantity
            $newQuantity = $existingItem['quantity'] + $quantity;
            if ($newQuantity > $product['quantity']) {
                $error = "Cannot add more items. Total would exceed available quantity ({$product['quantity']} {$product['unit']}).";
                if ($isAjax) {
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit;
                } else {
                    $_SESSION['error'] = $error;
                    $redirect = $_SERVER['HTTP_REFERER'] ?? '../buyer/buyer-market.php';
                    header('Location: ' . $redirect);
                    exit;
                }
            }
            
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $existingItem['id']]);
            $message = "Updated {$product['name']} quantity in cart (now {$newQuantity} {$product['unit']})";
        } else {
            // Add new item to cart
            $stmt = $pdo->prepare("INSERT INTO cart (buyer_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $productId, $quantity]);
            $message = "Added {$quantity} {$product['unit']} of {$product['name']} to cart";
        }
        
        // Get updated cart count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE buyer_id = ?");
        $stmt->execute([$user['id']]);
        $cartCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Log activity
        SessionManager::logActivity($user['id'], 'cart', $message);
        
        if ($isAjax) {
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'cart_count' => $cartCount
            ]);
            exit;
        } else {
            $_SESSION['success'] = $message;
        }
        
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Add to cart error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        
        $error = "Failed to add product to cart: " . $e->getMessage();
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $error, 'debug' => $e->getTraceAsString()]);
            exit;
        } else {
            $_SESSION['error'] = $error;
        }
    }
}

// Redirect for non-AJAX requests
if (!$isAjax) {
    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? '../buyer/buyer-cart.php';
    header('Location: ' . $redirectUrl);
    exit;
}
?>
