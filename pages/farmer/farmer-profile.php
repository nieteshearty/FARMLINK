<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require farmer role
$user = SessionManager::requireRole('farmer');

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Original profile update code
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $farmName = trim($_POST['farm_name']);
        $location = trim($_POST['location']);
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        
        // Convert empty strings to null for coordinates
        if ($latitude === '' || $latitude === 'null') $latitude = null;
        if ($longitude === '' || $longitude === 'null') $longitude = null;
    
    // Handle profile picture upload
    $profilePicture = $user['profile_picture']; // Keep existing if no new upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            $relativePath = 'uploads/profiles/' . $fileName;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                // Delete old profile picture if exists
                if ($user['profile_picture']) {
                    $oldFs = $_SERVER['DOCUMENT_ROOT'] . (str_starts_with($user['profile_picture'], '/') ? $user['profile_picture'] : ('/' . $user['profile_picture']));
                    if (file_exists($oldFs)) unlink($oldFs);
                }
                $profilePicture = $relativePath;
            }
        }
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if username/email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $user['id']]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username or email already exists.";
        } else {
            // Update profile
            if (!empty($newPassword)) {
                if (empty($currentPassword) || !password_verify($currentPassword, $user['password'])) {
                    $_SESSION['error'] = "Current password is incorrect.";
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, farm_name = ?, location = ?, latitude = ?, longitude = ?, profile_picture = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $farmName, $location, $latitude, $longitude, $profilePicture, $hashedPassword, $user['id']]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, farm_name = ?, location = ?, latitude = ?, longitude = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$username, $email, $farmName, $location, $latitude, $longitude, $profilePicture, $user['id']]);
            }
            
            if (!isset($_SESSION['error'])) {
                // Update session data
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['farm_name'] = $farmName;
                $_SESSION['user']['location'] = $location;
                $_SESSION['user']['latitude'] = $latitude;
                $_SESSION['user']['longitude'] = $longitude;
                $_SESSION['user']['profile_picture'] = $profilePicture;
                
                $_SESSION['success'] = "Profile updated successfully!";
                SessionManager::logActivity($user['id'], 'profile', "Updated profile information");
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update profile.";
    }
    
    header('Location: farmer-profile.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • Profile</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
  <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/farmer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
  
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""/>
  
  <!-- Leaflet JavaScript -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
          crossorigin=""></script>
</head>
<body data-page="farmer-profile">
  <nav>
    <div class="nav-left">
      <a href="farmer-dashboard.php"><img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK" class="logo"></a>
      <span class="brand">FARMLINK - FARMER</span>
    </div>
    <span>Profile</span>
  </nav>

  <div class="sidebar">
    <a href="farmer-dashboard.php">Dashboard</a>
    <a href="farmer-products.php">My Products</a>
    <a href="farmer-orders.php">Orders</a>
    <a href="farmer-delivery-zones.php">Delivery Zones</a>
    <a href="farmer-profile.php" class="active">Profile</a>
    <a href="<?= BASE_URL ?>/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>Profile Settings</h1>
    <p class="lead">Update your farm information and account details.</p>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="form-section">
      <form method="POST" enctype="multipart/form-data">
        <h3>Profile Picture</h3>
        <div class="profile-upload">
          <div class="current-profile">
            <?php if ($user['profile_picture']): ?>
              <?php 
                // Handle different path formats for existing profile pictures
                $profilePicPath = $user['profile_picture'];
                if (strpos($profilePicPath, 'http') === 0) {
                  // use as is
                } elseif (strpos($profilePicPath, '/') === 0) {
                  $profilePicPath = BASE_URL . $profilePicPath;
                } else {
                  $profilePicPath = BASE_URL . '/uploads/profiles/' . $profilePicPath;
                }
              ?>
              <img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile Picture" onerror="this.src='<?= BASE_URL ?>/assets/img/default-avatar.png';" class="current-pic">
            <?php else: ?>
              <div class="profile-pic-default current-pic">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
              </div>
            <?php endif; ?>
            <label>Current Profile Picture</label>
          </div>
          <input type="file" name="profile_picture" id="profilePic" accept="image/*" />
          <div class="profile-preview" id="profilePreview" style="display:none;">
            <img id="previewImg" src="" alt="Profile Preview" />
            <label>New Profile Picture</label>
          </div>
        </div>
        
        <h3>Basic Information</h3>
        <input name="username" placeholder="Username" value="<?= htmlspecialchars($user['username']) ?>" required />
        <input name="email" type="email" placeholder="Email" value="<?= htmlspecialchars($user['email']) ?>" required />
        <input name="farm_name" placeholder="Farm Name" value="<?= htmlspecialchars($user['farm_name'] ?? '') ?>" />
        <div class="location-search-container">
          <input name="location" id="location-input" placeholder="Location (e.g., Tuguegarao City, Cagayan)" value="<?= htmlspecialchars($user['location'] ?? '') ?>" />
          <button type="button" class="location-search-btn" onclick="searchLocation()" title="Search location on map">
            🔍
          </button>
        </div>
        
        <!-- Interactive Location Setting -->
        <div class="location-setting-section">
          <h4>📍 Set Your Farm Location</h4>
          <p class="location-help">Click on the map to set your farm's precise location. This helps buyers find you and calculate delivery distances.</p>
          
          <div class="coordinate-inputs">
            <div class="coordinate-group">
              <label for="latitude">Latitude:</label>
              <input type="number" name="latitude" id="latitude" step="any" placeholder="e.g., 17.6132" value="<?= htmlspecialchars($user['latitude'] ?? '') ?>" />
            </div>
            <div class="coordinate-group">
              <label for="longitude">Longitude:</label>
              <input type="number" name="longitude" id="longitude" step="any" placeholder="e.g., 121.7270" value="<?= htmlspecialchars($user['longitude'] ?? '') ?>" />
            </div>
          </div>
          
          <div class="location-buttons">
            <button type="button" class="btn btn-secondary" onclick="getCurrentLocation()">📍 Use My Current Location</button>
            <button type="button" class="btn btn-secondary" onclick="clearLocation()">🗑️ Clear Location</button>
            <button type="button" class="btn btn-secondary" onclick="centerMapOnPhilippines()">🇵🇭 Center on Philippines</button>
          </div>
          
          <!-- Interactive Leaflet Map -->
          <div id="location-map" class="location-map"></div>
          
          <div class="location-status" id="location-status">
            <span class="status-text">Click on the map to set your farm location</span>
          </div>
        </div>
        
        <h3>Change Password (Optional)</h3>
        <input name="current_password" type="password" placeholder="Current Password" />
        <input name="new_password" type="password" placeholder="New Password" />
        
        <div style="text-align:right; margin-top: 16px;">
          <button type="submit" class="btn">Update Profile</button>
        </div>
      </form>
    </section>


  </main>

  <style>
    /* Force agricultural green sidebar background */
    .sidebar {
      background: #2E7D32 !important;
      position: fixed;
      left: 0;
      top: 80px; /* Lowered sidebar position */
      width: 200px;
      height: calc(100vh - 80px);
      overflow-y: auto;
      z-index: 1000;
    }
    
    /* Ensure body doesn't have horizontal scroll */
    body {
      overflow-x: hidden;
    }
    
    .form-section {
      max-width: 100%;
      width: 100%;
      padding: 20px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    
    .form-section input, .form-section select, .form-section textarea {
      width: 100%;
      margin: 8px 0;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      box-sizing: border-box;
      font-size: 14px;
    }

    .form-section input:focus, .form-section select:focus, .form-section textarea:focus {
      outline: none;
      border-color: #4CAF50;
      box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
    }
    
    .form-section h3 {
      margin-top: 24px;
      margin-bottom: 12px;
      color: #2d6a4f;
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

    /* Location Setting Styles */
    .location-setting-section {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      margin: 20px 0;
      border-left: 4px solid #4CAF50;
    }

    .location-setting-section h4 {
      margin: 0 0 8px 0;
      color: #2E7D32;
      font-size: 18px;
    }

    .location-help {
      color: #666;
      margin-bottom: 20px;
      font-size: 14px;
      line-height: 1.4;
    }

    .coordinate-inputs {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 16px;
    }

    .coordinate-group {
      display: flex;
      flex-direction: column;
    }

    .coordinate-group label {
      font-weight: 600;
      color: #2E7D32;
      margin-bottom: 4px;
      font-size: 13px;
    }

    .coordinate-group input {
      padding: 10px 12px;
      border: 2px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.3s ease;
    }

    .coordinate-group input:focus {
      outline: none;
      border-color: #2E7D32;
      box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
    }

    .location-buttons {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .location-buttons .btn {
      font-size: 13px;
      padding: 8px 12px;
      white-space: nowrap;
    }

    .location-map {
      height: 400px;
      width: 100%;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-bottom: 12px;
    }

    .location-status {
      background: white;
      padding: 12px;
      border-radius: 6px;
      text-align: center;
      border: 1px solid #ddd;
    }

    .location-status .status-text {
      color: #666;
      font-size: 14px;
    }

    .location-status.success {
      background: #d4edda;
      border-color: #c3e6cb;
      color: #155724;
    }

    .location-status.error {
      background: #f8d7da;
      border-color: #f5c6cb;
      color: #721c24;
    }

    /* Leaflet map specific styles */
    .leaflet-container {
      font-family: inherit;
    }

    .leaflet-popup-content-wrapper {
      border-radius: 8px;
    }

    .leaflet-popup-content {
      margin: 12px 16px;
      line-height: 1.4;
    }

    .farm-popup {
      text-align: center;
    }

    .farm-popup h4 {
      margin: 0 0 8px 0;
      color: #2E7D32;
    }

    .farm-popup p {
      margin: 4px 0;
      color: #666;
      font-size: 13px;
    }

    /* Custom marker styles */
    .custom-farm-marker {
      background: transparent;
      border: none;
    }

    .marker-pin {
      font-size: 24px;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
      cursor: grab;
      animation: bounce 2s infinite;
      transition: transform 0.2s ease;
    }

    .marker-pin:hover {
      transform: scale(1.1);
      filter: brightness(1.2);
    }

    .marker-pin:active {
      cursor: grabbing;
      transform: scale(0.95);
    }

    @keyframes bounce {
      0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
      }
      40% {
        transform: translateY(-5px);
      }
      60% {
        transform: translateY(-3px);
      }
    }

    /* Enhanced map cursor */
    .leaflet-container {
      cursor: crosshair;
    }

    .leaflet-container:active {
      cursor: pointer;
    }

    /* Map click feedback */
    .leaflet-clickable {
      cursor: pointer;
    }

    /* Location input auto-update animation */
    .location-input-updated {
      background-color: #e8f5e8 !important;
      border-color: #4CAF50 !important;
      transition: all 0.3s ease;
    }

    /* Loading indicator for reverse geocoding */
    .geocoding-loading {
      position: relative;
    }

    .geocoding-loading::after {
      content: "🔍";
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.5; }
      100% { opacity: 1; }
    }

    /* Location search container */
    .location-search-container {
      position: relative;
      display: flex;
      align-items: center;
      margin-bottom: 16px;
    }

    .location-search-container input[name="location"] {
      flex: 1;
      padding-right: 45px;
    }

    .location-search-btn {
      position: absolute;
      right: 8px;
      background: #4CAF50;
      border: none;
      border-radius: 4px;
      padding: 6px 10px;
      font-size: 14px;
      cursor: pointer;
      color: white;
      transition: background-color 0.3s ease;
    }

    .location-search-btn:hover {
      background: #45a049;
    }

    .location-search-btn:active {
      background: #3d8b40;
    }

    .location-search-btn.searching {
      animation: pulse 1.5s infinite;
      background: #2196F3;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .coordinate-inputs {
        grid-template-columns: 1fr;
      }

      .location-buttons {
        flex-direction: column;
      }

      .location-buttons .btn {
        width: 100%;
      }

      .location-map {
        height: 300px;
      }
    }

    /* Farm Location Map Styles */
    .farm-location-section {
      margin-top: 30px;
      background: linear-gradient(135deg, #f1f8e9 0%, #e8f5e8 100%);
      border-left: 4px solid #4CAF50;
    }

    .farm-location-section h3 {
      margin: 0 0 12px 0;
      color: #2E7D32;
      font-size: 22px;
    }

    .location-description {
      color: #666;
      margin-bottom: 20px;
      font-size: 14px;
      line-height: 1.5;
    }

    .farm-info-display {
      background: white;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .farm-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 12px;
    }

    .info-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 0;
    }

    .info-item strong {
      color: #2E7D32;
      min-width: 100px;
    }

    .info-item span {
      color: #555;
    }

    .coordinates {
      font-family: monospace;
      background: #e8f5e8;
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 12px;
    }

    .map-container {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      margin-bottom: 20px;
    }

    .interactive-map {
      position: relative;
    }

    .interactive-map iframe {
      width: 100%;
      height: 400px;
      border: none;
      display: block;
    }

    .map-actions {
      padding: 16px;
      background: #f8f9fa;
      border-top: 1px solid #e9ecef;
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .map-actions .btn {
      flex: 1;
      min-width: 140px;
      font-size: 13px;
      padding: 10px 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .no-location-map {
      height: 300px;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px dashed #dee2e6;
    }

    .no-location-content {
      text-align: center;
      color: #6c757d;
      max-width: 400px;
      padding: 20px;
    }

    .no-location-icon {
      font-size: 48px;
      margin-bottom: 16px;
      opacity: 0.5;
    }

    .no-location-content h4 {
      margin: 0 0 12px 0;
      color: #495057;
    }

    .no-location-content p {
      margin: 8px 0;
      line-height: 1.4;
    }

    .location-text {
      background: white;
      padding: 12px;
      border-radius: 6px;
      border: 1px solid #dee2e6;
      margin-top: 16px;
    }

    .location-benefits {
      background: white;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .location-benefits h4 {
      margin: 0 0 16px 0;
      color: #2E7D32;
      font-size: 18px;
    }

    .benefits-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }

    .benefit-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      background: #f8f9fa;
      border-radius: 8px;
      border-left: 3px solid #4CAF50;
    }

    .benefit-icon {
      font-size: 24px;
      flex-shrink: 0;
    }

    .benefit-text strong {
      display: block;
      color: #2E7D32;
      font-size: 14px;
      margin-bottom: 2px;
    }

    .benefit-text small {
      color: #666;
      font-size: 12px;
      line-height: 1.3;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .farm-details {
        grid-template-columns: 1fr;
      }

      .map-actions {
        flex-direction: column;
      }

      .map-actions .btn {
        min-width: auto;
      }

      .benefits-grid {
        grid-template-columns: 1fr;
      }

      .interactive-map iframe {
        height: 300px;
      }
    }


    /* Delivery Zones Styles */
    .section-description {
      color: #666;
      margin-bottom: 20px;
      font-style: italic;
    }

    .delivery-zone-form {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 30px;
      border-left: 4px solid #4CAF50;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 15px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group label {
      font-weight: 600;
      margin-bottom: 5px;
      color: #333;
    }

    .checkbox-group {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 10px;
      margin-top: 5px;
    }

    .checkbox-group label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: normal;
      cursor: pointer;
    }

    .checkbox-group input[type="checkbox"] {
      margin: 0;
    }

    .btn-success {
      background: #4CAF50;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: background-color 0.3s;
    }

    .btn-success:hover {
      background: #45a049;
    }

    .zones-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
    }

    .zone-card {
      background: white;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .zone-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }

    .zone-header h5 {
      margin: 0;
      color: #2d6a4f;
      font-size: 1.1em;
    }

    .zone-status {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.8em;
      font-weight: 600;
      text-transform: uppercase;
    }

    .zone-status.active {
      background: #d4edda;
      color: #155724;
    }

    .zone-status.inactive {
      background: #f8d7da;
      color: #721c24;
    }

    .zone-details p {
      margin: 8px 0;
      font-size: 0.9em;
    }

    .zone-details strong {
      color: #2d6a4f;
    }

    .zone-actions {
      display: flex;
      gap: 10px;
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #eee;
    }

    .btn-edit, .btn-delete {
      padding: 8px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.85em;
      display: flex;
      align-items: center;
      gap: 5px;
      transition: background-color 0.3s;
    }

    .btn-edit {
      background: #007bff;
      color: white;
    }

    .btn-edit:hover {
      background: #0056b3;
    }

    .btn-delete {
      background: #dc3545;
      color: white;
    }

    .btn-delete:hover {
      background: #c82333;
    }

    .no-zones {
      text-align: center;
      padding: 40px;
      color: #666;
      background: #f8f9fa;
      border-radius: 8px;
      border: 2px dashed #ddd;
    }

    .no-zones i {
      color: #4CAF50;
      margin-right: 8px;
    }

    @media (max-width: 768px) {
      .form-row {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .checkbox-group {
        grid-template-columns: repeat(2, 1fr);
      }

      .zones-grid {
        grid-template-columns: 1fr;
      }

      .zone-actions {
        flex-direction: column;
      }
    }
  </style>
</div>

<script>
// Profile picture preview
document.getElementById('profilePic').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('previewImg').src = e.target.result;
      document.getElementById('profilePreview').style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
});

