<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require farmer role
$user = SessionManager::requireRole('farmer');

$pdo = getDBConnection();

// Get order ID from URL
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    header('Location: farmer-orders.php?error=invalid_order');
    exit;
}

// Get order details with buyer information
$stmt = $pdo->prepare("
    SELECT o.*, 
           u.username as buyer_name, 
           u.email as buyer_email,
           u.phone_number as buyer_phone,
           u.location as buyer_address,
           u.company as buyer_company,
           u.profile_picture as buyer_profile_picture
    FROM orders o
    JOIN users u ON o.buyer_id = u.id
    WHERE o.id = ? AND o.farmer_id = ?
");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: farmer-orders.php?error=order_not_found');
    exit;
}

// Get order items
$orderItems = DatabaseHelper::getOrderItems($orderId);

// Handle status update and delivery scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['status'];
        $validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
        
        if (in_array($newStatus, $validStatuses)) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ? AND farmer_id = ?");
            $stmt->execute([$newStatus, $orderId, $user['id']]);
            
            // Refresh order data
            $stmt = $pdo->prepare("
                SELECT o.*, 
                       u.username as buyer_name, 
                       u.email as buyer_email,
                       u.location as buyer_address,
                       u.company as buyer_company
                FROM orders o
                JOIN users u ON o.buyer_id = u.id
                WHERE o.id = ? AND o.farmer_id = ?
            ");
            $stmt->execute([$orderId, $user['id']]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $success_message = "Order status updated successfully!";
        }
    } elseif ($_POST['action'] === 'schedule_delivery') {
        $deliveryDate = $_POST['delivery_date'];
        $deliveryTime = $_POST['delivery_time'];
        $deliveryNotes = $_POST['delivery_notes'] ?? '';
        
        // Validate delivery date (must be future date)
        $selectedDate = new DateTime($deliveryDate);
        $today = new DateTime();
        
        if ($selectedDate >= $today) {
            // Combine date and time
            $deliveryDateTime = $deliveryDate . ' ' . $deliveryTime;
            
            $stmt = $pdo->prepare("
                UPDATE orders SET 
                    estimated_delivery_date = ?, 
                    delivery_time_slot = ?, 
                    delivery_notes = ?,
                    updated_at = NOW() 
                WHERE id = ? AND farmer_id = ?
            ");
            $stmt->execute([$deliveryDateTime, $deliveryTime, $deliveryNotes, $orderId, $user['id']]);
            
            // Refresh order data
            $stmt = $pdo->prepare("
                SELECT o.*, 
                       u.username as buyer_name, 
                       u.email as buyer_email,
                       u.location as buyer_address,
                       u.company as buyer_company
                FROM orders o
                JOIN users u ON o.buyer_id = u.id
                WHERE o.id = ? AND o.farmer_id = ?
            ");
            $stmt->execute([$orderId, $user['id']]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $success_message = "Delivery schedule set successfully! The buyer will be notified.";
        } else {
            $error_message = "Please select a future date for delivery.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Order #<?= $order['id'] ?> Details | FarmLink</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/farmer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-page="order-details">
    <nav>
        <div class="nav-left">
            <a href="farmer-dashboard.php"><img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK" class="logo"></a>
            <span class="brand">FARMLINK - FARMER</span>
        </div>
        <div class="nav-right">
            <span>Order #<?= $order['id'] ?> Details</span>
        </div>
    </nav>

    <div class="sidebar">
        <a href="farmer-dashboard.php">Dashboard</a>
        <a href="farmer-products.php">My Products</a>
        <a href="farmer-orders.php" class="active">Orders</a>
        <a href="farmer-profile.php#delivery-zones">Delivery Zones</a>
        <a href="farmer-profile.php">Profile</a>
        <a href="<?= BASE_URL ?>/pages/auth/logout.php">Logout</a>
    </div>

    <main class="main">
        <div class="page-header">
            <div class="header-content">
                <h1>Order #<?= $order['id'] ?> Details</h1>
                <a href="farmer-orders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>

        <div class="order-details-grid">
            <!-- Order Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Order Information</h3>
                    <div class="order-status">
                        <span class="status-badge status-<?= $order['status'] ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="order-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Order ID:</label>
                            <span>#<?= $order['id'] ?></span>
                        </div>
                        <div class="info-item">
                            <label>Order Date:</label>
                            <span><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Total Amount:</label>
                            <span class="total-amount">₱<?= number_format($order['total'], 2) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Last Updated:</label>
                            <span><?= date('F j, Y g:i A', strtotime($order['updated_at'])) ?></span>
                        </div>
                        <?php if ($order['delivery_method'] ?? false): ?>
                        <div class="info-item">
                            <label>Delivery Method:</label>
                            <span class="delivery-method"><?= ucfirst($order['delivery_method']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($order['estimated_delivery_date'] ?? false): ?>
                        <div class="info-item">
                            <label>Scheduled Delivery:</label>
                            <span class="delivery-schedule">
                                <i class="fas fa-calendar-alt"></i>
                                <?= date('F j, Y g:i A', strtotime($order['estimated_delivery_date'])) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($order['delivery_notes'] ?? false): ?>
                        <div class="info-item full-width">
                            <label>Delivery Notes:</label>
                            <span class="delivery-notes"><?= htmlspecialchars($order['delivery_notes']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Buyer Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Buyer Information</h3>
                </div>
                <div class="buyer-details">
                    <div class="buyer-profile-section">
                        <div class="buyer-avatar">
                            <?php if (!empty($order['buyer_profile_picture'])): ?>
                                <?php 
                                $profilePicPath = trim($order['buyer_profile_picture']);
                                if (strpos($profilePicPath, 'http') === 0) {
                                    // use as is
                                } elseif (strpos($profilePicPath, BASE_URL . '/') === 0 || strpos($profilePicPath, '/FARMLINK/') === 0) {
                                    // already base-prefixed
                                } elseif (strpos($profilePicPath, 'uploads/') === 0) {
                                    $profilePicPath = BASE_URL . '/' . $profilePicPath;
                                } elseif (strpos($profilePicPath, '/') === 0) {
                                    $profilePicPath = BASE_URL . $profilePicPath;
                                } else {
                                    $profilePicPath = BASE_URL . '/uploads/profiles/' . $profilePicPath;
                                }
                                ?>
                                <img src="<?= htmlspecialchars($profilePicPath) ?>" 
                                     alt="Buyer Profile" 
                                     class="profile-pic"
                                     onerror="this.onerror=null; this.src='<?= BASE_URL ?>/assets/img/default-avatar.png';">
                            <?php else: ?>
                                <div class="profile-pic-default">
                                    <?= strtoupper(substr($order['buyer_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="buyer-basic-info">
                            <h4><?= htmlspecialchars($order['buyer_name']) ?></h4>
                            <?php if ($order['buyer_company'] ?? false): ?>
                                <p class="company-name"><?= htmlspecialchars($order['buyer_company']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <label>📧 Email:</label>
                            <span><a href="mailto:<?= htmlspecialchars($order['buyer_email']) ?>" class="contact-link"><?= htmlspecialchars($order['buyer_email']) ?></a></span>
                        </div>
                        
                        <?php if ($order['buyer_phone'] ?? false): ?>
                        <div class="info-item">
                            <label>📱 Phone:</label>
                            <span><a href="tel:<?= htmlspecialchars($order['buyer_phone']) ?>" class="contact-link"><?= htmlspecialchars($order['buyer_phone']) ?></a></span>
                        </div>
                        <?php else: ?>
                        <div class="info-item">
                            <label>📱 Phone:</label>
                            <span class="no-info">Not provided</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['buyer_company'] ?? false): ?>
                        <div class="info-item">
                            <label>🏢 Company:</label>
                            <span><?= htmlspecialchars($order['buyer_company']) ?></span>
                        </div>
                        <?php else: ?>
                        <div class="info-item">
                            <label>🏢 Company:</label>
                            <span class="no-info">Individual buyer</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['buyer_address'] ?? false): ?>
                        <div class="info-item">
                            <label>📍 Location:</label>
                            <span><?= htmlspecialchars($order['buyer_address']) ?></span>
                        </div>
                        <?php else: ?>
                        <div class="info-item">
                            <label>📍 Location:</label>
                            <span class="no-info">Not specified</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['delivery_address'] ?? false): ?>
                        <div class="info-item full-width">
                            <label>🚚 Delivery Address:</label>
                            <span><?= htmlspecialchars($order['delivery_address']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['delivery_phone'] ?? false): ?>
                        <div class="info-item">
                            <label>📞 Delivery Contact:</label>
                            <span><a href="tel:<?= htmlspecialchars($order['delivery_phone']) ?>" class="contact-link"><?= htmlspecialchars($order['delivery_phone']) ?></a></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['delivery_instructions'] ?? false): ?>
                        <div class="info-item full-width">
                            <label>📝 Delivery Instructions:</label>
                            <span class="delivery-instructions"><?= htmlspecialchars($order['delivery_instructions']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="card">
            <div class="card-header">
                <h3>Order Items</h3>
            </div>
            <div class="order-items">
                <?php if (empty($orderItems)): ?>
                    <p class="no-items">No items found for this order.</p>
                <?php else: ?>
                    <div class="items-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="product-info">
                                                <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                                <small>per <?= htmlspecialchars($item['unit']) ?></small>
                                            </div>
                                        </td>
                                        <td><?= number_format($item['quantity'], 2) ?> <?= htmlspecialchars($item['unit']) ?></td>
                                        <td>₱<?= number_format($item['price'], 2) ?></td>
                                        <td class="item-total">₱<?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="total-row">
                                    <td colspan="3"><strong>Total Amount:</strong></td>
                                    <td class="grand-total"><strong>₱<?= number_format($order['total'], 2) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delivery Scheduling -->
        <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-truck"></i> Schedule Delivery</h3>
                <p class="card-subtitle">Set when you will deliver this order to the buyer</p>
            </div>
            <div class="delivery-scheduling">
                <form method="POST" class="delivery-form">
                    <input type="hidden" name="action" value="schedule_delivery">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="delivery_date">
                                <i class="fas fa-calendar"></i> Delivery Date:
                            </label>
                            <input type="date" 
                                   name="delivery_date" 
                                   id="delivery_date" 
                                   class="form-control" 
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= $order['estimated_delivery_date'] ? date('Y-m-d', strtotime($order['estimated_delivery_date'])) : '' ?>"
                                   required>
                        </div>
                        <div class="form-group">
                            <label for="delivery_time">
                                <i class="fas fa-clock"></i> Delivery Time:
                            </label>
                            <select name="delivery_time" id="delivery_time" class="form-control" required>
                                <option value="">Select Time</option>
                                <option value="08:00" <?= ($order['delivery_time_slot'] ?? '') === '08:00' ? 'selected' : '' ?>>8:00 AM</option>
                                <option value="09:00" <?= ($order['delivery_time_slot'] ?? '') === '09:00' ? 'selected' : '' ?>>9:00 AM</option>
                                <option value="10:00" <?= ($order['delivery_time_slot'] ?? '') === '10:00' ? 'selected' : '' ?>>10:00 AM</option>
                                <option value="11:00" <?= ($order['delivery_time_slot'] ?? '') === '11:00' ? 'selected' : '' ?>>11:00 AM</option>
                                <option value="13:00" <?= ($order['delivery_time_slot'] ?? '') === '13:00' ? 'selected' : '' ?>>1:00 PM</option>
                                <option value="14:00" <?= ($order['delivery_time_slot'] ?? '') === '14:00' ? 'selected' : '' ?>>2:00 PM</option>
                                <option value="15:00" <?= ($order['delivery_time_slot'] ?? '') === '15:00' ? 'selected' : '' ?>>3:00 PM</option>
                                <option value="16:00" <?= ($order['delivery_time_slot'] ?? '') === '16:00' ? 'selected' : '' ?>>4:00 PM</option>
                                <option value="17:00" <?= ($order['delivery_time_slot'] ?? '') === '17:00' ? 'selected' : '' ?>>5:00 PM</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="delivery_notes">
                            <i class="fas fa-sticky-note"></i> Delivery Instructions (Optional):
                        </label>
                        <textarea name="delivery_notes" 
                                  id="delivery_notes" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="Any special instructions for the buyer (e.g., call upon arrival, gate code, etc.)"><?= htmlspecialchars($order['delivery_notes'] ?? '') ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-calendar-check"></i> 
                            <?= ($order['estimated_delivery_date'] ?? false) ? 'Update Schedule' : 'Set Schedule' ?>
                        </button>
                        <?php if ($order['estimated_delivery_date'] ?? false): ?>
                        <div class="current-schedule">
                            <i class="fas fa-info-circle"></i>
                            <strong>Current Schedule:</strong> 
                            <?= date('F j, Y g:i A', strtotime($order['estimated_delivery_date'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Status Update -->
        <div class="card">
            <div class="card-header">
                <h3>Update Order Status</h3>
            </div>
            <div class="status-update">
                <form method="POST" class="status-form">
                    <input type="hidden" name="action" value="update_status">
                    <div class="form-group">
                        <label for="status">Change Status:</label>
                        <select name="status" id="status" class="form-control">
                            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <style>
        /* Force agricultural green sidebar background */
        .sidebar {
            background: #2E7D32 !important;
        }
        
        .page-header {
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .order-info, .buyer-details {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-item label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
        }

        .info-item span, .info-item a {
            font-size: 1em;
            color: #333;
        }

        .total-amount {
            font-weight: 700;
            color: #4CAF50;
            font-size: 1.2em;
        }

        .order-status {
            display: flex;
            align-items: center;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .items-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .product-info strong {
            display: block;
        }

        .product-info small {
            color: #666;
        }

        .item-total {
            font-weight: 600;
            color: #4CAF50;
        }

        .total-row {
            background: #f8f9fa;
        }

        .grand-total {
            font-size: 1.1em;
            color: #4CAF50;
        }

        .status-form {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .card-subtitle {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
            font-weight: normal;
        }

        .delivery-scheduling {
            padding: 20px;
        }

        .delivery-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            color: #4CAF50;
            width: 16px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .current-schedule {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.9em;
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            border-left: 4px solid #4CAF50;
        }

        .current-schedule i {
            color: #4CAF50;
        }

        .btn-success {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s ease;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .delivery-method {
            text-transform: capitalize;
            font-weight: 600;
            color: #4CAF50;
        }

        .delivery-schedule {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #2196F3;
        }

        .delivery-schedule i {
            color: #2196F3;
        }

        .delivery-notes {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #4CAF50;
            font-style: italic;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .no-items {
            text-align: center;
            color: #666;
            padding: 40px;
            font-style: italic;
        }

        /* Enhanced Buyer Information Styles */
        .buyer-profile-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
        }

        .buyer-avatar {
            flex-shrink: 0;
        }

        .buyer-avatar .profile-pic,
        .buyer-avatar .profile-pic-default {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            object-fit: cover;
        }

        .buyer-avatar .profile-pic-default {
            background: #4CAF50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }

        .buyer-basic-info h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
        }

        .buyer-basic-info .company-name {
            margin: 0;
            color: #666;
            font-size: 14px;
            font-style: italic;
        }

        .contact-link {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .contact-link:hover {
            color: #45a049;
            text-decoration: underline;
        }

        .no-info {
            color: #999;
            font-style: italic;
        }

        .delivery-instructions {
            background: #fff3cd;
            padding: 8px 12px;
            border-radius: 4px;
            border-left: 3px solid #ffc107;
            font-style: italic;
            display: block;
            margin-top: 5px;
        }

        .info-item label {
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        @media (max-width: 768px) {
            .order-details-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .status-form {
                flex-direction: column;
                align-items: stretch;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .current-schedule {
                text-align: center;
            }

            .buyer-profile-section {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .buyer-avatar .profile-pic,
            .buyer-avatar .profile-pic-default {
                width: 50px;
                height: 50px;
            }

            .buyer-basic-info h4 {
                font-size: 16px;
            }
        }
    </style>
  <script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>
