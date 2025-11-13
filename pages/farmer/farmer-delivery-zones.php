<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

// Require farmer role
$user = SessionManager::requireRole('farmer');

// Handle delivery zone operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getDBConnection();
        
        if ($action === 'add_delivery_zone') {
            $zoneName = trim($_POST['zone_name']);
            $areasCovered = $_POST['areas_covered'] ?? '';
            $deliveryFee = $_POST['delivery_fee'] ?? 0;
            $minimumOrder = $_POST['minimum_order'] ?? 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($zoneName)) {
                $_SESSION['error'] = "Please provide a zone name.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO farmer_delivery_zones (farmer_id, zone_name, areas_covered, delivery_fee, minimum_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user['id'], $zoneName, $areasCovered, $deliveryFee, $minimumOrder, $isActive]);
                
                $_SESSION['success'] = "Delivery zone added successfully!";
                SessionManager::logActivity($user['id'], 'delivery_zone', "Added delivery zone: {$zoneName}");
            }
            
        } elseif ($action === 'edit_delivery_zone') {
            $zoneId = $_POST['zone_id'];
            $zoneName = trim($_POST['zone_name']);
            $areasCovered = $_POST['areas_covered'] ?? '';
            $deliveryFee = $_POST['delivery_fee'] ?? 0;
            $minimumOrder = $_POST['minimum_order'] ?? 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($zoneName)) {
                $_SESSION['error'] = "Please provide a zone name.";
            } else {
                $stmt = $pdo->prepare("UPDATE farmer_delivery_zones SET zone_name = ?, areas_covered = ?, delivery_fee = ?, minimum_order = ?, is_active = ? WHERE id = ? AND farmer_id = ?");
                $stmt->execute([$zoneName, $areasCovered, $deliveryFee, $minimumOrder, $isActive, $zoneId, $user['id']]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = "Delivery zone updated successfully!";
                    SessionManager::logActivity($user['id'], 'delivery_zone', "Updated delivery zone: {$zoneName}");
                } else {
                    $_SESSION['error'] = "Delivery zone not found or no changes made.";
                }
            }
            
        } elseif ($action === 'delete_delivery_zone') {
            $zoneId = $_POST['zone_id'];
            
            // First delete associated schedules
            $stmt = $pdo->prepare("DELETE FROM farmer_delivery_schedule WHERE zone_id = ?");
            $stmt->execute([$zoneId]);
            
            // Then delete the zone
            $stmt = $pdo->prepare("DELETE FROM farmer_delivery_zones WHERE id = ? AND farmer_id = ?");
            $stmt->execute([$zoneId, $user['id']]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Delivery zone deleted successfully!";
                SessionManager::logActivity($user['id'], 'delivery_zone', "Deleted delivery zone");
            } else {
                $_SESSION['error'] = "Delivery zone not found.";
            }
            
        } elseif ($action === 'add_delivery_schedule') {
            $zoneId = $_POST['zone_id'];
            $dayOfWeek = $_POST['day_of_week'];
            $timeSlot = $_POST['time_slot'];
            
            if (empty($dayOfWeek) || empty($timeSlot)) {
                $_SESSION['error'] = "Please provide both day and time slot.";
            } else {
                // Check if schedule already exists
                $stmt = $pdo->prepare("SELECT id FROM farmer_delivery_schedule WHERE zone_id = ? AND day_of_week = ? AND time_slot = ?");
                $stmt->execute([$zoneId, $dayOfWeek, $timeSlot]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error'] = "This schedule already exists for the selected zone.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO farmer_delivery_schedule (zone_id, day_of_week, time_slot) VALUES (?, ?, ?)");
                    $stmt->execute([$zoneId, $dayOfWeek, $timeSlot]);
                    
                    $_SESSION['success'] = "Delivery schedule added successfully!";
                    SessionManager::logActivity($user['id'], 'delivery_schedule', "Added delivery schedule: {$dayOfWeek} {$timeSlot}");
                }
            }
            
        } elseif ($action === 'delete_delivery_schedule') {
            $scheduleId = $_POST['schedule_id'];
            
            $stmt = $pdo->prepare("DELETE FROM farmer_delivery_schedule WHERE id = ?");
            $stmt->execute([$scheduleId]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Delivery schedule deleted successfully!";
                SessionManager::logActivity($user['id'], 'delivery_schedule', "Deleted delivery schedule");
            } else {
                $_SESSION['error'] = "Delivery schedule not found.";
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        error_log("Delivery zone error: " . $e->getMessage());
    }
    
    header('Location: farmer-delivery-zones.php');
    exit;
}