// Delivery zone management functions
function editZone(zoneId) {
  // For now, just alert - can be enhanced later
  alert('Edit functionality coming soon! Zone ID: ' + zoneId);
}

function deleteZone(zoneId, zoneName) {
  if (confirm('Are you sure you want to delete the delivery zone "' + zoneName + '"? This action cannot be undone.')) {
    // Create a form to submit the delete request
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_zone';
    
    const zoneIdInput = document.createElement('input');
    zoneIdInput.type = 'hidden';
    zoneIdInput.name = 'zone_id';
    zoneIdInput.value = zoneId;
    
    form.appendChild(actionInput);
    form.appendChild(zoneIdInput);
    document.body.appendChild(form);
    form.submit();
  }
}
</script>

<style>
.profile-upload {
  display: flex;
  gap: 20px;
  align-items: center;
  margin: 20px 0;
  flex-wrap: wrap;
  width: 100%;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 2px dashed #4CAF50;
}

.current-profile, .profile-preview {
  text-align: center;
}

.current-pic, .profile-preview img {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  border: 3px solid #4CAF50;
  object-fit: cover;
  margin-bottom: 8px;
}

.profile-pic-default {
  background-color: #4CAF50;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 24px;
}

.profile-upload label {
  font-size: 12px;
  color: #666;
  display: block;
}

