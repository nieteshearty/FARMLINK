<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require buyer role
$user = SessionManager::requireRole('buyer');

// Handle cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getDBConnection();
        
        if ($action === 'update_quantity') {
            $productId = $_POST['product_id'];
            $quantity = $_POST['quantity'];
            
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE buyer_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $user['id'], $productId]);
            
            $_SESSION['success'] = "Cart updated successfully!";
            
        } elseif ($action === 'remove_item') {
            $productId = $_POST['product_id'];
            
            $stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ? AND product_id = ?");
            $stmt->execute([$user['id'], $productId]);
            
            $_SESSION['success'] = "Item removed from cart!";
            
            // Redirect to the cart page
            header('Location: buyer-cart.php');
            exit;
            
        } elseif ($action === 'place_order') {
            // Get cart items
            $cartItems = DatabaseHelper::getCart($user['id']);
            
            if (empty($cartItems)) {
                $_SESSION['error'] = "Your cart is empty!";
            } else {
                // Get delivery details from form
                $deliveryAddress = $_POST['delivery_address'] ?? 'Sitio, Kapusoy, Larrazabal, Larrazabal, Naval, Visayas, Biliran 6560';
                $deliveryPhone = $_POST['delivery_phone'] ?? '+63 963 718 9463';
                $deliveryInstructions = $_POST['delivery_instructions'] ?? '';
                $deliveryCoordinates = $_POST['delivery_coordinates'] ?? '11.5564,124.3992';
                $paymentMethod = $_POST['payment_method'] ?? 'cod';
                $shippingMethod = $_POST['shipping_method'] ?? 'fast';
                $deliveryFee = floatval($_POST['delivery_fee'] ?? 60);
                $voucherCode = $_POST['voucher_code'] ?? '';
                $voucherDiscount = floatval($_POST['voucher_discount'] ?? 0);
                
                // Group items by farmer
                $ordersByFarmer = [];
                foreach ($cartItems as $item) {
                    $farmerId = $item['farmer_id'] ?? 0;
                    if (!isset($ordersByFarmer[$farmerId])) {
                        $ordersByFarmer[$farmerId] = [];
                    }
                    $ordersByFarmer[$farmerId][] = $item;
                }
                
                $pdo->beginTransaction();
                
                try {
                    foreach ($ordersByFarmer as $farmerId => $items) {
                        $subtotal = array_sum(array_map(function($item) {
                            return $item['price'] * $item['quantity'];
                        }, $items));
                        
                        // Calculate total with delivery fee and voucher discount
                        $total = $subtotal + $deliveryFee - $voucherDiscount;
                        
                        // Create order with delivery details
                        $stmt = $pdo->prepare("
                            INSERT INTO orders (
                                buyer_id, farmer_id, total, 
                                delivery_address, delivery_phone, delivery_instructions, 
                                delivery_coordinates, delivery_fee, 
                                payment_method, shipping_method,
                                voucher_code, voucher_discount,
                                estimated_delivery
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 DAY))
                        ");
                        $stmt->execute([
                            $user['id'], $farmerId, $total,
                            $deliveryAddress, $deliveryPhone, $deliveryInstructions,
                            $deliveryCoordinates, $deliveryFee,
                            $paymentMethod, $shippingMethod,
                            $voucherCode, $voucherDiscount
                        ]);
                        $orderId = $pdo->lastInsertId();
                        
                        // Add order items
                        foreach ($items as $item) {
                            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
                            
                            // Update product quantity
                            $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                            $stmt->execute([$item['quantity'], $item['product_id']]);
                        }
                    }
                    
                    // Clear cart
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ?");
                    $stmt->execute([$user['id']]);
                    
                    $pdo->commit();
                    
                    // Log activity
                    SessionManager::logActivity($user['id'], 'order', 'Placed new order');
                    
                    $_SESSION['success'] = "Order placed successfully!";
                    $_SESSION['new_order_placed'] = true;
                    $_SESSION['new_order_time'] = time();
                    header('Location: buyer-orders.php');
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $_SESSION['error'] = "Failed to place order. Please try again.";
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred. Please try again.";
    }
    
    header('Location: buyer-cart.php');
    exit;
}

// Get cart items
$cartItems = DatabaseHelper::getCart($user['id']);
$subtotal = 0;
$totalItems = 0;

foreach ($cartItems as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    $subtotal += $itemTotal;
    $totalItems += $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • Shopping Cart</title>
  <link rel="icon" type="image/png" href="/FARMLINK/assets/img/farmlink.png">
  <link rel="stylesheet" href="/FARMLINK/style.css">
  <link rel="stylesheet" href="/FARMLINK/assets/css/buyer.css">
  <link rel="stylesheet" href="/FARMLINK/assets/css/logout-confirmation.css">
</head>
<body data-page="buyer-cart">
  <nav>
    <div class="nav-left">
      <a href="buyer-dashboard.php"><img src="/FARMLINK/assets/img/farmlink.png" alt="FARMLINK Logo" class="nav-logo"></a>
      <span class="brand">FARMLINK</span>
    </div>
    <span>Shopping Cart</span>
  </nav>

  <div class="sidebar">
    <a href="buyer-dashboard.php">Dashboard</a>
    <a href="buyer-market.php">Browse Market</a>
    <a href="buyer-cart.php" class="active">Shopping Cart</a>
    <a href="buyer-orders.php">My Orders</a>
    <a href="buyer-profile.php">Profile</a>
    <a href="/FARMLINK/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>Shopping Cart</h1>
    <p class="lead">Review your selected items before placing your order.</p>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section id="cartContent">
      <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
          <div class="empty-cart-icon">🛒</div>
          <h3>Your cart is empty</h3>
          <p>Browse our market to add some fresh farm products!</p>
          <button class="btn btn-primary" onclick="location.href='buyer-market.php'">Browse Products</button>
        </div>
      <?php else: ?>
        <!-- Delivery Address Section -->
        <div class="delivery-address-section">
          <div class="address-header">
            <div class="address-icon">📍</div>
            <div class="address-title">Delivery Address</div>
          </div>
          <div class="address-content">
            <div class="address-info">
              <div class="recipient-name"><?= htmlspecialchars($user['username']) ?></div>
              <div class="recipient-phone">(+63) 963 718 9463</div>
              <div class="recipient-address" id="deliveryAddress">
                Sitio, Kapusoy, Larrazabal, Larrazabal, Naval, Visayas, Biliran 6560
              </div>
              <span class="address-badge">Default</span>
            </div>
            <div class="address-actions">
              <button class="btn-change" onclick="changeAddress()">Change</button>
            </div>
          </div>
        </div>

        <!-- Cart Header with Select All -->
        <div class="cart-header">
          <div class="select-all-section">
            <label class="checkbox-container">
              <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
              <span class="checkmark"></span>
              Select All (<?= count($cartItems) ?> items)
            </label>
          </div>
          <div class="bulk-actions">
            <button class="btn-text" onclick="deleteSelected()" id="deleteSelectedBtn" style="display:none;">Delete Selected</button>
          </div>
        </div>

        <!-- Cart Items -->
        <div class="cart-items-container">
              <?php foreach ($cartItems as $index => $item): ?>
                <?php $itemTotal = $item['price'] * $item['quantity']; ?>
                <div class="cart-item" data-product-id="<?= $item['product_id'] ?>">
                  <div class="item-checkbox">
                    <label class="checkbox-container">
                      <input type="checkbox" class="item-select" value="<?= $item['product_id'] ?>" onchange="updateSelectAll()">
                      <span class="checkmark"></span>
                    </label>
                  </div>
                  
                  <div class="item-image">
                      <?php 
                      // Robust image path handling
                      $imagePath = '';
                      
                      if (!empty($item['image'])) {
                          $imageValue = trim($item['image']);
                          
                          // Handle different path formats stored in database
                          if (strpos($imageValue, 'http') === 0) {
                              // Full URL - use as is
                              $imagePath = $imageValue;
                          } elseif (strpos($imageValue, '/FARMLINK/') === 0) {
                              // Already has /FARMLINK/ prefix - use as is
                              $imagePath = $imageValue;
                          } elseif (strpos($imageValue, 'uploads/products/') === 0) {
                              // Relative path starting with uploads/products/
                              $imagePath = '/FARMLINK/' . $imageValue;
                          } elseif (strpos($imageValue, '/') === 0) {
                              // Starts with / but no FARMLINK prefix
                              $imagePath = '/FARMLINK' . $imageValue;
                          } else {
                              // Just filename - add full path
                              $imagePath = '/FARMLINK/uploads/products/' . basename($imageValue);
                          }
                      }
                      ?>
                    <?php if (!empty($imagePath)): ?>
                      <img src="<?= htmlspecialchars($imagePath) ?>" 
                           alt="<?= htmlspecialchars($item['name']) ?>" 
                           class="product-image"
                           onerror="this.onerror=null; this.src='/FARMLINK/assets/img/placeholder.png';">
                    <?php else: ?>
                      <div class="product-image-placeholder">📷</div>
                    <?php endif; ?>
                  </div>
                  
                  <div class="item-details">
                    <div class="product-info">
                      <h4 class="product-name"><?= htmlspecialchars($item['name']) ?></h4>
                      <p class="product-category"><?= htmlspecialchars($item['category'] ?? '') ?></p>
                      <p class="farmer-name">by <?= htmlspecialchars($item['farmer_name']) ?></p>
                    </div>
                    
                    <div class="item-controls">
                      <div class="price-section">
                        <span class="price">₱<?= number_format($item['price'], 2) ?></span>
                      </div>
                      
                      <div class="quantity-controls">
                        <button type="button" class="qty-btn" onclick="updateQuantity(<?= $item['product_id'] ?>, -1, <?= $item['quantity'] ?>)">-</button>
                        <input type="number" class="qty-input" value="<?= $item['quantity'] ?>" min="1" 
                               id="qty-<?= $item['product_id'] ?>" 
                               onchange="updateQuantityDirect(<?= $item['product_id'] ?>, this.value)">
                        <button type="button" class="qty-btn" onclick="updateQuantity(<?= $item['product_id'] ?>, 1, <?= $item['quantity'] ?>)">+</button>
                      </div>
                      
                      <div class="item-total">
                        <span class="total-price" id="total-<?= $item['product_id'] ?>">₱<?= number_format($itemTotal, 2) ?></span>
                      </div>
                      
                      <div class="item-actions">
                        <button type="button" class="btn-remove" onclick="removeItem(<?= $item['product_id'] ?>)" title="Remove item">
                          🗑️
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
        </div>

        <!-- Shipping Options Section -->
        <div class="shipping-section">
          <div class="shipping-header">
            <div class="shipping-title">Shipping Option:</div>
            <div class="shipping-method">
              <span class="method-name">FARMLINK Fast Delivery</span>
              <button class="btn-change-shipping" onclick="changeShipping()">Change</button>
            </div>
          </div>
          <div class="shipping-details">
            <div class="delivery-time">Get by 7 - 16 Oct</div>
            <div class="shipping-fee">₱60</div>
          </div>
        </div>

        <!-- Voucher Section -->
        <div class="voucher-section">
          <div class="voucher-header">
            <div class="voucher-icon">🎫</div>
            <div class="voucher-title">Farm Voucher</div>
          </div>
          <div class="voucher-content">
            <button class="btn-select-voucher" onclick="selectVoucher()">Select Voucher</button>
          </div>
        </div>

        <!-- Message Section -->
        <div class="message-section">
          <div class="message-header">
            <div class="message-title">Message for Farmers:</div>
          </div>
          <div class="message-content">
            <textarea class="message-input" placeholder="Please leave a message..." maxlength="200"></textarea>
          </div>
        </div>

        <!-- Sticky Bottom Bar -->
        <div class="cart-bottom-bar">
          <div class="bottom-left">
            <label class="checkbox-container">
              <input type="checkbox" id="selectAllBottom" onchange="toggleSelectAll()">
              <span class="checkmark"></span>
              Select All
            </label>
            <button class="btn-text" onclick="deleteSelected()" id="deleteSelectedBtnBottom" style="display:none;">Delete</button>
          </div>
          
          <div class="bottom-right">
            <div class="summary-info">
              <span class="selected-count">Total (<span id="selectedCount">0</span> items): </span>
              <span class="total-amount" id="totalAmount">₱0.00</span>
            </div>
            <button type="button" class="btn btn-primary btn-checkout" onclick="proceedToCheckout()" disabled id="checkoutBtn">
              Checkout
            </button>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <style>
    /* Empty Cart Styles */
    .empty-cart {
      text-align: center;
      padding: 60px 20px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .empty-cart-icon {
      font-size: 64px;
      margin-bottom: 20px;
      opacity: 0.5;
    }
    
    /* Delivery Address Section */
    .delivery-address-section {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .address-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 12px;
    }
    
    .address-icon {
      font-size: 18px;
      color: #2d6a4f;
    }
    
    .address-title {
      font-size: 16px;
      font-weight: 600;
      color: #2d6a4f;
    }
    
    .address-content {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
    }
    
    .address-info {
      flex: 1;
    }
    
    .recipient-name {
      font-size: 16px;
      font-weight: 600;
      color: #333;
      margin-bottom: 4px;
    }
    
    .recipient-phone {
      font-size: 14px;
      color: #666;
      margin-bottom: 8px;
    }
    
    .recipient-address {
      font-size: 14px;
      color: #666;
      line-height: 1.4;
      margin-bottom: 8px;
    }
    
    .address-badge {
      display: inline-block;
      background: #e8f5e8;
      color: #2d6a4f;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .btn-change {
      background: none;
      border: 1px solid #2d6a4f;
      color: #2d6a4f;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s;
    }
    
    .btn-change:hover {
      background: #2d6a4f;
      color: white;
    }
    
    /* Shipping Section */
    .shipping-section {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .shipping-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    
    .shipping-title {
      font-size: 16px;
      font-weight: 600;
      color: #333;
    }
    
    .shipping-method {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .method-name {
      font-size: 14px;
      color: #2d6a4f;
      font-weight: 500;
    }
    
    .btn-change-shipping {
      background: none;
      border: none;
      color: #2d6a4f;
      cursor: pointer;
      font-size: 14px;
      text-decoration: underline;
    }
    
    .shipping-details {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .delivery-time {
      font-size: 14px;
      color: #666;
    }
    
    .shipping-fee {
      font-size: 16px;
      font-weight: 600;
      color: #2d6a4f;
    }
    
    /* Voucher Section */
    .voucher-section {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .voucher-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 12px;
    }
    
    .voucher-icon {
      font-size: 18px;
    }
    
    .voucher-title {
      font-size: 16px;
      font-weight: 600;
      color: #333;
    }
    
    .btn-select-voucher {
      background: none;
      border: 1px dashed #2d6a4f;
      color: #2d6a4f;
      padding: 12px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      width: 100%;
      transition: all 0.2s;
    }
    
    .btn-select-voucher:hover {
      background: #f8f9fa;
    }
    
    /* Message Section */
    .message-section {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .message-header {
      margin-bottom: 12px;
    }
    
    .message-title {
      font-size: 16px;
      font-weight: 600;
      color: #333;
    }
    
    .message-input {
      width: 100%;
      min-height: 80px;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      font-family: inherit;
      resize: vertical;
      outline: none;
    }
    
    .message-input:focus {
      border-color: #2d6a4f;
    }
    
    .message-input::placeholder {
      color: #999;
    }
    
    /* Modal Styles */
    .modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 10000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .modal-content {
      background: white;
      border-radius: 12px;
      max-width: 600px;
      width: 100%;
      max-height: 80vh;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px;
      border-bottom: 1px solid #eee;
    }
    
    .modal-header h3 {
      margin: 0;
      font-size: 18px;
      font-weight: 600;
      color: #333;
    }
    
    .modal-close {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #666;
      padding: 0;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.2s;
    }
    
    .modal-close:hover {
      background: #f5f5f5;
      color: #333;
    }
    
    .modal-body {
      padding: 20px;
      max-height: 60vh;
      overflow-y: auto;
    }
    
    /* Address Actions Header */
    .address-actions-header {
      margin-bottom: 20px;
    }
    
    .btn-add-address {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #2d6a4f;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
    }
    
    .btn-add-address:hover {
      background: #1e4a36;
    }
    
    .add-icon {
      font-size: 16px;
      font-weight: bold;
    }
    
    /* Address Items */
    .saved-addresses {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    
    .address-item {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 16px;
      border: 1px solid #eee;
      border-radius: 8px;
      transition: all 0.2s;
    }
    
    .address-item:hover {
      border-color: #2d6a4f;
      box-shadow: 0 2px 8px rgba(45, 106, 79, 0.1);
    }
    
    .address-item.address-default {
      border-color: #2d6a4f;
      background: #f8fdf9;
    }
    
    .address-item-content {
      flex: 1;
    }
    
    .address-item-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 8px;
      flex-wrap: wrap;
    }
    
    .address-name {
      font-weight: 600;
      color: #333;
    }
    
    .address-phone {
      color: #666;
      font-size: 14px;
    }
    
    .default-badge {
      background: #2d6a4f;
      color: white;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .address-item-address {
      color: #666;
      font-size: 14px;
      line-height: 1.4;
    }
    
    .address-item-actions {
      margin-left: 16px;
    }
    
    .btn-select-address {
      background: #2d6a4f;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s;
    }
    
    .btn-select-address:hover {
      background: #1e4a36;
    }
    
    .btn-select-address.selected {
      background: #ccc;
      color: #666;
      cursor: not-allowed;
    }
    
    /* New Address Form */
    .form-header {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid #eee;
    }
    
    .btn-back {
      background: none;
      border: none;
      color: #2d6a4f;
      cursor: pointer;
      font-size: 14px;
      padding: 8px 0;
    }
    
    .btn-back:hover {
      text-decoration: underline;
    }
    
    .form-header h4 {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
      color: #333;
    }
    
    .form-group {
      margin-bottom: 16px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 500;
      color: #333;
      font-size: 14px;
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      transition: border-color 0.2s;
    }
    
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
      border-color: #2d6a4f;
    }
    
    .form-group textarea {
      resize: vertical;
      min-height: 80px;
    }
    
    .form-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px solid #eee;
    }
    
    .btn-cancel {
      background: none;
      border: 1px solid #ddd;
      color: #666;
      padding: 12px 24px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s;
    }
    
    .btn-cancel:hover {
      border-color: #999;
      color: #333;
    }
    
    .btn-save {
      background: #2d6a4f;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
    }
    
    .btn-save:hover {
      background: #1e4a36;
    }
    
    /* Notification Styles */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 16px 20px;
      border-radius: 8px;
      color: white;
      font-weight: 500;
      z-index: 10001;
      transform: translateX(100%);
      transition: transform 0.3s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    .notification.show {
      transform: translateX(0);
    }
    
    .notification-success {
      background: #28a745;
    }
    
    .notification-error {
      background: #dc3545;
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
      .modal {
        padding: 10px;
      }
      
      .modal-content {
        max-height: 90vh;
      }
      
      .address-item {
        flex-direction: column;
        gap: 12px;
      }
      
      .address-item-actions {
        margin-left: 0;
        align-self: stretch;
      }
      
      .btn-select-address {
        width: 100%;
      }
      
      .form-actions {
        flex-direction: column;
      }
      
      .btn-cancel,
      .btn-save {
        width: 100%;
      }
    }
    
    /* Shipping Options Styles */
    .shipping-options {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-bottom: 20px;
    }
    
    .shipping-option {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 20px;
      border: 2px solid #eee;
      border-radius: 12px;
      transition: all 0.2s;
      cursor: pointer;
    }
    
    .shipping-option:hover {
      border-color: #2d6a4f;
      box-shadow: 0 4px 12px rgba(45, 106, 79, 0.1);
    }
    
    .shipping-option.selected {
      border-color: #2d6a4f;
      background: #f8fdf9;
      box-shadow: 0 4px 12px rgba(45, 106, 79, 0.15);
    }
    
    .shipping-option-content {
      flex: 1;
    }
    
    .shipping-option-header {
      display: flex;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 12px;
    }
    
    .shipping-icon {
      font-size: 24px;
      flex-shrink: 0;
    }
    
    .shipping-info {
      flex: 1;
    }
    
    .shipping-name {
      font-size: 16px;
      font-weight: 600;
      color: #333;
      margin-bottom: 4px;
    }
    
    .shipping-description {
      font-size: 14px;
      color: #666;
    }
    
    .shipping-price {
      font-size: 18px;
      font-weight: 600;
      color: #2d6a4f;
      flex-shrink: 0;
    }
    
    .shipping-details {
      margin-left: 40px;
    }
    
    .delivery-estimate {
      font-size: 14px;
      color: #666;
      margin-bottom: 8px;
    }
    
    .shipping-features {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    
    .feature-tag {
      background: #e8f5e8;
      color: #2d6a4f;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .shipping-option-actions {
      margin-left: 16px;
      flex-shrink: 0;
    }
    
    .btn-select-shipping {
      background: #2d6a4f;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
      min-width: 80px;
    }
    
    .btn-select-shipping:hover {
      background: #1e4a36;
    }
    
    .btn-select-shipping.selected {
      background: #ccc;
      color: #666;
      cursor: not-allowed;
    }
    
    .shipping-note {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 16px;
      background: #fff3cd;
      border: 1px solid #ffeaa7;
      border-radius: 8px;
      margin-top: 20px;
    }
    
    .note-icon {
      font-size: 18px;
      flex-shrink: 0;
    }
    
    .note-text {
      font-size: 14px;
      color: #856404;
      line-height: 1.4;
    }
    
    /* Mobile Responsive for Shipping */
    @media (max-width: 768px) {
      .shipping-option {
        flex-direction: column;
        gap: 16px;
      }
      
      .shipping-option-header {
        flex-direction: column;
        gap: 12px;
      }
      
      .shipping-info {
        order: 1;
      }
      
      .shipping-price {
        order: 2;
        align-self: flex-start;
      }
      
      .shipping-icon {
        order: 0;
        align-self: center;
      }
      
      .shipping-details {
        margin-left: 0;
      }
      
      .shipping-option-actions {
        margin-left: 0;
        align-self: stretch;
      }
      
      .btn-select-shipping {
        width: 100%;
      }
      
      .shipping-features {
        justify-content: flex-start;
      }
    }
    
    /* Voucher Modal Styles */
    .voucher-tabs {
      display: flex;
      border-bottom: 1px solid #eee;
      margin-bottom: 20px;
    }
    
    .voucher-tab {
      flex: 1;
      padding: 12px 20px;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      color: #666;
      transition: all 0.2s;
      border-bottom: 2px solid transparent;
    }
    
    .voucher-tab.active {
      color: #2d6a4f;
      border-bottom-color: #2d6a4f;
    }
    
    .voucher-tab:hover {
      color: #2d6a4f;
      background: #f8f9fa;
    }
    
    .voucher-content-modal {
      position: relative;
    }
    
    .voucher-tab-content {
      display: none;
    }
    
    .voucher-tab-content.active {
      display: block;
    }
    
    .voucher-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
      max-height: 400px;
      overflow-y: auto;
      padding-right: 8px;
    }
    
    .voucher-item {
      display: flex;
      justify-content: space-between;
      align-items: stretch;
      border: 2px solid #eee;
      border-radius: 12px;
      overflow: hidden;
      transition: all 0.2s;
    }
    
    .voucher-item:hover {
      border-color: #2d6a4f;
      box-shadow: 0 4px 12px rgba(45, 106, 79, 0.1);
    }
    
    .voucher-item.selected {
      border-color: #2d6a4f;
      background: #f8fdf9;
    }
    
    .voucher-item.used {
      opacity: 0.6;
      border-color: #ddd;
    }
    
    .voucher-card {
      flex: 1;
      padding: 20px;
      background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
      position: relative;
    }
    
    .voucher-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    
    .voucher-icon {
      font-size: 24px;
    }
    
    .voucher-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .voucher-badge.new-user {
      background: #e3f2fd;
      color: #1976d2;
    }
    
    .voucher-badge.shipping {
      background: #e8f5e8;
      color: #2d6a4f;
    }
    
    .voucher-badge.bulk {
      background: #fff3e0;
      color: #f57c00;
    }
    
    .voucher-badge.seasonal {
      background: #f3e5f5;
      color: #7b1fa2;
    }
    
    .voucher-badge.loyalty {
      background: #fff8e1;
      color: #f9a825;
    }
    
    .voucher-badge.used {
      background: #f5f5f5;
      color: #666;
    }
    
    .voucher-details {
      margin-bottom: 12px;
    }
    
    .voucher-title {
      font-size: 16px;
      font-weight: 600;
      color: #333;
      margin-bottom: 4px;
    }
    
    .voucher-description {
      font-size: 14px;
      color: #666;
      margin-bottom: 8px;
    }
    
    .voucher-code-display {
      font-size: 12px;
      color: #2d6a4f;
      font-weight: 600;
      background: #e8f5e8;
      padding: 4px 8px;
      border-radius: 4px;
      display: inline-block;
      margin-bottom: 8px;
    }
    
    .voucher-conditions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    
    .condition {
      font-size: 12px;
      color: #666;
      background: #f8f9fa;
      padding: 2px 6px;
      border-radius: 3px;
    }
    
    .voucher-discount-badge {
      position: absolute;
      top: 15px;
      right: 15px;
      background: #2d6a4f;
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      transform: rotate(15deg);
    }
    
    .voucher-actions {
      display: flex;
      align-items: center;
      padding: 20px;
      background: #f8f9fa;
      border-left: 1px solid #eee;
    }
    
    .btn-apply-voucher {
      background: #2d6a4f;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
      min-width: 80px;
    }
    
    .btn-apply-voucher:hover {
      background: #1e4a36;
    }
    
    .btn-apply-voucher:disabled {
      background: #ccc;
      color: #666;
      cursor: not-allowed;
    }
    
    /* Applied Voucher Styles */
    .applied-voucher {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 16px;
      background: #e8f5e8;
      border: 1px solid #2d6a4f;
      border-radius: 6px;
    }
    
    .voucher-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .voucher-code {
      font-weight: 600;
      color: #2d6a4f;
    }
    
    .voucher-discount {
      background: #2d6a4f;
      color: white;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .btn-remove-voucher {
      background: none;
      border: none;
      color: #666;
      cursor: pointer;
      font-size: 18px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .btn-remove-voucher:hover {
      background: #fff;
      color: #333;
    }
    
    /* Mobile Responsive for Vouchers */
    @media (max-width: 768px) {
      .voucher-item {
        flex-direction: column;
      }
      
      .voucher-actions {
        border-left: none;
        border-top: 1px solid #eee;
        padding: 16px 20px;
      }
      
      .btn-apply-voucher {
        width: 100%;
      }
      
      .voucher-discount-badge {
        position: static;
        transform: none;
        margin-top: 8px;
        align-self: flex-start;
      }
      
      .voucher-conditions {
        flex-direction: column;
        gap: 4px;
      }
    }
    
    /* Cart Header */
    .cart-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 20px;
      background: white;
      border-radius: 12px;
      margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    /* Checkbox Styles */
    .checkbox-container {
      display: flex;
      align-items: center;
      cursor: pointer;
      font-size: 14px;
      user-select: none;
      gap: 8px;
    }
    
    .checkbox-container input {
      display: none;
    }
    
    .checkmark {
      width: 18px;
      height: 18px;
      border: 2px solid #ddd;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .checkbox-container input:checked + .checkmark {
      background-color: #2d6a4f;
      border-color: #2d6a4f;
    }
    
    .checkbox-container input:checked + .checkmark:after {
      content: '✓';
      color: white;
      font-size: 12px;
      font-weight: bold;
    }
    
    /* Cart Items Container */
    .cart-items-container {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 80px;
    }
    
    /* Individual Cart Item */
    .cart-item {
      display: flex;
      align-items: center;
      padding: 20px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: all 0.2s;
      gap: 16px;
    }
    
    .cart-item:hover {
      box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    .item-checkbox {
      flex-shrink: 0;
    }
    
    .item-image {
      flex-shrink: 0;
    }
    
    .product-image {
      width: 80px;
      height: 80px;
      border-radius: 8px;
      object-fit: cover;
      border: 1px solid #eee;
    }
    
    .product-image-placeholder {
      width: 80px;
      height: 80px;
      border-radius: 8px;
      border: 1px solid #eee;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8f9fa;
      font-size: 24px;
    }
    
    .item-details {
      flex: 1;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .product-info {
      flex: 1;
    }
    
    .product-name {
      font-size: 16px;
      font-weight: 600;
      margin: 0 0 4px 0;
      color: #333;
    }
    
    .product-category {
      font-size: 12px;
      color: #666;
      margin: 0 0 4px 0;
    }
    
    .farmer-name {
      font-size: 12px;
      color: #2d6a4f;
      margin: 0;
    }
    
    .item-controls {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    
    .price-section .price {
      font-size: 16px;
      font-weight: 600;
      color: #2d6a4f;
    }
    
    /* Quantity Controls */
    .quantity-controls {
      display: flex;
      align-items: center;
      border: 1px solid #ddd;
      border-radius: 6px;
      overflow: hidden;
    }
    
    .qty-btn {
      width: 32px;
      height: 32px;
      border: none;
      background: #f8f9fa;
      cursor: pointer;
      font-size: 16px;
      font-weight: bold;
      transition: all 0.2s;
    }
    
    .qty-btn:hover {
      background: #e9ecef;
    }
    
    .qty-input {
      width: 50px;
      height: 32px;
      border: none;
      text-align: center;
      font-size: 14px;
      outline: none;
    }
    
    .item-total .total-price {
      font-size: 16px;
      font-weight: 600;
      color: #333;
      min-width: 80px;
      text-align: right;
    }
    
    .btn-remove {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 18px;
      padding: 8px;
      border-radius: 6px;
      transition: all 0.2s;
    }
    
    .btn-remove:hover {
      background: #fee;
    }
    
    /* Bottom Bar */
    .cart-bottom-bar {
      position: fixed;
      bottom: 0;
      left: 200px;
      right: 0;
      background: white;
      border-top: 1px solid #eee;
      padding: 16px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
      z-index: 1000;
    }
    
    .bottom-left {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    
    .bottom-right {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    
    .summary-info {
      text-align: right;
    }
    
    .selected-count {
      font-size: 14px;
      color: #666;
    }
    
    .total-amount {
      font-size: 18px;
      font-weight: 600;
      color: #2d6a4f;
    }
    
    .btn-checkout {
      min-width: 120px;
      height: 44px;
      font-size: 16px;
      font-weight: 600;
    }
    
    .btn-checkout:disabled {
      background: #ccc;
      cursor: not-allowed;
    }
    
    .btn-text {
      background: none;
      border: none;
      color: #2d6a4f;
      cursor: pointer;
      font-size: 14px;
      text-decoration: underline;
    }
    
    .btn-text:hover {
      color: #1e4a36;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .cart-bottom-bar {
        left: 0;
        padding: 12px 16px;
      }
      
      .cart-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
      
      .item-details {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
      
      .item-controls {
        width: 100%;
        justify-content: space-between;
      }
    }
    
    .alert {
      padding: 12px;
      margin: 16px 0;
      border-radius: 8px;
    }
    
    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
      align-items: center;
      justify-content: center;
      background: #f5f5f5;
      font-size: 24px;
    }
    
    .btn-danger {
      background-color: #dc3545;
      color: white;
    }
    
    .btn-danger:hover {
      background-color: #c82333;
    }

    .nav-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .nav-logo {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      background: white;
      border: 2px solid #4CAF50;
    }
  </style>

  <script>
    let selectedItems = new Set();
    let cartData = <?= json_encode(array_column($cartItems, null, 'product_id')) ?>;
    
    function toggleSelectAll() {
      const selectAll = document.getElementById('selectAll');
      const selectAllBottom = document.getElementById('selectAllBottom');
      const itemCheckboxes = document.querySelectorAll('.item-select');
      
      // Sync both select all checkboxes
      selectAll.checked = selectAllBottom.checked = selectAll.checked || selectAllBottom.checked;
      
      itemCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
        if (selectAll.checked) {
          selectedItems.add(parseInt(checkbox.value));
        } else {
          selectedItems.delete(parseInt(checkbox.value));
        }
      });
      
      updateUI();
    }
    
    function updateSelectAll() {
      const itemCheckboxes = document.querySelectorAll('.item-select');
      const checkedBoxes = document.querySelectorAll('.item-select:checked');
      const selectAll = document.getElementById('selectAll');
      const selectAllBottom = document.getElementById('selectAllBottom');
      
      selectAll.checked = selectAllBottom.checked = itemCheckboxes.length === checkedBoxes.length;
      
      // Update selected items set
      selectedItems.clear();
      checkedBoxes.forEach(checkbox => {
        selectedItems.add(parseInt(checkbox.value));
      });
      
      updateUI();
    }
    
    function updateUI() {
      const deleteBtn = document.getElementById('deleteSelectedBtn');
      const deleteBtnBottom = document.getElementById('deleteSelectedBtnBottom');
      const checkoutBtn = document.getElementById('checkoutBtn');
      const selectedCount = document.getElementById('selectedCount');
      const totalAmount = document.getElementById('totalAmount');
      
      const hasSelected = selectedItems.size > 0;
      
      // Show/hide delete buttons
      deleteBtn.style.display = hasSelected ? 'inline' : 'none';
      deleteBtnBottom.style.display = hasSelected ? 'inline' : 'none';
      
      // Enable/disable checkout
      checkoutBtn.disabled = !hasSelected;
      
      // Update counts and total
      selectedCount.textContent = selectedItems.size;
      
      let total = 0;
      selectedItems.forEach(productId => {
        if (cartData[productId]) {
          const item = cartData[productId];
          const quantity = parseInt(document.getElementById(`qty-${productId}`).value);
          total += item.price * quantity;
        }
      });
      
      totalAmount.textContent = `₱${total.toFixed(2)}`;
    }
    
    function updateQuantity(productId, change, currentQty) {
      const newQty = Math.max(1, currentQty + change);
      const qtyInput = document.getElementById(`qty-${productId}`);
      qtyInput.value = newQty;
      updateQuantityDirect(productId, newQty);
    }
    
    function updateQuantityDirect(productId, quantity) {
      quantity = Math.max(1, parseInt(quantity));
      
      // Update display
      const qtyInput = document.getElementById(`qty-${productId}`);
      const totalElement = document.getElementById(`total-${productId}`);
      
      qtyInput.value = quantity;
      
      if (cartData[productId]) {
        const itemTotal = cartData[productId].price * quantity;
        totalElement.textContent = `₱${itemTotal.toFixed(2)}`;
      }
      
      // Update cart data
      if (cartData[productId]) {
        cartData[productId].quantity = quantity;
      }
      
      updateUI();
      
      // Send update to server
      const formData = new FormData();
      formData.append('action', 'update_quantity');
      formData.append('product_id', productId);
      formData.append('quantity', quantity);
      
      fetch('buyer-cart.php', {
        method: 'POST',
        body: formData
      }).catch(error => console.error('Error updating quantity:', error));
    }
    
    function removeItem(productId) {
      if (confirm('Remove this item from cart?')) {
        const formData = new FormData();
        formData.append('action', 'remove_item');
        formData.append('product_id', productId);
        
        fetch('buyer-cart.php', {
          method: 'POST',
          body: formData
        }).then(() => {
          location.reload();
        }).catch(error => console.error('Error removing item:', error));
      }
    }
    
    function deleteSelected() {
      if (selectedItems.size === 0) return;
      
      if (confirm(`Remove ${selectedItems.size} selected item(s) from cart?`)) {
        const promises = Array.from(selectedItems).map(productId => {
          const formData = new FormData();
          formData.append('action', 'remove_item');
          formData.append('product_id', productId);
          
          return fetch('buyer-cart.php', {
            method: 'POST',
            body: formData
          });
        });
        
        Promise.all(promises).then(() => {
          location.reload();
        }).catch(error => console.error('Error removing items:', error));
      }
    }
    
    function proceedToCheckout() {
      if (selectedItems.size === 0) {
        alert('Please select items to checkout');
        return;
      }
      
      if (confirm(`Proceed to checkout with ${selectedItems.size} item(s)?`)) {
        // Collect delivery details from the cart form
        const deliveryAddress = document.getElementById('deliveryAddress').textContent.trim();
        const recipientName = document.querySelector('.recipient-name').textContent.trim();
        const recipientPhone = document.querySelector('.recipient-phone').textContent.trim();
        const messageText = document.querySelector('.message-input').value.trim();
        
        // Get shipping details
        const shippingMethod = document.querySelector('.method-name').textContent.trim();
        const shippingFee = document.querySelector('.shipping-fee').textContent.replace('₱', '').trim();
        
        // Get applied voucher details
        const appliedVoucher = document.querySelector('.applied-voucher');
        let voucherCode = '';
        let voucherDiscount = 0;
        if (appliedVoucher) {
          voucherCode = appliedVoucher.querySelector('.voucher-code').textContent.trim();
          const discountText = appliedVoucher.querySelector('.voucher-discount').textContent;
          if (discountText.includes('%')) {
            voucherDiscount = parseFloat(discountText.replace('%', '').replace('OFF', '').trim());
          } else {
            voucherDiscount = parseFloat(discountText.replace('₱', '').replace('OFF', '').trim());
          }
        }
        
        // Create form with all delivery details
        const form = document.createElement('form');
        form.method = 'POST';
        
        // Add action
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'place_order';
        form.appendChild(actionInput);
        
        // Add delivery details
        const deliveryInputs = [
          { name: 'delivery_address', value: deliveryAddress },
          { name: 'delivery_phone', value: recipientPhone },
          { name: 'delivery_instructions', value: messageText },
          { name: 'delivery_coordinates', value: '11.5564,124.3992' },
          { name: 'shipping_method', value: shippingMethod },
          { name: 'delivery_fee', value: shippingFee },
          { name: 'voucher_code', value: voucherCode },
          { name: 'voucher_discount', value: voucherDiscount },
          { name: 'payment_method', value: 'cod' }
        ];
        
        deliveryInputs.forEach(inputData => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = inputData.name;
          input.value = inputData.value;
          form.appendChild(input);
        });
        
        // Add selected items
        selectedItems.forEach(productId => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'selected_items[]';
          input.value = productId;
          form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
      }
    }
    
    // Address and shipping functions
    function changeAddress() {
      document.getElementById('addressModal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
    
    function closeAddressModal() {
      document.getElementById('addressModal').style.display = 'none';
      document.body.style.overflow = 'auto';
    }
    
    function selectAddress(addressData) {
      // Update the delivery address display
      document.getElementById('deliveryAddress').textContent = addressData.fullAddress;
      document.querySelector('.recipient-name').textContent = addressData.name;
      document.querySelector('.recipient-phone').textContent = addressData.phone;
      
      // Close modal
      closeAddressModal();
      
      // Show success message
      showNotification('Delivery address updated successfully!', 'success');
    }
    
    function addNewAddress() {
      document.getElementById('newAddressForm').style.display = 'block';
      document.getElementById('addressList').style.display = 'none';
    }
    
    function showAddressList() {
      document.getElementById('newAddressForm').style.display = 'none';
      document.getElementById('addressList').style.display = 'block';
    }
    
    function saveNewAddress() {
      const form = document.getElementById('newAddressFormElement');
      const formData = new FormData(form);
      
      // Validate required fields
      const name = formData.get('name');
      const phone = formData.get('phone');
      const address = formData.get('address');
      
      if (!name || !phone || !address) {
        showNotification('Please fill in all required fields', 'error');
        return;
      }
      
      // Create new address object
      const newAddress = {
        name: name,
        phone: phone,
        fullAddress: address,
        isDefault: false
      };
      
      // Add to addresses list (in real app, save to database)
      addAddressToList(newAddress);
      
      // Clear form
      form.reset();
      
      // Show address list
      showAddressList();
      
      showNotification('New address added successfully!', 'success');
    }
    
    function addAddressToList(address) {
      const addressList = document.getElementById('savedAddresses');
      const addressItem = document.createElement('div');
      addressItem.className = 'address-item';
      addressItem.innerHTML = `
        <div class="address-item-content">
          <div class="address-item-header">
            <span class="address-name">${address.name}</span>
            <span class="address-phone">${address.phone}</span>
          </div>
          <div class="address-item-address">${address.fullAddress}</div>
        </div>
        <div class="address-item-actions">
          <button class="btn-select-address" onclick="selectAddress({
            name: '${address.name}',
            phone: '${address.phone}',
            fullAddress: '${address.fullAddress}'
          })">Select</button>
        </div>
      `;
      addressList.appendChild(addressItem);
    }
    
    function showNotification(message, type) {
      const notification = document.createElement('div');
      notification.className = `notification notification-${type}`;
      notification.textContent = message;
      
      document.body.appendChild(notification);
      
      // Show notification
      setTimeout(() => {
        notification.classList.add('show');
      }, 100);
      
      // Hide notification after 3 seconds
      setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
          document.body.removeChild(notification);
        }, 300);
      }, 3000);
    }
    
    function changeShipping() {
      document.getElementById('shippingModal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
    
    function closeShippingModal() {
      document.getElementById('shippingModal').style.display = 'none';
      document.body.style.overflow = 'auto';
    }
    
    function selectShipping(shippingData) {
      // Update the shipping display
      document.querySelector('.method-name').textContent = shippingData.name;
      document.querySelector('.delivery-time').textContent = shippingData.deliveryTime;
      document.querySelector('.shipping-fee').textContent = shippingData.fee;
      
      // Update selected shipping option in modal
      document.querySelectorAll('.shipping-option').forEach(option => {
        option.classList.remove('selected');
      });
      document.querySelector(`[data-shipping="${shippingData.id}"]`).classList.add('selected');
      
      // Close modal
      closeShippingModal();
      
      // Show success message
      showNotification(`Shipping method changed to ${shippingData.name}`, 'success');
      
      // Update checkout total (if shipping fee changed)
      updateShippingFee(shippingData.feeAmount);
    }
    
    function updateShippingFee(newFee) {
      // Update the total calculation to include new shipping fee
      // This would integrate with the existing cart total calculation
      const currentTotal = parseFloat(document.getElementById('totalAmount').textContent.replace('₱', ''));
      // In a real implementation, you'd recalculate the total with the new shipping fee
      updateUI();
    }
    
    function selectVoucher() {
      document.getElementById('voucherModal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
    
    function closeVoucherModal() {
      document.getElementById('voucherModal').style.display = 'none';
      document.body.style.overflow = 'auto';
    }
    
    function applyVoucher(voucherData) {
      // Update the voucher display
      const voucherContent = document.querySelector('.voucher-content');
      voucherContent.innerHTML = `
        <div class="applied-voucher">
          <div class="voucher-info">
            <span class="voucher-code">${voucherData.code}</span>
            <span class="voucher-discount">-${voucherData.discount}</span>
          </div>
          <button class="btn-remove-voucher" onclick="removeVoucher()" title="Remove voucher">×</button>
        </div>
      `;
      
      // Update selected voucher in modal
      document.querySelectorAll('.voucher-item').forEach(item => {
        item.classList.remove('selected');
      });
      document.querySelector(`[data-voucher="${voucherData.id}"]`).classList.add('selected');
      
      // Close modal
      closeVoucherModal();
      
      // Show success message
      showNotification(`Voucher ${voucherData.code} applied! You saved ${voucherData.discount}`, 'success');
      
      // Update checkout total with discount
      updateVoucherDiscount(voucherData.discountAmount, voucherData.type);
    }
    
    function removeVoucher() {
      // Reset voucher display
      const voucherContent = document.querySelector('.voucher-content');
      voucherContent.innerHTML = `
        <button class="btn-select-voucher" onclick="selectVoucher()">Select Voucher</button>
      `;
      
      // Remove selection from modal
      document.querySelectorAll('.voucher-item').forEach(item => {
        item.classList.remove('selected');
      });
      
      // Show success message
      showNotification('Voucher removed successfully', 'success');
      
      // Update checkout total (remove discount)
      updateVoucherDiscount(0, 'none');
    }
    
    function updateVoucherDiscount(discountAmount, discountType) {
      // Update the total calculation to include voucher discount
      // This would integrate with the existing cart total calculation
      const currentTotal = parseFloat(document.getElementById('totalAmount').textContent.replace('₱', ''));
      // In a real implementation, you'd recalculate the total with the voucher discount
      updateUI();
    }
    
    function checkVoucherEligibility(voucher, cartTotal) {
      // Check if voucher can be applied based on conditions
      if (voucher.minSpend && cartTotal < voucher.minSpend) {
        return false;
      }
      if (voucher.maxUses && voucher.usedCount >= voucher.maxUses) {
        return false;
      }
      if (voucher.expiry && new Date() > new Date(voucher.expiry)) {
        return false;
      }
      return true;
    }
    
    function showVoucherTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.voucher-tab-content').forEach(content => {
        content.classList.remove('active');
      });
      
      // Remove active class from all tabs
      document.querySelectorAll('.voucher-tab').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Show selected tab content
      if (tabName === 'available') {
        document.getElementById('availableVouchers').classList.add('active');
        document.querySelector('.voucher-tab:first-child').classList.add('active');
      } else if (tabName === 'used') {
        document.getElementById('usedVouchers').classList.add('active');
        document.querySelector('.voucher-tab:last-child').classList.add('active');
      }
    }
    
    // Keyboard support for modals
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const addressModal = document.getElementById('addressModal');
        const shippingModal = document.getElementById('shippingModal');
        const voucherModal = document.getElementById('voucherModal');
        
        if (addressModal.style.display === 'block') {
          closeAddressModal();
        } else if (shippingModal.style.display === 'block') {
          closeShippingModal();
        } else if (voucherModal.style.display === 'block') {
          closeVoucherModal();
        }
      }
    });
    
    // Click outside modal to close
    document.addEventListener('click', function(e) {
      const addressModal = document.getElementById('addressModal');
      const shippingModal = document.getElementById('shippingModal');
      const voucherModal = document.getElementById('voucherModal');
      
      if (e.target === addressModal) {
        closeAddressModal();
      } else if (e.target === shippingModal) {
        closeShippingModal();
      } else if (e.target === voucherModal) {
        closeVoucherModal();
      }
    });
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
      updateUI();
    });
  </script>

  <!-- Address Selection Modal -->
  <div id="addressModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Select Delivery Address</h3>
        <button class="modal-close" onclick="closeAddressModal()">&times;</button>
      </div>
      
      <div class="modal-body">
        <!-- Address List View -->
        <div id="addressList">
          <div class="address-actions-header">
            <button class="btn-add-address" onclick="addNewAddress()">
              <span class="add-icon">+</span>
              Add New Address
            </button>
          </div>
          
          <div class="saved-addresses" id="savedAddresses">
            <!-- Default Address -->
            <div class="address-item address-default">
              <div class="address-item-content">
                <div class="address-item-header">
                  <span class="address-name"><?= htmlspecialchars($user['username']) ?></span>
                  <span class="address-phone">(+63) 963 718 9463</span>
                  <span class="default-badge">Default</span>
                </div>
                <div class="address-item-address">Sitio, Kapusoy, Larrazabal, Larrazabal, Naval, Visayas, Biliran 6560</div>
              </div>
              <div class="address-item-actions">
                <button class="btn-select-address selected" disabled>Selected</button>
              </div>
            </div>
            
            <!-- Sample Additional Addresses -->
            <div class="address-item">
              <div class="address-item-content">
                <div class="address-item-header">
                  <span class="address-name"><?= htmlspecialchars($user['username']) ?></span>
                  <span class="address-phone">(+63) 917 123 4567</span>
                </div>
                <div class="address-item-address">123 Rizal Street, Poblacion, Naval, Biliran 6560</div>
              </div>
              <div class="address-item-actions">
                <button class="btn-select-address" onclick="selectAddress({
                  name: '<?= htmlspecialchars($user['username']) ?>',
                  phone: '(+63) 917 123 4567',
                  fullAddress: '123 Rizal Street, Poblacion, Naval, Biliran 6560'
                })">Select</button>
              </div>
            </div>
            
            <div class="address-item">
              <div class="address-item-content">
                <div class="address-item-header">
                  <span class="address-name">Maria Santos</span>
                  <span class="address-phone">(+63) 928 765 4321</span>
                </div>
                <div class="address-item-address">456 Mabini Avenue, Caibiran, Biliran 6571</div>
              </div>
              <div class="address-item-actions">
                <button class="btn-select-address" onclick="selectAddress({
                  name: 'Maria Santos',
                  phone: '(+63) 928 765 4321',
                  fullAddress: '456 Mabini Avenue, Caibiran, Biliran 6571'
                })">Select</button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- New Address Form -->
        <div id="newAddressForm" style="display: none;">
          <div class="form-header">
            <button class="btn-back" onclick="showAddressList()">← Back to Addresses</button>
            <h4>Add New Address</h4>
          </div>
          
          <form id="newAddressFormElement">
            <div class="form-group">
              <label for="name">Full Name *</label>
              <input type="text" id="name" name="name" required placeholder="Enter full name">
            </div>
            
            <div class="form-group">
              <label for="phone">Phone Number *</label>
              <input type="tel" id="phone" name="phone" required placeholder="+63 XXX XXX XXXX">
            </div>
            
            <div class="form-group">
              <label for="address">Complete Address *</label>
              <textarea id="address" name="address" required placeholder="House/Unit/Floor No., Street Name, Barangay, City, Province, Postal Code" rows="3"></textarea>
            </div>
            
            <div class="form-group">
              <label for="label">Address Label (Optional)</label>
              <select id="label" name="label">
                <option value="home">Home</option>
                <option value="office">Office</option>
                <option value="other">Other</option>
              </select>
            </div>
            
            <div class="form-actions">
              <button type="button" class="btn-cancel" onclick="showAddressList()">Cancel</button>
              <button type="button" class="btn-save" onclick="saveNewAddress()">Save Address</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Shipping Options Modal -->
  <div id="shippingModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Select Shipping Method</h3>
        <button class="modal-close" onclick="closeShippingModal()">&times;</button>
      </div>
      
      <div class="modal-body">
        <div class="shipping-options">
          <!-- Fast Delivery -->
          <div class="shipping-option selected" data-shipping="fast">
            <div class="shipping-option-content">
              <div class="shipping-option-header">
                <div class="shipping-icon">🚚</div>
                <div class="shipping-info">
                  <div class="shipping-name">FARMLINK Fast Delivery</div>
                  <div class="shipping-description">Express delivery for fresh produce</div>
                </div>
                <div class="shipping-price">₱60</div>
              </div>
              <div class="shipping-details">
                <div class="delivery-estimate">Get by 7 - 16 Oct</div>
                <div class="shipping-features">
                  <span class="feature-tag">✓ Temperature Controlled</span>
                  <span class="feature-tag">✓ Same Day Available</span>
                </div>
              </div>
            </div>
            <div class="shipping-option-actions">
              <button class="btn-select-shipping selected" disabled>Selected</button>
            </div>
          </div>
          
          <!-- Standard Delivery -->
          <div class="shipping-option" data-shipping="standard">
            <div class="shipping-option-content">
              <div class="shipping-option-header">
                <div class="shipping-icon">📦</div>
                <div class="shipping-info">
                  <div class="shipping-name">Standard Delivery</div>
                  <div class="shipping-description">Regular delivery service</div>
                </div>
                <div class="shipping-price">₱35</div>
              </div>
              <div class="shipping-details">
                <div class="delivery-estimate">Get by 10 - 20 Oct</div>
                <div class="shipping-features">
                  <span class="feature-tag">✓ Secure Packaging</span>
                  <span class="feature-tag">✓ Tracking Available</span>
                </div>
              </div>
            </div>
            <div class="shipping-option-actions">
              <button class="btn-select-shipping" onclick="selectShipping({
                id: 'standard',
                name: 'Standard Delivery',
                deliveryTime: 'Get by 10 - 20 Oct',
                fee: '₱35',
                feeAmount: 35
              })">Select</button>
            </div>
          </div>
          
          <!-- Economy Delivery -->
          <div class="shipping-option" data-shipping="economy">
            <div class="shipping-option-content">
              <div class="shipping-option-header">
                <div class="shipping-icon">🚛</div>
                <div class="shipping-info">
                  <div class="shipping-name">Economy Delivery</div>
                  <div class="shipping-description">Budget-friendly option</div>
                </div>
                <div class="shipping-price">₱20</div>
              </div>
              <div class="shipping-details">
                <div class="delivery-estimate">Get by 15 - 25 Oct</div>
                <div class="shipping-features">
                  <span class="feature-tag">✓ Basic Packaging</span>
                  <span class="feature-tag">✓ Bulk Delivery</span>
                </div>
              </div>
            </div>
            <div class="shipping-option-actions">
              <button class="btn-select-shipping" onclick="selectShipping({
                id: 'economy',
                name: 'Economy Delivery',
                deliveryTime: 'Get by 15 - 25 Oct',
                fee: '₱20',
                feeAmount: 20
              })">Select</button>
            </div>
          </div>
          
          <!-- Premium Delivery -->
          <div class="shipping-option" data-shipping="premium">
            <div class="shipping-option-content">
              <div class="shipping-option-header">
                <div class="shipping-icon">⚡</div>
                <div class="shipping-info">
                  <div class="shipping-name">Premium Express</div>
                  <div class="shipping-description">Ultra-fast premium service</div>
                </div>
                <div class="shipping-price">₱120</div>
              </div>
              <div class="shipping-details">
                <div class="delivery-estimate">Get by Tomorrow</div>
                <div class="shipping-features">
                  <span class="feature-tag">✓ Next Day Delivery</span>
                  <span class="feature-tag">✓ Premium Packaging</span>
                  <span class="feature-tag">✓ White Glove Service</span>
                </div>
              </div>
            </div>
            <div class="shipping-option-actions">
              <button class="btn-select-shipping" onclick="selectShipping({
                id: 'premium',
                name: 'Premium Express',
                deliveryTime: 'Get by Tomorrow',
                fee: '₱120',
                feeAmount: 120
              })">Select</button>
            </div>
          </div>
          
          <!-- Pickup Option -->
          <div class="shipping-option" data-shipping="pickup">
            <div class="shipping-option-content">
              <div class="shipping-option-header">
                <div class="shipping-icon">🏪</div>
                <div class="shipping-info">
                  <div class="shipping-name">Store Pickup</div>
                  <div class="shipping-description">Collect from FARMLINK hub</div>
                </div>
                <div class="shipping-price">FREE</div>
              </div>
              <div class="shipping-details">
                <div class="delivery-estimate">Ready in 2-3 hours</div>
                <div class="shipping-features">
                  <span class="feature-tag">✓ No Delivery Fee</span>
                  <span class="feature-tag">✓ Quality Check</span>
                </div>
              </div>
            </div>
            <div class="shipping-option-actions">
              <button class="btn-select-shipping" onclick="selectShipping({
                id: 'pickup',
                name: 'Store Pickup',
                deliveryTime: 'Ready in 2-3 hours',
                fee: 'FREE',
                feeAmount: 0
              })">Select</button>
            </div>
          </div>
        </div>
        
        <div class="shipping-note">
          <div class="note-icon">💡</div>
          <div class="note-text">
            <strong>Note:</strong> Delivery times may vary based on weather conditions and product availability. 
            Fresh produce requires special handling for optimal quality.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Voucher Selection Modal -->
  <div id="voucherModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Select Voucher</h3>
        <button class="modal-close" onclick="closeVoucherModal()">&times;</button>
      </div>
      
      <div class="modal-body">
        <div class="voucher-tabs">
          <button class="voucher-tab active" onclick="showVoucherTab('available')">Available</button>
          <button class="voucher-tab" onclick="showVoucherTab('used')">Used</button>
        </div>
        
        <div class="voucher-content-modal">
          <!-- Available Vouchers Tab -->
          <div id="availableVouchers" class="voucher-tab-content active">
            <div class="voucher-list">
              <!-- New User Discount -->
              <div class="voucher-item" data-voucher="newuser">
                <div class="voucher-card">
                  <div class="voucher-header">
                    <div class="voucher-icon">🎉</div>
                    <div class="voucher-badge new-user">New User</div>
                  </div>
                  <div class="voucher-details">
                    <div class="voucher-title">Welcome to FARMLINK</div>
                    <div class="voucher-description">Get 20% off your first order</div>
                    <div class="voucher-code-display">Code: WELCOME20</div>
                    <div class="voucher-conditions">
                      <span class="condition">Min spend: ₱100</span>
                      <span class="condition">Valid until: Dec 31, 2024</span>
                    </div>
                  </div>
                  <div class="voucher-discount-badge">20% OFF</div>
                </div>
                <div class="voucher-actions">
                  <button class="btn-apply-voucher" onclick="applyVoucher({
                    id: 'newuser',
                    code: 'WELCOME20',
                    discount: '20% OFF',
                    discountAmount: 20,
                    type: 'percentage'
                  })">Apply</button>
                </div>
              </div>
              
              <!-- Free Shipping -->
              <div class="voucher-item" data-voucher="freeship">
                <div class="voucher-card">
                  <div class="voucher-header">
                    <div class="voucher-icon">🚚</div>
                    <div class="voucher-badge shipping">Free Shipping</div>
                  </div>
                  <div class="voucher-details">
                    <div class="voucher-title">Free Delivery</div>
                    <div class="voucher-description">No delivery charges on this order</div>
                    <div class="voucher-code-display">Code: FREESHIP</div>
                    <div class="voucher-conditions">
                      <span class="condition">Min spend: ₱200</span>
                      <span class="condition">Valid for 7 days</span>
                    </div>
                  </div>
                  <div class="voucher-discount-badge">FREE SHIP</div>
                </div>
                <div class="voucher-actions">
                  <button class="btn-apply-voucher" onclick="applyVoucher({
                    id: 'freeship',
                    code: 'FREESHIP',
                    discount: 'Free Shipping',
                    discountAmount: 60,
                    type: 'shipping'
                  })">Apply</button>
                </div>
              </div>
              
              <!-- Bulk Order Discount -->
              <div class="voucher-item" data-voucher="bulk10">
                <div class="voucher-card">
                  <div class="voucher-header">
                    <div class="voucher-icon">📦</div>
                    <div class="voucher-badge bulk">Bulk Order</div>
                  </div>
                  <div class="voucher-details">
                    <div class="voucher-title">Bulk Purchase Discount</div>
                    <div class="voucher-description">Save more when you buy more</div>
                    <div class="voucher-code-display">Code: BULK10</div>
                    <div class="voucher-conditions">
                      <span class="condition">Min spend: ₱500</span>
                      <span class="condition">Max discount: ₱100</span>
                    </div>
                  </div>
                  <div class="voucher-discount-badge">₱50 OFF</div>
                </div>
                <div class="voucher-actions">
                  <button class="btn-apply-voucher" onclick="applyVoucher({
                    id: 'bulk10',
                    code: 'BULK10',
                    discount: '₱50 OFF',
                    discountAmount: 50,
                    type: 'fixed'
                  })">Apply</button>
                </div>
              </div>
              
              <!-- Seasonal Discount -->
              <div class="voucher-item" data-voucher="harvest15">
                <div class="voucher-card">
                  <div class="voucher-header">
                    <div class="voucher-icon">🌾</div>
                    <div class="voucher-badge seasonal">Seasonal</div>
                  </div>
                  <div class="voucher-details">
                    <div class="voucher-title">Harvest Season Special</div>
                    <div class="voucher-description">Fresh harvest, fresh savings</div>
                    <div class="voucher-code-display">Code: HARVEST15</div>
                    <div class="voucher-conditions">
                      <span class="condition">Min spend: ₱300</span>
                      <span class="condition">Expires: Oct 31, 2024</span>
                    </div>
                  </div>
                  <div class="voucher-discount-badge">15% OFF</div>
                </div>
                <div class="voucher-actions">
                  <button class="btn-apply-voucher" onclick="applyVoucher({
                    id: 'harvest15',
                    code: 'HARVEST15',
                    discount: '15% OFF',
                    discountAmount: 15,
                    type: 'percentage'
                  })">Apply</button>
                </div>
              </div>
              
              <!-- Loyalty Reward -->
              <div class="voucher-item" data-voucher="loyal25">
                <div class="voucher-card">
                  <div class="voucher-header">
                    <div class="voucher-icon">⭐</div>
                    <div class="voucher-badge loyalty">Loyalty Reward</div>
                  </div>
                  <div class="voucher-details">
                    <div class="voucher-title">Loyal Customer Bonus</div>
                    <div class="voucher-description">Thank you for choosing FARMLINK</div>
                    <div class="voucher-code-display">Code: LOYAL25</div>
                    <div class="voucher-conditions">
                      <span class="condition">Min spend: ₱150</span>
                      <span class="condition">Limited time offer</span>
                    </div>
                  </div>
                  <div class="voucher-discount-badge">₱25 OFF</div>
                </div>
                <div class="voucher-actions">
                  <button class="btn-apply-voucher" onclick="applyVoucher({
                    id: 'loyal25',
                    code: 'LOYAL25',
                    discount: '₱25 OFF',
                    discountAmount: 25,
                    type: 'fixed'
                  })">Apply</button>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Used Vouchers Tab -->
          <div id="usedVouchers" class="voucher-tab-content">
            <div class="voucher-list">
              <div class="voucher-item used">
                <div class="voucher-card">
                  <div class="voucher-header">
                    <div class="voucher-icon">🎁</div>
                    <div class="voucher-badge used">Used</div>
                  </div>
                  <div class="voucher-details">
                    <div class="voucher-title">First Order Discount</div>
                    <div class="voucher-description">Used on Sep 15, 2024</div>
                    <div class="voucher-code-display">Code: FIRST10</div>
                  </div>
                  <div class="voucher-discount-badge">₱10 OFF</div>
                </div>
                <div class="voucher-actions">
                  <button class="btn-apply-voucher" disabled>Used</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="voucher-note">
          <div class="note-icon">💡</div>
          <div class="note-text">
            <strong>Tip:</strong> Vouchers are automatically applied at checkout. Only one voucher can be used per order. 
            Check terms and conditions for each voucher.
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="/FARMLINK/assets/js/logout-confirmation.js"></script>
</body>
</html>
