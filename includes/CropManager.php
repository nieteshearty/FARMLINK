<?php
require_once __DIR__ . '/product_helpers.php';

class CropManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Add a new crop
     * 
     * @param array $data Crop data (name, description, price, quantity, category, farmer_id, image)
     * @param array $file $_FILES array element for the image
     * @return array Result with status and message
     */
    public function addCrop($data, $file = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Handle image upload if provided
            $imagePath = '';
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $imagePath = handleImageUpload($file);
                if ($imagePath === false) {
                    throw new Exception('Failed to upload image. Please try again.');
                }
            }
            
            // Insert crop into database
            $stmt = $this->pdo->prepare("
                INSERT INTO products (name, description, price, quantity, category, farmer_id, image, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['price'],
                $data['quantity'],
                $data['category'] ?? 'Other',
                $data['farmer_id'],
                $imagePath
            ]);
            
            if (!$result) {
                throw new Exception('Failed to add crop to database');
            }
            
            $this->pdo->commit();
            return [
                'success' => true,
                'message' => 'Crop added successfully',
                'id' => $this->pdo->lastInsertId()
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            // Clean up uploaded file if transaction failed
            if (!empty($imagePath)) {
                deleteProductImage($imagePath);
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update an existing crop
     * 
     * @param int $cropId ID of the crop to update
     * @param array $data Updated crop data
     * @param array|null $file $_FILES array element for the new image (optional)
     * @return array Result with status and message
     */
    public function updateCrop($cropId, $data, $file = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current crop data
            $currentCrop = $this->getCropById($cropId);
            if (!$currentCrop) {
                throw new Exception('Crop not found');
            }
            
            $imagePath = $currentCrop['image'];
            
            // Handle new image upload if provided
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $newImagePath = handleImageUpload($file);
                if ($newImagePath === false) {
                    throw new Exception('Failed to upload new image');
                }
                
                // Delete old image if it exists and is not the placeholder
                if (!empty($imagePath)) {
                    deleteProductImage($imagePath);
                }
                
                $imagePath = $newImagePath;
            }
            
            // Update crop in database
            $stmt = $this->pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, quantity = ?, 
                    category = ?, image = ?, updated_at = NOW()
                WHERE id = ? AND farmer_id = ?
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['price'],
                $data['quantity'],
                $data['category'] ?? 'Other',
                $imagePath,
                $cropId,
                $data['farmer_id']
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('No changes made or crop not found');
            }
            
            $this->pdo->commit();
            return [
                'success' => true,
                'message' => 'Crop updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete a crop
     * 
     * @param int $cropId ID of the crop to delete
     * @param int $farmerId ID of the farmer (for verification)
     * @return array Result with status and message
     */
    public function deleteCrop($cropId, $farmerId) {
        try {
            $this->pdo->beginTransaction();
            
            // Get crop data before deleting
            $crop = $this->getCropById($cropId);
            
            if (!$crop) {
                throw new Exception('Crop not found');
            }
            
            // Verify ownership
            if ($crop['farmer_id'] != $farmerId) {
                throw new Exception('You do not have permission to delete this crop');
            }
            
            // Delete the crop
            $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ? AND farmer_id = ?");
            $result = $stmt->execute([$cropId, $farmerId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                throw new Exception('Failed to delete crop');
            }
            
            // Delete the associated image if it exists and is not the placeholder
            if (!empty($crop['image'])) {
                deleteProductImage($crop['image']);
            }
            
            $this->pdo->commit();
            return [
                'success' => true,
                'message' => 'Crop deleted successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get crop by ID
     * 
     * @param int $cropId ID of the crop
     * @return array|null Crop data or null if not found
     */
    public function getCropById($cropId) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$cropId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all crops for a farmer
     * 
     * @param int $farmerId ID of the farmer
     * @return array List of crops
     */
    public function getFarmerCrops($farmerId) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE farmer_id = ? ORDER BY created_at DESC");
        $stmt->execute([$farmerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all available products with farmer information
     * 
     * @return array Array of products with farmer details
     */
    public function getAllProducts()
    {
        try {
            $query = "
                SELECT p.*, u.username as farmer_name, u.farm_name, u.location as farm_location
                FROM products p
                JOIN users u ON p.farmer_id = u.id
                WHERE p.quantity > 0  -- Only show products that are in stock
                ORDER BY p.created_at DESC
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all products: " . $e->getMessage());
            return [];
        }
    }
}