.profile-upload input[type="file"] {
  margin: 10px 0;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  background: white;
}

.btn {
  background: #4CAF50;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: background-color 0.3s ease;
  width: auto;
  min-width: 120px;
}

.btn:hover {
  background: #45a049;
}

/* Responsive improvements */
@media (max-width: 768px) {
  .main {
    width: 100%;
    margin-left: 0;
    padding: 15px;
  }
  
  .form-section {
    padding: 15px;
    margin: 10px 0;
  }
  
  .profile-upload {
    flex-direction: column;
    text-align: center;
  }
  
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .btn {
    width: 100%;
    margin-top: 10px;
  }
}

/* Main content area improvements */
.main {
  padding: 20px;
  padding-top: 100px; /* Account for lowered sidebar */
  max-width: none;
  width: calc(100% - 200px); /* Account for sidebar width */
  margin-left: 200px; /* Sidebar width */
  box-sizing: border-box;
}

/* Ensure forms don't get too narrow */
.form-section {
  min-width: 300px;
}
</style>

<script>
// Global variables for maps - Updated for Cabucgayan fix
let locationMap = null;
let displayMap = null;
let currentMarker = null;
let displayMarker = null;

// Profile picture preview functionality
document.addEventListener('DOMContentLoaded', function() {
  const profilePicInput = document.getElementById('profilePic');
  const profilePreview = document.getElementById('profilePreview');
  const previewImg = document.getElementById('previewImg');
  
  if (profilePicInput) {
    profilePicInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          previewImg.src = e.target.result;
          profilePreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
      } else {
        profilePreview.style.display = 'none';
      }
    });
  }

  // Initialize maps
  initializeLocationMap();
  
  // Set up coordinate input listeners
  setupCoordinateInputs();
  
  // Set up location input listeners
  setupLocationInputListeners();
});