// Get farmer's delivery zones
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM farmer_delivery_zones WHERE farmer_id = ? ORDER BY zone_name");
$stmt->execute([$user['id']]);
$deliveryZones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get delivery schedules for all zones
$deliverySchedules = [];
if (!empty($deliveryZones)) {
    $zoneIds = array_column($deliveryZones, 'id');
    $placeholders = str_repeat('?,', count($zoneIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM farmer_delivery_schedule WHERE zone_id IN ($placeholders) ORDER BY zone_id, day_of_week, time_slot");
    $stmt->execute($zoneIds);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group schedules by zone_id
    foreach ($schedules as $schedule) {
        $deliverySchedules[$schedule['zone_id']][] = $schedule;
    }
}

// Check if editing a zone
$editingZone = null;
if (isset($_GET['edit']) && $_GET['edit']) {
    $editId = $_GET['edit'];
    foreach ($deliveryZones as $zone) {
        if ($zone['id'] == $editId) {
            $editingZone = $zone;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • Delivery Zones</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
  <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/farmer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
</head>
<body data-page="farmer-delivery-zones">
  <nav>
    <div class="nav-left">
      <a href="farmer-dashboard.php"><img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="logo"></a>
      <span class="brand">FARMLINK - FARMER</span>
    </div>
    <span>Delivery Zones</span>
  </nav>

  <div class="sidebar">
    <a href="farmer-dashboard.php">Dashboard</a>
    <a href="farmer-products.php">My Products</a>
    <a href="farmer-orders.php">Orders</a>
    <a href="farmer-delivery-zones.php" class="active">Delivery Zones</a>
    <a href="farmer-profile.php">Profile</a>
    <a href="<?= BASE_URL ?>/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>🚚 Delivery Zones & Schedule</h1>
    <p class="lead">Set up delivery areas and schedules to let buyers know when you deliver to specific locations.</p>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Add/Edit Delivery Zone Form -->
    <section class="form-section">
      <h3><?= $editingZone ? 'Edit Delivery Zone' : 'Add New Delivery Zone' ?></h3>
      <form method="POST" class="delivery-zone-form">
        <input type="hidden" name="action" value="<?= $editingZone ? 'edit_delivery_zone' : 'add_delivery_zone' ?>">
        <?php if ($editingZone): ?>
          <input type="hidden" name="zone_id" value="<?= $editingZone['id'] ?>">
        <?php endif; ?>
        
        <div class="form-row">
          <div class="form-group">
            <label for="zone_name">Zone Name</label>
            <input type="text" name="zone_name" id="zone_name" 
                   placeholder="e.g., Cagayan Area" 
                   value="<?= $editingZone['zone_name'] ?? '' ?>" required>
          </div>
          
          <div class="form-group">
            <label for="areas_covered">Areas Covered</label>
            <select name="areas_covered" id="areas_covered" required>
              <option value="">Select Area</option>
              <option value="Tuguegarao City" <?= ($editingZone['areas_covered'] ?? '') === 'Tuguegarao City' ? 'selected' : '' ?>>Tuguegarao City</option>
              <option value="Cauayan City" <?= ($editingZone['areas_covered'] ?? '') === 'Cauayan City' ? 'selected' : '' ?>>Cauayan City</option>
              <option value="Ilagan City" <?= ($editingZone['areas_covered'] ?? '') === 'Ilagan City' ? 'selected' : '' ?>>Ilagan City</option>
              <option value="Santiago City" <?= ($editingZone['areas_covered'] ?? '') === 'Santiago City' ? 'selected' : '' ?>>Santiago City</option>
              <option value="Bayombong" <?= ($editingZone['areas_covered'] ?? '') === 'Bayombong' ? 'selected' : '' ?>>Bayombong</option>
              <option value="Cabarroguis" <?= ($editingZone['areas_covered'] ?? '') === 'Cabarroguis' ? 'selected' : '' ?>>Cabarroguis</option>
            </select>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="delivery_fee">Delivery Fee (₱)</label>
            <input type="number" name="delivery_fee" id="delivery_fee" 
                   step="0.01" min="0" placeholder="50.00" 
                   value="<?= $editingZone['delivery_fee'] ?? '' ?>">
          </div>
          
          <div class="form-group">
            <label for="minimum_order">Minimum Order (₱)</label>
            <input type="number" name="minimum_order" id="minimum_order" 
                   step="0.01" min="0" placeholder="200.00" 
                   value="<?= $editingZone['minimum_order'] ?? '' ?>">
          </div>
        </div>
        
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="is_active" <?= ($editingZone['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span class="checkmark"></span>
            Active Zone (buyers can select this zone)
          </label>
        </div>
        
        <div class="form-actions">
          <?php if ($editingZone): ?>
            <a href="farmer-delivery-zones.php" class="btn btn-secondary">Cancel</a>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary">
            <?= $editingZone ? 'Update Zone' : 'Add Zone' ?>
          </button>
        </div>
      </form>
    </section>

    <!-- Current Delivery Zones -->
    <section class="zones-section">
      <h3>Current Delivery Zones</h3>
      
      <?php if (empty($deliveryZones)): ?>
        <div class="empty-state">
          <div class="empty-icon">🚚</div>
          <h4>No delivery zones yet</h4>
          <p>Add your first delivery zone above to start offering delivery services to buyers.</p>
        </div>
      <?php else: ?>
        <div class="zones-grid">
          <?php foreach ($deliveryZones as $zone): ?>
            <div class="zone-card <?= $zone['is_active'] ? 'active' : 'inactive' ?>">
              <div class="zone-header">
                <h4><?= htmlspecialchars($zone['zone_name']) ?></h4>
                <div class="zone-status">
                  <?= $zone['is_active'] ? '<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>' ?>
                </div>
              </div>
              
              <div class="zone-details">
                <div class="detail-item">
                  <span class="detail-label">📍 Area:</span>
                  <span class="detail-value"><?= htmlspecialchars($zone['areas_covered']) ?></span>
                </div>
                
                <div class="detail-item">
                  <span class="detail-label">💰 Delivery Fee:</span>
                  <span class="detail-value">₱<?= number_format($zone['delivery_fee'], 2) ?></span>
                </div>
                
                <div class="detail-item">
                  <span class="detail-label">📦 Min Order:</span>
                  <span class="detail-value">₱<?= number_format($zone['minimum_order'], 2) ?></span>
                </div>
              </div>
              
              <!-- Delivery Schedule for this zone -->
              <?php if (isset($deliverySchedules[$zone['id']])): ?>
                <div class="zone-schedule">
                  <h5>📅 Delivery Schedule</h5>
                  <div class="schedule-list">
                    <?php foreach ($deliverySchedules[$zone['id']] as $schedule): ?>
                      <div class="schedule-item">
                        <span class="schedule-day"><?= ucfirst($schedule['day_of_week']) ?></span>
                        <span class="schedule-time"><?= htmlspecialchars($schedule['time_slot']) ?></span>
                        <form method="POST" style="display: inline;">
                          <input type="hidden" name="action" value="delete_delivery_schedule">
                          <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                          <button type="submit" class="btn-delete-schedule" onclick="return confirm('Delete this schedule?')" title="Delete Schedule">×</button>
                        </form>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
              
              <!-- Add Schedule Form -->
              <div class="add-schedule">
                <h5>➕ Add Schedule</h5>
                <form method="POST" class="schedule-form">
                  <input type="hidden" name="action" value="add_delivery_schedule">
                  <input type="hidden" name="zone_id" value="<?= $zone['id'] ?>">
                  
                  <div class="schedule-inputs">
                    <select name="day_of_week" required>
                      <option value="">Day</option>
                      <option value="monday">Monday</option>
                      <option value="tuesday">Tuesday</option>
                      <option value="wednesday">Wednesday</option>
                      <option value="thursday">Thursday</option>
                      <option value="friday">Friday</option>
                      <option value="saturday">Saturday</option>
                      <option value="sunday">Sunday</option>
                    </select>
                    
                    <select name="time_slot" required>
                      <option value="">Time</option>
                      <option value="8:00 AM - 10:00 AM">8:00 AM - 10:00 AM</option>
                      <option value="10:00 AM - 12:00 PM">10:00 AM - 12:00 PM</option>
                      <option value="1:00 PM - 3:00 PM">1:00 PM - 3:00 PM</option>
                      <option value="3:00 PM - 5:00 PM">3:00 PM - 5:00 PM</option>
                      <option value="5:00 PM - 7:00 PM">5:00 PM - 7:00 PM</option>
                    </select>
                    
                    <button type="submit" class="btn btn-small">Add</button>
                  </div>
                </form>
              </div>
              
              <div class="zone-actions">
                <a href="farmer-delivery-zones.php?edit=<?= $zone['id'] ?>" class="btn btn-secondary">Edit</a>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="action" value="delete_delivery_zone">
                  <input type="hidden" name="zone_id" value="<?= $zone['id'] ?>">
                  <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this delivery zone and all its schedules?')">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <style>
    /* Delivery Zones Specific Styles */
    .delivery-zone-form {
      background: white;
      padding: 24px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      margin-bottom: 32px;
    }
    
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 16px;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
    }
    
    .form-group label {
      margin-bottom: 6px;
      font-weight: 600;
      color: #2E7D32;
    }
    
    .form-group input,
    .form-group select {
      padding: 12px;
      border: 2px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.3s ease;
    }
    
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #2E7D32;
      box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
    }
    
    .checkbox-label {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      margin: 16px 0;
    }
    
    .form-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      margin-top: 24px;
    }
    
    .zones-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
      gap: 24px;
    }
    
    .zone-card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      border-left: 4px solid #2E7D32;
    }
    
    .zone-card.inactive {
      border-left-color: #ccc;
      opacity: 0.7;
    }
    
    .zone-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }
    
    .zone-header h4 {
      margin: 0;
      color: #2E7D32;
    }
    
    .status-active {
      background: #4CAF50;
      color: white;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .status-inactive {
      background: #ccc;
      color: white;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .zone-details {
      margin-bottom: 20px;
    }
    
    .detail-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      padding: 8px 0;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .detail-label {
      font-weight: 500;
      color: #666;
    }
    
    .detail-value {
      font-weight: 600;
      color: #333;
    }
    
    .zone-schedule {
      margin: 20px 0;
      padding: 16px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    
    .zone-schedule h5 {
      margin: 0 0 12px 0;
      color: #2E7D32;
      font-size: 14px;
    }
    
    .schedule-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    
    .schedule-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 12px;
      background: white;
      border-radius: 6px;
      border: 1px solid #e0e0e0;
    }
    
    .schedule-day {
      font-weight: 600;
      color: #2E7D32;
      min-width: 80px;
    }
    
    .schedule-time {
      color: #666;
      flex: 1;
      text-align: center;
    }
    
    .btn-delete-schedule {
      background: #ff4444;
      color: white;
      border: none;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      cursor: pointer;
      font-size: 16px;
      line-height: 1;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .add-schedule {
      margin: 20px 0;
      padding: 16px;
      background: #e8f5e8;
      border-radius: 8px;
    }
    
    .add-schedule h5 {
      margin: 0 0 12px 0;
      color: #2E7D32;
      font-size: 14px;
    }
    
    .schedule-inputs {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    
    .schedule-inputs select {
      flex: 1;
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 13px;
    }
    
    .btn-small {
      padding: 8px 16px;
      font-size: 13px;
    }
    
    .zone-actions {
      display: flex;
      gap: 8px;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid #f0f0f0;
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #666;
    }
    
    .empty-icon {
      font-size: 48px;
      margin-bottom: 16px;
    }
    
    .empty-state h4 {
      margin: 0 0 8px 0;
      color: #333;
    }
    
    .empty-state p {
      margin: 0;
      max-width: 400px;
      margin: 0 auto;
    }
    
    @media (max-width: 768px) {
      .form-row {
        grid-template-columns: 1fr;
      }
      
      .zones-grid {
        grid-template-columns: 1fr;
      }
      
      .schedule-inputs {
        flex-direction: column;
      }
      
      .schedule-inputs select,
      .btn-small {
        width: 100%;
      }
    }
  </style>
</body>
</html>
