<?php
/**
 * Inventory Management Helper
 * Handles stock tracking, reservations, and alerts
 */

class InventoryHelper {
    
    /**
     * Update product stock
     */
    public static function updateStock($productId, $quantity, $type, $referenceType = 'manual', $referenceId = null, $notes = null, $userId = null) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Get current product info
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception('Product not found');
            }
            
            $oldStock = floatval($product['current_stock']);
            $newStock = $oldStock;
            
            // Calculate new stock based on type
            switch ($type) {
                case 'in':
                    $newStock = $oldStock + $quantity;
                    break;
                case 'out':
                    $newStock = max(0, $oldStock - $quantity);
                    break;
                case 'adjustment':
                    $newStock = $quantity;
                    break;
                case 'reserved':
                    // Don't change current_stock, update reserved_stock
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET reserved_stock = reserved_stock + ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$quantity, $productId]);
                    break;
                case 'released':
                    // Release reserved stock back to available
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET reserved_stock = GREATEST(0, reserved_stock - ?) 
                        WHERE id = ?
                    ");
                    $stmt->execute([$quantity, $productId]);
                    break;
            }
            
            // Update product stock (except for reserved/released)
            if (!in_array($type, ['reserved', 'released'])) {
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET current_stock = ?, 
                        updated_at = CURRENT_TIMESTAMP,
                        status = CASE 
                            WHEN ? <= 0 THEN 'out_of_stock'
                            ELSE 'active'
                        END
                    WHERE id = ?
                ");
                $stmt->execute([$newStock, $newStock, $productId]);
            }
            
            // Log inventory change
            $stmt = $pdo->prepare("
                INSERT INTO inventory_logs (
                    product_id, type, quantity, reference_type, 
                    reference_id, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productId, $type, $quantity, $referenceType,
                $referenceId, $notes, $userId
            ]);
            
            // Check for low stock alert
            if ($newStock <= floatval($product['low_stock_threshold']) && $newStock > 0) {
                self::createStockAlert($productId, $product['farmer_id'], 'low_stock', $newStock, $product['low_stock_threshold']);
            } elseif ($newStock <= 0) {
                self::createStockAlert($productId, $product['farmer_id'], 'out_of_stock', $newStock, $product['low_stock_threshold']);
            }
            
            $pdo->commit();
            
            return [
                'success' => true,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'change' => $newStock - $oldStock
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Inventory update error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Reserve stock for an order
     */
    public static function reserveStock($productId, $quantity, $orderId) {
        try {
            $pdo = getDBConnection();
            
            // Check available stock
            $stmt = $pdo->prepare("
                SELECT current_stock, reserved_stock, name 
                FROM products 
                WHERE id = ?
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                return ['success' => false, 'error' => 'Product not found'];
            }
            
            $availableStock = floatval($product['current_stock']) - floatval($product['reserved_stock']);
            
            if ($availableStock < $quantity) {
                return [
                    'success' => false, 
                    'error' => 'Insufficient stock available',
                    'available' => $availableStock,
                    'requested' => $quantity
                ];
            }
            
            // Reserve the stock
            $result = self::updateStock($productId, $quantity, 'reserved', 'order', $orderId, 
                "Reserved for order #{$orderId}");
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Stock reservation error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Release reserved stock (when order is cancelled)
     */
    public static function releaseStock($productId, $quantity, $orderId) {
        return self::updateStock($productId, $quantity, 'released', 'order', $orderId, 
            "Released from cancelled order #{$orderId}");
    }
    
    /**
     * Confirm stock usage (when order is completed)
     */
    public static function confirmStockUsage($productId, $quantity, $orderId) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Release reserved stock
            self::updateStock($productId, $quantity, 'released', 'order', $orderId);
            
            // Deduct from current stock
            $result = self::updateStock($productId, $quantity, 'out', 'order', $orderId, 
                "Sold in order #{$orderId}");
            
            // Update product sales count
            $stmt = $pdo->prepare("
                UPDATE products 
                SET total_sales = total_sales + 1 
                WHERE id = ?
            ");
            $stmt->execute([$productId]);
            
            $pdo->commit();
            return $result;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Stock confirmation error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create stock alert
     */
    private static function createStockAlert($productId, $farmerId, $alertType, $currentStock, $thresholdStock) {
        try {
            $pdo = getDBConnection();
            
            // Check if alert already exists and is unresolved
            $stmt = $pdo->prepare("
                SELECT id FROM stock_alerts 
                WHERE product_id = ? AND alert_type = ? AND is_resolved = FALSE
            ");
            $stmt->execute([$productId, $alertType]);
            
            if ($stmt->fetch()) {
                return; // Alert already exists
            }
            
            // Create new alert
            $stmt = $pdo->prepare("
                INSERT INTO stock_alerts (
                    product_id, farmer_id, alert_type, 
                    current_stock, threshold_stock
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$productId, $farmerId, $alertType, $currentStock, $thresholdStock]);
            
            // Create notification for farmer
            $alertMessages = [
                'low_stock' => 'Low stock alert',
                'out_of_stock' => 'Out of stock alert',
                'expiring_soon' => 'Product expiring soon'
            ];
            
            $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $productName = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, data, action_url) 
                VALUES (?, 'stock_alert', ?, ?, ?, ?)
            ");
            
            $notificationData = json_encode([
                'product_id' => $productId,
                'product_name' => $productName,
                'alert_type' => $alertType,
                'current_stock' => $currentStock,
                'threshold_stock' => $thresholdStock
            ]);
            
            $message = $alertType === 'low_stock' 
                ? "Your product '{$productName}' is running low (only {$currentStock} left)"
                : "Your product '{$productName}' is out of stock";
            
            $stmt->execute([
                $farmerId,
                $alertMessages[$alertType],
                $message,
                $notificationData,
                '/FARMLINK/pages/farmer/farmer-products.php?product=' . $productId
            ]);
            
        } catch (Exception $e) {
            error_log("Stock alert creation error: " . $e->getMessage());
        }
    }
    
    /**
     * Get inventory history for a product
     */
    public static function getInventoryHistory($productId, $limit = 50, $offset = 0) {
        try {
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    il.*,
                    u.username as created_by_name,
                    CASE 
                        WHEN il.reference_type = 'order' THEN CONCAT('Order #', il.reference_id)
                        ELSE il.reference_type
                    END as reference_display
                FROM inventory_logs il
                LEFT JOIN users u ON il.created_by = u.id
                WHERE il.product_id = ?
                ORDER BY il.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$productId, $limit, $offset]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'history' => $history
            ];
            
        } catch (Exception $e) {
            error_log("Inventory history error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get stock alerts for a farmer
     */
    public static function getStockAlerts($farmerId, $includeResolved = false) {
        try {
            $pdo = getDBConnection();
            
            $whereClause = "sa.farmer_id = ?";
            $params = [$farmerId];
            
            if (!$includeResolved) {
                $whereClause .= " AND sa.is_resolved = FALSE";
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    sa.*,
                    p.name as product_name,
                    p.image as product_image
                FROM stock_alerts sa
                JOIN products p ON sa.product_id = p.id
                WHERE {$whereClause}
                ORDER BY sa.created_at DESC
            ");
            
            $stmt->execute($params);
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'alerts' => $alerts
            ];
            
        } catch (Exception $e) {
            error_log("Stock alerts error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Resolve stock alert
     */
    public static function resolveStockAlert($alertId, $farmerId) {
        try {
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("
                UPDATE stock_alerts 
                SET is_resolved = TRUE, resolved_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND farmer_id = ?
            ");
            $stmt->execute([$alertId, $farmerId]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Resolve alert error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