// Initialize the location setting map
function initializeLocationMap() {
  const mapElement = document.getElementById('location-map');
  if (!mapElement) return;

  // Default center on Philippines
  const defaultLat = <?= $user['latitude'] ?? '12.8797' ?>;
  const defaultLng = <?= $user['longitude'] ?? '121.7740' ?>;
  
  // Initialize map
  locationMap = L.map('location-map').setView([defaultLat, defaultLng], <?= $user['latitude'] ? '15' : '6' ?>);
  
  // Add tile layer
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
  }).addTo(locationMap);
  
  // Add existing marker if coordinates exist
  <?php if ($user['latitude'] && $user['longitude']): ?>
    // Create custom marker icon for existing location
    const existingFarmIcon = L.divIcon({
      className: 'custom-farm-marker',
      html: '<div class="marker-pin">📍</div>',
      iconSize: [30, 30],
      iconAnchor: [15, 30],
      popupAnchor: [0, -30]
    });
    
    currentMarker = L.marker([<?= $user['latitude'] ?>, <?= $user['longitude'] ?>], { 
      icon: existingFarmIcon,
      draggable: true
    })
      .addTo(locationMap)
      .bindPopup('<div class="farm-popup"><h4><?= htmlspecialchars($user['farm_name'] ?: $user['username']) ?></h4><p>Your farm location<br><small>Drag pin or click elsewhere to move</small></p></div>');
    
    // Add drag functionality to existing marker
    const existingFarmName = '<?= htmlspecialchars($user['farm_name'] ?: $user['username']) ?>';
    addDragEventsToMarker(currentMarker, existingFarmName);
    
    // Get address for existing location if we don't have location text
    const currentLocationText = document.querySelector('input[name="location"]').value;
    if (!currentLocationText || currentLocationText.trim() === '') {
      reverseGeocode(<?= $user['latitude'] ?>, <?= $user['longitude'] ?>);
    }
  <?php endif; ?>
  
  // Add click event to set location
  locationMap.on('click', function(e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;
    
    // Provide immediate visual feedback
    updateLocationStatus('Setting location...', 'info');
    
    // Set the location
    setLocationOnMap(lat, lng);
  });
  
  // Add mousemove event for coordinate preview
  let previewTimeout;
  locationMap.on('mousemove', function(e) {
    const lat = e.latlng.lat.toFixed(6);
    const lng = e.latlng.lng.toFixed(6);
    
    // Clear previous timeout
    clearTimeout(previewTimeout);
    
    // Show coordinate preview with slight delay to avoid flickering
    previewTimeout = setTimeout(() => {
      if (!currentMarker) {
        updateLocationStatus(`📍 Click to set location: ${lat}, ${lng}`);
      } else {
        updateLocationStatus(`📍 Farm location set. Click elsewhere to move: ${lat}, ${lng}`);
      }
    }, 100);
  });
  
  // Clear preview when mouse leaves map
  locationMap.on('mouseout', function(e) {
    clearTimeout(previewTimeout);
    if (!currentMarker) {
      updateLocationStatus('Click anywhere on the map to set your farm location');
    } else {
      updateLocationStatus('Farm location is set. Click elsewhere to move it.', 'success');
    }
  });
  
  // Initial status message
  if (!currentMarker) {
    updateLocationStatus('Click anywhere on the map to set your farm location');
  } else {
    updateLocationStatus('Farm location is set. Click elsewhere to move it.', 'success');
  }
}


