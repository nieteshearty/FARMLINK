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
            
        } elseif ($action === 'select_items') {
            $selectedItems = $_POST['selected_items'] ?? [];
            
            // Handle both array and comma-separated string
            if (is_string($selectedItems)) {
                $selectedItems = !empty($selectedItems) ? explode(',', $selectedItems) : [];
            }
            
            $_SESSION['selected_cart_items'] = $selectedItems;
            
        } elseif ($action === 'save_delivery_address') {
            $deliveryAddress = $_POST['delivery_address'] ?? '';
            $deliveryCoordinates = $_POST['delivery_coordinates'] ?? '';
            $deliveryInstructions = $_POST['delivery_instructions'] ?? '';
            
            $_SESSION['delivery_info'] = [
                'address' => $deliveryAddress,
                'coordinates' => $deliveryCoordinates,
                'instructions' => $deliveryInstructions
            ];
            
            $_SESSION['success'] = "Delivery address saved!";
            
        } elseif ($action === 'place_order') {
            // Get selected cart items or all if none selected
            $selectedItems = $_SESSION['selected_cart_items'] ?? [];
            $cartItems = DatabaseHelper::getCart($user['id']);
            $deliveryInfo = $_SESSION['delivery_info'] ?? null;
            $deliveryMethod = $_POST['delivery_method'] ?? 'delivery';
            
            // Filter to only selected items if any are selected
            if (!empty($selectedItems)) {
                $cartItems = array_filter($cartItems, function($item) use ($selectedItems) {
                    return in_array($item['product_id'], $selectedItems);
                });
            }
            
            if (empty($cartItems)) {
                $_SESSION['error'] = "Please select items to order!";
            } elseif ($deliveryMethod === 'delivery' && (!$deliveryInfo || empty($deliveryInfo['address']))) {
                $_SESSION['error'] = "Please set your delivery address!";
            } else {
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
                        $total = array_sum(array_map(function($item) {
                            return $item['price'] * $item['quantity'];
                        }, $items));
                        
                        // Create order with delivery method and information
                        if ($deliveryMethod === 'pickup') {
                            // For pickup orders, no delivery address needed
                            $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, farmer_id, total, delivery_method) VALUES (?, ?, ?, ?)");
                            $stmt->execute([
                                $user['id'], 
                                $farmerId, 
                                $total,
                                'pickup'
                            ]);
                        } else {
                            // For delivery orders, include address information
                            $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, farmer_id, total, delivery_method, delivery_address, delivery_coordinates, delivery_instructions) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $user['id'], 
                                $farmerId, 
                                $total,
                                'delivery',
                                $deliveryInfo['address'],
                                $deliveryInfo['coordinates'],
                                $deliveryInfo['instructions']
                            ]);
                        }
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
                    
                    // Clear selected items from cart
                    if (!empty($selectedItems)) {
                        $placeholders = str_repeat('?,', count($selectedItems) - 1) . '?';
                        $stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ? AND product_id IN ($placeholders)");
                        $stmt->execute(array_merge([$user['id']], $selectedItems));
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ?");
                        $stmt->execute([$user['id']]);
                    }
                    
                    // Clear selected items session
                    unset($_SESSION['selected_cart_items']);
                    unset($_SESSION['applied_voucher']);
                    
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
$selectedItems = $_SESSION['selected_cart_items'] ?? [];
$deliveryInfo = $_SESSION['delivery_info'] ?? null;

// Filter out expired products from selected items
if (!empty($selectedItems) && !empty($cartItems)) {
    $validSelectedItems = [];
    foreach ($selectedItems as $productId) {
        // Find the product in cart items
        foreach ($cartItems as $item) {
            if ($item['product_id'] == $productId) {
                $isExpired = $item['is_product_expired'] ?? false;
                // Only keep non-expired products in selection
                if (!$isExpired) {
                    $validSelectedItems[] = $productId;
                }
                break;
            }
        }
    }
    $selectedItems = $validSelectedItems;
    // Update session with filtered selection
    $_SESSION['selected_cart_items'] = $selectedItems;
}

