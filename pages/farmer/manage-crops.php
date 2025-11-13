<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/CropManager.php';

// Require farmer role
$user = SessionManager::requireRole('farmer');

// Initialize CropManager
$cropManager = new CropManager(getDBConnection());

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $result = $cropManager->addCrop([
                    'name' => $_POST['name'],
                    'description' => $_POST['description'],
                    'price' => floatval($_POST['price']),
                    'quantity' => intval($_POST['quantity']),
                    'category' => $_POST['category'],
                    'farmer_id' => $user['id']
                ], $_FILES['image'] ?? null);
                
                if ($result['success']) {
                    $_SESSION['success'] = 'Crop added successfully';
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'update':
                $result = $cropManager->updateCrop($_POST['crop_id'], [
                    'name' => $_POST['name'],
                    'description' => $_POST['description'],
                    'price' => floatval($_POST['price']),
                    'quantity' => intval($_POST['quantity']),
                    'category' => $_POST['category'],
                    'farmer_id' => $user['id']
                ], $_FILES['image'] ?? null);
                
                if ($result['success']) {
                    $_SESSION['success'] = 'Crop updated successfully';
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
                
            case 'delete':
                $result = $cropManager->deleteCrop($_POST['crop_id'], $user['id']);
                if ($result['success']) {
                    $_SESSION['success'] = 'Crop deleted successfully';
                } else {
                    $_SESSION['error'] = $result['message'];
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get farmer's crops
$crops = $cropManager->getFarmerCrops($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Crops - FarmLink</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/farmer.css">
    <style>
        /* Force agricultural green sidebar background */
        .sidebar {
            background: #2E7D32 !important;
        }
        
        .crop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .crop-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #fff;
        }
        .crop-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 4px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        .btn-danger {
            background: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <?php include $basePath . '/includes/header.php'; ?>
    
    <div class="container">
        <h1>Manage Your Crops</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <h2>Add New Crop</h2>
        <form method="POST" enctype="multipart/form-data" class="crop-form">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label for="name">Crop Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Price (per unit)</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="quantity">Quantity Available</label>
                <input type="number" id="quantity" name="quantity" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <option value="Vegetables">Vegetables</option>
                    <option value="Fruits">Fruits</option>
                    <option value="Grains">Grains</option>
                    <option value="Dairy">Dairy</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="image">Product Image</label>
                <input type="file" id="image" name="image" accept="image/*">
            </div>
            
            <button type="submit" class="btn btn-primary">Add Crop</button>
        </form>
        
        <h2>Your Crops</h2>
        <div class="crop-grid">
            <?php foreach ($crops as $crop): ?>
                <div class="crop-card">
                    <?php 
                    // Display the product image with fallback to placeholder
                    $imagePath = !empty($crop['image']) ? $crop['image'] : (defined('BASE_URL') ? BASE_URL : '') . '/assets/img/placeholder.jpg';
                    ?>
                    <img src="<?= htmlspecialchars($imagePath) ?>" 
                         alt="<?= htmlspecialchars($crop['name']) ?>" 
                         class="crop-image"
                         onerror="this.onerror=null; this.src='<?= BASE_URL ?>/assets/img/placeholder.jpg';">
                    
                    <h3><?= htmlspecialchars($crop['name']) ?></h3>
                    <p><?= htmlspecialchars($crop['description']) ?></p>
                    <p><strong>Price:</strong> $<?= number_format($crop['price'], 2) ?> per unit</p>
                    <p><strong>In Stock:</strong> <?= $crop['quantity'] ?> units</p>
                    <p><strong>Category:</strong> <?= htmlspecialchars($crop['category']) ?></p>
                    
                    <div class="action-buttons">
                        <button class="btn btn-primary" 
                                onclick="editCrop(<?= htmlspecialchars(json_encode($crop)) ?>)">
                            Edit
                        </button>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="crop_id" value="<?= $crop['id'] ?>">
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm('Are you sure you want to delete this crop?')">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Edit Crop</h2>
            <form id="editForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="crop_id" id="edit_crop_id">
                
                <div class="form-group">
                    <label for="edit_name">Crop Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_price">Price (per unit)</label>
                    <input type="number" id="edit_price" name="price" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_quantity">Quantity Available</label>
                    <input type="number" id="edit_quantity" name="quantity" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_category">Category</label>
                    <select id="edit_category" name="category" required>
                        <option value="Vegetables">Vegetables</option>
                        <option value="Fruits">Fruits</option>
                        <option value="Grains">Grains</option>
                        <option value="Dairy">Dairy</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_image">New Image (leave empty to keep current)</label>
                    <input type="file" id="edit_image" name="image" accept="image/*">
                </div>
                
                <button type="submit" class="btn btn-primary">Update Crop</button>
            </form>
        </div>
    </div>
    
    <script>
        // Function to open the edit modal with crop data
        function editCrop(crop) {
            document.getElementById('edit_crop_id').value = crop.id;
            document.getElementById('edit_name').value = crop.name;
            document.getElementById('edit_description').value = crop.description || '';
            document.getElementById('edit_price').value = crop.price;
            document.getElementById('edit_quantity').value = crop.quantity;
            document.getElementById('edit_category').value = crop.category || 'Other';
            
            // Show the modal
            document.getElementById('editModal').style.display = 'block';
        }
        
        // Function to close the modal
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close the modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
    
    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
        }
        
        .action-buttons {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
    </style>
</body>
</html>