// Set location on map
function setLocationOnMap(lat, lng) {
  // Ensure coordinates are numbers and within valid ranges
  lat = parseFloat(lat);
  lng = parseFloat(lng);
  
  if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
    updateLocationStatus('Invalid coordinates provided', 'error');
    return;
  }
  
  // Update coordinate inputs with precise values
  document.getElementById('latitude').value = lat.toFixed(6);
  document.getElementById('longitude').value = lng.toFixed(6);
  
  // Remove existing marker
  if (currentMarker) {
    locationMap.removeLayer(currentMarker);
  }
  
  // Get farm name for marker popup
  const farmName = document.querySelector('input[name="farm_name"]').value || 
                   document.querySelector('input[name="username"]').value || 
                   'Your Farm';
  
  // Create custom marker icon (green for farm)
  const farmIcon = L.divIcon({
    className: 'custom-farm-marker',
    html: '<div class="marker-pin">📍</div>',
    iconSize: [30, 30],
    iconAnchor: [15, 30],
    popupAnchor: [0, -30]
  });
  
  // Debug logging
  
  // Start with loading message
  let displayLocation = 'Getting address...';
  let locationName = null;
  
  // Add new marker at exact clicked location with drag functionality
  currentMarker = L.marker([lat, lng], { 
    icon: farmIcon,
    draggable: true
  })
    .addTo(locationMap)
    .bindPopup(`
      <div class="farm-popup">
        <h4>${farmName}</h4>
        <p><strong>Farm Location</strong></p>
        <p>📍 ${displayLocation}</p>
        <p>Latitude: ${lat.toFixed(6)}</p>
        <p>Longitude: ${lng.toFixed(6)}</p>
        <small>Drag pin or click elsewhere to move</small>
      </div>
    `)
    .openPopup();
  
  // Add drag event listeners to update coordinates when marker is dragged
  addDragEventsToMarker(currentMarker, farmName);
  
  // Center map on the new marker
  locationMap.setView([lat, lng], locationMap.getZoom());
  
  // Update status with coordinates first
  updateLocationStatus(`📍 Farm location set at ${lat.toFixed(6)}, ${lng.toFixed(6)}`, 'success');
  
  // Get location name asynchronously
  getLocationNameFromCoordinates(lat, lng).then(locationName => {
    if (locationName) {
      
      // Update location input field
      const locationInput = document.querySelector('input[name="location"]');
      if (locationInput) {
        locationInput.value = locationName;
        locationInput.classList.add('location-input-updated');
        
        // Force the input to update by triggering events
        locationInput.dispatchEvent(new Event('input', { bubbles: true }));
        locationInput.dispatchEvent(new Event('change', { bubbles: true }));
        
        setTimeout(() => {
          locationInput.classList.remove('location-input-updated');
        }, 3000);
      }
      
      // Update marker popup with the actual location name
      if (currentMarker) {
        currentMarker.setPopupContent(`
          <div class="farm-popup">
            <h4>${farmName}</h4>
            <p><strong>Farm Location</strong></p>
            <p>📍 ${locationName}</p>
            <p>Latitude: ${lat.toFixed(6)}</p>
            <p>Longitude: ${lng.toFixed(6)}</p>
            <small>Drag pin or click elsewhere to move</small>
          </div>
        `);
      }
      
      // Update status with location name
      updateLocationStatus(`📍 Location set: ${locationName}`, 'success');
      showLocationNotification(`Location found: ${locationName}`, 'success');
      
    } else {
      showLocationNotification('Location set, but address could not be determined', 'info');
    }
  }).catch(error => {
    console.error('Error getting location name:', error);
    showLocationNotification('Location set, but address lookup failed', 'error');
  });
}

// Add drag events to marker (extracted to avoid code duplication)
function addDragEventsToMarker(marker, farmName) {
  marker.on('dragstart', function(e) {
    updateLocationStatus('🔄 Dragging farm location...', 'info');
  });
  
  marker.on('drag', function(e) {
    const dragLat = e.target.getLatLng().lat;
    const dragLng = e.target.getLatLng().lng;
    updateLocationStatus(`🔄 Dragging: ${dragLat.toFixed(6)}, ${dragLng.toFixed(6)}`, 'info');
  });
  
  marker.on('dragend', function(e) {
    const newLat = e.target.getLatLng().lat;
    const newLng = e.target.getLatLng().lng;
    
    // Update coordinate inputs
    document.getElementById('latitude').value = newLat.toFixed(6);
    document.getElementById('longitude').value = newLng.toFixed(6);
    
    // Update popup content with loading message
    e.target.setPopupContent(`
      <div class="farm-popup">
        <h4>${farmName}</h4>
        <p><strong>Farm Location</strong></p>
        <p>Latitude: ${newLat.toFixed(6)}</p>
        <p>Longitude: ${newLng.toFixed(6)}</p>
        <p id="location-address">🔍 Getting address...</p>
        <small>Drag pin or click elsewhere to move</small>
      </div>
    `);
    
    // Update status
    updateLocationStatus(`📍 Farm location updated: ${newLat.toFixed(6)}, ${newLng.toFixed(6)}`, 'success');
    showLocationNotification('Farm location updated by dragging!', 'success');
    
    // Get new address for dragged location
    reverseGeocode(newLat, newLng);
  });
}

