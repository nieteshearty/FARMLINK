<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require login
$user = SessionManager::getUser();
if (!$user) {
    $redirect = $user['role'] === 'farmer' ? '../pages/farmer/farmer-orders.php' : '../pages/buyer/buyer-orders.php';
    header('Location: ' . $redirect);
    exit;
}

$orderId = $_GET['id'] ?? 0;

try {
    $pdo = getDBConnection();
    
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, 
               b.username as buyer_name, b.company as buyer_company,
               f.username as farmer_name, f.farm_name
        FROM orders o
        JOIN users b ON o.buyer_id = b.id
        JOIN users f ON o.farmer_id = f.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        die('Order not found');
    }
    
    // Check permissions
    if ($user['role'] !== 'admin' && $user['id'] != $order['buyer_id'] && $user['id'] != $order['farmer_id']) {
        die('Access denied');
    }
    
    // Get order items
    $orderItems = DatabaseHelper::getOrderItems($orderId);
    
} catch (Exception $e) {
    die('Error loading order details');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Order Details - #<?= $order['id'] ?></title>
    <link rel="icon" type="image/png" href="/FARMLINK/assets/img/farmlink.png">
    <link rel="stylesheet" href="/FARMLINK/style.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/<?= $user['role'] === 'farmer' ? 'farmer' : 'buyer' ?>.css">
</head>
<body style="padding: 20px;">
    <div class="card">
        <h2>Order Details - #<?= $order['id'] ?></h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
            <div>
                <h3>Order Information</h3>
                <p><strong>Status:</strong> <span class="status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></p>
                <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></p>
                <p><strong>Total:</strong> ₱<?= number_format($order['total'], 2) ?></p>
            </div>
            
            <div>
                <h3>Parties</h3>
                <p><strong>Buyer:</strong> <?= htmlspecialchars($order['buyer_name']) ?></p>
                <?php if ($order['buyer_company']): ?>
                    <p><strong>Company:</strong> <?= htmlspecialchars($order['buyer_company']) ?></p>
                <?php endif; ?>
                <p><strong>Farmer:</strong> <?= htmlspecialchars($order['farmer_name']) ?></p>
                <?php if ($order['farm_name']): ?>
                    <p><strong>Farm:</strong> <?= htmlspecialchars($order['farm_name']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <h3>Order Items</h3>
        <table style="width: 100%;">
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
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td><?= number_format($item['quantity'], 2) ?> <?= htmlspecialchars($item['unit'] ?? 'kg') ?></td>
                        <td>₱<?= number_format($item['price'], 2) ?></td>
                        <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; border-top: 2px solid #2d6a4f;">
                    <td colspan="3">Total:</td>
                    <td>₱<?= number_format($order['total'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
        
        <div style="text-align: center; margin-top: 20px;">
            <button onclick="window.history.back()" class="btn btn-secondary">← Back</button>
            <button onclick="window.print()" class="btn">Print</button>
        </div>
        
        <style>
            .status-pending { color: #e67e22; font-weight: bold; }
            .status-completed { color: #27ae60; font-weight: bold; }
            .status-cancelled { color: #e74c3c; font-weight: bold; }
            
            table {
                border-collapse: collapse;
                margin: 16px 0;
            }
            
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            
            th {
                background-color: #f8f9fa;
            }
        </style>
    </div>
</body>
</html>