// Get delivery zones for farmers in cart
$farmerDeliveryZones = [];
if (!empty($cartItems)) {
    try {
        $pdo = getDBConnection();
        $farmerIds = array_unique(array_column($cartItems, 'farmer_id'));
        
        if (!empty($farmerIds)) {
            $placeholders = str_repeat('?,', count($farmerIds) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT z.*, u.username as farmer_name,
                       GROUP_CONCAT(DISTINCT s.day_of_week ORDER BY 
                           CASE s.day_of_week 
                               WHEN 'monday' THEN 1
                               WHEN 'tuesday' THEN 2
                               WHEN 'wednesday' THEN 3
                               WHEN 'thursday' THEN 4
                               WHEN 'friday' THEN 5
                               WHEN 'saturday' THEN 6
                               WHEN 'sunday' THEN 7
                           END
                       ) as delivery_days_list,
                       GROUP_CONCAT(DISTINCT s.time_slot ORDER BY s.time_slot) as time_slots_list
                FROM farmer_delivery_zones z
                LEFT JOIN farmer_delivery_schedule s ON z.id = s.zone_id
                LEFT JOIN users u ON z.farmer_id = u.id
                WHERE z.farmer_id IN ($placeholders) AND z.is_active = 1
                GROUP BY z.id
                ORDER BY z.farmer_id, z.zone_name
            ");
            $stmt->execute($farmerIds);
            $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group zones by farmer
            foreach ($zones as $zone) {
                $farmerDeliveryZones[$zone['farmer_id']][] = $zone;
            }
            
            // Get scheduled deliveries for farmers
            $stmt = $pdo->prepare("
                SELECT o.farmer_id, o.estimated_delivery_date, o.delivery_time_slot, o.delivery_notes,
                       u.username as farmer_name
                FROM orders o
                LEFT JOIN users u ON o.farmer_id = u.id
                WHERE o.farmer_id IN ($placeholders) 
                AND o.estimated_delivery_date >= CURDATE()
                AND o.status IN ('pending', 'confirmed')
                ORDER BY o.estimated_delivery_date ASC, o.delivery_time_slot ASC
            ");
            $stmt->execute($farmerIds);
            $scheduledDeliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group scheduled deliveries by farmer
            $farmerScheduledDeliveries = [];
            foreach ($scheduledDeliveries as $delivery) {
                $farmerScheduledDeliveries[$delivery['farmer_id']][] = $delivery;
            }
        }
    } catch (Exception $e) {
        // Silently handle error - delivery zones are optional
        error_log("Error fetching delivery zones: " . $e->getMessage());
    }
}

// Ensure selectedItems is always an array
if (!is_array($selectedItems)) {
    $selectedItems = [];
}

$subtotal = 0;
$totalItems = 0;
$selectedSubtotal = 0;
$selectedItemsCount = 0;

foreach ($cartItems as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    $isExpired = $item['is_product_expired'] ?? false;
    
    // Only include non-expired products in totals
    if (!$isExpired) {
        $subtotal += $itemTotal;
        $totalItems += $item['quantity'];
        
        // Calculate selected items totals (only for non-expired products)
        if (in_array($item['product_id'], $selectedItems)) {
            $selectedSubtotal += $itemTotal;
            $selectedItemsCount += $item['quantity'];
        }
    }
}

// Final total is just the subtotal (no discounts or shipping fees)
$finalTotal = $selectedSubtotal;

// No shipping fee - removed as requested
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
  <!-- Leaflet Maps for Location Services -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body data-page="buyer-cart">
  <nav>
    <div class="nav-left">
      <a href="buyer-dashboard.php"><img src="/FARMLINK/assets/img/farmlink.png" alt="FARMLINK Logo" class="logo"></a>
      <span class="brand">FARMLINK - BUYER</span>
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
      <!-- Shopee-like Cart Header -->
      <?php if (!empty($cartItems)): ?>
        <div class="cart-header">
          <div class="select-all-section">
            <label class="checkbox-container">
              <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
              <span class="checkmark"></span>
              Select All (<?= count($cartItems) ?> items)
            </label>
            <button type="button" class="btn-link" onclick="deleteSelected()" id="deleteSelectedBtn" style="display:none;">Delete Selected</button>
          </div>
          <div class="cart-actions">
            <span class="cart-total-items"><?= $totalItems ?> items in cart</span>
          </div>
        </div>
      <?php endif; ?>
      
      <?php if (empty($cartItems)): ?>
        <div class="card" style="text-align:center; padding:40px;">
          <h3>Your cart is empty</h3>
          <p>Browse our market to add some fresh farm products!</p>
          <button class="btn" onclick="location.href='buyer-market.php'">Browse Products</button>
        </div>
      <?php else: ?>
        <!-- Shopee-style Cart Items -->
        <div class="cart-items-container">
          <?php foreach ($cartItems as $item): ?>
            <?php 
            $itemTotal = $item['price'] * $item['quantity']; 
            $isExpired = $item['is_product_expired'] ?? false;
            $expiresAt = $item['calculated_expires_at'] ?? null;
            ?>
            <div class="cart-item-card <?= $isExpired ? 'expired-product' : '' ?>" 
                 data-product-id="<?= $item['product_id'] ?>"
                 data-price="<?= $item['price'] ?>"
                 data-quantity="<?= $item['quantity'] ?>"
                 data-total="<?= $itemTotal ?>"
                 data-expired="<?= $isExpired ? '1' : '0' ?>">
              <?php if ($isExpired): ?>
                <div class="expired-indicator">
                  <div class="expired-line"></div>
                  <div class="expired-badge">
                    <span class="expired-icon">⚠️</span>
                    <span class="expired-text">Product Expired</span>
                  </div>
                </div>
              <?php endif; ?>
              <div class="item-select">
                <label class="checkbox-container <?= $isExpired ? 'disabled' : '' ?>">
                  <input type="checkbox" class="item-checkbox" value="<?= $item['product_id'] ?>" 
                         <?= in_array($item['product_id'], $selectedItems) ? 'checked' : '' ?>
                         <?= $isExpired ? 'disabled' : '' ?>
                         onchange="updateSelection()">
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
                       class="item-thumbnail"
                       onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='block';">
                  <div class="item-thumbnail-placeholder" style="display:none;">📷</div>
                <?php else: ?>
                  <div class="item-thumbnail-placeholder">📷</div>
                <?php endif; ?>
              </div>
              
              <div class="item-details">
                <div class="item-info">
                  <h4 class="item-name <?= $isExpired ? 'expired-name' : '' ?>"><?= htmlspecialchars($item['name']) ?></h4>
                  <p class="item-category"><?= htmlspecialchars($item['category'] ?? '') ?></p>
                  <p class="item-farmer">by <?= htmlspecialchars($item['farmer_name']) ?></p>
                  <?php if ($expiresAt): ?>
                    <?php 
                    $expiresDate = new DateTime($expiresAt);
                    $now = new DateTime();
                    $diff = $now->diff($expiresDate);
                    $hoursLeft = ($diff->days * 24) + $diff->h;
                    ?>
                    
                    <?php if ($isExpired): ?>
                      <div class="expiration-info expired">
                        <span class="expiration-icon">🚫</span>
                        <span class="expiration-text">Expired on <?= $expiresDate->format('M j, Y g:i A') ?></span>
                      </div>
                    <?php elseif ($hoursLeft <= 24 && $hoursLeft > 0): ?>
                      <div class="expiration-info warning">
                        <span class="expiration-icon">⏰</span>
                        <span class="expiration-text">Expires <?= $expiresDate->format('M j, g:i A') ?> (<?= $hoursLeft ?>h left)</span>
                      </div>
                    <?php elseif ($diff->days <= 3): ?>
                      <div class="expiration-info caution">
                        <span class="expiration-icon">⚠️</span>
                        <span class="expiration-text">Expires <?= $expiresDate->format('M j, Y g:i A') ?> (<?= $diff->days ?> days)</span>
                      </div>
                    <?php else: ?>
                      <div class="expiration-info normal">
                        <span class="expiration-icon">📅</span>
                        <span class="expiration-text">Best before <?= $expiresDate->format('M j, Y g:i A') ?></span>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="expiration-info normal">
                      <span class="expiration-icon">📅</span>
                      <span class="expiration-text">No expiration date set</span>
                    </div>
                  <?php endif; ?>
                  
                  <!-- Delivery Zone Information -->
                  <?php if (isset($farmerDeliveryZones[$item['farmer_id']])): ?>
                    <div class="delivery-zones-info">
                      <div class="delivery-zones-header">
                        <i class="delivery-icon">🚚</i>
                        <span class="delivery-label">Delivery Areas:</span>
                      </div>
                      <?php foreach ($farmerDeliveryZones[$item['farmer_id']] as $zone): ?>
                        <div class="delivery-zone-item">
                          <div class="zone-details">
                            <strong class="zone-name"><?= htmlspecialchars($zone['zone_name']) ?></strong>
                            <span class="zone-areas"><?= htmlspecialchars($zone['cities']) ?></span>
                          </div>
                          <?php if ($zone['delivery_days_list']): ?>
                            <div class="zone-schedule">
                              <span class="schedule-days"><?= ucwords(str_replace(',', ', ', $zone['delivery_days_list'])) ?></span>
                              <?php if ($zone['time_slots_list']): ?>
                                <span class="schedule-time"><?= htmlspecialchars($zone['time_slots_list']) ?></span>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                          <div class="zone-pricing">
                            <span class="delivery-fee">₱<?= number_format($zone['delivery_fee'], 2) ?> delivery</span>
                            <?php if ($zone['min_order_amount'] > 0): ?>
                              <span class="min-order">Min: ₱<?= number_format($zone['min_order_amount'], 2) ?></span>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <div class="no-delivery-zones">
                      <i class="info-icon">ℹ️</i>
                      <span>Contact farmer for delivery details</span>
                    </div>
                  <?php endif; ?>
                  
                  <!-- Scheduled Deliveries Information -->
                  <?php if (isset($farmerScheduledDeliveries[$item['farmer_id']]) && !empty($farmerScheduledDeliveries[$item['farmer_id']])): ?>
                    <div class="scheduled-deliveries-info">
                      <div class="scheduled-deliveries-header">
                        <i class="schedule-icon">📅</i>
                        <span class="schedule-label">Upcoming Deliveries:</span>
                      </div>
                      <?php 
                      $deliveries = array_slice($farmerScheduledDeliveries[$item['farmer_id']], 0, 3); // Show max 3 upcoming deliveries
                      foreach ($deliveries as $delivery): 
                      ?>
                        <div class="scheduled-delivery-item">
                          <div class="delivery-date-time">
                            <span class="delivery-date"><?= date('M j, Y', strtotime($delivery['estimated_delivery_date'])) ?></span>
                            <?php if ($delivery['delivery_time_slot']): ?>
                              <span class="delivery-time"><?= htmlspecialchars($delivery['delivery_time_slot']) ?></span>
                            <?php endif; ?>
                          </div>
                          <?php if ($delivery['delivery_notes']): ?>
                            <div class="delivery-notes">
                              <span class="notes-label">Note:</span>
                              <span class="notes-text"><?= htmlspecialchars($delivery['delivery_notes']) ?></span>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                      <div class="schedule-info-note">
                        <small>💡 These are the farmer's scheduled delivery times. Your order will be included in the next available slot.</small>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="item-price-section">
                  <div class="item-price">₱<?= number_format($item['price'], 2) ?></div>
                  
                  <div class="quantity-controls <?= $isExpired ? 'disabled' : '' ?>">
                    <button type="button" class="qty-btn" 
                            <?= $isExpired ? 'disabled' : '' ?>
                            onclick="updateQuantity(<?= $item['product_id'] ?>, -1, <?= $item['quantity'] ?>)">-</button>
                    <span class="quantity-display" id="qty-<?= $item['product_id'] ?>"><?= $item['quantity'] ?></span>
                    <button type="button" class="qty-btn" 
                            <?= $isExpired ? 'disabled' : '' ?>
                            onclick="updateQuantity(<?= $item['product_id'] ?>, 1, <?= $item['quantity'] ?>)">+</button>
                  </div>
                  
                  <div class="item-total">₱<?= number_format($itemTotal, 2) ?></div>
                  
                  <button type="button" class="remove-btn" onclick="removeItem(<?= $item['product_id'] ?>)">🗑️</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Delivery Method Selection -->
        <div class="delivery-method-section">
          <h4>🚚 Delivery Method</h4>
          <div class="delivery-method-container">
            <div class="delivery-method-dropdown">
              <select id="deliveryMethodSelect" name="delivery_method" onchange="toggleDeliveryMethod()" class="delivery-method-select">
                <option value="delivery" selected>🚚 Home Delivery - Get your order delivered to your address</option>
                <option value="pickup">📦 Pickup - Collect your order from the farmer</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Delivery Address Section -->
        <div class="delivery-section" id="deliveryAddressSection">
          <div class="delivery-card">
            <div class="delivery-header">
              <span class="delivery-icon">📍</span>
              <span>Delivery Address</span>
              <button type="button" class="change-address-btn" onclick="openAddressModal()">Change</button>
            </div>
            
            <?php if ($deliveryInfo): ?>
              <div class="current-address">
                <div class="address-info">
                  <strong><?= htmlspecialchars($user['username']) ?></strong>
                  <p><?= htmlspecialchars($deliveryInfo['address']) ?></p>
                  <?php if (!empty($deliveryInfo['instructions'])): ?>
                    <small class="delivery-instructions">Instructions: <?= htmlspecialchars($deliveryInfo['instructions']) ?></small>
                  <?php endif; ?>
                </div>
                <button type="button" class="view-map-btn" onclick="viewOnMap('<?= htmlspecialchars($deliveryInfo['coordinates']) ?>', '<?= htmlspecialchars($deliveryInfo['address']) ?>')">🗺️ View</button>
              </div>
            <?php else: ?>
              <div class="no-address">
                <p>Please set your delivery address</p>
                <button type="button" class="set-address-btn" onclick="openAddressModal()">Set Address</button>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Pickup Information Section (Hidden by default) -->
        <div class="pickup-section" id="pickupInfoSection" style="display: none;">
          <div class="pickup-card">
            <div class="pickup-header">
              <span class="pickup-icon">📦</span>
              <span>Pickup Information</span>
            </div>
            
            <div class="pickup-info">
              <div class="pickup-notice">
                <div class="notice-icon">ℹ️</div>
                <div class="notice-content">
                  <strong>Pickup Instructions:</strong>
                  <p>You will receive pickup details from each farmer after placing your order. Pickup locations and times may vary by farmer.</p>
                </div>
              </div>
              
              <div class="farmers-pickup-info" id="farmersPickupInfo">
                <!-- This will be populated with farmer pickup information -->
              </div>
            </div>
          </div>
        </div>
        
        <!-- Shopee-style Order Summary -->
        <div class="order-summary-section">
          <div class="summary-card">
            <h3>Order Summary</h3>
            
            <div class="summary-row">
              <span>Subtotal (<span id="selectedItemsCount"><?= $selectedItemsCount ?></span> items):</span>
              <span id="selectedSubtotal">₱<?= number_format($selectedSubtotal, 2) ?></span>
            </div>
            
            
            <div class="summary-total">
              <span>Total:</span>
              <span id="finalTotal">₱<?= number_format($finalTotal, 2) ?></span>
            </div>
            
            <div class="checkout-section">
              <form method="POST" id="checkoutForm">
                <input type="hidden" name="action" value="select_items">
                <input type="hidden" name="selected_items" id="selectedItemsInput" value="<?= implode(',', $selectedItems) ?>">
              </form>
              
              <form method="POST" id="mainCheckoutForm">
                <input type="hidden" name="action" value="place_order">
                <input type="hidden" name="delivery_method" id="checkoutDeliveryMethod" value="delivery">
                <button type="submit" class="checkout-btn" id="checkoutButton"
                        <?= empty($selectedItems) ? 'disabled' : '' ?>
                        onclick="return confirmCheckout()">
                  <span id="checkoutButtonText">Checkout (<?= $selectedItemsCount ?> items)</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </main>
  
  <!-- Address Modal -->
  <div class="address-modal" id="addressModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Set Delivery Address</h3>
        <button type="button" class="close-modal" onclick="closeAddressModal()">&times;</button>
      </div>
      
      <form method="POST" class="address-form" id="addressForm">
        <input type="hidden" name="action" value="save_delivery_address">
        <input type="hidden" name="delivery_coordinates" id="deliveryCoordinates">
        
        <div class="form-group">
          <label for="searchAddress">Search Location</label>
          <div class="map-controls">
            <input type="text" id="searchAddress" class="map-search" placeholder="Try: Naval, Tacloban, Cebu, Manila, or your city/barangay" 
                   oninput="showSearchSuggestions()" onblur="hideSearchSuggestions()" onfocus="showSearchSuggestions()">
            <button type="button" class="search-btn" onclick="searchLocation()" id="searchBtn">Search</button>
            <button type="button" class="location-btn" onclick="getCurrentLocation()" id="locationBtn">My Location</button>
          </div>
          <div id="searchSuggestions" class="search-suggestions" style="display: none;"></div>
          <div id="searchStatus" class="search-status"></div>
        </div>
        
        <div id="mapContainer" class="map-container"></div>
        
        <div class="form-group">
          <label for="deliveryAddress">Full Address</label>
          <input type="text" name="delivery_address" id="deliveryAddress" 
                 value="<?= htmlspecialchars($deliveryInfo['address'] ?? '') ?>" 
                 placeholder="Complete address" required>
        </div>
        
        <div class="form-group">
          <label for="deliveryInstructions">Delivery Instructions (Optional)</label>
          <textarea name="delivery_instructions" id="deliveryInstructions" 
                    placeholder="Additional instructions for delivery"><?= htmlspecialchars($deliveryInfo['instructions'] ?? '') ?></textarea>
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeAddressModal()">Cancel</button>
          <button type="submit" class="btn-save">Save Address</button>
        </div>
      </form>
    </div>
  </div>

  <style>
    /* Shopee-style Cart Styles */
    .cart-header {
      background: white;
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .select-all-section {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    
    .checkbox-container {
      display: flex;
      align-items: center;
      cursor: pointer;
      font-size: 14px;
      gap: 8px;
    }
    
    .checkbox-container input[type="checkbox"] {
      width: 18px;
      height: 18px;
      accent-color: #ee4d2d;
    }
    
    .btn-link {
      background: none;
      border: none;
      color: #ee4d2d;
      cursor: pointer;
      text-decoration: underline;
      font-size: 14px;
    }
    
    .cart-items-container {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 24px;
    }
    
    .cart-item-card {
      background: white;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      display: flex;
      gap: 16px;
      align-items: center;
      position: relative;
    }
    
    /* Expired Product Styles */
    .cart-item-card.expired-product {
      background: #fafafa;
      border: 2px solid #ff4444;
      opacity: 0.7;
    }
    
    .expired-indicator {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      pointer-events: none;
      z-index: 2;
    }
    
    .expired-line {
      position: absolute;
      top: 0;
      left: 0;
      width: 4px;
      height: 100%;
      background: #ff4444;
      border-radius: 8px 0 0 8px;
    }
    
    .expired-badge {
      position: absolute;
      top: 12px;
      right: 12px;
      background: #ff4444;
      color: white;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 4px;
      box-shadow: 0 2px 4px rgba(255, 68, 68, 0.3);
    }
    
    .expired-icon {
      font-size: 12px;
    }
    
    .expired-name {
      color: #666 !important;
      text-decoration: line-through;
    }
    
    .expiration-info {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-top: 6px;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .expiration-info.expired {
      background: #ffebee;
      color: #c62828;
      border: 1px solid #ffcdd2;
    }
    
    .expiration-info.warning {
      background: #fff3e0;
      color: #ef6c00;
      border: 1px solid #ffcc02;
    }
    
    .expiration-info.caution {
      background: #fffde7;
      color: #f57f17;
      border: 1px solid #fff176;
    }
    
    .expiration-info.normal {
      background: #f1f8e9;
      color: #388e3c;
      border: 1px solid #c8e6c9;
    }
    
    .expiration-icon {
      font-size: 14px;
    }
    
    /* Disabled Controls for Expired Products */
    .checkbox-container.disabled {
      opacity: 0.5;
      pointer-events: none;
    }
    
    .quantity-controls.disabled {
      opacity: 0.5;
      pointer-events: none;
    }
    
    .quantity-controls.disabled .qty-btn {
      background: #f5f5f5;
      color: #ccc;
      cursor: not-allowed;
    }
    
    .item-select {
      flex-shrink: 0;
    }
    
    .item-image {
      flex-shrink: 0;
    }
    
    .item-thumbnail {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #eee;
    }
    
    .item-thumbnail-placeholder {
      width: 80px;
      height: 80px;
      border-radius: 8px;
      border: 1px solid #eee;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f5f5f5;
      font-size: 32px;
    }
    
    .item-details {
      flex: 1;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .item-info h4 {
      margin: 0 0 4px 0;
      font-size: 16px;
      font-weight: 500;
    }
    
    .item-category, .item-farmer {
      margin: 2px 0;
      font-size: 12px;
      color: #666;
    }

    /* Delivery Zones Styles */
    .delivery-zones-info {
      margin-top: 12px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 6px;
      border-left: 3px solid #4CAF50;
    }

    .delivery-zones-header {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 8px;
      font-size: 12px;
      font-weight: 600;
      color: #4CAF50;
    }

    .delivery-icon {
      font-size: 14px;
    }

    .delivery-zone-item {
      margin-bottom: 8px;
      padding: 8px;
      background: white;
      border-radius: 4px;
      border: 1px solid #e0e0e0;
    }

    .delivery-zone-item:last-child {
      margin-bottom: 0;
    }

    .zone-details {
      margin-bottom: 4px;
    }

    .zone-name {
      font-size: 11px;
      font-weight: 600;
      color: #2d6a4f;
      display: block;
    }

    .zone-areas {
      font-size: 10px;
      color: #666;
      display: block;
    }

    .zone-schedule {
      display: flex;
      flex-direction: column;
      gap: 2px;
      margin-bottom: 4px;
    }

    .schedule-days {
      font-size: 10px;
      font-weight: 500;
      color: #2196F3;
    }

    .schedule-time {
      font-size: 10px;
      color: #666;
    }

    .zone-pricing {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .delivery-fee {
      font-size: 10px;
      font-weight: 600;
      color: #4CAF50;
      background: #e8f5e8;
      padding: 2px 6px;
      border-radius: 3px;
    }

    .min-order {
      font-size: 10px;
      color: #ff9800;
      background: #fff3e0;
      padding: 2px 6px;
      border-radius: 3px;
    }

    .no-delivery-zones {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-top: 8px;
      padding: 6px 8px;
      background: #fff3cd;
      border-radius: 4px;
      font-size: 11px;
      color: #856404;
    }

    .info-icon {
      font-size: 12px;
    }

    /* Scheduled Deliveries Styles */
    .scheduled-deliveries-info {
      margin-top: 12px;
      padding: 10px;
      background: #f0f8ff;
      border-radius: 6px;
      border-left: 3px solid #2196F3;
    }

    .scheduled-deliveries-header {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 8px;
      font-size: 12px;
      font-weight: 600;
      color: #2196F3;
    }

    .schedule-icon {
      font-size: 14px;
    }

    .scheduled-delivery-item {
      margin-bottom: 8px;
      padding: 8px;
      background: white;
      border-radius: 4px;
      border: 1px solid #e3f2fd;
    }

    .scheduled-delivery-item:last-child {
      margin-bottom: 0;
    }

    .delivery-date-time {
      display: flex;
      flex-direction: column;
      gap: 2px;
      margin-bottom: 4px;
    }

    .delivery-date {
      font-size: 11px;
      font-weight: 600;
      color: #1976d2;
    }

    .delivery-time {
      font-size: 10px;
      color: #666;
      background: #e3f2fd;
      padding: 2px 6px;
      border-radius: 3px;
      width: fit-content;
    }

    .delivery-notes {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .notes-label {
      font-size: 9px;
      font-weight: 600;
      color: #ff9800;
    }

    .notes-text {
      font-size: 9px;
      color: #666;
      font-style: italic;
    }

    .schedule-info-note {
      margin-top: 8px;
      padding: 6px;
      background: #e8f5e8;
      border-radius: 4px;
      text-align: center;
    }

    .schedule-info-note small {
      font-size: 9px;
      color: #2e7d32;
    }
    
    .item-price-section {
      display: flex;
      align-items: center;
      gap: 24px;
    }
    
    .item-price {
      font-size: 16px;
      font-weight: 600;
      color: #ee4d2d;
      min-width: 80px;
    }
    
    .quantity-controls {
      display: flex;
      align-items: center;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    
    .qty-btn {
      width: 32px;
      height: 32px;
      border: none;
      background: #f5f5f5;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
    }
    
    .qty-btn:hover {
      background: #e0e0e0;
    }
    
    .quantity-display {
      width: 50px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-left: 1px solid #ddd;
      border-right: 1px solid #ddd;
      background: white;
    }
    
    .item-total {
      font-size: 16px;
      font-weight: 600;
      min-width: 80px;
      text-align: right;
    }
    
    .remove-btn {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 18px;
      padding: 8px;
      color: #999;
    }
    
    .remove-btn:hover {
      color: #ee4d2d;
    }
    
    
    /* Delivery Method Styles */
    .delivery-method-section {
      margin-bottom: 20px;
    }
    
    .delivery-method-container {
      background: white;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .delivery-method-dropdown {
      width: 100%;
    }
    
    .delivery-method-select {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 14px;
      background: white;
      cursor: pointer;
      transition: all 0.3s ease;
      appearance: none;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 12px center;
      background-size: 16px;
      padding-right: 40px;
    }
    
    .delivery-method-select:hover {
      border-color: #4CAF50;
      background-color: #f8f9fa;
    }
    
    .delivery-method-select:focus {
      outline: none;
      border-color: #4CAF50;
      box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
    }
    
    /* Pickup Section Styles */
    .pickup-section {
      margin-bottom: 20px;
    }
    
    .pickup-card {
      background: white;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .pickup-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
      font-weight: 500;
      color: #333;
    }
    
    .pickup-notice {
      display: flex;
      gap: 12px;
      padding: 12px;
      background: #fff3cd;
      border: 1px solid #ffeaa7;
      border-radius: 6px;
      margin-bottom: 16px;
    }
    
    .notice-icon {
      font-size: 18px;
      flex-shrink: 0;
    }
    
    .notice-content strong {
      color: #856404;
      display: block;
      margin-bottom: 4px;
    }
    
    .notice-content p {
      color: #856404;
      margin: 0;
      font-size: 14px;
      line-height: 1.4;
    }
    

    /* Delivery Address Styles */
    .delivery-section {
      margin-bottom: 24px;
    }
    
    .delivery-card {
      background: white;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .delivery-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      font-weight: 500;
    }
    
    .delivery-header span {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .change-address-btn, .set-address-btn {
      background: none;
      border: 1px solid #ee4d2d;
      color: #ee4d2d;
      padding: 6px 12px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
    }
    
    .change-address-btn:hover, .set-address-btn:hover {
      background: #ee4d2d;
      color: white;
    }
    
    .current-address {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px;
      background: #f8f9fa;
      border-radius: 4px;
      border-left: 4px solid #4CAF50;
    }
    
    .address-info strong {
      display: block;
      margin-bottom: 4px;
    }
    
    .address-info p {
      margin: 4px 0;
      color: #666;
    }
    
    .delivery-instructions {
      color: #888;
      font-style: italic;
    }

    /* Pickup Information Styles */
    .pickup-section {
      margin-bottom: 24px;
    }
    
    .pickup-card {
      background: white;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .pickup-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
      font-weight: 500;
      color: #333;
    }
    
    .pickup-notice {
      display: flex;
      gap: 12px;
      background: #e3f2fd;
      padding: 12px;
      border-radius: 6px;
      border-left: 4px solid #2196F3;
      margin-bottom: 16px;
    }
    
    .notice-icon {
      font-size: 20px;
      flex-shrink: 0;
    }
    
    .notice-content strong {
      display: block;
      margin-bottom: 4px;
      color: #1976D2;
    }
    
    .notice-content p {
      margin: 0;
      color: #424242;
      font-size: 14px;
      line-height: 1.4;
    }
    
    .farmers-pickup-info {
      display: grid;
      gap: 12px;
    }
    
    .farmer-pickup-item {
      background: #f8f9fa;
      padding: 12px;
      border-radius: 6px;
      border-left: 3px solid #4CAF50;
    }
    
    .farmer-pickup-name {
      font-weight: 600;
      color: #2E7D32;
      margin-bottom: 4px;
    }
    
    .farmer-pickup-location {
      font-size: 14px;
      color: #666;
      margin-bottom: 4px;
    }
    
    .farmer-pickup-hours {
      font-size: 12px;
      color: #888;
    }
    
    .view-map-btn {
      background: #4CAF50;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
    }
    
    .no-address {
      text-align: center;
      padding: 20px;
      color: #666;
    }
    
    .no-address p {
      margin-bottom: 12px;
    }
    
    .order-summary-section {
      position: sticky;
      top: 20px;
    }
    
    .summary-card {
      background: white;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .summary-card h3 {
      margin: 0 0 16px 0;
      font-size: 18px;
    }
    
    .summary-row {
      display: flex;
      justify-content: space-between;
      margin: 8px 0;
      padding: 8px 0;
    }
    
    .summary-row.discount {
      color: #4CAF50;
    }
    
    .summary-total {
      display: flex;
      justify-content: space-between;
      margin: 16px 0;
      padding: 16px 0;
      border-top: 2px solid #eee;
      font-weight: bold;
      font-size: 18px;
    }
    
    .checkout-section {
      margin-top: 20px;
    }
    
    .checkout-btn {
      width: 100%;
      padding: 16px;
      background: #ee4d2d;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.3s;
    }
    
    .checkout-btn:hover:not(:disabled) {
      background: #d73527;
    }
    
    .checkout-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      .cart-item-card {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .item-details {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
      
      .item-price-section {
        width: 100%;
        justify-content: space-between;
      }
      
      .current-address {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
      
      .view-map-btn {
        align-self: flex-end;
      }
    }
    
    /* Address Modal Styles */
    .address-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    
    .address-modal.show {
      display: flex;
    }
    
    .modal-content {
      background: white;
      border-radius: 8px;
      padding: 24px;
      max-width: 600px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 12px;
      border-bottom: 1px solid #eee;
    }
    
    .modal-header h3 {
      margin: 0;
    }
    
    .close-modal {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #999;
    }
    
    .address-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    
    .form-group label {
      font-weight: 500;
      color: #333;
    }
    
    .form-group input, .form-group textarea {
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
    }
    
    .form-group textarea {
      resize: vertical;
      min-height: 80px;
    }
    
    .map-container {
      height: 300px;
      border: 1px solid #ddd;
      border-radius: 4px;
      margin: 12px 0;
    }
    
    .map-controls {
      display: flex;
      gap: 8px;
      margin-bottom: 12px;
    }
    
    .map-search {
      flex: 1;
    }
    
    .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      margin-top: 20px;
      padding-top: 12px;
      border-top: 1px solid #eee;
    }
    
    .btn-cancel {
      padding: 12px 24px;
      background: #6c757d;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    
    .btn-save {
      padding: 12px 24px;
      background: #ee4d2d;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    
    .search-btn, .location-btn {
      padding: 8px 12px;
      background: #4CAF50;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.3s;
    }
    
    .search-btn:hover, .location-btn:hover {
      background: #45a049;
    }
    
    .search-btn:disabled, .location-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    /* Search Suggestions Styles */
    .search-suggestions {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-top: none;
      border-radius: 0 0 4px 4px;
      max-height: 200px;
      overflow-y: auto;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .suggestion-item {
      padding: 10px 12px;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: background-color 0.2s ease;
    }
    
    .suggestion-item:hover {
      background: #f8f9fa;
    }
    
    .suggestion-item:last-child {
      border-bottom: none;
    }
    
    .suggestion-icon {
      color: #4CAF50;
      font-size: 14px;
      width: 16px;
      text-align: center;
    }
    
    .suggestion-text {
      flex: 1;
      font-size: 14px;
      color: #333;
    }
    
    .suggestion-type {
      font-size: 12px;
      color: #666;
      background: #f0f0f0;
      padding: 2px 6px;
      border-radius: 3px;
    }
    
    .map-controls {
      position: relative;
    }

    /* View Map Modal Styles */
    .view-map-info {
      background: #f8f9fa;
      padding: 12px 16px;
      border-radius: 6px;
      margin-bottom: 16px;
      border-left: 4px solid #4CAF50;
    }
    
    .view-map-info p {
      margin: 4px 0;
      font-size: 14px;
    }
    
    .view-map-info strong {
      color: #2E7D32;
    }
    
    .modal-actions {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      margin-top: 16px;
    }
    
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    
    .btn-secondary {
      background: #6c757d;
      color: white;
    }
    
    .btn-secondary:hover {
      background: #5a6268;
    }
    
    .btn-primary {
      background: #4CAF50;
      color: white;
    }
    
    .btn-primary:hover {
      background: #45a049;
    }
    
    .btn-success {
      background: #28a745;
      color: white;
    }
    
    .btn-success:hover {
      background: #218838;
    }
    
    .search-status {
      margin-top: 8px;
      padding: 8px;
      border-radius: 4px;
      font-size: 14px;
      display: none;
    }
    
    .search-status.loading {
      display: block;
      background: #e3f2fd;
      color: #1976d2;
      border: 1px solid #bbdefb;
    }
    
    .search-status.success {
      display: block;
      background: #e8f5e8;
      color: #2e7d32;
      border: 1px solid #c8e6c9;
    }
    
    .search-status.error {
      display: block;
      background: #ffebee;
      color: #c62828;
      border: 1px solid #ffcdd2;
    }
    
    .summary-item {
      display: flex;
      justify-content: space-between;
      margin: 8px 0;
      padding: 8px 0;
      border-bottom: 1px solid #eee;
    }
    
    .summary-total {
      display: flex;
      justify-content: space-between;
      margin: 16px 0;
      padding: 12px 0;
      border-top: 2px solid #2d6a4f;
      font-weight: bold;
      font-size: 18px;
    }
    
    input[type="number"] {
      padding: 4px 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    
    .alert {
      padding: 12px;
      margin: 16px 0;
      border-radius: 4px;
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
    
    .cart-product-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .cart-product-thumb {
      width: 60px;
      height: 60px;
      border-radius: 8px;
      object-fit: cover;
      border: 1px solid #ddd;
    }
    
    .cart-product-thumb-placeholder {
      width: 60px;
      height: 60px;
      border-radius: 8px;
      border: 1px solid #ddd;
      display: flex;
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
  <script src="/FARMLINK/assets/js/logout-confirmation.js"></script>
  
  <script>
    // Toggle shipping details breakdown
    function toggleShippingDetails() {
      const details = document.getElementById('shippingDetails');
      const toggle = document.querySelector('.shipping-toggle');
      
      if (details) {
        if (details.style.display === 'none' || details.style.display === '') {
          details.style.display = 'block';
          if (toggle) toggle.textContent = '▲ Details';
        } else {
          details.style.display = 'none';
          if (toggle) toggle.textContent = '▼ Details';
        }
      }
    }
    
    // Search suggestions functionality
    function showSearchSuggestions() {
      const input = document.getElementById('searchAddress');
      const suggestionsDiv = document.getElementById('searchSuggestions');
      const query = input.value.toLowerCase().trim();
      
      if (query.length < 2) {
        suggestionsDiv.style.display = 'none';
        return;
      }
      
      // Get predefined locations for suggestions
      const predefinedLocations = {
        // Biliran Province
        'naval': { name: 'Naval, Biliran', type: 'Municipality', icon: '🏛️' },
        'biliran': { name: 'Biliran, Biliran', type: 'Municipality', icon: '🏛️' },
        'kawayan': { name: 'Kawayan, Biliran', type: 'Municipality', icon: '🏛️' },
        'culaba': { name: 'Culaba, Biliran', type: 'Municipality', icon: '🏛️' },
        'caibiran': { name: 'Caibiran, Biliran', type: 'Municipality', icon: '🏛️' },
        'almeria': { name: 'Almeria, Biliran', type: 'Municipality', icon: '🏛️' },
        'maripipi': { name: 'Maripipi, Biliran', type: 'Municipality', icon: '🏛️' },
        
        // Leyte Province
        'tacloban': { name: 'Tacloban City, Leyte', type: 'City', icon: '🏙️' },
        'ormoc': { name: 'Ormoc City, Leyte', type: 'City', icon: '🏙️' },
        'baybay': { name: 'Baybay City, Leyte', type: 'City', icon: '🏙️' },
        'maasin': { name: 'Maasin City, Southern Leyte', type: 'City', icon: '🏙️' },
        'sogod': { name: 'Sogod, Southern Leyte', type: 'Municipality', icon: '🏛️' },
        'abuyog': { name: 'Abuyog, Leyte', type: 'Municipality', icon: '🏛️' },
        'palo': { name: 'Palo, Leyte', type: 'Municipality', icon: '🏛️' },
        'tanauan': { name: 'Tanauan, Leyte', type: 'Municipality', icon: '🏛️' },
        
        // Major Cities
        'cebu': { name: 'Cebu City, Cebu', type: 'City', icon: '🏙️' },
        'manila': { name: 'Manila, Metro Manila', type: 'City', icon: '🏙️' },
        'davao': { name: 'Davao City, Davao del Sur', type: 'City', icon: '🏙️' },
        'iloilo': { name: 'Iloilo City, Iloilo', type: 'City', icon: '🏙️' },
        'baguio': { name: 'Baguio City, Benguet', type: 'City', icon: '🏙️' },
        
        // Agricultural Regions
        'nueva ecija': { name: 'Nueva Ecija Province', type: 'Province', icon: '🌾' },
        'pangasinan': { name: 'Pangasinan Province', type: 'Province', icon: '🌾' },
        'bukidnon': { name: 'Bukidnon Province', type: 'Province', icon: '🌾' },
        
        // Common Areas
        'poblacion': { name: 'Poblacion (Town Center)', type: 'Barangay', icon: '📍' },
        'downtown': { name: 'Downtown Area', type: 'Area', icon: '📍' },
        'marasbaras': { name: 'Marasbaras, Tacloban', type: 'Barangay', icon: '📍' }
      };
      
      // Filter suggestions based on query
      const matches = [];
      for (const [key, location] of Object.entries(predefinedLocations)) {
        if (key.includes(query) || location.name.toLowerCase().includes(query)) {
          matches.push({ key, ...location });
        }
      }
      
      // Display suggestions
      if (matches.length > 0) {
        let html = '';
        matches.slice(0, 8).forEach(match => {
          html += `
            <div class="suggestion-item" onclick="selectSuggestion('${match.key}', '${match.name}')">
              <span class="suggestion-icon">${match.icon}</span>
              <span class="suggestion-text">${match.name}</span>
              <span class="suggestion-type">${match.type}</span>
            </div>
          `;
        });
        suggestionsDiv.innerHTML = html;
        suggestionsDiv.style.display = 'block';
      } else {
        suggestionsDiv.style.display = 'none';
      }
    }
    
    function hideSearchSuggestions() {
      setTimeout(() => {
        document.getElementById('searchSuggestions').style.display = 'none';
      }, 200);
    }
    
    function selectSuggestion(key, name) {
      document.getElementById('searchAddress').value = key;
      document.getElementById('searchSuggestions').style.display = 'none';
      searchLocation();
    }
    
    // Shopee-like Cart JavaScript
    function toggleSelectAll() {
      const selectAll = document.getElementById('selectAll');
      const itemCheckboxes = document.querySelectorAll('.item-checkbox:not(:disabled)'); // Only select non-disabled checkboxes
      const deleteBtn = document.getElementById('deleteSelectedBtn');
      
      itemCheckboxes.forEach(checkbox => {
        if (!checkbox.disabled) { // Double check not disabled
          checkbox.checked = selectAll.checked;
        }
      });
      
      updateSelection();
    }
    
    function updateSelection() {
      const itemCheckboxes = document.querySelectorAll('.item-checkbox:not(:disabled)'); // Exclude disabled checkboxes
      const selectAll = document.getElementById('selectAll');
      const deleteBtn = document.getElementById('deleteSelectedBtn');
      const selectedItems = [];
      
      let checkedCount = 0;
      itemCheckboxes.forEach(checkbox => {
        if (checkbox.checked && !checkbox.disabled) { // Double check not disabled
          checkedCount++;
          selectedItems.push(checkbox.value);
        }
      });
      
      selectAll.checked = checkedCount === itemCheckboxes.length && checkedCount > 0;
      selectAll.indeterminate = checkedCount > 0 && checkedCount < itemCheckboxes.length;
      
      deleteBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
      
      // Update selected items in form
      document.getElementById('selectedItemsInput').value = selectedItems.join(',');
      
      // Submit selection to server
      const form = document.getElementById('checkoutForm');
      const formData = new FormData(form);
      formData.set('selected_items', selectedItems);
      
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      }).then(() => {
        // Refresh page to update totals
        if (selectedItems.length !== getCurrentSelectedCount()) {
          window.location.reload();
        }
      });
      
      // Update checkout button based on selection
      updateCheckoutButton();
      
      // Update totals in real-time
      updateOrderSummary();
    }
    
    // Real-time order summary calculation
    function updateOrderSummary() {
      const checkedCheckboxes = document.querySelectorAll('.item-checkbox:checked:not(:disabled)');
      let totalAmount = 0;
      let totalItems = 0;
      
      checkedCheckboxes.forEach(checkbox => {
        const productId = checkbox.value;
        const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
        
        if (cartItem && cartItem.dataset.expired === '0') {
          const itemTotal = parseFloat(cartItem.dataset.total) || 0;
          const itemQuantity = parseInt(cartItem.dataset.quantity) || 0;
          
          totalAmount += itemTotal;
          totalItems += itemQuantity;
        }
      });
      
      // Update the display
      document.getElementById('selectedItemsCount').textContent = totalItems;
      document.getElementById('selectedSubtotal').textContent = '₱' + totalAmount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      document.getElementById('finalTotal').textContent = '₱' + totalAmount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }
    
    function getCurrentSelectedCount() {
      return document.querySelectorAll('.item-checkbox:checked').length;
    }
    
    function updateQuantity(productId, change, currentQty) {
      const newQty = Math.max(1, currentQty + change);
      
      // Update the display immediately
      const qtyDisplay = document.getElementById(`qty-${productId}`);
      const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
      
      if (qtyDisplay && cartItem) {
        qtyDisplay.textContent = newQty;
        
        // Update data attributes for real-time calculation
        const price = parseFloat(cartItem.dataset.price) || 0;
        const newTotal = price * newQty;
        cartItem.dataset.quantity = newQty;
        cartItem.dataset.total = newTotal;
        
        // Update item total display
        const itemTotalElement = cartItem.querySelector('.item-total');
        if (itemTotalElement) {
          itemTotalElement.textContent = '₱' + newTotal.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          });
        }
        
        // Update order summary immediately
        updateOrderSummary();
      }
      
      // Submit to server
      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = `
        <input type="hidden" name="action" value="update_quantity">
        <input type="hidden" name="product_id" value="${productId}">
        <input type="hidden" name="quantity" value="${newQty}">
      `;
      
      document.body.appendChild(form);
      form.submit();
    }
    
    function removeItem(productId) {
      if (confirm('Remove this item from cart?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="remove_item">
          <input type="hidden" name="product_id" value="${productId}">
        `;
        
        document.body.appendChild(form);
        form.submit();
      }
    }
    
    function deleteSelected() {
      const selectedItems = document.querySelectorAll('.item-checkbox:checked');
      if (selectedItems.length === 0) return;
      
      if (confirm(`Remove ${selectedItems.length} selected items from cart?`)) {
        selectedItems.forEach(checkbox => {
          removeItem(checkbox.value);
        });
      }
    }
    
    // Map variables
    let deliveryMap;
    let deliveryMarker;
    const defaultCoords = [11.5564, 124.3992]; // Naval, Biliran coordinates
    
    // Initialize map when modal opens
    function openAddressModal() {
      document.getElementById('addressModal').style.display = 'block';
      
      // Initialize map after modal is visible
      setTimeout(() => {
        try {
          if (!deliveryMap && typeof L !== 'undefined') {
            deliveryMap = L.map('mapContainer', {
              center: defaultCoords,
              zoom: 15,
              zoomControl: true,
              attributionControl: true,
              scrollWheelZoom: true,
              doubleClickZoom: true,
              touchZoom: true,
              boxZoom: true,
              keyboard: true,
              dragging: true,
              tap: true,
              zoomSnap: 0.5,
              zoomDelta: 0.5,
              wheelPxPerZoomLevel: 60,
              maxZoom: 20,
              minZoom: 8
            });
            
            // Add multiple high-quality tile layers for better accuracy
            const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '© OpenStreetMap contributors',
              maxZoom: 19
            });
            
            const cartoLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
              attribution: '© CARTO, © OpenStreetMap contributors',
              maxZoom: 20
            });
            
            const esriLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
              attribution: '© Esri, Maxar, Earthstar Geographics',
              maxZoom: 19
            });
            
            // Use the high-quality Carto layer by default (similar to Google Maps style)
            const defaultLayer = cartoLayer;
            
            defaultLayer.addTo(deliveryMap);
            
            // Add layer control for switching between map types
            const baseLayers = {
              "Street Map": cartoLayer,
              "OpenStreetMap": osmLayer,
              "Satellite": esriLayer
            };
            
            L.control.layers(baseLayers).addTo(deliveryMap);
            
            // Add click event to set location
            deliveryMap.on('click', function(e) {
              setMapLocation(e.latlng.lat, e.latlng.lng);
            });
            
            // If there are existing coordinates, set them on the map
            <?php if ($deliveryInfo && !empty($deliveryInfo['coordinates'])): ?>
            const coords = '<?= $deliveryInfo['coordinates'] ?>'.split(',');
            if (coords.length === 2) {
              const lat = parseFloat(coords[0]);
              const lng = parseFloat(coords[1]);
              if (!isNaN(lat) && !isNaN(lng)) {
                setMapLocation(lat, lng);
              }
            }
            <?php endif; ?>
            
            showMapStatus('🗺️ Click on the map to set your delivery location', 'info');
            
          } else if (deliveryMap) {
            // Map already exists, just invalidate size
            deliveryMap.invalidateSize();
          }
        } catch (error) {
          console.error('Map initialization error:', error);
          showMapStatus('❌ Map failed to load. Please refresh the page.', 'error');
        }
      }, 300);
    }
    
    function showMapStatus(message, type) {
      let statusDiv = document.getElementById('mapStatus');
      if (!statusDiv) {
        statusDiv = document.createElement('div');
        statusDiv.id = 'mapStatus';
        statusDiv.style.cssText = `
          position: absolute;
          top: 10px;
          left: 10px;
          right: 10px;
          padding: 10px;
          border-radius: 5px;
          z-index: 1000;
          font-weight: bold;
          text-align: center;
        `;
        document.getElementById('mapContainer').appendChild(statusDiv);
      }
      
      statusDiv.textContent = message;
      statusDiv.className = `map-status ${type}`;
      
      // Style based on type
      switch(type) {
        case 'success':
          statusDiv.style.background = '#d4edda';
          statusDiv.style.color = '#155724';
          statusDiv.style.border = '1px solid #c3e6cb';
          break;
        case 'warning':
          statusDiv.style.background = '#fff3cd';
          statusDiv.style.color = '#856404';
          statusDiv.style.border = '1px solid #ffeaa7';
          break;
        case 'error':
          statusDiv.style.background = '#f8d7da';
          statusDiv.style.color = '#721c24';
          statusDiv.style.border = '1px solid #f5c6cb';
          break;
      }
      
      statusDiv.style.display = 'block';
    }
    
    function hideMapStatus() {
      const statusDiv = document.getElementById('mapStatus');
      if (statusDiv) {
        statusDiv.style.display = 'none';
      }
    }
    
    function closeAddressModal() {
      document.getElementById('addressModal').classList.remove('show');
    }
    
    function setMapLocation(lat, lng) {
      // Enhanced coordinate validation with precision
      const precision = 6; // 6 decimal places for ~1 meter accuracy
      lat = parseFloat(parseFloat(lat).toFixed(precision));
      lng = parseFloat(parseFloat(lng).toFixed(precision));
      
      if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        console.error('Invalid coordinates:', lat, lng);
        showMapStatus('❌ Invalid coordinates provided', 'error');
        return;
      }
      
      // Philippines bounds validation for accuracy
      if (lat < 4.0 || lat > 21.5 || lng < 116.0 || lng > 127.0) {
        console.warn('Coordinates outside Philippines bounds:', lat, lng);
        showMapStatus('⚠️ Location appears to be outside Philippines', 'warning');
      }
      
      try {
        // Remove existing marker
        if (deliveryMarker) {
          deliveryMap.removeLayer(deliveryMarker);
        }
        
        // Create high-precision marker with enhanced styling
        deliveryMarker = L.marker([lat, lng], {
          icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [30, 45],
            iconAnchor: [15, 45],
            popupAnchor: [1, -38],
            shadowSize: [45, 45]
          }),
          draggable: true,
          title: `Delivery Location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`,
          alt: 'Delivery Location Marker'
        }).addTo(deliveryMap);
        
        // Add drag event for real-time updates
        deliveryMarker.on('dragend', function(e) {
          const newLat = e.target.getLatLng().lat;
          const newLng = e.target.getLatLng().lng;
          setMapLocation(newLat, newLng);
          reverseGeocode(newLat, newLng);
        });
        
        // Add enhanced popup with accuracy information
        const accuracy = navigator.geolocation ? '±10m accuracy' : 'Click accuracy';
        deliveryMarker.bindPopup(`
          <div class="delivery-marker-popup">
            <h4>📍 Delivery Location</h4>
            <p><strong>Coordinates:</strong><br>${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
            <p><strong>Accuracy:</strong> ${accuracy}</p>
            <p><em>Drag marker to adjust position</em></p>
            <p><small>This location will be shared with the farmer for delivery.</small></p>
          </div>
        `);
        
        // Set view with higher zoom for precision
        deliveryMap.setView([lat, lng], 17);
        document.getElementById('deliveryCoordinates').value = lat + ',' + lng;
        
        // Trigger reverse geocoding for address
        reverseGeocode(lat, lng);
        
        // Show success feedback with coordinates
        showMapStatus(`✅ Location set: ${lat.toFixed(4)}, ${lng.toFixed(4)}`, 'success');
        setTimeout(() => hideMapStatus(), 3000);
        
      } catch (error) {
        console.error('Error setting map location:', error);
        showMapStatus(' Error setting location. Please try again.', 'error');
      }
    }
    
    // Enhanced reverse geocoding function
    function reverseGeocode(lat, lng) {
      // Enhanced reverse geocoding with better parameters for street-level accuracy
      const reverseUrl = `https://nominatim.openstreetmap.org/reverse?` + new URLSearchParams({
        format: 'json',
        lat: lat,
        lon: lng,
        zoom: '18', // High zoom for street-level accuracy
        addressdetails: '1',
        extratags: '1',
        namedetails: '1',
        'accept-language': 'en,fil'
      });
      
      // Show loading indicator
      const addressField = document.getElementById('deliveryAddress');
      addressField.value = '🔍 Getting street address...';
      
      fetch(reverseUrl, {
        headers: {
          'User-Agent': 'FARMLINK-App/1.0'
        }
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Reverse geocoding result:', data);
        
        if (data && data.display_name) {
          // Build detailed address with street information
          let cleanAddress = data.display_name;
          
          if (data.address) {
            const addr = data.address;
            const parts = [];
            
            // Include street/road information for delivery guidance
            if (addr.house_number && addr.road) {
              parts.push(`${addr.house_number} ${addr.road}`);
            } else if (addr.road) {
              parts.push(addr.road);
            }
            
            // Add landmarks if available
            if (addr.amenity) {
              parts.push(`near ${addr.amenity}`);
            }
            
            // Add barangay/village
            if (addr.village) parts.push(addr.village);
            else if (addr.suburb) parts.push(addr.suburb);
            else if (addr.neighbourhood) parts.push(addr.neighbourhood);
            
            // Add city/municipality
            if (addr.city) parts.push(addr.city);
            else if (addr.town) parts.push(addr.town);
            else if (addr.municipality) parts.push(addr.municipality);
            
            // Add province
            if (addr.state) parts.push(addr.state);
            else if (addr.province) parts.push(addr.province);
            
            // Add country
            if (addr.country) parts.push(addr.country);
            
            if (parts.length > 0) {
              cleanAddress = parts.join(', ');
            }
          }
          
          addressField.value = cleanAddress;
          showMapStatus('✅ Street address found from map location', 'success');
          setTimeout(() => hideMapStatus(), 3000);
          
        } else {
          throw new Error('No address data received');
        }
      })
      .catch(error => {
        console.error('Reverse geocoding error:', error);
        
        // Fallback with different zoom
        const fallbackUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`;
        
        fetch(fallbackUrl, {
          headers: {
            'User-Agent': 'FARMLINK-App/1.0'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data && data.display_name) {
            addressField.value = data.display_name;
            showMapStatus('✅ Address found (approximate)', 'success');
            setTimeout(() => hideMapStatus(), 3000);
          } else {
            // Final fallback based on Naval area
            addressField.value = `Pagsanghan Street area, Naval, Biliran, Philippines`;
            showMapStatus('⚠️ Using approximate location. Please edit if needed.', 'warning');
            setTimeout(() => hideMapStatus(), 4000);
          }
        })
        .catch(fallbackError => {
          console.error('Fallback geocoding failed:', fallbackError);
          addressField.value = `Naval Town Center, Naval, Biliran, Philippines`;
          showMapStatus('⚠️ Could not get exact address. Please edit if needed.', 'warning');
          setTimeout(() => hideMapStatus(), 4000);
        });
      });
    }
    
    function searchLocation() {
      const query = document.getElementById('searchAddress').value;
      if (!query) {
        showMapStatus('Please enter a location to search', 'warning');
        return;
      }
      
      const searchBtn = document.getElementById('searchBtn');
      const searchStatus = document.getElementById('searchStatus');
      
      // Show loading state
      searchBtn.disabled = true;
      searchBtn.textContent = 'Searching...';
      searchStatus.className = 'search-status loading';
      searchStatus.textContent = '🔍 Searching for location...';
      
      // Enhanced predefined locations with precise coordinates for Philippine areas
      const predefinedLocations = {
        // Biliran Province (Main agricultural area) - Updated with precise coordinates
        'naval': { lat: 11.5564, lng: 124.3992, name: 'Naval, Biliran, Philippines' },
        'biliran': { lat: 11.4656, lng: 124.4731, name: 'Biliran, Biliran, Philippines' },
        'larrazabal': { lat: 11.5500, lng: 124.3950, name: 'Larrazabal, Naval, Biliran, Philippines' },
        'kawayan': { lat: 11.3333, lng: 124.3833, name: 'Kawayan, Biliran, Philippines' },
        'culaba': { lat: 11.4667, lng: 124.4167, name: 'Culaba, Biliran, Philippines' },
        'caibiran': { lat: 11.5333, lng: 124.5667, name: 'Caibiran, Biliran, Philippines' },
        'almeria': { lat: 11.3833, lng: 124.4000, name: 'Almeria, Biliran, Philippines' },
        'maripipi': { lat: 11.2333, lng: 124.2833, name: 'Maripipi, Biliran, Philippines' },
        
        // Leyte Province (Major agricultural region)
        'tacloban': { lat: 11.2447, lng: 125.0047, name: 'Tacloban City, Leyte, Philippines' },
        'ormoc': { lat: 11.0059, lng: 124.6074, name: 'Ormoc City, Leyte, Philippines' },
        'baybay': { lat: 10.6794, lng: 124.8003, name: 'Baybay City, Leyte, Philippines' },
        'maasin': { lat: 10.1306, lng: 124.8428, name: 'Maasin City, Southern Leyte, Philippines' },
        'sogod': { lat: 10.3833, lng: 124.9833, name: 'Sogod, Southern Leyte, Philippines' },
        'abuyog': { lat: 10.7472, lng: 125.0139, name: 'Abuyog, Leyte, Philippines' },
        'palo': { lat: 11.1556, lng: 124.9917, name: 'Palo, Leyte, Philippines' },
        'tanauan': { lat: 11.1167, lng: 125.0167, name: 'Tanauan, Leyte, Philippines' },
        
        // Samar Province
        'catbalogan': { lat: 11.7756, lng: 124.8806, name: 'Catbalogan City, Samar, Philippines' },
        'calbayog': { lat: 12.0667, lng: 124.6000, name: 'Calbayog City, Samar, Philippines' },
        'borongan': { lat: 11.6333, lng: 125.4333, name: 'Borongan City, Eastern Samar, Philippines' },
        
        // Major Cities (Reference points)
        'cebu': { lat: 10.3157, lng: 123.8854, name: 'Cebu City, Cebu, Philippines' },
        'manila': { lat: 14.5995, lng: 120.9842, name: 'Manila, Metro Manila, Philippines' },
        'davao': { lat: 7.0731, lng: 125.6128, name: 'Davao City, Davao del Sur, Philippines' },
        'iloilo': { lat: 10.7202, lng: 122.5621, name: 'Iloilo City, Iloilo, Philippines' },
        'cagayan de oro': { lat: 8.4542, lng: 124.6319, name: 'Cagayan de Oro City, Misamis Oriental, Philippines' },
        'baguio': { lat: 16.4023, lng: 120.5960, name: 'Baguio City, Benguet, Philippines' },
        
        // Agricultural Regions
        'nueva ecija': { lat: 15.5784, lng: 121.1113, name: 'Cabanatuan City, Nueva Ecija, Philippines' },
        'pangasinan': { lat: 15.8617, lng: 120.2727, name: 'Lingayen, Pangasinan, Philippines' },
        'isabela': { lat: 16.9746, lng: 121.8081, name: 'Ilagan City, Isabela, Philippines' },
        'bukidnon': { lat: 8.1570, lng: 125.1264, name: 'Malaybalay City, Bukidnon, Philippines' },
        'negros occidental': { lat: 10.6767, lng: 122.9540, name: 'Bacolod City, Negros Occidental, Philippines' },
        'negros oriental': { lat: 9.3016, lng: 123.3016, name: 'Dumaguete City, Negros Oriental, Philippines' },
        
        // Common Barangays and Areas
        'poblacion': { lat: 11.2445, lng: 124.0055, name: 'Poblacion, Naval, Biliran, Philippines' },
        'downtown': { lat: 11.2447, lng: 125.0047, name: 'Downtown, Tacloban City, Leyte, Philippines' },
        'marasbaras': { lat: 11.2500, lng: 125.0100, name: 'Marasbaras, Tacloban City, Leyte, Philippines' },
        'sagkahan': { lat: 11.2400, lng: 125.0000, name: 'Sagkahan, Tacloban City, Leyte, Philippines' }
      };
      
      const queryLower = query.toLowerCase().trim();
      console.log('Searching for:', queryLower);
      
      // Try exact match first
      let predefined = predefinedLocations[queryLower];
      console.log('Exact match found:', predefined);
      
      // If no exact match, try partial matching
      if (!predefined) {
        for (const [key, location] of Object.entries(predefinedLocations)) {
          if (queryLower.includes(key) || key.includes(queryLower)) {
            predefined = location;
            console.log('Partial match found:', key, location);
            break;
          }
        }
      }
      
      if (predefined) {
        console.log('Using predefined location:', predefined);
        setMapLocation(predefined.lat, predefined.lng);
        document.getElementById('deliveryAddress').value = predefined.name;
        
        searchStatus.className = 'search-status success';
        searchStatus.textContent = '✅ Exact match found! Click on the map to adjust if needed.';
        
        // Show confirmation with location type
        showMapStatus(`📍 Found exact match: ${predefined.name.split(',')[0]}`, 'success');
        setTimeout(() => hideMapStatus(), 3000);
        
        searchBtn.disabled = false;
        searchBtn.textContent = 'Search';
        
        setTimeout(() => {
          searchStatus.style.display = 'none';
        }, 3000);
        return;
      }
      
      // Enhanced search with Philippines context and better parameters
      let searchQuery = query.trim();
      
      // Add Philippines context if not already present
      if (!searchQuery.toLowerCase().includes('philippines') && !searchQuery.toLowerCase().includes('ph')) {
        searchQuery = searchQuery + ', Philippines';
      }
      
      // Enhanced multi-service geocoding for maximum accuracy
      const nominatimUrl = `https://nominatim.openstreetmap.org/search?` + new URLSearchParams({
        format: 'json',
        q: searchQuery,
        limit: '15',
        countrycodes: 'ph',
        addressdetails: '1',
        extratags: '1',
        namedetails: '1',
        'accept-language': 'en,fil',
        bounded: '1',
        viewbox: '116.0,4.0,127.0,21.0', // Philippines bounding box
        dedupe: '1',
        polygon_geojson: '1'
      });
      
      // Backup geocoding service
      const photonUrl = `https://photon.komoot.io/api/?q=${encodeURIComponent(searchQuery)}&bbox=116.0,4.0,127.0,21.0&limit=10&lang=en`;
      
      const searchUrl = nominatimUrl;
      
      fetch(searchUrl, {
        method: 'GET',
        headers: {
          'User-Agent': 'FARMLINK-App/1.0'
        }
      })
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          return response.json();
        })
        .then(data => {
          if (data && data.length > 0) {
            console.log('Search results:', data);
            
            // Advanced result processing with scoring system
            let bestResult = null;
            let bestScore = -1;
            
            for (const result of data) {
              const displayName = result.display_name.toLowerCase();
              const originalQuery = query.toLowerCase().trim();
              let score = 0;
              
              // Score based on result type (higher is better)
              const typeScores = {
                'city': 100,
                'town': 90,
                'municipality': 85,
                'administrative': 80,
                'village': 70,
                'hamlet': 60,
                'suburb': 55,
                'neighbourhood': 50,
                'building': 40,
                'house': 30
              };
              
              score += typeScores[result.type] || 20;
              
              // Boost score for exact name matches at the beginning
              const nameParts = displayName.split(',');
              const firstPart = nameParts[0].trim();
              
              if (firstPart === originalQuery) {
                score += 200; // Exact match bonus
              } else if (firstPart.startsWith(originalQuery)) {
                score += 150; // Starts with query bonus
              } else if (firstPart.includes(originalQuery)) {
                score += 100; // Contains query bonus
              }
              
              // Boost score for Philippine administrative divisions
              if (displayName.includes('philippines')) {
                score += 50;
              }
              
              // Boost score for known agricultural regions
              const agriculturalRegions = ['biliran', 'leyte', 'samar', 'nueva ecija', 'pangasinan', 'bukidnon', 'negros'];
              for (const region of agriculturalRegions) {
                if (displayName.includes(region)) {
                  score += 30;
                  break;
                }
              }
              
              // Penalize very generic or unclear results
              if (result.type === 'house' && !result.house_number) {
                score -= 50;
              }
              
              // Boost score for results with proper administrative hierarchy
              if (result.address) {
                const address = result.address;
                if (address.city || address.town || address.municipality) score += 25;
                if (address.state || address.province) score += 20;
                if (address.country === 'Philippines') score += 15;
              }
              
              console.log(`Result: ${result.display_name}, Type: ${result.type}, Score: ${score}`);
              
              if (score > bestScore) {
                bestScore = score;
                bestResult = result;
              }
            }
            
            if (!bestResult) {
              bestResult = data[0]; // Fallback to first result
            }
            
            console.log(`Best result selected: ${bestResult.display_name} (Score: ${bestScore})`);
            
            // Validate coordinates are within reasonable bounds for Philippines
            const lat = parseFloat(bestResult.lat);
            const lng = parseFloat(bestResult.lon);
            
            if (isNaN(lat) || isNaN(lng)) {
              throw new Error('Invalid coordinates received');
            }
            
            // Philippines bounds check (more lenient)
            if (lat < 4.0 || lat > 21.0 || lng < 116.0 || lng > 127.0) {
              console.warn('Coordinates outside Philippines bounds, but proceeding:', lat, lng);
            }
            
            setMapLocation(lat, lng);
            
            // Create a cleaner address display
            const addressParts = bestResult.display_name.split(',');
            const cleanAddress = addressParts.slice(0, 3).join(', '); // Show first 3 parts for clarity
            document.getElementById('deliveryAddress').value = bestResult.display_name;
            
            searchStatus.className = 'search-status success';
            searchStatus.textContent = `✅ Found: ${cleanAddress}! Click on the map to adjust if needed.`;
            
            // Show additional info about the result
            if (bestResult.type) {
              const resultType = bestResult.type.charAt(0).toUpperCase() + bestResult.type.slice(1);
              showMapStatus(`📍 Located ${resultType}: ${addressParts[0]}`, 'success');
              setTimeout(() => hideMapStatus(), 3000);
            }
            
            setTimeout(() => {
              searchStatus.style.display = 'none';
            }, 4000);
          } else {
            // Fallback: try searching without Philippines suffix
            if (searchQuery.includes('Philippines')) {
              const fallbackQuery = query.replace(', Philippines', '');
              searchLocationFallback(fallbackQuery);
            } else {
              searchStatus.className = 'search-status error';
              searchStatus.textContent = '❌ Location not found. Try a more specific address or use "My Location".';
            }
          }
        })
        .catch(error => {
          console.error('Search error:', error);
          
          // Reset search button state
          searchBtn.disabled = false;
          searchBtn.textContent = 'Search';
          
          // Try alternative search without country restriction
          searchLocationAlternative(query, searchBtn, searchStatus);
        })
        .finally(() => {
          // Ensure button is always re-enabled
          if (searchBtn.disabled) {
            searchBtn.disabled = false;
            searchBtn.textContent = 'Search';
          }
        });
    }
    
    function searchLocationAlternative(query, searchBtn, searchStatus) {
      // Try without country restriction and with simpler parameters
      const simpleQuery = query.replace(', Philippines', '');
      const altUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(simpleQuery)}&limit=10`;
      
      fetch(altUrl)
        .then(response => response.json())
        .then(data => {
          if (data && data.length > 0) {
            // Filter results for Philippines if possible
            let result = data.find(item => 
              item.display_name && (
                item.display_name.includes('Philippines') || 
                item.display_name.includes('Biliran') ||
                item.display_name.includes('Leyte')
              )
            ) || data[0];
            
            const lat = parseFloat(result.lat);
            const lng = parseFloat(result.lon);
            
            if (!isNaN(lat) && !isNaN(lng)) {
              setMapLocation(lat, lng);
              document.getElementById('deliveryAddress').value = result.display_name;
              
              searchStatus.className = 'search-status success';
              searchStatus.textContent = '✅ Location found! Click on the map to adjust if needed.';
              
              setTimeout(() => {
                searchStatus.style.display = 'none';
              }, 3000);
            } else {
              throw new Error('Invalid coordinates');
            }
          } else {
            searchStatus.className = 'search-status error';
            searchStatus.textContent = '❌ Location not found. Try "My Location" or click on the map.';
          }
        })
        .catch(error => {
          console.error('Alternative search error:', error);
          searchStatus.className = 'search-status error';
          searchStatus.textContent = '❌ Search failed. Use "My Location" or click on the map to set address.';
        })
        .finally(() => {
          searchBtn.disabled = false;
          searchBtn.textContent = 'Search';
        });
    }
    
    function searchLocationFallback(query) {
      const searchStatus = document.getElementById('searchStatus');
      
      fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`)
        .then(response => response.json())
        .then(data => {
          if (data && data.length > 0) {
            const result = data[0];
            const lat = parseFloat(result.lat);
            const lng = parseFloat(result.lon);
            
            setMapLocation(lat, lng);
            document.getElementById('deliveryAddress').value = result.display_name;
            
            searchStatus.className = 'search-status success';
            searchStatus.textContent = '✅ Location found! Click on the map to adjust if needed.';
            
            setTimeout(() => {
              searchStatus.style.display = 'none';
            }, 3000);
          } else {
            searchStatus.className = 'search-status error';
            searchStatus.textContent = '❌ Location not found. Try a more specific address or use "My Location".';
          }
        })
        .catch(error => {
          console.error('Fallback search error:', error);
          searchStatus.className = 'search-status error';
          searchStatus.textContent = '❌ Unable to search. Please use "My Location" or click on the map.';
        });
    }
    
    function getCurrentLocation() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            setMapLocation(lat, lng);
            reverseGeocode(lat, lng);
          },
          function(error) {
            alert('Unable to get your location. Please search manually.');
          }
        );
      } else {
        alert('Geolocation is not supported by this browser.');
      }
    }
    
    function reverseGeocode(lat, lng) {
      // Enhanced reverse geocoding with better parameters and error handling
      const reverseUrl = `https://nominatim.openstreetmap.org/reverse?` + new URLSearchParams({
        format: 'json',
        lat: lat,
        lon: lng,
        zoom: '16', // Slightly lower zoom for better street-level results
        addressdetails: '1',
        extratags: '1',
        namedetails: '1',
        'accept-language': 'en,fil'
      });
      
      // Show loading indicator
      const addressField = document.getElementById('deliveryAddress');
      const originalValue = addressField.value;
      addressField.value = '🔍 Getting address...';
      
      fetch(reverseUrl, {
        headers: {
          'User-Agent': 'FARMLINK-App/1.0'
        }
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Reverse geocoding result:', data);
        
        if (data && data.display_name) {
          // Clean up the address for better display
          let cleanAddress = data.display_name;
          
          // If we have address components, try to build a detailed address with streets
          if (data.address) {
            const addr = data.address;
            const parts = [];
            
            // Include street/road information for delivery guidance
            if (addr.road) {
              parts.push(addr.road);
            }
            
            // Add specific landmarks or amenities if available
            if (addr.amenity) {
              parts.push(`near ${addr.amenity}`);
            }
            
            // Add village/suburb/neighbourhood (barangay level)
            if (addr.village) parts.push(addr.village);
            else if (addr.suburb) parts.push(addr.suburb);
            else if (addr.neighbourhood) parts.push(addr.neighbourhood);
            
            // Add city/town/municipality
            if (addr.city) parts.push(addr.city);
            else if (addr.town) parts.push(addr.town);
            else if (addr.municipality) parts.push(addr.municipality);
            
            // Add state/province
            if (addr.state) parts.push(addr.state);
            else if (addr.province) parts.push(addr.province);
            
            // Add country
            if (addr.country) parts.push(addr.country);
            
            if (parts.length > 0) {
              cleanAddress = parts.join(', ');
            }
          }
          
          addressField.value = cleanAddress;
          showMapStatus('✅ Address found from map location', 'success');
          setTimeout(() => hideMapStatus(), 3000);
          
        } else {
          throw new Error('No address data received');
        }
      })
      .catch(error => {
        console.error('Reverse geocoding error:', error);
        
        // Try alternative approach with different zoom level
        const fallbackUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=14&addressdetails=1`;
        
        fetch(fallbackUrl, {
          headers: {
            'User-Agent': 'FARMLINK-App/1.0'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data && data.display_name) {
            addressField.value = data.display_name;
            showMapStatus('✅ Address found (approximate location)', 'success');
            setTimeout(() => hideMapStatus(), 3000);
          } else {
            // Final fallback: use a descriptive format based on the map location
            addressField.value = `Naval Town Center, Naval, Biliran, Philippines`;
            showMapStatus('⚠️ Using approximate location. You can edit the address if needed.', 'warning');
            setTimeout(() => hideMapStatus(), 4000);
          }
        })
        .catch(fallbackError => {
          console.error('Fallback reverse geocoding also failed:', fallbackError);
          // Final fallback with specific Naval area reference
          addressField.value = `Naval Poblacion, Naval, Biliran, Philippines`;
          showMapStatus('⚠️ Could not get exact address. Please edit if needed.', 'warning');
          setTimeout(() => hideMapStatus(), 4000);
        });
      });
    }
    
    function viewOnMap(coordinates, address) {
      // If no coordinates, try to geocode the address
      if (!coordinates || coordinates.trim() === '') {
        if (!address || address.trim() === '') {
          alert('No location information available. Please set your delivery address first.');
          return;
        }
        
        // Show loading message
        const loadingAlert = document.createElement('div');
        loadingAlert.innerHTML = `
          <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                      background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); 
                      z-index: 10000; text-align: center;">
            <div style="margin-bottom: 10px;">🔍 Searching for location...</div>
            <div style="font-size: 14px; color: #666;">${address}</div>
          </div>
        `;
        document.body.appendChild(loadingAlert);
        
        // Try to geocode the address
        geocodeAddress(address)
          .then(result => {
            document.body.removeChild(loadingAlert);
            if (result) {
              createViewMapModal(result.lat, result.lng, address, true);
            } else {
              alert('Could not find location for this address. Please update your delivery address with a more specific location.');
            }
          })
          .catch(error => {
            document.body.removeChild(loadingAlert);
            console.error('Geocoding error:', error);
            alert('Error finding location. Please try updating your delivery address.');
          });
        return;
      }
      
      const coords = coordinates.split(',');
      if (coords.length !== 2) {
        alert('Invalid coordinates format.');
        return;
      }
      
      const lat = parseFloat(coords[0]);
      const lng = parseFloat(coords[1]);
      
      if (isNaN(lat) || isNaN(lng)) {
        alert('Invalid coordinates values.');
        return;
      }
      
      // Create a view-only map modal
      createViewMapModal(lat, lng, address);
    }
    
    // Geocoding function to find coordinates from address
    function geocodeAddress(address) {
      return new Promise((resolve, reject) => {
        // Add Philippines context if not present
        let searchQuery = address.trim();
        if (!searchQuery.toLowerCase().includes('philippines') && !searchQuery.toLowerCase().includes('ph')) {
          searchQuery = searchQuery + ', Philippines';
        }
        
        // Use Nominatim to geocode the address
        const geocodeUrl = `https://nominatim.openstreetmap.org/search?` + new URLSearchParams({
          format: 'json',
          q: searchQuery,
          limit: '5',
          countrycodes: 'ph',
          addressdetails: '1',
          bounded: '1',
          viewbox: '116.0,4.0,127.0,21.0' // Philippines bounding box
        });
        
        fetch(geocodeUrl, {
          headers: {
            'User-Agent': 'FARMLINK-App/1.0'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data && data.length > 0) {
            const result = data[0];
            const lat = parseFloat(result.lat);
            const lng = parseFloat(result.lon);
            
            if (!isNaN(lat) && !isNaN(lng)) {
              resolve({ lat, lng, display_name: result.display_name });
            } else {
              resolve(null);
            }
          } else {
            resolve(null);
          }
        })
        .catch(error => {
          reject(error);
        });
      });
    }
    
    function createViewMapModal(lat, lng, address, isGeocoded = false) {
      // Remove existing view modal if any
      const existingModal = document.getElementById('viewMapModal');
      if (existingModal) {
        existingModal.remove();
      }
      
      // Create modal HTML
      const geocodedNotice = isGeocoded ? 
        `<div style="background: #e8f5e8; padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; border-left: 4px solid #4CAF50;">
          <small><strong>📍 Location found by searching your address</strong></small>
        </div>` : '';
      
      const modalHTML = `
        <div id="viewMapModal" class="address-modal show">
          <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
              <h3>📍 Delivery Location</h3>
              <button type="button" class="close-modal" onclick="closeViewMapModal()">&times;</button>
            </div>
            
            ${geocodedNotice}
            
            <div class="view-map-info">
              <p><strong>Address:</strong> ${address}</p>
              <p><strong>Coordinates:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
            </div>
            
            <div id="viewMapContainer" class="map-container" style="height: 400px;"></div>
            
            <div class="modal-actions">
              <button type="button" class="btn btn-secondary" onclick="closeViewMapModal()">Close</button>
              <button type="button" class="btn btn-primary" onclick="openInGoogleMaps(${lat}, ${lng})">
                🗺️ Open in Google Maps
              </button>
              <button type="button" class="btn btn-success" onclick="updateAddressWithCoordinates(${lat}, ${lng}, '${address.replace(/'/g, "\\'")}')">
                💾 Save These Coordinates
              </button>
            </div>
          </div>
        </div>
      `;
      
      // Add modal to page
      document.body.insertAdjacentHTML('beforeend', modalHTML);
      
      // Initialize map after a short delay
      setTimeout(() => {
        initializeViewMap(lat, lng, address);
      }, 100);
    }
    
    function initializeViewMap(lat, lng, address) {
      try {
        // Initialize the view map
        const viewMap = L.map('viewMapContainer').setView([lat, lng], 15);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap contributors',
          maxZoom: 19
        }).addTo(viewMap);
        
        // Add marker with popup
        const marker = L.marker([lat, lng], {
          icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
          })
        }).addTo(viewMap);
        
        marker.bindPopup(`
          <div style="text-align: center;">
            <h4>📍 Delivery Location</h4>
            <p><strong>${address.split(',')[0]}</strong></p>
            <p><small>${lat.toFixed(6)}, ${lng.toFixed(6)}</small></p>
          </div>
        `).openPopup();
        
        // Ensure map renders properly
        setTimeout(() => {
          viewMap.invalidateSize();
        }, 200);
        
      } catch (error) {
        console.error('Error initializing view map:', error);
        alert('Error loading map. Please try again.');
      }
    }
    
    function closeViewMapModal() {
      const modal = document.getElementById('viewMapModal');
      if (modal) {
        modal.remove();
      }
    }
    
    // Add keyboard support for view modal
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const viewModal = document.getElementById('viewMapModal');
        if (viewModal) {
          closeViewMapModal();
        }
      }
    });
    
    function openInGoogleMaps(lat, lng) {
      const googleMapsUrl = `https://www.google.com/maps?q=${lat},${lng}`;
      window.open(googleMapsUrl, '_blank');
    }
    
    function updateAddressWithCoordinates(lat, lng, address) {
      // Create a form to submit the updated coordinates
      const form = document.createElement('form');
      form.method = 'POST';
      form.style.display = 'none';
      
      const actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = 'action';
      actionInput.value = 'save_delivery_address';
      
      const coordsInput = document.createElement('input');
      coordsInput.type = 'hidden';
      coordsInput.name = 'delivery_coordinates';
      coordsInput.value = `${lat},${lng}`;
      
      const addressInput = document.createElement('input');
      addressInput.type = 'hidden';
      addressInput.name = 'delivery_address';
      addressInput.value = address;
      
      const instructionsInput = document.createElement('input');
      instructionsInput.type = 'hidden';
      instructionsInput.name = 'delivery_instructions';
      instructionsInput.value = ''; // Keep existing instructions empty for now
      
      form.appendChild(actionInput);
      form.appendChild(coordsInput);
      form.appendChild(addressInput);
      form.appendChild(instructionsInput);
      
      document.body.appendChild(form);
      
      // Show confirmation
      if (confirm('Save these coordinates for your delivery address? This will help ensure accurate delivery location.')) {
        form.submit();
      } else {
        document.body.removeChild(form);
      }
    }
    
    // Close modal when clicking outside
    document.getElementById('addressModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeAddressModal();
      }
    });
    
    // Enhanced geolocation function
    function getCurrentLocation() {
      const locationBtn = document.getElementById('locationBtn');
      
      if (!navigator.geolocation) {
        showMapStatus(' Geolocation is not supported by this browser', 'error');
        return;
      }
      
      // Show loading state
      locationBtn.disabled = true;
      locationBtn.textContent = 'Getting Location...';
      showMapStatus(' Getting your current location...', 'warning');
      
      const options = {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 60000, // 1 minute for fresher location
        desiredAccuracy: 10, // 10 meters accuracy
        fallbackToIP: false
      };
      
      navigator.geolocation.getCurrentPosition(
        function(position) {
          const lat = position.coords.latitude;
          const lng = position.coords.longitude;
          
          setMapLocation(lat, lng);
          reverseGeocode(lat, lng);
          
          showMapStatus(' Current location found successfully!', 'success');
          setTimeout(() => hideMapStatus(), 3000);
          
          locationBtn.disabled = false;
          locationBtn.textContent = 'My Location';
        },
        function(error) {
          console.error('Geolocation error:', error);
          
          let errorMessage = ' ';
          switch(error.code) {
            case error.PERMISSION_DENIED:
              errorMessage += 'Location access denied. Please enable location services.';
              break;
            case error.POSITION_UNAVAILABLE:
              errorMessage += 'Location information unavailable. Try searching manually.';
              break;
            case error.TIMEOUT:
              errorMessage += 'Location request timed out. Try again or search manually.';
              break;
            default:
              errorMessage += 'Unknown location error. Try searching manually.';
              break;
          }
          
          showMapStatus(errorMessage, 'error');
          
          locationBtn.disabled = false;
          locationBtn.textContent = 'My Location';
        },
        options
      );
    }
    
    // Toggle delivery method functionality
    function toggleDeliveryMethod() {
      const deliveryMethodSelect = document.getElementById('deliveryMethodSelect');
      const deliveryMethod = deliveryMethodSelect.value;
      const deliveryAddressSection = document.getElementById('deliveryAddressSection');
      const pickupInfoSection = document.getElementById('pickupInfoSection');
      const checkoutDeliveryMethod = document.getElementById('checkoutDeliveryMethod');
      const checkoutButton = document.getElementById('checkoutButton');
      const checkoutButtonText = document.getElementById('checkoutButtonText');
      
      // Update hidden form field
      if (checkoutDeliveryMethod) {
        checkoutDeliveryMethod.value = deliveryMethod;
      }
      
      if (deliveryMethod === 'delivery') {
        deliveryAddressSection.style.display = 'block';
        if (pickupInfoSection) pickupInfoSection.style.display = 'none';
        
        // Update checkout button for delivery
        updateCheckoutButton();
      } else {
        deliveryAddressSection.style.display = 'none';
        if (pickupInfoSection) pickupInfoSection.style.display = 'block';
        showPickupInfo();
        
        // For pickup, no address required - enable checkout
        if (checkoutButton && checkoutButtonText) {
          const selectedCount = document.querySelectorAll('.item-checkbox:checked').length;
          if (selectedCount > 0) {
            checkoutButton.disabled = false;
            checkoutButtonText.textContent = `Checkout (${selectedCount} items) - Pickup`;
          }
        }
      }
    }
    
    // Update checkout button based on delivery method and address
    function updateCheckoutButton() {
      const deliveryMethod = document.getElementById('deliveryMethodSelect').value;
      const checkoutButton = document.getElementById('checkoutButton');
      const checkoutButtonText = document.getElementById('checkoutButtonText');
      const selectedCount = document.querySelectorAll('.item-checkbox:checked').length;
      
      if (!checkoutButton || !checkoutButtonText) return;
      
      if (selectedCount === 0) {
        checkoutButton.disabled = true;
        checkoutButtonText.textContent = 'Select items to checkout';
        return;
      }
      
      if (deliveryMethod === 'pickup') {
        checkoutButton.disabled = false;
        checkoutButtonText.textContent = `Checkout (${selectedCount} items) - Pickup`;
      } else {
        // For delivery, check if address is set
        const hasAddress = <?= $deliveryInfo ? 'true' : 'false' ?>;
        if (hasAddress) {
          checkoutButton.disabled = false;
          checkoutButtonText.textContent = `Checkout (${selectedCount} items) - Delivery`;
        } else {
          checkoutButton.disabled = true;
          checkoutButtonText.textContent = 'Set Delivery Address First';
        }
      }
    }
    
    // Confirm checkout with appropriate message
    function confirmCheckout() {
      const deliveryMethod = document.getElementById('deliveryMethodSelect').value;
      const selectedCount = document.querySelectorAll('.item-checkbox:checked').length;
      
      if (selectedCount === 0) {
        alert('Please select items to checkout');
        return false;
      }
      
      const message = deliveryMethod === 'pickup' 
        ? `Place order for ${selectedCount} items? You will receive pickup instructions from each farmer.`
        : `Place order for ${selectedCount} items for delivery?`;
        
      return confirm(message);
    }
    
    // Show pickup information for selected farmers
    function showPickupInfo() {
      // This will be enhanced to show farmer pickup locations
      console.log('Pickup method selected - showing farmer pickup locations');
    }

    // Initialize cart on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateSelection();
      
      // Initialize delivery method - show delivery address by default, hide pickup
      toggleDeliveryMethod();
      
      // Add Enter key support for address search
      const searchInput = document.getElementById('searchAddress');
      if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            searchLocation();
          }
        });
      }
    });
  </script>
</body>
</html>