// Function to get location name from coordinates using live geocoding
async function getLocationNameFromCoordinates(lat, lng) {
  try {
    // Use Nominatim reverse geocoding with better parameters
    const nominatimUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=14&addressdetails=1&accept-language=en`;
    
    const response = await fetch(nominatimUrl, {
      headers: {
        'User-Agent': 'FARMLINK Agricultural Marketplace'
      }
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const data = await response.json();
    
    if (data && data.display_name) {
      const locationName = parseNominatimAddress(data);
      return locationName;
    }
    
    return null;
  } catch (error) {
    console.error('Geocoding error:', error);
    return null;
  }
}

// Reverse geocoding function to get address from coordinates
function reverseGeocode(lat, lng) {
  // Show loading in status
  updateLocationStatus(`🔍 Getting location name for ${lat.toFixed(6)}, ${lng.toFixed(6)}...`, 'info');
  
  // Add loading indicator to location input
  const locationInput = document.querySelector('input[name="location"]');
  if (locationInput) {
    locationInput.classList.add('geocoding-loading');
    locationInput.placeholder = 'Getting location name...';
  }
  
  // First try with centralized location recognition
  const locationName = getLocationNameFromCoordinates(lat, lng);
  if (locationName) {
    handleSuccessfulGeocode(lat, lng, locationName);
    return;
  }
  
  // Try multiple reverse geocoding services for better reliability
  tryNominatimGeocode(lat, lng)
    .catch(() => tryAlternativeGeocode(lat, lng))
    .catch(() => handleGeocodeFailure(lat, lng));
}

function tryNominatimGeocode(lat, lng) {
  return new Promise((resolve, reject) => {
    // Use Nominatim with better parameters and User-Agent
    const nominatimUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=14&addressdetails=1&accept-language=en`;
    
    fetch(nominatimUrl, {
      headers: {
        'User-Agent': 'FARMLINK Agricultural Marketplace'
      }
    })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        if (data && data.display_name) {
          const locationName = parseNominatimAddress(data);
          handleSuccessfulGeocode(lat, lng, locationName);
          resolve(locationName);
        } else {
          reject(new Error('No address data'));
        }
      })
      .catch(error => {
        console.error('Nominatim geocoding error:', error);
        reject(error);
      });
  });
}

function tryAlternativeGeocode(lat, lng) {
  return new Promise((resolve, reject) => {
    // Fallback: Use a simpler approach with known Philippine locations
    const philippineLocations = [
      { name: 'Naval, Biliran', lat: 11.5682, lng: 124.4133, radius: 0.05 },
      { name: 'Caibiran, Biliran', lat: 11.5500, lng: 124.5833, radius: 0.05 },
      { name: 'Culaba, Biliran', lat: 11.4667, lng: 124.5667, radius: 0.05 },
      { name: 'Kawayan, Biliran', lat: 11.4833, lng: 124.5167, radius: 0.05 },
      { name: 'Maripipi, Biliran', lat: 11.4167, lng: 124.4000, radius: 0.05 },
      { name: 'Almeria, Biliran', lat: 11.3833, lng: 124.4167, radius: 0.05 },
      { name: 'Biliran, Biliran', lat: 11.4667, lng: 124.4833, radius: 0.05 },
      { name: 'Cabucgayan, Biliran', lat: 11.4729, lng: 124.5735, radius: 0.05 },
      { name: 'Tacloban, Leyte', lat: 11.2421, lng: 125.0079, radius: 0.1 },
      { name: 'Ormoc, Leyte', lat: 11.0059, lng: 124.6074, radius: 0.1 },
      { name: 'Cebu City, Cebu', lat: 10.3157, lng: 123.8854, radius: 0.1 },
      { name: 'Manila, Metro Manila', lat: 14.5995, lng: 120.9842, radius: 0.1 }
    ];
    
    // Find closest known location
    for (const location of philippineLocations) {
      const distance = Math.sqrt(
        Math.pow(lat - location.lat, 2) + Math.pow(lng - location.lng, 2)
      );
      
      if (distance <= location.radius) {
        handleSuccessfulGeocode(lat, lng, location.name);
        resolve(location.name);
        return;
      }
    }
    
    // If no close match, use generic Philippines location
    handleSuccessfulGeocode(lat, lng, 'Philippines');
    resolve('Philippines');
  });
}

function parseNominatimAddress(data) {
  const address = data.address || {};
  const components = [];
  
  console.log('Address components:', address); // Debug log
  
  // Add specific place (village, town, city, etc.)
  if (address.village) components.push(address.village);
  else if (address.town) components.push(address.town);
  else if (address.city) components.push(address.city);
  else if (address.municipality) components.push(address.municipality);
  else if (address.county) components.push(address.county);
  else if (address.suburb) components.push(address.suburb);
  else if (address.hamlet) components.push(address.hamlet);
  
  // Add municipality if different from above
  if (address.municipality && !components.includes(address.municipality)) {
    components.push(address.municipality);
  }
  
  // Add county if different from above (for Philippines, this is often the province)
  if (address.county && !components.includes(address.county)) {
    components.push(address.county);
  }
  
  // Add province/state
  if (address.state) components.push(address.state);
  else if (address.province) components.push(address.province);
  
  // If no components found, try to extract from display_name
  if (components.length === 0 && data.display_name) {
    const displayParts = data.display_name.split(',');
    if (displayParts.length >= 2) {
      // Take the first two meaningful parts
      components.push(displayParts[0].trim());
      if (displayParts[1].trim() !== displayParts[0].trim()) {
        components.push(displayParts[1].trim());
      }
    }
  }
  
  const result = components.join(', ') || data.display_name || 'Philippines';
  console.log('Parsed result:', result); // Debug log
  return result;
}

function handleSuccessfulGeocode(lat, lng, locationName) {
  // Update the location input field
  const locationInput = document.querySelector('input[name="location"]');
  if (locationInput) {
    locationInput.value = locationName;
    locationInput.classList.remove('geocoding-loading');
    locationInput.placeholder = 'Location';
    
    // Add visual feedback to show the field was updated
    locationInput.classList.add('location-input-updated');
    setTimeout(() => {
      locationInput.classList.remove('location-input-updated');
    }, 3000);
  }
  
  // Update marker popup with address
  if (currentMarker) {
    const farmName = document.querySelector('input[name="farm_name"]').value || 
                     document.querySelector('input[name="username"]').value || 
                     'Your Farm';
    
    currentMarker.setPopupContent(`
      <div class="farm-popup">
        <h4>${farmName}</h4>
        <p><strong>Farm Location</strong></p>
        <p>📍 ${locationName}</p>
        <p>Latitude: ${lat.toFixed(6)}</p>
        <p>Longitude: ${lng.toFixed(6)}</p>
        <small>Drag pin or click elsewhere to move</small>
      </div>
    `);
  }
  
  // Update status
  updateLocationStatus(`📍 Location set: ${locationName}`, 'success');
  showLocationNotification(`Location found: ${locationName}`, 'success');
}

