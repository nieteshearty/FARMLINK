<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require buyer role
$user = SessionManager::requireRole('buyer');

// Get dashboard statistics
$stats = DatabaseHelper::getStats('buyer', $user['id']);

// Get all farmers with their locations and product counts
$pdo = getDBConnection();
$stmt = $pdo->query("
    SELECT u.id, u.username, u.farm_name, u.location, 
           COUNT(p.id) as product_count,
           u.latitude, u.longitude
    FROM users u 
    LEFT JOIN products p ON u.id = p.farmer_id 
    WHERE u.role = 'farmer' 
    GROUP BY u.id, u.username, u.farm_name, u.location, u.latitude, u.longitude
    HAVING product_count > 0
    ORDER BY u.farm_name, u.username
");
$farmers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected farmer's products
$selectedFarmerId = $_GET['farmer_id'] ?? null;
$selectedFarmer = null;
$farmerProducts = [];

if ($selectedFarmerId) {
    // Get selected farmer info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'farmer'");
    $stmt->execute([$selectedFarmerId]);
    $selectedFarmer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get farmer's products
    if ($selectedFarmer) {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as farmer_name, u.farm_name, u.location as farmer_location,
                   u.latitude, u.longitude
            FROM products p 
            JOIN users u ON p.farmer_id = u.id 
            WHERE p.farmer_id = ? 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$selectedFarmerId]);
        $farmerProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Get recent products from all farmers
    $recentProducts = array_slice(DatabaseHelper::getProducts(), 0, 8);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • Buyer Dashboard</title>
  <link rel="icon" type="image/png" href="/FARMLINK/assets/img/farmlink.png">
  <link rel="stylesheet" href="/FARMLINK/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="/FARMLINK/assets/css/buyer.css?v=<?= time() ?>">
  <link rel="stylesheet" href="/FARMLINK/assets/css/logout-confirmation.css?v=<?= time() ?>">
  
  <!-- Profile Picture Enhancement Styles -->
  <style>
    .nav-right .profile-pic,
    .nav-right .profile-pic-default {
        width: 45px !important;
        height: 45px !important;
        border-radius: 50% !important;
        border: 3px solid #ffffff !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2) !important;
        transition: all 0.3s ease !important;
        display: inline-block !important;
        overflow: hidden !important;
        background: #2E7D32 !important;
        color: white !important;
        text-decoration: none !important;
        line-height: 39px !important;
        text-align: center !important;
        font-weight: bold !important;
        font-size: 16px !important;
        margin-left: 15px !important;
        margin-right: 10px !important;
        position: relative !important;
    }

    .nav-right .profile-pic img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        border-radius: 50% !important;
    }

    .nav-right .profile-pic:hover,
    .nav-right .profile-pic-default:hover {
        transform: scale(1.05) !important;
        border-color: #4CAF50 !important;
        box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4) !important;
    }

    /* Online status indicator */
    .nav-right .profile-pic::after,
    .nav-right .profile-pic-default::after {
        content: '' !important;
        position: absolute !important;
        bottom: 2px !important;
        right: 2px !important;
        width: 12px !important;
        height: 12px !important;
        background: #4CAF50 !important;
        border: 2px solid white !important;
        border-radius: 50% !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3) !important;
    }
  </style>
  
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""/>
  
  <!-- Leaflet JavaScript -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
          crossorigin=""></script>
