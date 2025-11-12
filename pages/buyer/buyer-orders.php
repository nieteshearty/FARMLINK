<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require buyer role
$user = SessionManager::requireRole('buyer');

// Helper function to get time ago
function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hrs ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

// Get buyer's orders with delivery information
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT o.*, u.username as farmer_name, u.farm_name,
               o.estimated_delivery_date, o.delivery_time_slot, o.delivery_notes,
               o.delivery_address, o.delivery_instructions
        FROM orders o
        LEFT JOIN users u ON o.farmer_id = u.id
        WHERE o.buyer_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
}

$stats = DatabaseHelper::getStats('buyer', $user['id']);

// Handle order status update (if needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'cancel_order') {
        $orderId = $_POST['order_id'];
        
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND buyer_id = ? AND status = 'pending'");
            $stmt->execute([$orderId, $user['id']]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Order cancelled successfully!";
                SessionManager::logActivity($user['id'], 'order', 'Cancelled order #' . $orderId);
            } else {
                $_SESSION['error'] = "Unable to cancel order.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to cancel order.";
        }
        
        header('Location: buyer-orders.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • My Orders</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
  <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/buyer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
</head>
<body data-page="buyer-orders">
  <nav>
    <div class="nav-left">
      <a href="buyer-dashboard.php"><img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="logo"></a>
      <span class="brand">FARMLINK - BUYER</span>
    </div>
    <span>My Orders</span>
  </nav>

  <div class="sidebar">
    <a href="buyer-dashboard.php">Dashboard</a>
    <a href="buyer-market.php">Browse Market</a>
    <a href="buyer-cart.php">Shopping Cart</a>
    <a href="buyer-orders.php" class="active">My Orders</a>
    <a href="buyer-profile.php">Profile</a>
    <a href="<?= BASE_URL ?>/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>My Orders</h1>
    <p class="lead">Track your order history and status.</p>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success <?= isset($_SESSION['new_order_placed']) ? 'order-success-highlight' : '' ?>">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <?php if (isset($_SESSION['new_order_placed'])): ?>
          <br><strong>🎉 Your new order is highlighted below!</strong>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="stats">
      <div class="card stat-card">
        <h3>Total Orders</h3>
        <p class="stat-number"><?= $stats['total_orders'] ?></p>
        <small>All time</small>
      </div>
      <div class="card stat-card pending-card">
        <h3>Pending Orders</h3>
        <p class="stat-number"><?= $stats['pending_orders'] ?></p>
        <small>Awaiting fulfillment</small>
      </div>
      <div class="card stat-card completed-card">
        <h3>Completed Orders</h3>
        <p class="stat-number"><?= count(array_filter($orders, function($o) { return $o['status'] === 'completed'; })) ?></p>
        <small>Successfully delivered</small>
      </div>
      <div class="card stat-card spent-card">
        <h3>Total Spent</h3>
        <p class="stat-number">₱<?= number_format($stats['total_spent'], 2) ?></p>
        <small>Lifetime purchases</small>
      </div>
    </section>

    <!-- Order Status Filter -->
    <section class="order-filters">
      <h3>Filter Orders</h3>
      <div class="filter-buttons">
        <button class="filter-btn active" onclick="filterOrders('all')">All Orders</button>
        <button class="filter-btn pending-btn" onclick="filterOrders('pending')">Pending</button>
        <button class="filter-btn completed-btn" onclick="filterOrders('completed')">Completed</button>
        <button class="filter-btn cancelled-btn" onclick="filterOrders('cancelled')">Cancelled</button>
      </div>
    </section>

    <section class="table-wrap">
      <h3>Order History</h3>
      <table>
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Farmer</th>
            <th>Total</th>
            <th>Status</th>
            <th>Order Date</th>
            <th>Delivery Schedule</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="6" style="text-align:center; padding:40px; color:#999;">
                No orders yet. <a href="market.php" style="color:#2d6a4f;">Browse our market</a> to make your first order!
              </td>
            </tr>
          <?php else: ?>
            <?php 
            $recentOrderIds = [];
            $recentThreshold = strtotime('-24 hours');
            $justPlacedThreshold = isset($_SESSION['new_order_time']) ? $_SESSION['new_order_time'] - 60 : 0; // Orders placed in last minute
            
            foreach ($orders as $order) {
              if (strtotime($order['created_at']) > $recentThreshold) {
                $recentOrderIds[] = $order['id'];
              }
            }
            ?>
            <?php foreach ($orders as $order): ?>
              <?php 
              $isRecent = in_array($order['id'], $recentOrderIds); 
              $isJustPlaced = isset($_SESSION['new_order_placed']) && strtotime($order['created_at']) > $justPlacedThreshold;
              ?>
              <tr class="order-row <?= $isRecent ? 'order-recent' : '' ?> <?= $isJustPlaced ? 'order-just-placed' : '' ?>" data-status="<?= $order['status'] ?>">
                <td>
                  <div class="order-id-cell">
                    #<?= $order['id'] ?>
                    <?php if ($isJustPlaced): ?>
                      <span class="just-placed-badge">JUST PLACED!</span>
                    <?php elseif ($isRecent): ?>
                      <span class="new-badge">NEW</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td><?= htmlspecialchars($order['farmer_name']) ?></td>
                <td>₱<?= number_format($order['total'], 2) ?></td>
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
                      <div class="status-progress">
                        <div class="progress-bar">
                          <div class="progress-fill" style="width: 33%"></div>
                        </div>
                        <small>Order received</small>
                      </div>
                    <?php elseif ($order['status'] === 'completed'): ?>
                      <div class="status-progress">
                        <div class="progress-bar">
                          <div class="progress-fill completed" style="width: 100%"></div>
                        </div>
                        <small>Delivered</small>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="date-cell">
                    <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                    <?php if ($isJustPlaced): ?>
                      <br><small class="time-just-placed">🚀 Fresh from cart!</small>
                    <?php elseif ($isRecent): ?>
                      <br><small class="time-recent">Just placed!</small>
                    <?php endif; ?>
                    <br><small class="order-age"><?= getTimeAgo($order['created_at']) ?></small>
                  </div>
                </td>
                <td>
                  <div class="delivery-schedule-cell">
                    <?php if ($order['estimated_delivery_date']): ?>
                      <div class="delivery-info">
                        <div class="delivery-date">
                          📅 <?= date('M j, Y', strtotime($order['estimated_delivery_date'])) ?>
                        </div>
                        <?php if ($order['delivery_time_slot']): ?>
                          <div class="delivery-time">
                            🕐 <?= htmlspecialchars($order['delivery_time_slot']) ?>
                          </div>
                        <?php endif; ?>
                        <?php if ($order['delivery_notes']): ?>
                          <div class="delivery-notes">
                            📝 <?= htmlspecialchars($order['delivery_notes']) ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <div class="no-delivery-schedule">
                        <small>⏳ Not scheduled yet</small>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="action-buttons">
                    <a href="../common/order-details.php?id=<?= $order['id'] ?>" class="btn btn-sm">View Details</a>
                    <?php if ($order['status'] === 'pending'): ?>
                      <form method="POST" style="display: inline; margin-left: 8px;">
                        <input type="hidden" name="action" value="cancel_order">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this order?')">Cancel</button>
                      </form>
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

  <?php 
  // Clear the new order session flags after displaying
  if (isset($_SESSION['new_order_placed'])) {
    unset($_SESSION['new_order_placed']);
    unset($_SESSION['new_order_time']);
  }
  ?>

  <script>
    // View order details
    function viewOrder(orderId) {
      // Create modal or redirect to order details page
      window.open('../common/order-details.php?id=' + orderId, '_blank', 'width=600,height=400');
    }

    // Filter orders by status
    function filterOrders(status) {
      const rows = document.querySelectorAll('.order-row');
      const buttons = document.querySelectorAll('.filter-btn');
      
      // Update active button
      buttons.forEach(btn => btn.classList.remove('active'));
      event.target.classList.add('active');
      
      // Show/hide rows based on status
      rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
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

    /* Delivery Schedule Styles */
    .delivery-schedule-cell {
      min-width: 200px;
      padding: 8px;
    }

    .delivery-info {
      background: #f0f8ff;
      padding: 8px;
      border-radius: 6px;
      border-left: 3px solid #2196F3;
    }

    .delivery-date {
      font-size: 12px;
      font-weight: 600;
      color: #1976d2;
      margin-bottom: 4px;
    }

    .delivery-time {
      font-size: 11px;
      color: #666;
      margin-bottom: 4px;
    }

    .delivery-notes {
      font-size: 10px;
      color: #ff9800;
      font-style: italic;
      background: #fff3e0;
      padding: 4px 6px;
      border-radius: 3px;
      margin-top: 4px;
    }

    .no-delivery-schedule {
      text-align: center;
      color: #999;
      font-style: italic;
    }

    .no-delivery-schedule small {
      font-size: 11px;
    }
    
    .btn-danger {
      background-color: #e74c3c;
      color: white;
    }
    
    .btn-danger:hover {
      background-color: #c0392b;
    }
    
    a {
      color: #2d6a4f;
      text-decoration: none;
    }
    
    a:hover {
      text-decoration: underline;
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
    
    .order-recent {
      background-color: #e8f5e8;
      border-left: 4px solid #4CAF50;
      animation: highlightFade 3s ease-in-out;
    }
    
    .new-badge {
      background-color: #ff4444;
      color: white;
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 10px;
      margin-left: 8px;
      font-weight: bold;
      animation: pulse 2s infinite;
    }
    
    .time-recent {
      color: #4CAF50;
      font-weight: bold;
      font-style: italic;
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
    
    .order-recent:hover {
      background-color: #dcedc8;
    }

    /* Just Placed Order Highlighting */
    .order-just-placed {
      background: linear-gradient(135deg, #4CAF50, #81C784);
      border: 3px solid #2E7D32;
      box-shadow: 0 0 20px rgba(76, 175, 80, 0.4);
      animation: orderPlacedGlow 2s ease-in-out infinite alternate;
    }

    .just-placed-badge {
      background: linear-gradient(45deg, #FF6B35, #F7931E);
      color: white;
      font-size: 11px;
      padding: 4px 8px;
      border-radius: 12px;
      margin-left: 8px;
      font-weight: bold;
      animation: bounce 1s infinite;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }

    .time-just-placed {
      color: #2E7D32;
      font-weight: bold;
      font-style: italic;
      animation: pulse 1.5s infinite;
    }

    .order-success-highlight {
      background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
      border: 2px solid #28a745;
      animation: successPulse 2s ease-in-out;
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

    @keyframes bounce {
      0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
      40% { transform: translateY(-5px); }
      60% { transform: translateY(-3px); }
    }

    @keyframes successPulse {
      0% { 
        background: linear-gradient(135deg, #4CAF50, #81C784);
        transform: scale(1);
      }
      50% { 
        background: linear-gradient(135deg, #66BB6A, #A5D6A7);
        transform: scale(1.01);
      }
      100% { 
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        transform: scale(1);
      }
    }

    /* Enhanced Statistics Cards */
    .stat-card {
      position: relative;
      overflow: hidden;
    }

    .stat-number {
      font-size: 2.5em;
      font-weight: bold;
      margin: 8px 0;
      color: #2d6a4f;
    }

    .pending-card {
      border-left: 4px solid #f39c12;
    }

    .completed-card {
      border-left: 4px solid #27ae60;
    }

    .spent-card {
      border-left: 4px solid #8e44ad;
    }

    /* Order Filters */
    .order-filters {
      margin: 20px 0;
      padding: 16px;
      background: #f8f9fa;
      border-radius: 8px;
    }

    .filter-buttons {
      display: flex;
      gap: 12px;
      margin-top: 12px;
      flex-wrap: wrap;
    }

    .filter-btn {
      padding: 10px 20px;
      border: 2px solid transparent;
      background: white;
      border-radius: 25px;
      cursor: pointer;
      transition: all 0.3s;
      font-weight: 500;
      font-size: 0.9em;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .filter-btn.active {
      background: #4CAF50;
      color: white;
      border-color: #4CAF50;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
    }

    .filter-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 3px 6px rgba(0,0,0,0.15);
    }

    .pending-btn {
      border-color: #f39c12;
      color: #f39c12;
    }

    .pending-btn.active {
      background: #f39c12;
      color: white;
      box-shadow: 0 4px 8px rgba(243, 156, 18, 0.3);
    }

    .completed-btn {
      border-color: #27ae60;
      color: #27ae60;
    }

    .completed-btn.active {
      background: #27ae60;
      color: white;
      box-shadow: 0 4px 8px rgba(39, 174, 96, 0.3);
    }

    .cancelled-btn {
      border-color: #e74c3c;
      color: #e74c3c;
    }

    .cancelled-btn.active {
      background: #e74c3c;
      color: white;
      box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
    }

    /* Enhanced Status Badges */
    .status-container {
      display: flex;
      flex-direction: column;
      gap: 8px;
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

    /* Progress Bars */
    .status-progress {
      margin-top: 4px;
    }

    .progress-bar {
      width: 100%;
      height: 6px;
      background: #e9ecef;
      border-radius: 3px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: #f39c12;
      transition: width 0.3s ease;
    }

    .progress-fill.completed {
      background: #27ae60;
    }

    /* Enhanced Table Cells */
    .order-id-cell {
      font-weight: bold;
    }

    .date-cell {
      font-size: 0.9em;
    }

    .order-age {
      color: #6c757d;
      font-style: italic;
    }

    .action-buttons {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 0.85em;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .filter-buttons {
        flex-wrap: wrap;
      }
      
      .action-buttons {
        flex-direction: column;
        gap: 4px;
      }
    }
  </style>
  <script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>
