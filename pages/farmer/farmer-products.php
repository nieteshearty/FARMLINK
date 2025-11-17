<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';
require $basePath . '/includes/ImageHelper.php';

// Require farmer role
$user = SessionManager::requireRole('farmer');

// Handle product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getDBConnection();
        
        if ($action === 'add_product') {
            $name = trim($_POST['name']);
            $category = $_POST['category'] ?? '';
            $quantity = $_POST['quantity'];
            $price = $_POST['price'];
            $unit = $_POST['unit'] ?? 'kg';
            $description = trim($_POST['description'] ?? '');
            $expiresAt = $_POST['expires_at'] ?? null;
            
            // Handle product image upload
            $productImage = null;
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $basePath . '/uploads/products/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = uniqid() . '_' . $_FILES['product_image']['name'];
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadPath)) {
                    // Store as relative path to work with BASE_URL
                    $productImage = 'uploads/products/' . $fileName;
                }
            }
            
            if (empty($name) || $quantity <= 0 || $price <= 0 || empty($expiresAt)) {
                $_SESSION['error'] = "Please fill all required fields correctly, including expiration date.";
            } else {
                // Convert datetime-local format to MySQL datetime
                $expiresAtFormatted = date('Y-m-d H:i:s', strtotime($expiresAt));
                
                $stmt = $pdo->prepare("INSERT INTO products (farmer_id, name, category, quantity, price, unit, description, image, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user['id'], $name, $category, $quantity, $price, $unit, $description, $productImage, $expiresAtFormatted]);
                
                $expiryDate = date('M j, Y g:i A', strtotime($expiresAtFormatted));
                $_SESSION['success'] = "Product added successfully! Expires on {$expiryDate}";
                SessionManager::logActivity($user['id'], 'product', "Added new product: {$name}");
            }
            
        } elseif ($action === 'edit_product') {
            $productId = $_POST['product_id'];
            $name = trim($_POST['name']);
            $category = $_POST['category'] ?? '';
            $quantity = $_POST['quantity'];
            $price = $_POST['price'];
            $unit = $_POST['unit'] ?? 'kg';
            $description = trim($_POST['description'] ?? '');
            $expiresAt = $_POST['expires_at'] ?? null;
            
            // Get current product to preserve existing image if no new upload
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ? AND farmer_id = ?");
            $stmt->execute([$productId, $user['id']]);
            $currentProduct = $stmt->fetch();
            $productImage = $currentProduct['image'] ?? null;
            
            // Handle product image upload
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                // Create uploads directory if it doesn't exist
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExtension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExtension, $allowedExtensions)) {
                    $fileName = uniqid() . '.' . $fileExtension;
                    $uploadPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadPath)) {
                        // Delete old image if exists
                        if ($productImage) {
                            $oldFs = $_SERVER['DOCUMENT_ROOT'] . (str_starts_with($productImage, '/') ? $productImage : ('/' . $productImage));
                            if (file_exists($oldFs)) {
                                unlink($oldFs);
                            }
                        }
                        // Store as relative path
                        $productImage = 'uploads/products/' . $fileName;
                    }
                }
            }
            
            if (empty($name) || $quantity <= 0 || $price <= 0 || empty($expiresAt)) {
                $_SESSION['error'] = "Please fill all required fields correctly, including expiration date.";
            } else {
                // Convert datetime-local format to MySQL datetime
                $expiresAtFormatted = date('Y-m-d H:i:s', strtotime($expiresAt));
                
                $stmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, quantity = ?, price = ?, unit = ?, description = ?, image = ?, expires_at = ? WHERE id = ? AND farmer_id = ?");
                $stmt->execute([$name, $category, $quantity, $price, $unit, $description, $productImage, $expiresAtFormatted, $productId, $user['id']]);
                
                if ($stmt->rowCount() > 0) {
                    $expiryDate = date('M j, Y g:i A', strtotime($expiresAtFormatted));
                    $_SESSION['success'] = "Product updated successfully! Expires on {$expiryDate}";
                    SessionManager::logActivity($user['id'], 'product', "Updated product: {$name}");
                } else {
                    $_SESSION['error'] = "Product not found or no changes made.";
                }
            }
            
        } elseif ($action === 'delete_product') {
            $productId = $_POST['product_id'];
            
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND farmer_id = ?");
            $stmt->execute([$productId, $user['id']]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Product deleted successfully!";
                SessionManager::logActivity($user['id'], 'product', "Deleted product ID: {$productId}");
            } else {
                $_SESSION['error'] = "Product not found.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred. Please try again.";
    }
    
    header('Location: farmer-products.php');
    exit;
}

// Get farmer's products
$products = DatabaseHelper::getProducts($user['id']);

