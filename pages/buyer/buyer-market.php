<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/ImageHelper.php';

// Require buyer role
$user = SessionManager::requireRole('buyer');

// Get all products and farmers
$products = [];
$farmers = [];

try {
    // Fetch products from API
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT p.*, u.username as farmer_name 
        FROM products p 
        JOIN users u ON p.farmer_id = u.id 
        WHERE p.quantity > 0
        ORDER BY p.created_at DESC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch farmers
    $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'farmer' ORDER BY username");
    $farmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Log error but don't show to user
    error_log('Error fetching products: ' . $e->getMessage());
}

// Handle filters
$searchTerm = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$farmerId = $_GET['farmer_id'] ?? '';

// Apply filters
$filteredProducts = $products;

if ($searchTerm) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($searchTerm) {
        return stripos($product['name'], $searchTerm) !== false || 
               stripos($product['description'], $searchTerm) !== false;
    });
}

if ($category) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($category) {
        return $product['category'] === $category;
    });
}

if ($farmerId) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($farmerId) {
        return $product['farmer_id'] == $farmerId;
    });
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • Browse Market</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
  <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/buyer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
</head>
<body data-page="buyer-market">
  <nav>
    <div class="nav-left">
      <a href="buyer-dashboard.php"><img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="logo"></a>
      <span class="brand">FARMLINK - BUYER</span>
    </div>
    <span>Browse Market</span>
  </nav>

  <div class="sidebar">
    <a href="buyer-dashboard.php">Dashboard</a>
    <a href="buyer-market.php" class="active">Browse Market</a>
    <a href="buyer-cart.php">Shopping Cart</a>
    <a href="buyer-orders.php">My Orders</a>
    <a href="buyer-profile.php">Profile</a>
    <a href="<?= BASE_URL ?>/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>Marketplace</h1>
    <p class="lead">Browse fresh farm products from local farmers.</p>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="filters">
      <div class="card">
        <h3>Filters</h3>
        <form method="GET" action="">
          <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($searchTerm) ?>" />
          <select name="category">
            <option value="">All Categories</option>
            <option value="Vegetables" <?= $category === 'Vegetables' ? 'selected' : '' ?>>Vegetables</option>
            <option value="Fruits" <?= $category === 'Fruits' ? 'selected' : '' ?>>Fruits</option>
            <option value="Grains" <?= $category === 'Grains' ? 'selected' : '' ?>>Grains</option>
            <option value="Dairy" <?= $category === 'Dairy' ? 'selected' : '' ?>>Dairy</option>
          </select>
          <select name="farmer_id">
            <option value="">All Farmers</option>
            <?php foreach ($farmers as $farmer): ?>
              <option value="<?= $farmer['id'] ?>" <?= $farmerId == $farmer['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($farmer['username']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn">Apply Filters</button>
        </form>
      </div>
    </section>

    <section class="market-grid">
      <?php if (empty($filteredProducts)): ?>
        <p class="no-products">No products found matching your criteria.</p>
      <?php else: ?>
        <?php foreach ($filteredProducts as $product): ?>
          <div class="market-card">
            <?php
              $placeholder = BASE_URL . '/assets/img/product-placeholder.svg';
              $imageUrl = ImageHelper::normalizeImagePath($product['image'] ?? '', 'products');
            ?>
            <div class="product-image">
              <?php if (!empty($imageUrl)): ?>
                <img 
                  src="<?= htmlspecialchars($imageUrl) ?>"
                  alt="<?= htmlspecialchars($product['name']) ?>"
                  class="product-thumb"
                  loading="lazy"
                  style="width: 100%; height: 200px; object-fit: cover;"
                  onerror="this.onerror=null; this.src='<?= htmlspecialchars($placeholder) ?>';"
                >
              <?php else: ?>
                <img 
                  src="<?= htmlspecialchars($placeholder) ?>"
                  alt="<?= htmlspecialchars($product['name']) ?>"
                  class="product-thumb"
                  loading="lazy"
                  style="width: 100%; height: 200px; object-fit: cover;"
                >
              <?php endif; ?>
            </div>
            <div class="product-details">
              <h3><?= htmlspecialchars($product['name']) ?></h3>
              <p class="farmer-name">By: <?= htmlspecialchars($product['farmer_name']) ?></p>
              <?php if (isset($product['farm_name']) && $product['farm_name']): ?>
                <p class="farm-name"><?= htmlspecialchars($product['farm_name']) ?></p>
              <?php endif; ?>
              <p class="price">₱<?= number_format($product['price'], 2) ?>/<?= htmlspecialchars($product['unit']) ?></p>
              <p class="quantity">Available: <?= $product['quantity'] ?> <?= htmlspecialchars($product['unit']) ?></p>
              <?php if (!empty($product['description'])): ?>
                <p class="description"><?= htmlspecialchars($product['description']) ?></p>
              <?php endif; ?>
              <div class="crop-actions">
                <form method="POST" action="../common/add-to-cart.php" style="display: inline;">
                  <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                  <input type="number" name="quantity" min="1" max="<?= $product['quantity'] ?>" value="1" style="width:60px;margin-right:8px;" />
                  <button type="submit" class="add-to-cart">Add to Cart</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('#addToCartForm');
    
    forms.forEach(form => {
      form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const data = {
          buyer_id: formData.get('buyer_id'),
          product_id: formData.get('product_id'),
          quantity: parseInt(formData.get('quantity'))
        };
        
        try {
          const response = await fetch('<?= BASE_URL ?>/api/cart.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
          });
          
          const result = await response.json();
          
          if (result.ok) {
            alert('Item added to cart successfully!');
            // Optionally update cart count in the UI
            const cartCount = document.querySelector('.cart-count');
            if (cartCount) {
              cartCount.textContent = result.cart?.length || '0';
            }
          } else {
            alert(result.error || 'Failed to add item to cart');
          }
        } catch (error) {
          console.error('Error:', error);
          alert('An error occurred while adding to cart');
        }
      });
    });
  });
  </script>

  <style>
    /* Force dark green sidebar background */
    .sidebar {
      background: #1B5E20 !important;
      top: 80px !important;
    }
    
    /* Navigation Logo Fix */
    nav .nav-logo {
      width: 40px;
      height: 40px;
      object-fit: contain;
      display: block;
      border-radius: 0;
      background: transparent;
      padding: 2px;
    }

    nav .nav-left {
      display: flex;
      align-items: center;
      gap: 12px;
      height: 100%;
    }

    nav .brand {
      font-size: 1.5rem;
      font-weight: bold;
      color: #ffffff;
      margin: 0;
      line-height: 1;
    }

    .market-card {
      display: flex;
      flex-direction: column;
      border: 1px solid #e0e0e0;
      border-radius: 12px;
      overflow: hidden;
      background: white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: transform 0.2s, box-shadow 0.2s;
      height: 100%;
    }

    .market-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .product-image {
      width: 100%;
      height: 200px;
      overflow: hidden;
      background-color: #f9f9f9;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .product-details {
      padding: 16px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .product-details h3 {
      margin: 0 0 8px 0;
      color: #333;
      font-size: 1.2em;
    }

    .farmer-name {
      color: #4CAF50;
      font-weight: 500;
      margin: 4px 0;
      font-size: 0.95em;
    }

    .farm-name {
      color: #666;
      font-size: 0.9em;
      margin: 2px 0 8px 0;
    }

    .price {
      font-size: 1.25em;
      font-weight: bold;
      color: #2E7D32;
      margin: 8px 0;
    }

    .quantity {
      color: #666;
      font-size: 0.9em;
      margin: 4px 0 12px 0;
    }

    .description {
      color: #666;
      font-size: 0.9em;
      margin: 8px 0 16px 0;
      flex-grow: 1;
      line-height: 1.5;
    }

    .crop-actions {
      margin-top: auto;
      padding-top: 12px;
      border-top: 1px solid #eee;
    }

    .add-to-cart {
      background-color: #4CAF50;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.95em;
      transition: background-color 0.2s;
    }

    .add-to-cart:hover {
      background-color: #3e8e41;
    }

    .market-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 24px;
      padding: 20px 0;
    }

    @media (max-width: 768px) {
      .market-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      }
    }

    .no-products {
      grid-column: 1 / -1;
      text-align: center;
      color: #666;
      padding: 40px 0;
    }
  </style>
  <script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>