function handleGeocodeFailure(lat, lng) {
  console.error('All geocoding methods failed');
  
  // Remove loading indicator
  const locationInput = document.querySelector('input[name="location"]');
  if (locationInput) {
    locationInput.classList.remove('geocoding-loading');
    locationInput.placeholder = 'Location';
    // Set a fallback location name
    locationInput.value = 'Philippines';
  }
  
  updateLocationStatus(`📍 Location set at ${lat.toFixed(6)}, ${lng.toFixed(6)} (using fallback address)`, 'success');
  showLocationNotification('Location set with fallback address: Philippines', 'info');
  
  // Update popup with fallback
  if (currentMarker) {
    const farmName = document.querySelector('input[name="farm_name"]').value || 
                     document.querySelector('input[name="username"]').value || 
                     'Your Farm';
    
    currentMarker.setPopupContent(`
      <div class="farm-popup">
        <h4>${farmName}</h4>
        <p><strong>Farm Location</strong></p>
        <p>📍 Philippines</p>
        <p>Latitude: ${lat.toFixed(6)}</p>
        <p>Longitude: ${lng.toFixed(6)}</p>
        <small>Drag pin or click elsewhere to move</small>
      </div>
    `);
  }
}

// Setup coordinate input listeners
function setupCoordinateInputs() {
  const latInput = document.getElementById('latitude');
  const lngInput = document.getElementById('longitude');
  
  function updateMapFromInputs() {
    const lat = parseFloat(latInput.value);
    const lng = parseFloat(lngInput.value);
    
    if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
      locationMap.setView([lat, lng], 15);
      
      if (currentMarker) {
        locationMap.removeLayer(currentMarker);
      }
      
      const farmName = document.querySelector('input[name="farm_name"]').value || document.querySelector('input[name="username"]').value || 'Your Farm';
      currentMarker = L.marker([lat, lng])
        .addTo(locationMap)
        .bindPopup(`<div class="farm-popup"><h4>${farmName}</h4><p>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}</p></div>`);
      
      updateLocationStatus(`Location set: ${lat.toFixed(6)}, ${lng.toFixed(6)}`, 'success');
    }
  }
  
  latInput.addEventListener('input', updateMapFromInputs);
  lngInput.addEventListener('input', updateMapFromInputs);
}

// Get current location using GPS
function getCurrentLocation() {
  if (!navigator.geolocation) {
    updateLocationStatus('Geolocation is not supported by this browser', 'error');
    return;
  }
  
  updateLocationStatus('Getting your location...');
  
  navigator.geolocation.getCurrentPosition(
    function(position) {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      
      setLocationOnMap(lat, lng);
      locationMap.setView([lat, lng], 15);
      
      showLocationNotification('Location updated successfully!', 'success');
    },
    function(error) {
      let errorMessage = 'Unable to get your location. ';
      switch(error.code) {
        case error.PERMISSION_DENIED:
          errorMessage += 'Please allow location access and try again.';
          break;
        case error.POSITION_UNAVAILABLE:
          errorMessage += 'Location information is unavailable.';
          break;
        case error.TIMEOUT:
          errorMessage += 'Location request timed out.';
          break;
        default:
          errorMessage += 'An unknown error occurred.';
          break;
      }
      updateLocationStatus(errorMessage, 'error');
      showLocationNotification(errorMessage, 'error');
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 60000
    }
  );
}

// Clear location
function clearLocation() {
  document.getElementById('latitude').value = '';
  document.getElementById('longitude').value = '';
  
  if (currentMarker) {
    locationMap.removeLayer(currentMarker);
    currentMarker = null;
  }
  
  centerMapOnPhilippines();
  updateLocationStatus('Click on the map to set your farm location');
  showLocationNotification('Location cleared', 'info');
}

// Center map on Philippines
function centerMapOnPhilippines() {
  if (locationMap) {
    locationMap.setView([12.8797, 121.7740], 6);
  }
}

// Update location status
function updateLocationStatus(message, type = '') {
  const statusElement = document.getElementById('location-status');
  const statusText = statusElement.querySelector('.status-text');
  
  statusText.textContent = message;
  
  // Reset classes
  statusElement.className = 'location-status';
  
  // Add type class if specified
  if (type) {
    statusElement.classList.add(type);
  }
}

// External map functions (Google Maps)
function openFullMap(lat, lng, farmName) {
  const url = `https://www.google.com/maps?q=${lat},${lng}&z=15&t=m`;
  window.open(url, '_blank');
}

function getDirections(lat, lng) {
  const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
  window.open(url, '_blank');
}

function shareLocation(lat, lng, farmName) {
  const locationData = {
    name: farmName,
    coordinates: `${lat}, ${lng}`,
    googleMapsUrl: `https://www.google.com/maps?q=${lat},${lng}`,
    message: `Check out ${farmName}'s location on the map!`
  };
  
  if (navigator.share) {
    navigator.share({
      title: `${farmName} - Farm Location`,
      text: locationData.message,
      url: locationData.googleMapsUrl
    }).then(() => {
      showLocationNotification('Location shared successfully!', 'success');
    }).catch((error) => {
      copyLocationToClipboard(locationData);
    });
  } else {
    copyLocationToClipboard(locationData);
  }
}

function copyLocationToClipboard(locationData) {
  const textToCopy = `${locationData.name}\nCoordinates: ${locationData.coordinates}\nMap: ${locationData.googleMapsUrl}`;
  
  if (navigator.clipboard) {
    navigator.clipboard.writeText(textToCopy).then(() => {
      showLocationNotification('Location details copied to clipboard!', 'success');
    }).catch(() => {
      fallbackCopyToClipboard(textToCopy);
    });
  } else {
    fallbackCopyToClipboard(textToCopy);
  }
}

