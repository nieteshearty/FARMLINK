<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require farmer role
$user = SessionManager::requireRole('farmer');

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $orderId = $_POST['order_id'];
        $status = $_POST['status'];
        
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND farmer_id = ?");
            $stmt->execute([$status, $orderId, $user['id']]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Order status updated successfully!";
                SessionManager::logActivity($user['id'], 'order', "Updated order #{$orderId} status to {$status}");
            } else {
                $_SESSION['error'] = "Unable to update order status.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to update order status.";
        }
        
        header('Location: farmer-orders.php');
        exit;
    }
}

// Get farmer's orders
$orders = DatabaseHelper::getOrders($user['id'], 'farmer');
$stats = DatabaseHelper::getStats('farmer', $user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • Orders</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
  <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/farmer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body data-page="farmer-orders">
  <nav>
    <div class="nav-left">
      <a href="farmer-dashboard.php"><img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="logo"></a>
      <span class="brand">FARMLINK - FARMER</span>
    </div>
    <span>Orders</span>
  </nav>

  <div class="sidebar">
    <a href="farmer-dashboard.php">Dashboard</a>
    <a href="farmer-products.php">My Products</a>
    <a href="farmer-orders.php" class="active">Orders</a>
    <a href="farmer-delivery-zones.php">Delivery Zones</a>
    <a href="farmer-profile.php">Profile</a>
    <a href="<?= BASE_URL ?>/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>Orders</h1>
    <p class="lead">Manage your incoming orders and track sales.</p>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="stats">
      <div class="card stat-card">
        <h3>Pending Orders</h3>
        <p><?= $stats['pending_orders'] ?></p>
      </div>
      <div class="card stat-card">
        <h3>Completed Orders</h3>
        <p><?= $stats['completed_orders'] ?></p>
      </div>
      <div class="card stat-card">
        <h3>Total Sales</h3>
        <p>₱<?= number_format($stats['total_sales'], 2) ?></p>
      </div>
      <div class="card stat-card">
        <h3>Total Products</h3>
        <p><?= $stats['total_products'] ?></p>
      </div>
    </section>

    <section class="table-wrap">
      <h3>Order Management</h3>
      <table>
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Buyer</th>
            <th>Delivery Address</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="6" style="text-align:center; padding:40px; color:#999;">
                No orders yet. Keep adding products to attract buyers!
              </td>
            </tr>
          <?php else: ?>
            <?php 
            $recentOrderIds = [];
            $recentThreshold = strtotime('-24 hours');
            foreach ($orders as $order) {
              if (strtotime($order['created_at']) > $recentThreshold) {
                $recentOrderIds[] = $order['id'];
              }
            }
            ?>
            <?php foreach ($orders as $order): ?>
              <?php $isRecent = in_array($order['id'], $recentOrderIds); ?>
              <tr class="order-row <?= $isRecent ? 'order-recent' : '' ?> <?= $order['status'] === 'pending' ? 'order-pending' : '' ?>" data-status="<?= $order['status'] ?>">
                <td>
                  <div class="order-id-cell">
                    #<?= $order['id'] ?>
                    <?php if ($isRecent): ?>
                      <span class="new-order-badge">NEW ORDER!</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="buyer-info">
                    <strong><?= htmlspecialchars($order['buyer_name']) ?></strong>
                    <?php if ($order['buyer_company'] ?? false): ?>
                      <br><small class="company-name"><?= htmlspecialchars($order['buyer_company']) ?></small>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="delivery-info">
                    <?php if (!empty($order['delivery_address'])): ?>
                      <div class="address-text">
                        📍 <?= htmlspecialchars($order['delivery_address']) ?>
                      </div>
                      <?php if (!empty($order['delivery_coordinates'])): ?>
                        <button type="button" class="view-location-btn" 
                                onclick="viewDeliveryLocation('<?= htmlspecialchars($order['delivery_coordinates']) ?>', '<?= htmlspecialchars($order['delivery_address']) ?>', '<?= htmlspecialchars($order['buyer_name']) ?>', <?= $order['id'] ?>)">
                          🗺️ View on Map
                        </button>
                      <?php endif; ?>
                      <?php if (!empty($order['delivery_instructions'])): ?>
                        <small class="delivery-instructions">📝 <?= htmlspecialchars($order['delivery_instructions']) ?></small>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="no-address">No delivery address</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="order-total">
                    ₱<?= number_format($order['total'], 2) ?>
                    <?php if ($isRecent): ?>
                      <br><small class="fresh-sale">💰 Fresh sale!</small>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="status-container">
                    <span class="status-badge status-<?= $order['status'] ?>">
                      <?php if ($order['status'] === 'pending'): ?>
                        🕐 <?= ucfirst($order['status']) ?>
                      <?php elseif ($order['status'] === 'completed'): ?>
                        ✅ <?= ucfirst($order['status']) ?>
                      <?php elseif ($order['status'] === 'cancelled'): ?>
                        ❌ <?= ucfirst($order['status']) ?>
                      <?php endif; ?>
                    </span>
                    <?php if ($order['status'] === 'pending'): ?>
                      <div class="action-needed">
                        <small>⚠️ Action needed</small>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="date-cell">
                    <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                    <?php if ($isRecent): ?>
                      <br><small class="time-recent">Just received!</small>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="action-buttons">
                    <a href="order-details.php?id=<?= $order['id'] ?>" class="btn btn-sm">View Details</a>
                    <?php if ($order['status'] === 'pending'): ?>
                      <form method="POST" style="display: inline; margin-left: 8px;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <select name="status" class="status-select" onchange="this.form.submit()">
                          <option value="pending" selected>Pending</option>
                          <option value="completed">Completed</option>
                          <option value="cancelled">Cancel</option>
                        </select>
                      </form>
                    <?php elseif ($order['status'] === 'completed'): ?>
                      <span class="completed-badge">Order fulfilled</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- Delivery Location Map Modal -->
  <div class="map-modal" id="deliveryMapModal">
    <div class="map-modal-content">
      <div class="map-modal-header">
        <h3 id="mapModalTitle">Buyer Delivery Location</h3>
        <button type="button" class="close-map-modal" onclick="closeDeliveryMap()">&times;</button>
      </div>
      
      <div class="buyer-details" id="buyerDetails">
        <!-- Buyer information will be populated here -->
      </div>
      
      <div id="deliveryMapContainer" class="delivery-map-container"></div>
      
      <div class="map-actions">
        <button type="button" class="btn-google-maps" id="googleMapsBtn">
          🌍 Open in Google Maps
        </button>
        <button type="button" class="btn-close" onclick="closeDeliveryMap()">Close</button>
      </div>
    </div>
  </div>

  <script>
    let deliveryMap = null;
    let deliveryMarker = null;
    
    function viewOrder(orderId) {
      window.location.href = 'order-details.php?id=' + orderId;
    }
    
    function viewDeliveryLocation(coordinates, address, buyerName, orderId) {
      // Enhanced validation and error handling
      if (!coordinates || coordinates.trim() === '') {
        showDeliveryAlert('❌ No delivery coordinates available for this order.', 'error');
        return;
      }
      
      const coords = coordinates.split(',');
      if (coords.length !== 2) {
        showDeliveryAlert('❌ Invalid coordinates format. Please ask buyer to update their delivery location.', 'error');
        return;
      }
      
      const lat = parseFloat(coords[0].trim());
      const lng = parseFloat(coords[1].trim());
      
      // Validate coordinate ranges
      if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        showDeliveryAlert('❌ Invalid coordinates. Please ask buyer to update their delivery location.', 'error');
        return;
      }
      
      // Show modal with loading state
      document.getElementById('deliveryMapModal').style.display = 'flex';
      showMapLoadingState();
      
      // Update buyer details
      document.getElementById('buyerDetails').innerHTML = `
        <div class="buyer-info-card">
          <h4>📦 Order #${orderId}</h4>
          <div class="buyer-detail">
            <strong>👤 Buyer:</strong> ${buyerName}
          </div>
          <div class="buyer-detail">
            <strong>📍 Address:</strong> ${address}
          </div>
          <div class="buyer-detail">
            <strong>🗺️ Coordinates:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}
          </div>
        </div>
      `;
      
      // Initialize or update map with enhanced error handling
      setTimeout(() => {
        try {
          if (!deliveryMap && typeof L !== 'undefined') {
            deliveryMap = L.map('deliveryMapContainer').setView([lat, lng], 15);
            
            // Add tile layer with error handling
            const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '© OpenStreetMap contributors',
              maxZoom: 19,
              timeout: 10000
            });
            
            tileLayer.on('tileerror', function(error) {
              console.warn('Map tile loading error:', error);
              showDeliveryAlert('⚠️ Map tiles loading slowly. Please wait...', 'warning');
            });
            
            tileLayer.addTo(deliveryMap);
            
            // Map ready event
            deliveryMap.whenReady(function() {
              hideMapLoadingState();
              showDeliveryAlert('✅ Delivery location loaded successfully!', 'success');
            });
            
          } else if (deliveryMap) {
            deliveryMap.setView([lat, lng], 15);
            hideMapLoadingState();
          } else {
            hideMapLoadingState();
            showDeliveryAlert('❌ Map library not loaded. Please refresh the page.', 'error');
            return;
          }
          
          // Remove existing marker
          if (deliveryMarker) {
            deliveryMap.removeLayer(deliveryMarker);
          }
          
          // Add enhanced delivery marker
          deliveryMarker = L.marker([lat, lng], {
            icon: L.icon({
              iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
              shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
              iconSize: [25, 41],
              iconAnchor: [12, 41],
              popupAnchor: [1, -34],
              shadowSize: [41, 41]
            })
          }).addTo(deliveryMap);
          
          // Add enhanced popup with delivery details
          deliveryMarker.bindPopup(`
            <div class="delivery-popup">
              <h4>🚚 Delivery Location</h4>
              <p><strong>👤 Buyer:</strong> ${buyerName}</p>
              <p><strong>📦 Order:</strong> #${orderId}</p>
              <p><strong>📍 Address:</strong> ${address}</p>
              <p><strong>🗺️ Coordinates:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
              <hr style="margin: 10px 0;">
              <p style="font-size: 12px; color: #666;"><em>Click the Google Maps button below to get directions</em></p>
            </div>
          `).openPopup();
          
          deliveryMap.invalidateSize();
          
          // Update Google Maps button with enhanced functionality
          document.getElementById('googleMapsBtn').onclick = function() {
            const googleMapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;
            window.open(googleMapsUrl, '_blank');
            showDeliveryAlert('🗺️ Opening Google Maps for directions...', 'success');
          };
          
        } catch (error) {
          console.error('Map initialization error:', error);
          hideMapLoadingState();
          showDeliveryAlert('❌ Error loading map. Please try again or refresh the page.', 'error');
        }
      }, 100);
    }
    
    function closeDeliveryMap() {
      document.getElementById('deliveryMapModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    document.getElementById('deliveryMapModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeDeliveryMap();
      }
    });
    
    // ESC key to close modal
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeDeliveryMap();
      }
    });
    
    // Helper functions for better user experience
    function showDeliveryAlert(message, type) {
      const alertDiv = document.createElement('div');
      alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        z-index: 10000;
        font-weight: bold;
        max-width: 400px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      `;
      
      switch(type) {
        case 'success':
          alertDiv.style.background = '#d4edda';
          alertDiv.style.color = '#155724';
          alertDiv.style.border = '1px solid #c3e6cb';
          break;
        case 'error':
          alertDiv.style.background = '#f8d7da';
          alertDiv.style.color = '#721c24';
          alertDiv.style.border = '1px solid #f5c6cb';
          break;
        case 'warning':
          alertDiv.style.background = '#fff3cd';
          alertDiv.style.color = '#856404';
          alertDiv.style.border = '1px solid #ffeaa7';
          break;
      }
      
      alertDiv.textContent = message;
      document.body.appendChild(alertDiv);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        if (alertDiv.parentNode) {
          alertDiv.parentNode.removeChild(alertDiv);
        }
      }, 5000);
      
      // Click to dismiss
      alertDiv.addEventListener('click', () => {
        if (alertDiv.parentNode) {
          alertDiv.parentNode.removeChild(alertDiv);
        }
      });
    }
    
    function showMapLoadingState() {
      const mapContainer = document.getElementById('deliveryMapContainer');
      if (mapContainer) {
        const loadingDiv = document.createElement('div');
        loadingDiv.id = 'mapLoading';
        loadingDiv.style.cssText = `
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          background: rgba(255,255,255,0.9);
          padding: 20px;
          border-radius: 8px;
          text-align: center;
          z-index: 1000;
          box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        `;
        loadingDiv.innerHTML = `
          <div style="font-size: 24px; margin-bottom: 10px;">🗺️</div>
          <div style="font-weight: bold; color: #2E7D32;">Loading delivery location...</div>
          <div style="font-size: 12px; color: #666; margin-top: 5px;">Please wait while we load the map</div>
        `;
        mapContainer.appendChild(loadingDiv);
      }
    }
    
    function hideMapLoadingState() {
      const loadingDiv = document.getElementById('mapLoading');
      if (loadingDiv && loadingDiv.parentNode) {
        loadingDiv.parentNode.removeChild(loadingDiv);
      }
    }
  </script>

  <style>
    /* Force agricultural green sidebar background */
    .sidebar {
      background: #2E7D32 !important;
    }
    
    .status-pending { color: #e67e22; font-weight: bold; }
    .status-completed { color: #27ae60; font-weight: bold; }
    .status-cancelled { color: #e74c3c; font-weight: bold; }
    
    select {
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

    /* Enhanced Order Highlighting */
    .order-recent {
      background-color: #e8f5e8;
      border-left: 4px solid #4CAF50;
      animation: highlightFade 3s ease-in-out;
    }

    .order-pending {
      border-left: 3px solid #f39c12;
      background-color: #fff8e1;
    }

    .new-order-badge {
      background: linear-gradient(45deg, #4CAF50, #66BB6A);
      color: white;
      font-size: 10px;
      padding: 3px 8px;
      border-radius: 12px;
      margin-left: 8px;
      font-weight: bold;
      animation: pulse 2s infinite;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }

    .buyer-info {
      line-height: 1.4;
    }

    .company-name {
      color: #666;
      font-style: italic;
    }

    .location {
      color: #4CAF50;
      font-weight: 500;
    }

    .fresh-sale {
      color: #2E7D32;
      font-weight: bold;
      animation: pulse 1.5s infinite;
    }

    .status-container {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .status-badge {
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: bold;
      font-size: 0.9em;
      display: inline-block;
    }

    .status-pending {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }

    .status-completed {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .status-cancelled {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .action-needed {
      color: #f39c12;
      font-weight: bold;
      animation: blink 2s infinite;
    }

    .time-recent {
      color: #4CAF50;
      font-weight: bold;
      font-style: italic;
    }

    .action-buttons {
      display: flex;
      flex-direction: column;
      gap: 8px;
      align-items: flex-start;
    }

    .status-select {
      padding: 6px 10px;
      border: 2px solid #4CAF50;
      border-radius: 6px;
      background: white;
      font-weight: bold;
      cursor: pointer;
    }

    .status-select:hover {
      background: #e8f5e8;
    }

    .completed-badge {
      background: #d4edda;
      color: #155724;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.85em;
      font-weight: bold;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 0.85em;
    }

    @keyframes highlightFade {
      0% { background-color: #c8e6c9; }
      50% { background-color: #e8f5e8; }
      100% { background-color: #f9f9f9; }
    }

    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.7; }
      100% { opacity: 1; }
    }

    @keyframes blink {
      0%, 50% { opacity: 1; }
      51%, 100% { opacity: 0.5; }
    }

    .order-recent:hover {
      background-color: #dcedc8;
    }

    .order-pending:hover {
      background-color: #fff3cd;
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
    
    /* Delivery Info Styles */
    .delivery-info {
      max-width: 200px;
    }
    
    .address-text {
      font-size: 12px;
      line-height: 1.4;
      margin-bottom: 8px;
      color: #333;
    }
    
    .view-location-btn {
      background: #4CAF50;
      color: white;
      border: none;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 11px;
      cursor: pointer;
      margin-bottom: 4px;
      display: block;
    }
    
    .view-location-btn:hover {
      background: #45a049;
    }
    
    .delivery-instructions {
      display: block;
      color: #666;
      font-style: italic;
      margin-top: 4px;
    }
    
    .no-address {
      color: #999;
      font-style: italic;
      font-size: 12px;
    }
    
    /* Map Modal Styles */
    .map-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    
    .map-modal-content {
      background: white;
      border-radius: 8px;
      width: 90%;
      max-width: 800px;
      max-height: 90vh;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }
    
    .map-modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px;
      border-bottom: 1px solid #eee;
      background: #f8f9fa;
    }
    
    .map-modal-header h3 {
      margin: 0;
      color: #2d6a4f;
    }
    
    .close-map-modal {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #999;
      padding: 0;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .close-map-modal:hover {
      color: #333;
    }
    
    .buyer-details {
      padding: 20px;
      background: #f8f9fa;
      border-bottom: 1px solid #eee;
    }
    
    .buyer-info-card {
      background: white;
      padding: 16px;
      border-radius: 8px;
      border-left: 4px solid #4CAF50;
    }
    
    .buyer-info-card h4 {
      margin: 0 0 12px 0;
      color: #2d6a4f;
    }
    
    .buyer-detail {
      margin: 8px 0;
      font-size: 14px;
    }
    
    .buyer-detail strong {
      color: #2d6a4f;
    }
    
    .delivery-map-container {
      height: 400px;
      width: 100%;
    }
    
    .map-actions {
      display: flex;
      justify-content: space-between;
      padding: 20px;
      background: #f8f9fa;
      border-top: 1px solid #eee;
    }
    
    .btn-google-maps {
      background: #4285f4;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: background 0.3s;
    }
    
    .btn-google-maps:hover {
      background: #3367d6;
    }
    
    .btn-close {
      background: #6c757d;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
    }
    
    .btn-close:hover {
      background: #545b62;
    }
    
    .delivery-popup {
      font-size: 14px;
      line-height: 1.4;
    }
    
    .delivery-popup h4 {
      margin: 0 0 8px 0;
      color: #2d6a4f;
    }
    
    .delivery-popup p {
      margin: 4px 0;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
      .map-modal-content {
        width: 95%;
        margin: 10px;
      }
      
      .delivery-map-container {
        height: 300px;
      }
      
      .map-actions {
        flex-direction: column;
        gap: 10px;
      }
      
      .delivery-info {
        max-width: none;
      }
      
      .address-text {
        font-size: 11px;
      }
    }
  </style>
  <script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>