// Check if editing a product
$editingProduct = null;
if (isset($_GET['edit']) && $_GET['edit']) {
    $editId = $_GET['edit'];
    foreach ($products as $product) {
        if ($product['id'] == $editId) {
            $editingProduct = $product;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • Manage Products</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
  <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/farmer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
</head>
<body data-page="farmer-products">
  <nav>
    <div class="nav-left">
      <a href="farmer-dashboard.php"><img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="logo"></a>
      <span class="brand">FARMLINK - FARMER</span>
    </div>
    <span>Manage Products</span>
  </nav>

  <div class="sidebar">
    <a href="farmer-dashboard.php">Dashboard</a>
    <a href="farmer-products.php" class="active">My Products</a>
    <a href="farmer-orders.php">Orders</a>
    <a href="farmer-delivery-zones.php">Delivery Zones</a>
    <a href="farmer-profile.php">Profile</a>
    <a href="<?= BASE_URL ?>/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>My Products</h1>
    <p class="lead">Manage your farm products here.</p>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="form-section">
      <h3><?= $editingProduct ? 'Edit Product' : 'Add New Product' ?></h3>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $editingProduct ? 'edit_product' : 'add_product' ?>">
        <?php if ($editingProduct): ?>
          <input type="hidden" name="product_id" value="<?= $editingProduct['id'] ?>">
        <?php endif; ?>
        
        <input name="name" placeholder="Product Name" value="<?= $editingProduct['name'] ?? '' ?>" required />
        
        <!-- Product Image Upload -->
        <div class="product-image-upload">
          <label for="productImage">Product Image</label>
          <?php if ($editingProduct && $editingProduct['image']): ?>
            <div class="current-image">
              <?php
                $fullImageUrl = ImageHelper::normalizeImagePath($editingProduct['image'] ?? '', 'products');
              ?>
              <img src="<?= htmlspecialchars($fullImageUrl) ?>" 
                   alt="<?= htmlspecialchars($editingProduct['name']) ?>" 
                   class="current-product-pic"
                   onerror="console.log('Failed to load image:', this.src); this.onerror=null; this.src='<?= BASE_URL ?>/assets/img/1.jpg';">
              <label>Current Image</label>
            </div>
          <?php endif; ?>
          <input type="file" name="product_image" id="productImage" accept="image/*" />
          <div class="image-preview" id="imagePreview" style="display:none;">
            <img id="previewProductImg" src="" alt="Product Preview" />
            <label>New Image</label>
          </div>
        </div>
        
        <select name="category">
          <option value="">Select Category</option>
          <option value="Vegetables" <?= ($editingProduct['category'] ?? '') === 'Vegetables' ? 'selected' : '' ?>>Vegetables</option>
          <option value="Fruits" <?= ($editingProduct['category'] ?? '') === 'Fruits' ? 'selected' : '' ?>>Fruits</option>
          <option value="Grains" <?= ($editingProduct['category'] ?? '') === 'Grains' ? 'selected' : '' ?>>Grains</option>
          <option value="Dairy" <?= ($editingProduct['category'] ?? '') === 'Dairy' ? 'selected' : '' ?>>Dairy</option>
        </select>
        
        <input name="quantity" type="number" step="0.01" placeholder="Quantity" value="<?= $editingProduct['quantity'] ?? '' ?>" required />
        
        <select name="unit">
          <option value="kg" <?= ($editingProduct['unit'] ?? 'kg') === 'kg' ? 'selected' : '' ?>>kg</option>
          <option value="lbs" <?= ($editingProduct['unit'] ?? '') === 'lbs' ? 'selected' : '' ?>>lbs</option>
          <option value="pieces" <?= ($editingProduct['unit'] ?? '') === 'pieces' ? 'selected' : '' ?>>pieces</option>
          <option value="liters" <?= ($editingProduct['unit'] ?? '') === 'liters' ? 'selected' : '' ?>>liters</option>
        </select>
        
        <input name="price" type="number" step="0.01" placeholder="Price per unit" value="<?= $editingProduct['price'] ?? '' ?>" required />
        
        <textarea name="description" placeholder="Description (optional)"><?= $editingProduct['description'] ?? '' ?></textarea>
        
        <!-- Expiration Date Field -->
        <div class="expiration-field">
          <label for="expires_at">Expiration Date & Time</label>
          <input type="datetime-local" 
                 name="expires_at" 
                 id="expires_at" 
                 value="<?= $editingProduct && $editingProduct['expires_at'] ? date('Y-m-d\TH:i', strtotime($editingProduct['expires_at'])) : date('Y-m-d\TH:i', strtotime('+3 days')) ?>"
                 min="<?= date('Y-m-d\TH:i') ?>"
                 required />
          <small class="field-help">Set when this product will expire and become unavailable</small>
        </div>
        
        <input name="image" placeholder="Image URL (optional)" value="<?= $editingProduct['image'] ?? '' ?>" />
        
        <div style="text-align:right">
          <?php if ($editingProduct): ?>
            <a href="farmer-products.php" class="btn" style="margin-right: 8px;">Cancel</a>
          <?php endif; ?>
          <button type="submit" class="btn"><?= $editingProduct ? 'Update Product' : 'Add Product' ?></button>
        </div>
      </form>
    </section>

    <section class="table-wrap">
      <h3>Current Products</h3>
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Expires</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr>
              <td colspan="6" style="text-align:center; padding:40px; color:#999;">
                No products yet. Add your first product above!
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($products as $product): ?>
              <tr>
                <td>
                  <div class="product-info">
                    <?php if ($product['image']): ?>
                        <?php $fullImageUrl = ImageHelper::normalizeImagePath($product['image'] ?? '', 'products'); ?>
                      <img src="<?= htmlspecialchars($fullImageUrl) ?>" 
                           alt="<?= htmlspecialchars($product['name']) ?>" 
                           class="product-thumb"
                           onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='block';">
                      <div class="product-thumb-placeholder" style="display:none;">📷</div>
                    <?php else: ?>
                      <div class="product-thumb-placeholder">📷</div>
                    <?php endif; ?>
                    
                    <div>
                      <strong><?= htmlspecialchars($product['name']) ?></strong>
                      <?php if ($product['description']): ?>
                        <br><small><?= htmlspecialchars(substr($product['description'], 0, 50)) ?>...</small>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($product['category']) ?></td>
                <td><?= $product['quantity'] ?> <?= htmlspecialchars($product['unit']) ?></td>
                <td>₱<?= number_format($product['price'], 2) ?></td>
                <td>
                  <?php if ($product['expires_at']): ?>
                    <?php 
                    $expiryDate = new DateTime($product['expires_at']);
                    $now = new DateTime();
                    $isExpired = $expiryDate < $now;
                    $diff = $now->diff($expiryDate);
                    
                    if ($isExpired) {
                        echo '<span style="color: #dc3545; font-weight: bold;">🚫 Expired</span><br>';
                        echo '<small>' . $expiryDate->format('M j, Y g:i A') . '</small>';
                    } elseif ($diff->days == 0) {
                        echo '<span style="color: #fd7e14; font-weight: bold;">⏰ Today</span><br>';
                        echo '<small>' . $expiryDate->format('g:i A') . '</small>';
                    } elseif ($diff->days <= 1) {
                        echo '<span style="color: #ffc107; font-weight: bold;">⚠️ Tomorrow</span><br>';
                        echo '<small>' . $expiryDate->format('M j, g:i A') . '</small>';
                    } else {
                        echo '<span style="color: #28a745;">✅ ' . $diff->days . ' days</span><br>';
                        echo '<small>' . $expiryDate->format('M j, Y g:i A') . '</small>';
                    }
                    ?>
                  <?php else: ?>
                    <span style="color: #6c757d;">No expiry set</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="farmer-products.php?edit=<?= $product['id'] ?>" class="btn">Edit</a>
                  <form method="POST" style="display: inline; margin-left: 8px;">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this product?')">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>

  <style>
    /* Force agricultural green sidebar background */
    .sidebar {
      background: #2E7D32 !important;
    }
    
    .form-section {
      margin-bottom: 30px;
    }
    
    .form-section input, .form-section select, .form-section textarea {
      width: 100%;
      margin: 8px 0;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    
    .form-section textarea {
      height: 80px;
      resize: vertical;
    }
    
    .expiration-field {
      margin: 16px 0;
    }
    
    .expiration-field label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #2E7D32;
    }
    
    .expiration-field input[type="datetime-local"] {
      width: 100%;
      padding: 12px;
      border: 2px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.3s ease;
    }
    
    .expiration-field input[type="datetime-local"]:focus {
      outline: none;
      border-color: #2E7D32;
      box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
    }
    
    .field-help {
      display: block;
      margin-top: 4px;
      font-size: 12px;
      color: #666;
      font-style: italic;
    }
    
    .btn-danger {
      background-color: #e74c3c;
      color: white;
    }
    
    .btn-danger:hover {
      background-color: #c0392b;
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
    
    .product-image-upload {
      margin: 16px 0;
      padding: 16px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #f9f9f9;
    }

    .product-image-upload label {
      display: block;
      font-weight: bold;
      margin-bottom: 8px;
      color: #333;
    }

    .current-image, .image-preview {
      display: inline-block;
      margin: 8px 16px 8px 0;
      text-align: center;
    }

    .current-product-pic, .image-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 8px;
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

    .product-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .product-thumb {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      object-fit: cover;
      border: 1px solid #ddd;
    }

    .product-thumb-placeholder {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      border: 1px solid #ddd;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f5f5f5;
      font-size: 20px;
    }
  </style>
  
  <script>
    // Image Preview Functionality
    document.addEventListener('DOMContentLoaded', function() {
      const productImageInput = document.getElementById('productImage');
      const imagePreview = document.getElementById('imagePreview');
      const previewImg = document.getElementById('previewProductImg');
      
      if (productImageInput && imagePreview && previewImg) {
        productImageInput.addEventListener('change', function() {
          const file = this.files[0];
          
          if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
              previewImg.src = e.target.result;
              imagePreview.style.display = 'block';
            }
            
            reader.readAsDataURL(file);
          } else {
            previewImg.src = '';
            imagePreview.style.display = 'none';
          }
        });
      }
      
      // Show preview for existing image when editing
      <?php if ($editingProduct && $editingProduct['image']): ?>
        if (previewImg) {
          previewImg.src = '<?= $editingProduct['image'] ?>';
          imagePreview.style.display = 'block';
        }
      <?php endif; ?>
    });
  </script>
  <script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>