function fallbackCopyToClipboard(text) {
  const textArea = document.createElement('textarea');
  textArea.value = text;
  textArea.style.position = 'fixed';
  textArea.style.left = '-999999px';
  textArea.style.top = '-999999px';
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  
  try {
    document.execCommand('copy');
    showLocationNotification('Location details copied to clipboard!', 'success');
  } catch (err) {
    showLocationNotification('Unable to copy location. Please copy manually.', 'error');
  }
  
  document.body.removeChild(textArea);
}

function showLocationNotification(message, type = 'info') {
  const existingNotification = document.querySelector('.location-notification');
  if (existingNotification) {
    existingNotification.remove();
  }
  
  const notification = document.createElement('div');
  notification.className = 'location-notification';
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
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }, 3000);
}

// Setup location input listeners for forward geocoding
function setupLocationInputListeners() {
  const locationInput = document.getElementById('location-input');
  if (!locationInput) return;

  // Add Enter key support for location search
  locationInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      searchLocation();
    }
  });

  // Add debounced input listener for auto-search
  let searchTimeout;
  locationInput.addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    if (query.length >= 3) {
      searchTimeout = setTimeout(() => {
        searchLocation(query, true); // true = auto-search mode
      }, 1500); // Wait 1.5 seconds after user stops typing
    }
  });
}

// Search location using forward geocoding
function searchLocation(customQuery = null, isAutoSearch = false) {
  const locationInput = document.getElementById('location-input');
  const searchBtn = document.querySelector('.location-search-btn');
  
  if (!locationInput) return;
  
  const query = customQuery || locationInput.value.trim();
  
  if (!query) {
    if (!isAutoSearch) {
      showLocationNotification('Please enter a location to search', 'error');
    }
    return;
  }

  // Show loading state
  if (searchBtn) {
    searchBtn.classList.add('searching');
    searchBtn.innerHTML = '⏳';
  }
  
  if (!isAutoSearch) {
    updateLocationStatus(`🔍 Searching for "${query}"...`, 'info');
  }

  // Use Nominatim geocoding service with Philippines bias
  const nominatimUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=ph&limit=1&addressdetails=1&accept-language=en`;

  fetch(nominatimUrl)
    .then(response => response.json())
    .then(data => {
      // Reset button state
      if (searchBtn) {
        searchBtn.classList.remove('searching');
        searchBtn.innerHTML = '🔍';
      }

      if (data && data.length > 0) {
        const result = data[0];
        const lat = parseFloat(result.lat);
        const lng = parseFloat(result.lon);
        
        if (!isNaN(lat) && !isNaN(lng)) {
          // Update coordinates
          document.getElementById('latitude').value = lat.toFixed(6);
          document.getElementById('longitude').value = lng.toFixed(6);
          
          // Update map and marker
          if (locationMap) {
            locationMap.setView([lat, lng], 15);
            
            // Remove existing marker
            if (currentMarker) {
              locationMap.removeLayer(currentMarker);
            }
            
            // Create new marker
            const farmIcon = L.divIcon({
              className: 'custom-farm-marker',
              html: '<div class="marker-pin">📍</div>',
              iconSize: [30, 30],
              iconAnchor: [15, 30],
              popupAnchor: [0, -30]
            });
            
            const farmName = document.querySelector('input[name="farm_name"]').value || 
                             document.querySelector('input[name="username"]').value || 
                             'Your Farm';
            
            currentMarker = L.marker([lat, lng], { 
              icon: farmIcon,
              draggable: true
            })
              .addTo(locationMap)
              .bindPopup(`
                <div class="farm-popup">
                  <h4>${farmName}</h4>
                  <p><strong>Farm Location</strong></p>
                  <p>📍 ${result.display_name}</p>
                  <p>Latitude: ${lat.toFixed(6)}</p>
                  <p>Longitude: ${lng.toFixed(6)}</p>
                  <small>Drag pin or click elsewhere to move</small>
                </div>
              `)
              .openPopup();
            
            // Add drag events to new marker
            addDragEventsToMarker(currentMarker, farmName);
          }
          
          // Update location input with formatted address if different
          if (result.display_name && result.display_name !== query) {
            // Format the address nicely
            const address = result.address || {};
            const components = [];
            
            if (address.village) components.push(address.village);
            else if (address.town) components.push(address.town);
            else if (address.city) components.push(address.city);
            else if (address.municipality) components.push(address.municipality);
            
            if (address.municipality && !components.includes(address.municipality)) {
              components.push(address.municipality);
            }
            
            if (address.state) components.push(address.state);
            else if (address.province) components.push(address.province);
            
            const formattedAddress = components.join(', ') || result.display_name;
            
            if (formattedAddress !== query) {
              locationInput.value = formattedAddress;
              locationInput.classList.add('location-input-updated');
              setTimeout(() => {
                locationInput.classList.remove('location-input-updated');
              }, 3000);
            }
          }
          
          // Update status
          updateLocationStatus(`📍 Found: ${result.display_name}`, 'success');
          
          if (!isAutoSearch) {
            showLocationNotification(`Location found: ${result.display_name}`, 'success');
          }
          
        } else {
          throw new Error('Invalid coordinates received');
        }
      } else {
        // No results found
        updateLocationStatus(`❌ Location "${query}" not found`, 'error');
        if (!isAutoSearch) {
          showLocationNotification(`Location "${query}" not found. Try a different search term.`, 'error');
        }
      }
    })
    .catch(error => {
      console.error('Forward geocoding error:', error);
      
      // Reset button state
      if (searchBtn) {
        searchBtn.classList.remove('searching');
        searchBtn.innerHTML = '🔍';
      }
      
      updateLocationStatus(`❌ Search failed for "${query}"`, 'error');
      if (!isAutoSearch) {
        showLocationNotification('Location search failed. Please check your internet connection.', 'error');
      }
    });
}
</script>

<script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>
