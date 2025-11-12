<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require superadmin role
$user = SessionManager::requireRole('superadmin');

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'delete_product') {
            $productId = (int)$_POST['product_id'];
            
            // Get product image to delete file
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            // Delete the product
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            
            if ($stmt->rowCount() > 0) {
                // Delete image file if exists
                if ($product && $product['image']) {
                    $imagePath = $basePath . '/uploads/products/' . basename($product['image']);
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                $message = "Product deleted successfully!";
                
                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, message) VALUES (?, ?)");
                $stmt->execute([$user['id'], "Deleted product ID: $productId"]);
            } else {
                $error = "Product not found.";
            }
        } elseif ($action === 'toggle_status') {
            $productId = (int)$_POST['product_id'];
            $stmt = $pdo->prepare("UPDATE products SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
            $stmt->execute([$productId]);
            $message = "Product status updated successfully!";
            
            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, message) VALUES (?, ?)");
            $stmt->execute([$user['id'], "Toggled status for product ID: $productId"]);
        } elseif ($action === 'update_quantity') {
            $productId = (int)$_POST['product_id'];
            $newQuantity = (int)$_POST['new_quantity'];
            
            if ($newQuantity >= 0) {
                $stmt = $pdo->prepare("UPDATE products SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $productId]);
                $message = "Product quantity updated successfully!";
                
                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, message) VALUES (?, ?)");
                $stmt->execute([$user['id'], "Updated quantity for product ID: $productId to $newQuantity"]);
            } else {
                $error = "Invalid quantity value.";
            }
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit;
        
    } catch (Exception $e) {
        error_log("Product management error: " . $e->getMessage());
        $error = "An error occurred. Please try again.";
    }
}

// Get all products with farmer information using DatabaseHelper
$products = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, u.username as farmer_name, u.email as farmer_email, u.profile_picture as farmer_avatar
        FROM products p 
        JOIN users u ON p.farmer_id = u.id 
        WHERE u.role = 'farmer'
        ORDER BY p.created_at DESC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Products query error: " . $e->getMessage());
}

// Get product statistics
$productStats = [
    'total' => count($products),
    'active' => count(array_filter($products, fn($p) => ($p['status'] ?? 'active') === 'active' && $p['quantity'] > 0)),
    'categories' => count(array_unique(array_column($products, 'category'))),
    'out_of_stock' => count(array_filter($products, fn($p) => $p['quantity'] == 0)),
    'total_value' => array_sum(array_map(fn($p) => $p['price'] * $p['quantity'], $products))
];

