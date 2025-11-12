<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require farmer role
$user = SessionManager::requireRole('farmer');

// Get dashboard statistics
$stats = DatabaseHelper::getStats('farmer', $user['id']);
$recentActivity = DatabaseHelper::getRecentActivity(5, $user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • Farmer Dashboard</title>
  <link rel="icon" type="image/png" href="/FARMLINK/assets/img/farmlink.png">
  <link rel="stylesheet" href="/FARMLINK/style.css">
  <link rel="stylesheet" href="/FARMLINK/assets/css/farmer.css">
  <link rel="stylesheet" href="/FARMLINK/assets/css/logout-confirmation.css">
</head>
<body data-page="farmer-dashboard">
  <nav>
    <div class="nav-left">
      <a href="farmer-dashboard.php"><img src="/FARMLINK/assets/img/farmlink.png" alt="FARMLINK" class="logo"></a>
      <span class="brand">FARMLINK - FARMER</span>
    </div>
    <div class="nav-right">
      <?php if ($user['profile_picture']): ?>
        <?php 
          // Handle different path formats for existing profile pictures
          $profilePicPath = $user['profile_picture'];
          
          // If the path doesn't start with /, it's likely just a filename
          if (strpos($profilePicPath, '/') !== 0) {
            $profilePicPath = '/FARMLINK/uploads/profiles/' . $profilePicPath;
          }
          
          // If it's already a full path starting with /FARMLINK/, use as is
          // Otherwise, ensure it has the correct prefix
          if (strpos($profilePicPath, '/FARMLINK/') !== 0) {
            $profilePicPath = '/FARMLINK' . ltrim($profilePicPath, '/');
          }
        ?>
        <img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile" class="profile-pic" onerror="this.src='/FARMLINK/assets/img/default-avatar.png';">
      <?php else: ?>
        <div class="profile-pic-default">
          <?= strtoupper(substr($user['username'], 0, 1)) ?>
        </div>
      <?php endif; ?>
      <span>Farmer Dashboard</span>
    </div>
  </nav>

  <div class="sidebar">
    <a href="farmer-dashboard.php" class="active">Dashboard</a>
    <a href="farmer-products.php">My Products</a>
    <a href="farmer-orders.php">Orders</a>
    <a href="farmer-delivery-zones.php">Delivery Zones</a>
    <a href="farmer-profile.php">Profile</a>
    <a href="/FARMLINK/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>Welcome, <?= htmlspecialchars($user['username']) ?>!</h1>
    <p class="lead">Manage your farm products and track your sales</p>

    <section class="stats">
      <div class="card stat-card">
        <h3>Total Products</h3>
        <p><?= $stats['total_products'] ?></p>
      </div>
      <div class="card stat-card">
        <h3>Total Sales</h3>
        <p>₱<?= number_format($stats['total_sales'], 2) ?></p>
      </div>
      <div class="card stat-card">
        <h3>Pending Orders</h3>
        <p><?= $stats['pending_orders'] ?></p>
      </div>
      <div class="card stat-card">
        <h3>Completed Orders</h3>
        <p><?= $stats['completed_orders'] ?></p>
      </div>
    </section>

    <section class="card">
      <h3>Recent Activity</h3>
      <div class="activity-list">
        <?php if (empty($recentActivity)): ?>
          <p>No recent activity.</p>
        <?php else: ?>
          <?php foreach ($recentActivity as $activity): ?>
            <div class="activity-item">
              <span class="activity-time"><?= date('H:i:s', strtotime($activity['created_at'])) ?></span>
              <span class="activity-message"><?= htmlspecialchars($activity['message']) ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <h3>Quick Actions</h3>
      <div class="quick-actions">
        <button class="btn" onclick="location.href='farmer-products.php?action=add'">Add New Product</button>
        <button class="btn" onclick="location.href='farmer-orders.php'">View Orders</button>
        <button class="btn" onclick="location.href='farmer-profile.php'">Update Profile</button>
      </div>
    </section>
  </main>
</div>

  <style>
    /* Force dark green sidebar background */
    .sidebar {
      background: #1B5E20 !important;
      top: 80px !important;
    }
    
    nav {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .nav-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      background: white;
    }

    .profile-pic {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid #4CAF50;
      object-fit: cover;
    }

    .profile-pic-default {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid #4CAF50;
      background-color: #4CAF50;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 16px;
    }
  </style>
  <script src="/FARMLINK/assets/js/logout-confirmation.js"></script>
</body>
</html>