</head>
<body data-page="buyer-dashboard">
  <nav>
    <div class="nav-left">
      <a href="buyer-dashboard.php"><img src="/FARMLINK/assets/img/farmlink.png" alt="FARMLINK" class="logo"></a>
      <span class="brand">FARMLINK - BUYER</span>
    </div>
    <div class="nav-right">
      <?php if ($user['profile_picture']): ?>
        <?php 
          // Handle different path formats for profile pictures
          $profilePicPath = $user['profile_picture'];
          
          // If the path doesn't start with /, it's likely just a filename
          if (!str_starts_with($profilePicPath, '/')) {
            $profilePicPath = '/FARMLINK/uploads/profiles/' . $profilePicPath;
          }
          // Note: If it's already a full path starting with /FARMLINK/, we keep it as is
        ?>
        <a href="buyer-profile.php"><img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile" class="profile-pic"></a>
      <?php else: ?>
        <a href="buyer-profile.php" class="profile-pic-default">
          <?= strtoupper(substr($user['username'], 0, 1)) ?>
        </a>
      <?php endif; ?>
      <span>Buyer Dashboard</span>
    </div>
  </nav>

  <div class="sidebar">
    <a href="buyer-dashboard.php" class="active">Dashboard</a>
    <a href="buyer-market.php">Browse Market</a>
    <a href="buyer-cart.php">Shopping Cart</a>
    <a href="buyer-orders.php">My Orders</a>
    <a href="buyer-profile.php">Profile</a>
    <a href="/FARMLINK/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>Welcome, <?= htmlspecialchars($user['username']) ?>!</h1>
    <p class="lead">Browse fresh farm products and manage your orders.</p>

    <section class="stats">
      <div class="card stat-card">
        <h3>Cart Items</h3>
        <p><?= $stats['cart_items'] ?></p>
      </div>
      <div class="card stat-card">
        <h3>Total Orders</h3>
        <p><?= $stats['total_orders'] ?></p>
      </div>
      <div class="card stat-card">
        <h3>Pending Orders</h3>
        <p><?= $stats['pending_orders'] ?></p>
      </div>
      <div class="card stat-card">
        <h3>Total Spent</h3>
        <p>₱<?= number_format($stats['total_spent'], 2) ?></p>
      </div>
    </section>

    <section class="card">
      <h3>Quick Actions</h3>
      <div class="quick-actions">
        <button class="btn" onclick="location.href='buyer-cart.php'">View Cart</button>
        <button class="btn" onclick="location.href='buyer-orders.php'">My Orders</button>
      </div>
    </section>

    <!-- Farmer Selection Section -->
    <section class="card farmer-selection-section">
      <div class="farmer-selection-header">
        <h3>🌾 Browse by Farmer</h3>
        <p class="section-description">Select a farmer to view their products and location</p>
      </div>
      
      <div class="farmer-controls">
        <div class="farmer-dropdown-container">
          <label for="farmer-select">Choose Farmer:</label>
          <select id="farmer-select" class="farmer-select" onchange="selectFarmer(this.value)">
            <option value="">All Farmers</option>
            <?php foreach ($farmers as $farmer): ?>
              <option value="<?= $farmer['id'] ?>" <?= $selectedFarmerId == $farmer['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($farmer['farm_name'] ?: $farmer['username']) ?> 
                (<?= $farmer['product_count'] ?> products)
                <?= $farmer['location'] ? ' - ' . htmlspecialchars($farmer['location']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <?php if ($selectedFarmer): ?>
          <button class="btn btn-secondary" onclick="clearFarmerSelection()">View All Farmers</button>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($selectedFarmer): ?>
      <!-- Selected Farmer Info & Map -->
      <section class="card farmer-info-section">
        <div class="farmer-info-content">
          <div class="farmer-details">
            <div class="farmer-header">
              <h3>🚜 <?= htmlspecialchars($selectedFarmer['farm_name'] ?: $selectedFarmer['username']) ?></h3>
              <div class="farmer-meta">
                <span class="farmer-location">📍 <?= htmlspecialchars($selectedFarmer['location'] ?: 'Location not specified') ?></span>
                <span class="farmer-products">📦 <?= count($farmerProducts) ?> products available</span>
              </div>
            </div>
            
            
            <?php if ($selectedFarmer['latitude'] && $selectedFarmer['longitude']): ?>
              <div class="farmer-coordinates">
                <small>📍 Coordinates: <?= $selectedFarmer['latitude'] ?>, <?= $selectedFarmer['longitude'] ?></small>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- Farmer's Exact Location Map -->
          <?php if ($selectedFarmer['latitude'] && $selectedFarmer['longitude']): ?>
            <div class="farmer-location-display">
              <h4 class="location-title">📍 Farmer's Exact Location</h4>
              <div class="location-coordinates-display">
                <span class="coord-label">Coordinates:</span>
                <span class="coord-values"><?= $selectedFarmer['latitude'] ?>, <?= $selectedFarmer['longitude'] ?></span>
              </div>
              <div id="farmer-exact-location-map" class="farmer-exact-map"></div>
              <div class="location-accuracy-info">
                <span class="accuracy-badge">🎯 Exact Farm Location</span>
                <span class="location-text"><?= htmlspecialchars($selectedFarmer['location'] ?: 'Farm Location') ?></span>
              </div>
            </div>
          <?php elseif ($selectedFarmer['location']): ?>
            <div class="farmer-location-display">
              <h4 class="location-title">📍 Farmer's Location Area</h4>
              <div class="location-text-display">
                <span class="location-area"><?= htmlspecialchars($selectedFarmer['location']) ?></span>
              </div>
              <div id="farmer-geocoded-location-map" class="farmer-exact-map"></div>
              <div class="location-accuracy-info">
                <span class="accuracy-badge">🗺️ General Area</span>
                <span class="location-note">Farmer hasn't set exact coordinates</span>
              </div>
            </div>
          <?php endif; ?>
          
          <!-- External View Map Button (Below Map) -->
          <?php if ($selectedFarmer['latitude'] && $selectedFarmer['longitude'] || $selectedFarmer['location']): ?>
            <div class="external-map-button">
              <?php if ($selectedFarmer['latitude'] && $selectedFarmer['longitude']): ?>
                <button class="btn-external-map" onclick="openFullMap(<?= $selectedFarmer['latitude'] ?>, <?= $selectedFarmer['longitude'] ?>, '<?= htmlspecialchars($selectedFarmer['farm_name'] ?: $selectedFarmer['username']) ?>')">
                  🗺️ View Full Map
                </button>
              <?php else: ?>
                <button class="btn-external-map" onclick="searchLocationOnGoogleMaps('<?= htmlspecialchars($selectedFarmer['location']) ?>')">
                  🗺️ View Full Map
                </button>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          </div>
          
        </div>
      </section>

      <!-- Full Screen Leaflet Map Modal -->
      <div id="fullMapModal" class="map-modal" style="display: none;">
        <div class="map-modal-content">
          <div class="map-modal-header">
            <h3 id="mapModalTitle">🚜 Farmer Location</h3>
            <button class="map-modal-close" onclick="closeFullMap()">&times;</button>
          </div>
          <div id="fullLeafletMap" class="full-leaflet-map"></div>
          <div class="map-modal-info">
            <div id="mapLocationInfo" class="location-info-panel">
              <p><strong>📍 Loading location information...</strong></p>
            </div>
          </div>
        </div>
      </div>

      <!-- Farmer's Products -->
      <section class="card">
        <h3>Products from <?= htmlspecialchars($selectedFarmer['farm_name'] ?: $selectedFarmer['username']) ?></h3>
        <div class="market-grid">
          <?php if (empty($farmerProducts)): ?>
            <div class="no-products">
              <div class="no-products-icon">📦</div>
              <h4>No products available</h4>
              <p>This farmer hasn't added any products yet.</p>
            </div>
          <?php else: ?>
            <?php foreach ($farmerProducts as $product): ?>
              <div class="market-card">
                <?php if ($product['image']): ?>
                  <div class="product-image">
                    <?php
                      $imageUrl = '';
                      $imagePath = trim($product['image']);
                      
                      if (strpos($imagePath, 'http') === 0) {
                          $imageUrl = $imagePath;
                      } elseif (strpos($imagePath, '/FARMLINK/') === 0) {
                          $imageUrl = $imagePath;
                      } elseif (strpos($imagePath, 'uploads/products/') === 0) {
                          $imageUrl = '/FARMLINK/' . $imagePath;
                      } elseif (strpos($imagePath, '/') === 0) {
                          $imageUrl = '/FARMLINK' . $imagePath;
                      } else {
                          $imageUrl = '/FARMLINK/uploads/products/' . basename($imagePath);
                      }
                    ?>
                    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='/FARMLINK/assets/img/placeholder-product.png';">
                  </div>
                <?php else: ?>
                  <div class="product-image">
                    <img src="/FARMLINK/assets/img/placeholder-product.png" alt="<?= htmlspecialchars($product['name']) ?>">
                  </div>
                <?php endif; ?>
                <div class="product-info">
                  <h4><?= htmlspecialchars($product['name']) ?></h4>
                  <p class="product-category"><?= htmlspecialchars($product['category'] ?? '') ?></p>
                  <p class="product-price">₱<?= number_format($product['price'], 2) ?>/<?= htmlspecialchars($product['unit']) ?></p>
                  <p class="product-quantity">Available: <?= $product['quantity'] ?> <?= htmlspecialchars($product['unit']) ?></p>
                  
                  <?php if ($product['expires_at']): ?>
                    <?php 
                    $expiresAt = $product['expires_at'];
                    $expiresDate = new DateTime($expiresAt);
                    $now = new DateTime();
                    $isExpired = $now > $expiresDate;
                    $diff = $now->diff($expiresDate);
                    ?>
                    
                    <?php if ($isExpired): ?>
                      <div class="expiration-info expired">
                        <span class="expiration-icon">🚫</span>
                        <span class="expiration-text">Expired</span>
                      </div>
                    <?php elseif ($diff->days <= 1): ?>
                      <div class="expiration-info warning">
                        <span class="expiration-icon">⏰</span>
                        <span class="expiration-text">Expires soon</span>
                      </div>
                    <?php else: ?>
                      <div class="expiration-info normal">
                        <span class="expiration-icon">📅</span>
                        <span class="expiration-text">Fresh</span>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <button class="btn btn-sm" onclick="addToCart(<?= $product['id'] ?>)" <?= isset($isExpired) && $isExpired ? 'disabled' : '' ?>>
                    Add to Cart
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    <?php else: ?>
      <!-- Recent Products from All Farmers -->
      <section class="card">
        <h3>Recent Products</h3>
        <div class="market-grid">
          <?php if (empty($recentProducts)): ?>
            <p>No products available.</p>
          <?php else: ?>
            <?php foreach ($recentProducts as $product): ?>
              <div class="market-card">
                <?php if ($product['image']): ?>
                  <div class="product-image">
                    <?php
                      $imageUrl = '';
                      $imagePath = trim($product['image']);
                      
                      if (strpos($imagePath, 'http') === 0) {
                          $imageUrl = $imagePath;
                      } elseif (strpos($imagePath, '/FARMLINK/') === 0) {
                          $imageUrl = $imagePath;
                      } elseif (strpos($imagePath, 'uploads/products/') === 0) {
                          $imageUrl = '/FARMLINK/' . $imagePath;
                      } elseif (strpos($imagePath, '/') === 0) {
                          $imageUrl = '/FARMLINK' . $imagePath;
                      } else {
                          $imageUrl = '/FARMLINK/uploads/products/' . basename($imagePath);
                      }
                    ?>
                    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='/FARMLINK/assets/img/placeholder-product.png';">
                  </div>
                <?php else: ?>
                  <div class="product-image">
                    <img src="/FARMLINK/assets/img/placeholder-product.png" alt="<?= htmlspecialchars($product['name']) ?>">
                  </div>
                <?php endif; ?>
                <div class="product-info">
                  <h4><?= htmlspecialchars($product['name']) ?></h4>
                  <p class="product-price">₱<?= number_format($product['price'], 2) ?>/<?= htmlspecialchars($product['unit']) ?></p>
                  <p class="product-farmer">by <?= htmlspecialchars($product['farmer_name']) ?></p>
                  <button class="btn btn-sm" onclick="addToCart(<?= $product['id'] ?>)">Add to Cart</button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <style>
    /* Force dark green sidebar background */
    .sidebar {
      background: #1B5E20 !important;
      top: 80px !important;
    }
    
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .nav-left {
      display: flex;
      align-items: center;
    }

    /* Farmer Selection Styles */
    .farmer-selection-section {
      margin-bottom: 24px;
    }

    .farmer-selection-header h3 {
      margin: 0 0 8px 0;
      color: #2E7D32;
    }

    .section-description {
      color: #666;
      margin: 0 0 20px 0;
      font-size: 14px;
    }

    .farmer-controls {
      display: flex;
      align-items: flex-end;
      gap: 16px;
      flex-wrap: wrap;
    }

    .farmer-dropdown-container {
      flex: 1;
      min-width: 300px;
    }

    .farmer-dropdown-container label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #2E7D32;
    }

    .farmer-select {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      background: white;
      transition: border-color 0.3s ease;
    }

    .farmer-select:focus {
      outline: none;
      border-color: #2E7D32;
      box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
    }

    /* Farmer Info Section */
    .farmer-info-section {
      margin-bottom: 24px;
      background: linear-gradient(135deg, #f1f8e9 0%, #e8f5e8 100%);
      border-left: 4px solid #4CAF50;
    }

    .farmer-info-content {
      display: grid;
      grid-template-columns: 1fr 300px;
      gap: 24px;
      align-items: start;
    }

    .farmer-header h3 {
      margin: 0 0 12px 0;
      color: #2E7D32;
      font-size: 20px;
    }

    .farmer-meta {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .farmer-location,
    .farmer-products {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 14px;
      color: #555;
    }

    .farmer-coordinates {
      margin-top: 12px;
      color: #777;
    }

    /* Mini Map Styles */
    .mini-map-container {
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .mini-map {
      height: 200px;
      position: relative;
      background: #f5f5f5;
    }

    .mini-map.no-location {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .map-placeholder {
      text-align: center;
      padding: 20px;
    }

    .map-placeholder.no-location {
      color: #999;
    }

    .map-help {
      margin-top: 8px;
      color: #666;
      line-height: 1.3;
    }

    .map-help small {
      font-size: 11px;
    }

    .map-marker {
      font-size: 32px;
      margin-bottom: 8px;
    }

    .map-icon {
      font-size: 48px;
      margin-bottom: 8px;
      opacity: 0.5;
    }

    .map-info strong {
      color: #2E7D32;
    }

    .map-controls {
      padding: 12px;
      background: white;
      border-top: 1px solid #eee;
      display: flex;
      gap: 8px;
    }

    .map-controls .btn {
      flex: 1;
      font-size: 12px;
      padding: 8px 12px;
    }

    /* Custom marker styles for mini map */
    .custom-farm-marker {
      background: transparent;
      border: none;
    }

    .farm-marker-pin {
      font-size: 20px;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
      animation: bounce 2s infinite;
    }

    @keyframes bounce {
      0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
      }
      40% {
        transform: translateY(-3px);
      }
      60% {
        transform: translateY(-2px);
      }
    }

    /* Farm popup styles */
    .farm-popup h4 {
      margin: 0 0 8px 0;
      color: #2E7D32;
      font-size: 14px;
    }

    .farm-popup p {
      margin: 4px 0;
      color: #666;
      font-size: 12px;
    }

    /* Detailed marker styles */
    .custom-farm-marker-detailed {
      background: transparent;
      border: none;
    }

    .detailed-marker-pin {
      font-size: 20px;
      color: #dc3545;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
      animation: pulse 2s infinite;
    }

    /* Red map marker styles - highly visible */
    .red-map-marker {
      background: transparent;
      border: none;
    }

    .red-pin {
      font-size: 28px;
      color: #dc3545;
      text-shadow: 3px 3px 6px rgba(0,0,0,0.5);
      animation: bounceMarker 2s infinite;
      filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.3));
    }

    @keyframes bounceMarker {
      0%, 20%, 50%, 80%, 100% {
        transform: translateY(0) scale(1);
      }
      40% {
        transform: translateY(-5px) scale(1.1);
      }
      60% {
        transform: translateY(-3px) scale(1.05);
      }
    }

    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }

    /* Detailed popup styles like in the second image */
    .detailed-farm-popup {
      font-family: Arial, sans-serif;
      min-width: 250px;
    }

    .detailed-farm-popup .popup-header {
      background: #2E7D32;
      color: white;
      padding: 8px 12px;
      margin: -8px -12px 8px -12px;
      border-radius: 4px 4px 0 0;
    }

    .detailed-farm-popup .popup-header h4 {
      margin: 0;
      font-size: 14px;
      font-weight: bold;
    }

    .detailed-farm-popup .popup-content {
      padding: 0;
    }

    .detailed-farm-popup .popup-content p {
      margin: 6px 0;
      font-size: 12px;
      line-height: 1.4;
    }

    .detailed-farm-popup .popup-content strong {
      color: #2E7D32;
      font-weight: 600;
    }

    .detailed-farm-popup .popup-footer {
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px solid #eee;
    }

    .detailed-farm-popup .popup-footer small {
      color: #666;
      font-size: 10px;
      font-style: italic;
    }

    /* Custom popup styling */
    .leaflet-popup-content-wrapper.custom-popup {
      border-radius: 6px;
      box-shadow: 0 3px 14px rgba(0,0,0,0.4);
    }

    .leaflet-popup-content.custom-popup {
      margin: 8px 12px;
    }

    /* Compact Mini Map Styles */
    .compact-map-container {
      width: 200px;
      height: 150px;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      border: 2px solid #e0e0e0;
      background: #f8f9fa;
    }

    .compact-map {
      width: 100%;
      height: 100%;
      position: relative;
    }

    .compact-map.no-location {
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f5f5f5;
    }

    .no-location-display {
      text-align: center;
      color: #999;
    }

    .location-icon {
      font-size: 24px;
      margin-bottom: 4px;
      opacity: 0.6;
    }

    .location-text {
      font-size: 11px;
      font-weight: 500;
    }

    /* View on Map Button */
    .map-view-button {
      position: absolute;
      bottom: 6px;
      left: 6px;
      right: 6px;
      z-index: 1000;
    }

    .btn-view-map {
      width: 100%;
      padding: 8px 12px;
      background: rgba(46, 125, 50, 0.95);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      backdrop-filter: blur(8px);
      box-shadow: 0 3px 8px rgba(0,0,0,0.3);
      border: 1px solid rgba(255,255,255,0.2);
    }

    .btn-view-map:hover {
      background: rgba(46, 125, 50, 1);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    }

    .btn-view-map:active {
      transform: translateY(-1px);
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }

    /* Adjust compact map container for button */
    .compact-map-container {
      position: relative;
      overflow: visible;
    }

    /* Ensure button appears above map */
    .compact-map {
      position: relative;
      z-index: 1;
    }

    /* External Map Button (Below Map) */
    .external-map-button {
      margin-top: 8px;
      text-align: center;
    }

    .btn-external-map {
      padding: 10px 20px;
      background: linear-gradient(135deg, #2E7D32, #388E3C);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 3px 10px rgba(46, 125, 50, 0.3);
      min-width: 140px;
    }

    .btn-external-map:hover {
      background: linear-gradient(135deg, #1B5E20, #2E7D32);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(46, 125, 50, 0.4);
    }

    .btn-external-map:active {
      transform: translateY(-1px);
      box-shadow: 0 3px 8px rgba(46, 125, 50, 0.3);
    }

    /* Farmer's Exact Location Display */
    .farmer-location-display {
      background: linear-gradient(135deg, #f8f9fa, #e9ecef);
      border: 2px solid #2E7D32;
      border-radius: 12px;
      padding: 20px;
      margin: 20px 0;
      box-shadow: 0 4px 12px rgba(46, 125, 50, 0.1);
    }

    .location-title {
      margin: 0 0 15px 0;
      color: #2E7D32;
      font-size: 18px;
      font-weight: 700;
      text-align: center;
      border-bottom: 2px solid #2E7D32;
      padding-bottom: 10px;
    }

    .location-coordinates-display {
      background: #e8f5e8;
      padding: 12px;
      border-radius: 8px;
      margin: 15px 0;
      text-align: center;
      border-left: 4px solid #2E7D32;
    }

    .coord-label {
      font-size: 12px;
      color: #666;
      font-weight: 600;
      display: block;
      margin-bottom: 5px;
    }

    .coord-values {
      font-family: 'Courier New', monospace;
      font-size: 16px;
      color: #2E7D32;
      font-weight: bold;
      background: white;
      padding: 8px 12px;
      border-radius: 6px;
      display: inline-block;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .farmer-exact-map {
      height: 250px;
      width: 100%;
      border-radius: 10px;
      border: 3px solid #2E7D32;
      margin: 15px 0;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      position: relative;
      overflow: hidden;
    }

    .location-accuracy-info {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: white;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #ddd;
    }

    .accuracy-badge {
      background: linear-gradient(135deg, #2E7D32, #388E3C);
      color: white;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      box-shadow: 0 2px 4px rgba(46, 125, 50, 0.3);
    }

    .location-text {
      color: #2E7D32;
      font-weight: 600;
      font-size: 14px;
    }

    .location-text-display {
      background: #fff3cd;
      padding: 12px;
      border-radius: 8px;
      margin: 15px 0;
      text-align: center;
      border-left: 4px solid #ffc107;
    }

    .location-area {
      font-size: 16px;
      color: #856404;
      font-weight: bold;
    }

    .location-note {
      color: #666;
      font-size: 12px;
      font-style: italic;
    }

    /* Full Screen Leaflet Map Modal */
    .map-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
      z-index: 10000;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .map-modal-content {
      width: 95%;
      height: 90%;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      display: flex;
      flex-direction: column;
    }

    .map-modal-header {
      background: linear-gradient(135deg, #2E7D32, #388E3C);
      color: white;
      padding: 15px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .map-modal-header h3 {
      margin: 0;
      font-size: 18px;
    }

    .map-modal-close {
      background: none;
      border: none;
      color: white;
      font-size: 24px;
      cursor: pointer;
      padding: 0;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s;
    }

    .map-modal-close:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    .full-leaflet-map {
      flex: 1;
      width: 100%;
      position: relative;
    }

    .map-modal-info {
      background: #f8f9fa;
      padding: 15px 20px;
      border-top: 1px solid #e0e0e0;
    }

    .location-info-panel {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }

    .location-info-panel p {
      margin: 0;
      font-size: 14px;
    }

    .location-coordinates {
      font-family: 'Courier New', monospace;
      background: #e8f5e8;
      padding: 8px 12px;
      border-radius: 6px;
      color: #2E7D32;
      font-weight: bold;
    }

    /* Enhanced popup styling */
    .farmer-location-popup .leaflet-popup-content-wrapper {
      border-radius: 12px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      border: none;
    }

    .farmer-location-popup .leaflet-popup-content {
      margin: 0;
      padding: 0;
    }

    .farmer-location-popup .leaflet-popup-tip {
      background: white;
      border: none;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    /* Enhanced farmer location marker animations */
    .farmer-location-marker {
      animation: markerBounce 2s infinite;
    }

    .pulsing-circle {
      animation: locationPulse 2s infinite;
    }

    .farmer-inner-circle {
      animation: innerCirclePulse 1.5s infinite;
    }

    .farmer-outer-circle {
      animation: outerCirclePulse 2.5s infinite;
    }

    @keyframes markerBounce {
      0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
      }
      40% {
        transform: translateY(-8px);
      }
      60% {
        transform: translateY(-4px);
      }
    }

    @keyframes locationPulse {
      0% {
        opacity: 0.8;
        transform: scale(1);
      }
      50% {
        opacity: 0.3;
        transform: scale(1.3);
      }
      100% {
        opacity: 0.8;
        transform: scale(1);
      }
    }

    @keyframes innerCirclePulse {
      0% {
        opacity: 0.4;
        transform: scale(1);
      }
      50% {
        opacity: 0.1;
        transform: scale(1.1);
      }
      100% {
        opacity: 0.4;
        transform: scale(1);
      }
    }

    @keyframes outerCirclePulse {
      0% {
        opacity: 0.2;
        transform: scale(1);
      }
      50% {
        opacity: 0.05;
        transform: scale(1.15);
      }
      100% {
        opacity: 0.2;
        transform: scale(1);
      }
    }

    /* Product Grid Enhancements */
    .market-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }

    .market-card {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .market-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .product-image {
      height: 180px;
      overflow: hidden;
      background: #f5f5f5;
    }

    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .product-info {
      padding: 16px;
    }

    .product-info h4 {
      margin: 0 0 8px 0;
      color: #2E7D32;
      font-size: 16px;
    }

    .product-category {
      color: #666;
      font-size: 12px;
      margin: 0 0 4px 0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .product-price {
      color: #4CAF50;
      font-weight: 600;
      font-size: 16px;
      margin: 8px 0;
    }

    .product-quantity {
      color: #777;
      font-size: 13px;
      margin: 4px 0 12px 0;
    }

    .product-farmer {
      color: #666;
      font-size: 13px;
      margin: 4px 0 12px 0;
    }

    /* Expiration Info Styles */
    .expiration-info {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 12px;
      margin: 8px 0;
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

    .expiration-info.normal {
      background: #f1f8e9;
      color: #388e3c;
      border: 1px solid #c8e6c9;
    }

    .expiration-icon {
      font-size: 14px;
    }

    /* No Products State */
    .no-products {
      grid-column: 1 / -1;
      text-align: center;
      padding: 60px 20px;
      color: #666;
    }

    .no-products-icon {
      font-size: 48px;
      margin-bottom: 16px;
    }

    .no-products h4 {
      margin: 0 0 8px 0;
      color: #333;
    }

    .no-products p {
      margin: 0;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .farmer-info-content {
        grid-template-columns: 1fr;
      }

      .farmer-controls {
        flex-direction: column;
        align-items: stretch;
      }

      .farmer-dropdown-container {
        min-width: auto;
      }

      .market-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      }
    }
  </style>

  <script>
    // Farmer selection functionality
    function selectFarmer(farmerId) {
      if (farmerId) {
        window.location.href = `buyer-dashboard.php?farmer_id=${farmerId}`;
      } else {
        window.location.href = 'buyer-dashboard.php';
      }
    }

    function clearFarmerSelection() {
      window.location.href = 'buyer-dashboard.php';
    }

    // Map functionality
    // Full Screen Leaflet Map Functions
    let fullScreenMap = null;

    function openFullMap(lat, lng, farmName) {
      // Show the modal
      document.getElementById('fullMapModal').style.display = 'flex';
      document.getElementById('mapModalTitle').textContent = `🚜 ${farmName} - Exact Location`;
      
      // Initialize the full screen Leaflet map
      setTimeout(() => {
        initializeFullScreenMap(lat, lng, farmName);
      }, 100);
    }

    function searchLocationOnGoogleMaps(location) {
      // Use geocoding to get coordinates, then show Leaflet map
      const knownLocations = {
        'Naval, Biliran': { lat: 11.561790, lng: 124.396527 }  // FREDREX SALAC current coordinates
      };
      
      if (knownLocations[location]) {
        const coords = knownLocations[location];
        openFullMap(coords.lat, coords.lng, location);
      } else {
        // Try geocoding
        const geocodeUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location + ', Philippines')}&limit=1`;
        
        fetch(geocodeUrl)
          .then(response => response.json())
          .then(data => {
            if (data && data.length > 0) {
              const lat = parseFloat(data[0].lat);
              const lng = parseFloat(data[0].lon);
              openFullMap(lat, lng, location);
            } else {
              alert('Location not found: ' + location);
            }
          })
          .catch(error => {
            console.error('Geocoding error:', error);
            alert('Could not find location: ' + location);
          });
      }
    }

    function closeFullMap() {
      document.getElementById('fullMapModal').style.display = 'none';
      if (fullScreenMap) {
        fullScreenMap.remove();
        fullScreenMap = null;
      }
    }

    function initializeFullScreenMap(lat, lng, farmName) {
      // Remove existing map if any
      if (fullScreenMap) {
        fullScreenMap.remove();
      }
      
      // Initialize full screen Leaflet map
      fullScreenMap = L.map('fullLeafletMap', {
        center: [lat, lng],
        zoom: 17,  // High zoom for detailed view
        zoomControl: true,
        scrollWheelZoom: true,
        doubleClickZoom: true,
        dragging: true,
        touchZoom: true,
        boxZoom: true,
        keyboard: true
      });
      
      // Add map tiles with multiple sources for reliability
      const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
        subdomains: ['a', 'b', 'c']
      });
      
      const cartoLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap contributors © CARTO',
        maxZoom: 19,
        subdomains: 'abcd'
      });
      
      // Add primary layer
      osmLayer.addTo(fullScreenMap);
      
      // Add layer control
      const baseLayers = {
        "OpenStreetMap": osmLayer,
        "CartoDB Voyager": cartoLayer
      };
      L.control.layers(baseLayers).addTo(fullScreenMap);
      
      // Add precise farmer location marker with highly visible design
      const farmerMarker = L.marker([lat, lng], {
        icon: L.divIcon({
          className: 'farmer-location-marker',
          html: `
            <div style="
              position: relative;
              width: 40px;
              height: 40px;
              background: #dc3545;
              border: 4px solid white;
              border-radius: 50% 50% 50% 0;
              transform: rotate(-45deg);
              box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            ">
              <div style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(45deg);
                font-size: 20px;
                color: white;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
              ">🚜</div>
            </div>
            <div style="
              position: absolute;
              top: -5px;
              left: 50%;
              transform: translateX(-50%);
              background: #dc3545;
              color: white;
              padding: 2px 8px;
              border-radius: 12px;
              font-size: 10px;
              font-weight: bold;
              white-space: nowrap;
              box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            ">FARM</div>
          `,
          iconSize: [40, 40],
          iconAnchor: [20, 40],
          popupAnchor: [0, -40]
        })
      }).addTo(fullScreenMap);
      
      // Add enhanced popup with better styling
      farmerMarker.bindPopup(`
        <div style="text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; min-width: 280px; max-width: 320px;">
          <div style="background: linear-gradient(135deg, #2E7D32, #388E3C); color: white; padding: 12px; margin: -10px -10px 15px -10px; border-radius: 8px 8px 0 0;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 600;">🚜 ${farmName}</h3>
          </div>
          
          <div style="background: #e8f5e8; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #2E7D32;">
            <p style="margin: 0; font-size: 14px; color: #2E7D32; font-weight: bold;">📍 EXACT FARMER LOCATION</p>
          </div>
          
          <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin: 12px 0;">
            <p style="margin: 0 0 5px 0; font-size: 12px; color: #666; font-weight: 500;">Coordinates:</p>
            <p style="margin: 0; font-family: 'Courier New', monospace; font-size: 13px; color: #2E7D32; font-weight: bold;">${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
          </div>
          
          <div style="background: #fff3cd; padding: 10px; border-radius: 6px; margin: 12px 0; border-left: 3px solid #ffc107;">
            <p style="margin: 0; font-size: 12px; color: #856404; font-weight: 500;">🎯 This pin shows the precise farm location</p>
          </div>
          
          <button onclick="getDirectionsToFarm(${lat}, ${lng})" style="
            background: linear-gradient(135deg, #2E7D32, #388E3C);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(46, 125, 50, 0.3);
          " onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(46, 125, 50, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(46, 125, 50, 0.3)'">
            🧭 Get Directions
          </button>
        </div>
      `, {
        maxWidth: 350,
        className: 'farmer-location-popup'
      }).openPopup();
      
      // Add highly visible accuracy circles with animation
      const innerCircle = L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.2,
        radius: 30,
        weight: 3,
        className: 'farmer-inner-circle'
      }).addTo(fullScreenMap);
      
      const outerCircle = L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.1,
        radius: 60,
        weight: 2,
        className: 'farmer-outer-circle'
      }).addTo(fullScreenMap);
      
      const pulsingCircle = L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.05,
        radius: 100,
        weight: 1,
        className: 'pulsing-circle'
      }).addTo(fullScreenMap);
      
      // Update info panel
      document.getElementById('mapLocationInfo').innerHTML = `
        <p><strong>📍 Farmer:</strong> ${farmName}</p>
        <p><strong>🗺️ Location:</strong> Naval, Biliran, Philippines</p>
        <div class="location-coordinates">${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
      `;
      
      // Force map to refresh
      setTimeout(() => {
        fullScreenMap.invalidateSize();
      }, 200);
    }

    function getDirectionsToFarm(lat, lng) {
      const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
      window.open(url, '_blank');
    }

    // Initialize mini map when page loads
    document.addEventListener('DOMContentLoaded', function() {
      initializeFarmerMiniMap();
      initializeFarmerExactLocationMap();
    });

    function initializeFarmerMiniMap() {
      // Initialize compact location map with exact coordinates
      const locationMapElement = document.getElementById('farmer-location-map');
      if (locationMapElement) {
        <?php if ($selectedFarmer && $selectedFarmer['latitude'] && $selectedFarmer['longitude']): ?>
          initializeCompactMap('farmer-location-map', <?= $selectedFarmer['latitude'] ?>, <?= $selectedFarmer['longitude'] ?>, '<?= htmlspecialchars($selectedFarmer['farm_name'] ?: $selectedFarmer['username']) ?>', '<?= htmlspecialchars($selectedFarmer['location'] ?: 'Farm Location') ?>');
        <?php endif; ?>
        return;
      }

      // Initialize compact map with geocoding from location text
      const geocodedLocationMapElement = document.getElementById('farmer-geocoded-location-map');
      if (geocodedLocationMapElement) {
        <?php if ($selectedFarmer && $selectedFarmer['location']): ?>
          initializeCompactMapWithGeocoding('farmer-geocoded-location-map', '<?= htmlspecialchars($selectedFarmer['location']) ?>', '<?= htmlspecialchars($selectedFarmer['farm_name'] ?: $selectedFarmer['username']) ?>');
        <?php endif; ?>
      }
    }

    function initializeFarmerExactLocationMap() {
      // Initialize the main farmer exact location map
      const exactLocationMapElement = document.getElementById('farmer-exact-location-map');
      if (exactLocationMapElement) {
        <?php if ($selectedFarmer && $selectedFarmer['latitude'] && $selectedFarmer['longitude']): ?>
          initializeExactLocationMap('farmer-exact-location-map', <?= $selectedFarmer['latitude'] ?>, <?= $selectedFarmer['longitude'] ?>, '<?= htmlspecialchars($selectedFarmer['farm_name'] ?: $selectedFarmer['username']) ?>', '<?= htmlspecialchars($selectedFarmer['location'] ?: 'Farm Location') ?>');
        <?php endif; ?>
        return;
      }

      // Initialize geocoded location map for general area
      const geocodedExactMapElement = document.getElementById('farmer-geocoded-location-map');
      if (geocodedExactMapElement) {
        <?php if ($selectedFarmer && $selectedFarmer['location']): ?>
          initializeExactLocationMapWithGeocoding('farmer-geocoded-location-map', '<?= htmlspecialchars($selectedFarmer['location']) ?>', '<?= htmlspecialchars($selectedFarmer['farm_name'] ?: $selectedFarmer['username']) ?>');
        <?php endif; ?>
      }
    }

    function initializeExactLocationMap(mapId, lat, lng, farmName, location) {
      // Create detailed map showing farmer's exact location
      const exactMap = L.map(mapId, {
        center: [lat, lng],
        zoom: 17,  // High zoom for detailed view
        zoomControl: true,
        scrollWheelZoom: true,
        doubleClickZoom: true,
        dragging: true,
        touchZoom: true,
        boxZoom: true,
        keyboard: true
      });

      // Add map tiles
      const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
        subdomains: ['a', 'b', 'c']
      });

      const cartoLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap contributors © CARTO',
        maxZoom: 19,
        subdomains: 'abcd'
      });

      // Add primary layer
      osmLayer.addTo(exactMap);

      // Add layer control
      const baseLayers = {
        "Street Map": osmLayer,
        "Clean Map": cartoLayer
      };
      L.control.layers(baseLayers).addTo(exactMap);

      // Add highly visible farmer marker
      const farmerMarker = L.marker([lat, lng], {
        icon: L.divIcon({
          className: 'farmer-exact-marker',
          html: `
            <div style="
              position: relative;
              width: 35px;
              height: 35px;
              background: #dc3545;
              border: 3px solid white;
              border-radius: 50% 50% 50% 0;
              transform: rotate(-45deg);
              box-shadow: 0 3px 6px rgba(0,0,0,0.3);
            ">
              <div style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(45deg);
                font-size: 16px;
                color: white;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
              ">🚜</div>
            </div>
          `,
          iconSize: [35, 35],
          iconAnchor: [17, 35],
          popupAnchor: [0, -35]
        })
      }).addTo(exactMap);

      // Add popup with farm details
      farmerMarker.bindPopup(`
        <div style="text-align: center; font-family: Arial, sans-serif; min-width: 200px;">
          <h4 style="margin: 0 0 8px 0; color: #2E7D32;">🚜 ${farmName}</h4>
          <div style="background: #e8f5e8; padding: 8px; border-radius: 4px; margin: 8px 0;">
            <p style="margin: 0; font-size: 12px; color: #2E7D32; font-weight: bold;">📍 EXACT FARM LOCATION</p>
          </div>
          <p style="margin: 4px 0; font-size: 11px;"><strong>📍 Address:</strong> ${location}</p>
          <p style="margin: 4px 0; font-size: 10px; color: #666;"><strong>Coordinates:</strong><br>${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
        </div>
      `);

      // Add accuracy circles
      const innerCircle = L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.15,
        radius: 25,
        weight: 2,
        className: 'farmer-inner-circle'
      }).addTo(exactMap);

      const outerCircle = L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.08,
        radius: 50,
        weight: 1,
        className: 'farmer-outer-circle'
      }).addTo(exactMap);

      // Force map refresh
      setTimeout(() => {
        exactMap.invalidateSize();
      }, 200);
    }

    function initializeExactLocationMapWithGeocoding(mapId, locationText, farmName) {
      // For farmers without exact coordinates, show general area
      const knownLocations = {
        'Naval, Biliran': { lat: 11.561790, lng: 124.396527 }
      };

      if (knownLocations[locationText]) {
        const coords = knownLocations[locationText];
        initializeExactLocationMap(mapId, coords.lat, coords.lng, farmName, locationText);
      } else {
        // Try geocoding
        const geocodeUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(locationText + ', Philippines')}&limit=1`;
        
        fetch(geocodeUrl)
          .then(response => response.json())
          .then(data => {
            if (data && data.length > 0) {
              const lat = parseFloat(data[0].lat);
              const lng = parseFloat(data[0].lon);
              initializeExactLocationMap(mapId, lat, lng, farmName, locationText);
            }
          })
          .catch(error => {
            console.error('Geocoding error:', error);
          });
      }
    }

    function initializeMapWithCoordinates(mapId, lat, lng, farmName, location, productCount) {
      // Initialize Leaflet map with exact coordinates using reliable settings
      const miniMap = L.map(mapId, {
        center: [lat, lng],
        zoom: 13,
        zoomControl: true,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        dragging: false,
        touchZoom: false,
        boxZoom: false,
        keyboard: false
      });
      
      // Add multiple tile layer options for reliability
      const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
        subdomains: ['a', 'b', 'c']
      });
      
      // Fallback tile layer
      const cartoDB = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap contributors © CARTO',
        maxZoom: 19,
        subdomains: 'abcd'
      });
      
      // Try to add OSM first, fallback to CartoDB if it fails
      osmLayer.addTo(miniMap);
      
      osmLayer.on('tileerror', function() {
        console.log('OSM tiles failed, switching to CartoDB');
        miniMap.removeLayer(osmLayer);
        cartoDB.addTo(miniMap);
      });
      
      // Wait a moment for tiles to load, then add marker
      setTimeout(() => {
        addDetailedFarmMarker(miniMap, lat, lng, farmName, location, productCount);
      }, 500);
      
      // Force map to refresh after initialization
      setTimeout(() => {
        miniMap.invalidateSize();
      }, 1000);
    }

    function initializeMapWithGeocoding(mapId, locationText, farmName, productCount) {
      // Show loading state
      const mapElement = document.getElementById(mapId);
      mapElement.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;"><div>🔍 Finding location...</div></div>';
      
      // Predefined coordinates for common Philippine locations
      const knownLocations = {
        'Naval, Biliran': { lat: 11.5682, lng: 124.4133, zoom: 14 },
        'Tacloban': { lat: 11.2421, lng: 125.0079, zoom: 13 },
        'Cebu': { lat: 10.3157, lng: 123.8854, zoom: 12 },
        'Manila': { lat: 14.5995, lng: 120.9842, zoom: 11 },
        'Davao': { lat: 7.1907, lng: 125.4553, zoom: 12 },
        'Iloilo': { lat: 10.7202, lng: 122.5621, zoom: 12 }
      };
      
      // Check if we have predefined coordinates
      const normalizedLocation = locationText.trim();
      let foundCoords = null;
      
      // Try exact match first
      if (knownLocations[normalizedLocation]) {
        foundCoords = knownLocations[normalizedLocation];
      } else {
        // Try partial matches
        for (const [key, coords] of Object.entries(knownLocations)) {
          if (normalizedLocation.toLowerCase().includes(key.toLowerCase()) || 
              key.toLowerCase().includes(normalizedLocation.toLowerCase())) {
            foundCoords = coords;
            break;
          }
        }
      }
      
      if (foundCoords) {
        // Use predefined coordinates
        initializeMapWithKnownCoordinates(mapId, foundCoords.lat, foundCoords.lng, foundCoords.zoom, farmName, locationText, productCount);
      } else {
        // Try geocoding with improved parameters
        const geocodeUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(locationText + ', Philippines')}&limit=1&addressdetails=1&countrycodes=ph`;
        
        fetch(geocodeUrl, {
          headers: {
            'User-Agent': 'FARMLINK Agricultural Marketplace'
          }
        })
          .then(response => response.json())
          .then(data => {
            if (data && data.length > 0) {
              const result = data[0];
              const lat = parseFloat(result.lat);
              const lng = parseFloat(result.lon);
              
              if (!isNaN(lat) && !isNaN(lng)) {
                initializeMapWithKnownCoordinates(mapId, lat, lng, 12, farmName, locationText, productCount);
              } else {
                showMapError(mapElement, locationText, 'Invalid coordinates received');
              }
            } else {
              showMapError(mapElement, locationText, 'Location not found in database');
            }
          })
          .catch(error => {
            console.error('Geocoding error:', error);
            showMapError(mapElement, locationText, 'Network error occurred');
          });
      }
    }
    
    function initializeMapWithKnownCoordinates(mapId, lat, lng, zoom, farmName, locationText, productCount) {
      const mapElement = document.getElementById(mapId);
      
      // Clear loading state and initialize map
      mapElement.innerHTML = '';
      
      // Initialize Leaflet map with proper settings
      const miniMap = L.map(mapId, {
        center: [lat, lng],
        zoom: zoom,
        zoomControl: true,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        dragging: false,
        touchZoom: false,
        boxZoom: false,
        keyboard: false
      });
      
      // Add multiple tile layer options for reliability
      const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
        subdomains: ['a', 'b', 'c']
      });
      
      // Fallback tile layer
      const cartoDB = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap contributors © CARTO',
        maxZoom: 19,
        subdomains: 'abcd'
      });
      
      // Try to add OSM first, fallback to CartoDB if it fails
      osmLayer.addTo(miniMap);
      
      osmLayer.on('tileerror', function() {
        console.log('OSM tiles failed, switching to CartoDB');
        miniMap.removeLayer(osmLayer);
        cartoDB.addTo(miniMap);
      });
      
      // Wait a moment for tiles to load, then add marker
      setTimeout(() => {
        addDetailedFarmMarker(miniMap, lat, lng, farmName, locationText, productCount);
      }, 500);
      
      // Force map to refresh after initialization
      setTimeout(() => {
        miniMap.invalidateSize();
      }, 1000);
    }
    
    function showMapError(mapElement, locationText, errorMessage) {
      mapElement.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #999; text-align: center; padding: 20px;">
          <div style="font-size: 32px; margin-bottom: 8px;">🗺️</div>
          <div>Could not load map</div>
          <small>${locationText}</small>
          <small style="color: #666; margin-top: 4px;">${errorMessage}</small>
        </div>
      `;
    }

    function addFarmMarker(map, lat, lng, farmName, location, productCount) {
      // Create custom farm marker
      const farmIcon = L.divIcon({
        className: 'custom-farm-marker',
        html: '<div class="farm-marker-pin">🚜</div>',
        iconSize: [30, 30],
        iconAnchor: [15, 30],
        popupAnchor: [0, -30]
      });
      
      // Add marker for the farmer
      const marker = L.marker([lat, lng], { icon: farmIcon })
        .addTo(map)
        .bindPopup(`
          <div class="farm-popup">
            <h4>${farmName}</h4>
            <p>📍 ${location}</p>
            <p><small>📦 ${productCount} products available</small></p>
          </div>
        `)
        .openPopup();
    }

    function addDetailedFarmMarker(map, lat, lng, farmName, location, productCount) {
      // Create a simple but visible red marker using CSS-based icon
      const redMarker = L.marker([lat, lng], {
        icon: L.divIcon({
          className: 'red-map-marker',
          html: '<div class="red-pin">📍</div>',
          iconSize: [30, 30],
          iconAnchor: [15, 30],
          popupAnchor: [0, -30]
        })
      });
      
      // Add the red marker to the map
      redMarker.addTo(map);
      
      // Add detailed popup like the second image
      redMarker.bindPopup(`
        <div class="detailed-farm-popup">
          <div class="popup-header">
            <h4>🚜 Farm Location</h4>
          </div>
          <div class="popup-content">
            <p><strong>👤 Farmer:</strong> ${farmName}</p>
            <p><strong>📍 Address:</strong> ${location}</p>
            <p><strong>📍 Coordinates:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
            <p><strong>📦 Products:</strong> ${productCount} available</p>
          </div>
          <div class="popup-footer">
            <small>Click the Google Maps button below to get directions</small>
          </div>
        </div>
      `, {
        maxWidth: 300,
        className: 'custom-popup'
      }).openPopup();
      
      // Add a large, visible pulsing circle around the marker
      const pulsingCircle = L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.2,
        radius: 200,
        weight: 3,
        className: 'pulsing-circle'
      }).addTo(map);
      
      // Add a second smaller circle for emphasis
      const innerCircle = L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.4,
        radius: 50,
        weight: 2
      }).addTo(map);
      
      return redMarker;
    }

    // Compact map initialization functions
    function initializeCompactMap(mapId, lat, lng, farmName, location) {
      const compactMap = L.map(mapId, {
        center: [lat, lng],
        zoom: 16,  // Higher zoom for more precise location view
        zoomControl: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        dragging: false,
        touchZoom: false,
        boxZoom: false,
        keyboard: false,
        attributionControl: false
      });
      
      // Add map tiles
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        subdomains: ['a', 'b', 'c']
      }).addTo(compactMap);
      
      // Add precise red pin marker at exact farmer location
      const redPin = L.marker([lat, lng], {
        icon: L.icon({
          iconUrl: 'data:image/svg+xml;base64,' + btoa(`
            <svg width="24" height="36" viewBox="0 0 24 36" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 0C5.4 0 0 5.4 0 12c0 12 12 24 12 24s12-12 12-24C24 5.4 18.6 0 12 0z" fill="#dc3545" stroke="#ffffff" stroke-width="2"/>
              <circle cx="12" cy="12" r="5" fill="white"/>
              <circle cx="12" cy="12" r="3" fill="#dc3545"/>
            </svg>
          `),
          iconSize: [24, 36],
          iconAnchor: [12, 36],
          popupAnchor: [0, -36]
        })
      }).addTo(compactMap);
      
      // Add detailed popup that shows on click
      redPin.bindPopup(`
        <div style="text-align: center; font-family: Arial, sans-serif; min-width: 200px;">
          <h4 style="margin: 0 0 8px 0; color: #2E7D32;">🚜 ${farmName}</h4>
          <div style="background: #e8f5e8; padding: 6px; border-radius: 4px; margin: 8px 0;">
            <p style="margin: 2px 0; font-size: 11px; color: #2E7D32; font-weight: bold;">📍 EXACT FARMER LOCATION</p>
          </div>
          <p style="margin: 4px 0; font-size: 12px;"><strong>📍 Address:</strong> ${location}</p>
          <p style="margin: 4px 0; font-size: 11px; color: #666;"><strong>Precise Coordinates:</strong><br>${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
          <div style="background: #fff3cd; padding: 4px; border-radius: 3px; margin: 8px 0;">
            <p style="margin: 0; font-size: 10px; color: #856404;">🎯 This pin shows the farmer's exact location</p>
          </div>
          <p style="margin: 8px 0 0 0; font-size: 10px; color: #999;">Click "View Full Map" for navigation & directions</p>
        </div>
      `);
      
      // Add accuracy circle to show precise location
      const accuracyCircle = L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.1,
        radius: 25,  // 25 meter accuracy circle
        weight: 2,
        className: 'farmer-location-circle'
      }).addTo(compactMap);
      
      // Add pulsing outer circle for visibility
      const pulsingCircle = L.circle([lat, lng], {
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.05,
        radius: 50,  // Larger pulsing circle
        weight: 1,
        className: 'pulsing-circle'
      }).addTo(compactMap);
      
      // Add click handler to show location details
      compactMap.on('click', function() {
        redPin.openPopup();
      });
    }

    function initializeCompactMapWithGeocoding(mapId, locationText, farmName) {
      const mapElement = document.getElementById(mapId);
      mapElement.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 10px; color: #666;">🔍 Loading...</div>';
      
      // Use precise coordinates for farmer locations
      const knownLocations = {
        'Naval, Biliran': { lat: 11.561790, lng: 124.396527 },  // FREDREX SALAC current coordinates
        'Tacloban': { lat: 11.242100, lng: 125.007900 },
        'Cebu': { lat: 10.315700, lng: 123.885400 },
        'Manila': { lat: 14.599500, lng: 120.984200 }
      };
      
      if (knownLocations[locationText]) {
        const coords = knownLocations[locationText];
        mapElement.innerHTML = '';
        initializeCompactMap(mapId, coords.lat, coords.lng, farmName, locationText);
      } else {
        // Try geocoding
        const geocodeUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(locationText + ', Philippines')}&limit=1`;
        
        fetch(geocodeUrl)
          .then(response => response.json())
          .then(data => {
            if (data && data.length > 0) {
              const lat = parseFloat(data[0].lat);
              const lng = parseFloat(data[0].lon);
              mapElement.innerHTML = '';
              initializeCompactMap(mapId, lat, lng, farmName, locationText);
            } else {
              mapElement.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 10px; color: #999;">📍 Location not found</div>';
            }
          })
          .catch(error => {
            mapElement.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 10px; color: #999;">❌ Map error</div>';
          });
      }
    }

    // Add to cart functionality
    function addToCart(productId) {
      fetch('../common/add-to-cart.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: `product_id=${productId}&quantity=1`
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          // Show success message
          showNotification(data.message || 'Product added to cart!', 'success');
          // Update cart count if there's a cart counter
          updateCartCount();
        } else {
          showNotification(data.message || 'Failed to add product to cart', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showNotification('Failed to add product to cart. Please try again.', 'error');
      });
    }

    // Notification system
    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `notification notification-${type}`;
      notification.textContent = message;
      notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 1000;
        transition: all 0.3s ease;
        ${type === 'success' ? 'background: #4CAF50;' : ''}
        ${type === 'error' ? 'background: #f44336;' : ''}
        ${type === 'info' ? 'background: #2196F3;' : ''}
      `;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
          document.body.removeChild(notification);
        }, 300);
      }, 3000);
    }

    // Update cart count (if cart counter exists)
    function updateCartCount() {
      fetch('../common/get-cart-count.php')
        .then(response => response.json())
        .then(data => {
          const cartCounters = document.querySelectorAll('.cart-count');
          cartCounters.forEach(counter => {
            counter.textContent = data.count || 0;
          });
        })
        .catch(error => console.error('Error updating cart count:', error));
    }
  </script>

  <script src="/FARMLINK/assets/js/logout-confirmation.js"></script>
</body>
</html>