if (isset($_GET['success'])) {
    $message = "Operation completed successfully";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>FarmLink • Manage Products</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/superadmin.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/navigation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-page="manage-products">
    <nav>
        <div class="nav-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="nav-logo">
            <span class="nav-title">Manage Products</span>
        </div>
        <div class="nav-right">
            <div class="user-menu">
                <span class="welcome">Welcome, <?= htmlspecialchars($user['username']) ?></span>
                <?php
                $profilePicPath = '';
                if (!empty($user['profile_picture'])) {
                    if (strpos($user['profile_picture'], BASE_URL . '/') === 0 || strpos($user['profile_picture'], '/FARMLINK/') === 0) {
                        $profilePicPath = $user['profile_picture'];
                    } elseif (strpos($user['profile_picture'], 'uploads/') === 0) {
                        $profilePicPath = BASE_URL . '/' . $user['profile_picture'];
                    } else {
                        $profilePicPath = BASE_URL . '/uploads/profiles/' . basename($user['profile_picture']);
                    }
                } else {
                    $profilePicPath = BASE_URL . '/assets/img/default-avatar.png';
                }
                ?>
                <img src="<?= $profilePicPath ?>" alt="Profile" class="avatar" onerror="this.src='<?= BASE_URL ?>/assets/img/default-avatar.png'">
            </div>
        </div>
    </nav>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage-products.php" class="active"><i class="fas fa-box"></i> Manage Products</a>
        <a href="system-monitoring.php"><i class="fas fa-desktop"></i> System Monitoring</a>
        <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics & Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="superadmin-profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <main class="main">
        <h1>Product Management</h1>
        <p class="lead">View and manage all products across the platform.</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Product Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $productStats['total'] ?></h3>
                <p>Total Products</p>
            </div>
            <div class="stat-card">
                <h3><?= $productStats['active'] ?></h3>
                <p>Active Products</p>
            </div>
            <div class="stat-card">
                <h3><?= $productStats['categories'] ?></h3>
                <p>Categories</p>
            </div>
            <div class="stat-card">
                <h3><?= $productStats['out_of_stock'] ?></h3>
                <p>Out of Stock</p>
            </div>
            <div class="stat-card">
                <h3>₱<?= number_format($productStats['total_value'], 2) ?></h3>
                <p>Total Inventory Value</p>
            </div>
        </div>

        <!-- Products Table -->
        <section class="card">
            <div class="card-header">
                <h3>All Products</h3>
                <div class="search-box">
                    <input type="text" id="productSearch" placeholder="Search products...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            
            <div class="table-container">
                <table id="productsTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Farmer</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:40px; color:#999;">
                                    <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                    No products found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="product-info">
                                            <?php if ($product['image']): ?>
                                                <?php
                                                // Robust image path handling
                                                $imageValue = trim($product['image']);
                                                $imageUrl = '';
                                                
                                                if (strpos($imageValue, 'http') === 0) {
                                                    $imageUrl = $imageValue;
                                                } elseif (strpos($imageValue, BASE_URL . '/') === 0 || strpos($imageValue, '/FARMLINK/') === 0) {
                                                    $imageUrl = $imageValue;
                                                } elseif (strpos($imageValue, 'uploads/products/') === 0) {
                                                    $imageUrl = BASE_URL . '/' . $imageValue;
                                                } elseif (strpos($imageValue, '/') === 0) {
                                                    $imageUrl = BASE_URL . $imageValue;
                                                } else {
                                                    $imageUrl = BASE_URL . '/uploads/products/' . basename($imageValue);
                                                }
                                                ?>
                                                <img src="<?= htmlspecialchars($imageUrl) ?>" 
                                                     alt="<?= htmlspecialchars($product['name']) ?>" 
                                                     class="product-thumb"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="product-thumb-placeholder" style="display: none;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="product-thumb-placeholder">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div>
                                                <strong><?= htmlspecialchars($product['name']) ?></strong>
                                                <br><small>ID: #<?= $product['id'] ?></small>
                                                <?php if ($product['description']): ?>
                                                    <br><small><?= htmlspecialchars(substr($product['description'], 0, 50)) ?>...</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="farmer-info">
                                            <?php
                                            $farmerPicPath = '';
                                            if (!empty($product['farmer_avatar'])) {
                                                if (strpos($product['farmer_avatar'], BASE_URL . '/') === 0 || strpos($product['farmer_avatar'], '/FARMLINK/') === 0) {
                                                    $farmerPicPath = $product['farmer_avatar'];
                                                } elseif (strpos($product['farmer_avatar'], 'uploads/') === 0) {
                                                    $farmerPicPath = BASE_URL . '/' . $product['farmer_avatar'];
                                                } else {
                                                    $farmerPicPath = BASE_URL . '/uploads/profiles/' . basename($product['farmer_avatar']);
                                                }
                                            }
                                            ?>
                                            <?php if ($farmerPicPath): ?>
                                                <img src="<?= $farmerPicPath ?>" alt="Farmer" class="farmer-thumb" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="farmer-thumb-placeholder" style="display: none;">
                                                    <?= strtoupper(substr($product['farmer_name'], 0, 1)) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="farmer-thumb-placeholder">
                                                    <?= strtoupper(substr($product['farmer_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars($product['farmer_name']) ?></strong>
                                                <br><small><?= htmlspecialchars($product['farmer_email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="category-badge"><?= htmlspecialchars($product['category']) ?></span>
                                    </td>
                                    <td>
                                        <div class="quantity-control">
                                            <strong><?= $product['quantity'] ?> <?= htmlspecialchars($product['unit']) ?></strong>
                                            <form method="POST" class="quantity-form" style="margin-top: 5px;">
                                                <input type="hidden" name="action" value="update_quantity">
                                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                <div style="display: flex; gap: 5px; align-items: center;">
                                                    <input type="number" name="new_quantity" value="<?= $product['quantity'] ?>" 
                                                           min="0" style="width: 60px; padding: 2px 4px; font-size: 0.8em;">
                                                    <button type="submit" class="btn btn-xs" title="Update Quantity">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>₱<?= number_format($product['price'], 2) ?></strong>
                                        <br><small>Total: ₱<?= number_format($product['price'] * $product['quantity'], 2) ?></small>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            <?php 
                                            $status = ($product['status'] ?? 'active') === 'active' && $product['quantity'] > 0 ? 'active' : 'inactive';
                                            if ($product['quantity'] == 0) $status = 'out-of-stock';
                                            ?>
                                            <button type="submit" class="status-badge <?= $status ?>" 
                                                    onclick="return confirm('Toggle product status?')"
                                                    title="Click to toggle status">
                                                <?php if ($status === 'out-of-stock'): ?>
                                                    Out of Stock
                                                <?php elseif ($status === 'active'): ?>
                                                    Active
                                                <?php else: ?>
                                                    Inactive
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="actions">
                                        <button onclick="showProductDetails(<?= $product['id'] ?>)" class="btn btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Delete this product? This cannot be undone.')">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Product Details Modal -->
    <div id="productDetailsModal" class="product-modal-overlay">
        <div class="product-modal">
            <div class="product-modal-header">
                <h3 id="modalProductName">Product Details</h3>
                <button class="modal-close" onclick="closeProductModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="product-modal-body">
                <div class="modal-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading product details...</p>
                </div>
                <div class="modal-content" style="display: none;">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-error" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Failed to load product details. Please try again.</p>
                </div>
            </div>
        </div>
    </div>

    <style>
        .product-info, .farmer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-thumb, .farmer-thumb {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #ddd;
        }

        .product-thumb-placeholder, .farmer-thumb-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            color: #666;
            font-size: 16px;
        }

        .farmer-thumb {
            border-radius: 50%;
        }

        .farmer-thumb-placeholder {
            border-radius: 50%;
            background: #4CAF50;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .category-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .quantity-control {
            min-width: 100px;
        }

        .quantity-form input[type="number"] {
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge.out-of-stock {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge:hover {
            opacity: 0.8;
        }

        .search-box {
            position: relative;
            max-width: 300px;
        }

        .search-box input {
            padding: 8px 35px 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }

        .search-box i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .btn-xs {
            padding: 2px 6px;
            font-size: 0.7em;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: 0.85em;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-container {
            overflow-x: auto;
        }

        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .search-box {
                max-width: none;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .menu-toggle {
            background: none;
            border: none;
            padding: 10px;
            font-size: 1.2em;
            cursor: pointer;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            background: #333;
            color: white;
            padding: 20px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }
        
        .sidebar.active {
            left: -250px;
        }
        
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px;
            display: block;
        }
        
        .sidebar a:hover {
            background: #444;
        }
        
        .sidebar a.active {
            background: #555;
        }
        
        /* Product Details Modal Styles */
        .product-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .product-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .product-modal {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 900px;
            width: 95%;
            max-height: 90vh;
            overflow: hidden;
            transform: scale(0.8) translateY(20px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .product-modal-overlay.show .product-modal {
            transform: scale(1) translateY(0);
        }
        
        .product-modal-header {
            background: linear-gradient(135deg, #2E7D32, #4CAF50);
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .product-modal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #FF6B35, #F7931E, #FFD23F);
        }
        
        .product-modal-header h3 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }
        
        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.2s;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .product-modal-body {
            padding: 0;
            max-height: calc(90vh - 80px);
            overflow-y: auto;
        }
        
        .modal-loading, .modal-error {
            padding: 60px 24px;
            text-align: center;
            color: #666;
        }
        
        .modal-loading i, .modal-error i {
            font-size: 3rem;
            margin-bottom: 16px;
            display: block;
        }
        
        .modal-loading i {
            color: #4CAF50;
        }
        
        .modal-error i {
            color: #f44336;
        }
        
        .modal-content {
            padding: 24px;
        }
        
        .product-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .product-image-section {
            text-align: center;
        }
        
        .product-main-image {
            width: 100%;
            max-width: 300px;
            height: 300px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            margin-bottom: 16px;
        }
        
        .product-image-placeholder {
            width: 100%;
            max-width: 300px;
            height: 300px;
            background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #4CAF50;
            margin: 0 auto 16px;
        }
        
        .product-info-section h4 {
            color: #2E7D32;
            margin-bottom: 16px;
            font-size: 1.2rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #333;
        }
        
        .info-value {
            color: #666;
        }
        
        .farmer-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .farmer-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .farmer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4CAF50;
        }
        
        .farmer-avatar-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #4CAF50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .farmer-info h5 {
            margin: 0 0 4px 0;
            color: #2E7D32;
            font-size: 1.1rem;
        }
        
        .farmer-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #e8f5e8, #f1f8e9);
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #c8e6c9;
        }
        
        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2E7D32;
            margin-bottom: 4px;
        }
        
        .metric-label {
            font-size: 0.85rem;
            color: #666;
        }
        
        .reviews-section h4 {
            color: #2E7D32;
            margin-bottom: 16px;
        }
        
        .review-item {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid #4CAF50;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .review-rating {
            color: #FF6B35;
        }
        
        .review-text {
            color: #666;
            line-height: 1.5;
        }
        
        @media (max-width: 768px) {
            .product-modal {
                width: 98%;
                margin: 10px;
            }
            
            .product-details-grid {
                grid-template-columns: 1fr;
            }
            
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .product-modal-header {
                padding: 16px 20px;
            }
            
            .product-modal-header h3 {
                font-size: 1.2rem;
            }
        }
    </style>

    <script>
        // Search functionality
        document.getElementById('productSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#productsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Confirm quantity updates
        document.querySelectorAll('.quantity-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const input = this.querySelector('input[name="new_quantity"]');
                if (!confirm(`Update quantity to ${input.value}?`)) {
                    e.preventDefault();
                }
            });
        });
        
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        // Product Details Modal Functions
        function showProductDetails(productId) {
            const modal = document.getElementById('productDetailsModal');
            const loading = modal.querySelector('.modal-loading');
            const content = modal.querySelector('.modal-content');
            const error = modal.querySelector('.modal-error');
            
            // Show modal and loading state
            modal.classList.add('show');
            loading.style.display = 'block';
            content.style.display = 'none';
            error.style.display = 'none';
            
            // Fetch product details
            fetch(`/FARMLINK/api/product-details.php?id=${productId}`)
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`Server error: ${response.status}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    displayProductDetails(data);
                    loading.style.display = 'none';
                    content.style.display = 'block';
                })
                .catch(err => {
                    console.error('Error fetching product details:', err);
                    loading.style.display = 'none';
                    error.style.display = 'block';
                    // Show error details in the error div
                    const errorDiv = modal.querySelector('.modal-error p');
                    errorDiv.textContent = `Error: ${err.message}. Please check the console for details.`;
                });
        }
        
        function displayProductDetails(data) {
            const product = data.product;
            const metrics = data.metrics;
            const reviews = data.reviews;
            
            // Update modal title
            document.getElementById('modalProductName').textContent = product.name;
            
            // Build image URL
            let imageUrl = '';
            if (product.image) {
                const imageValue = product.image.trim();
                if (imageValue.startsWith('http')) {
                    imageUrl = imageValue;
                } else if (imageValue.startsWith('/FARMLINK/')) {
                    imageUrl = imageValue;
                } else if (imageValue.startsWith('uploads/products/')) {
                    imageUrl = '/FARMLINK/' + imageValue;
                } else if (imageValue.startsWith('/')) {
                    imageUrl = '/FARMLINK' + imageValue;
                } else {
                    imageUrl = '/FARMLINK/uploads/products/' + imageValue.split('/').pop();
                }
            }
            
            // Build farmer avatar URL
            let farmerAvatarUrl = '';
            if (product.farmer_avatar) {
                const avatarValue = product.farmer_avatar.trim();
                if (avatarValue.startsWith('/FARMLINK/')) {
                    farmerAvatarUrl = avatarValue;
                } else if (avatarValue.startsWith('uploads/')) {
                    farmerAvatarUrl = '/FARMLINK/' + avatarValue;
                } else {
                    farmerAvatarUrl = '/FARMLINK/uploads/profiles/' + avatarValue.split('/').pop();
                }
            }
            
            // Format dates
            const createdDate = new Date(product.created_at).toLocaleDateString();
            const farmerJoined = new Date(product.farmer_joined).toLocaleDateString();
            
            // Generate stars for rating
            function generateStars(rating) {
                const fullStars = Math.floor(rating);
                const hasHalfStar = rating % 1 >= 0.5;
                let stars = '';
                
                for (let i = 0; i < fullStars; i++) {
                    stars += '<i class="fas fa-star"></i>';
                }
                if (hasHalfStar) {
                    stars += '<i class="fas fa-star-half-alt"></i>';
                }
                const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
                for (let i = 0; i < emptyStars; i++) {
                    stars += '<i class="far fa-star"></i>';
                }
                return stars;
            }
            
            const modalContent = `
                <div class="product-details-grid">
                    <div class="product-image-section">
                        ${imageUrl ? 
                            `<img src="${imageUrl}" alt="${product.name}" class="product-main-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                             <div class="product-image-placeholder" style="display: none;"><i class="fas fa-image"></i></div>` :
                            `<div class="product-image-placeholder"><i class="fas fa-image"></i></div>`
                        }
                        <div class="category-badge" style="display: inline-block; margin-top: 8px;">${product.category}</div>
                    </div>
                    
                    <div class="product-info-section">
                        <h4>Product Information</h4>
                        <div class="info-row">
                            <span class="info-label">Product ID:</span>
                            <span class="info-value">#${product.id}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Price:</span>
                            <span class="info-value">₱${parseFloat(product.price).toFixed(2)} per ${product.unit}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Available Quantity:</span>
                            <span class="info-value">${product.quantity} ${product.unit}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <span class="status-badge ${product.quantity > 0 ? (product.status === 'active' ? 'active' : 'inactive') : 'out-of-stock'}">
                                    ${product.quantity === 0 ? 'Out of Stock' : (product.status === 'active' ? 'Active' : 'Inactive')}
                                </span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Listed Date:</span>
                            <span class="info-value">${createdDate}</span>
                        </div>
                        ${product.description ? `
                        <div class="info-row">
                            <span class="info-label">Description:</span>
                            <span class="info-value">${product.description}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="farmer-section">
                    <div class="farmer-header">
                        ${farmerAvatarUrl ? 
                            `<img src="${farmerAvatarUrl}" alt="Farmer" class="farmer-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                             <div class="farmer-avatar-placeholder" style="display: none;">${product.farmer_name.charAt(0).toUpperCase()}</div>` :
                            `<div class="farmer-avatar-placeholder">${product.farmer_name.charAt(0).toUpperCase()}</div>`
                        }
                        <div class="farmer-info">
                            <h5>${product.farmer_name}</h5>
                            <p>${product.farmer_email}</p>
                            ${product.farm_name ? `<p><strong>Farm:</strong> ${product.farm_name}</p>` : ''}
                            ${product.company_name ? `<p><strong>Company:</strong> ${product.company_name}</p>` : ''}
                            <p><strong>Member since:</strong> ${farmerJoined}</p>
                        </div>
                    </div>
                    ${product.farm_address ? `<p><strong>Location:</strong> ${product.farm_address}</p>` : ''}
                </div>
                
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value">${metrics.total_sold_30_days || 0}</div>
                        <div class="metric-label">Sold (30 days)</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">₱${parseFloat(metrics.revenue_30_days || 0).toFixed(2)}</div>
                        <div class="metric-label">Revenue (30 days)</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">${metrics.avg_rating || 0}</div>
                        <div class="metric-label">Average Rating</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value">${metrics.total_orders || 0}</div>
                        <div class="metric-label">Total Orders</div>
                    </div>
                </div>
                
                ${reviews && reviews.length > 0 ? `
                <div class="reviews-section">
                    <h4>Recent Reviews (${metrics.review_count || 0} total)</h4>
                    ${reviews.map(review => `
                        <div class="review-item">
                            <div class="review-header">
                                <strong>${review.reviewer_name || 'Anonymous'}</strong>
                                <div class="review-rating">
                                    ${generateStars(review.rating || 0)}
                                </div>
                            </div>
                            ${review.comment ? `<div class="review-text">${review.comment}</div>` : ''}
                            <small style="color: #999;">${new Date(review.created_at).toLocaleDateString()}</small>
                        </div>
                    `).join('')}
                </div>
                ` : ''}
            `;
            
            document.querySelector('.modal-content').innerHTML = modalContent;
        }
        
        function closeProductModal() {
            const modal = document.getElementById('productDetailsModal');
            modal.classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('productDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('productDetailsModal');
                if (modal.classList.contains('show')) {
                    closeProductModal();
                }
            }
        });
    </script>
    <script src="/FARMLINK/assets/js/logout-confirmation.js"></script>
</body>
</html>
