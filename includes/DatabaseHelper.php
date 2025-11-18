<?php
class DatabaseHelper {

    private static array $columnCache = [];

    public static function tableHasColumn(string $table, string $column): bool {
        $cacheKey = strtolower($table . ':' . $column);
        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

        $pdo = getDBConnection();

        try {
            $normalizedTable = preg_replace('/[^a-z0-9_]/i', '', $table);
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$normalizedTable}` LIKE ?");
            $stmt->execute([$column]);
            $hasColumn = $stmt->fetch() ? true : false;
        } catch (Exception $e) {
            $hasColumn = false;
        }

        self::$columnCache[$cacheKey] = $hasColumn;
        return $hasColumn;
    }
    
    public static function getStats($role = null, $userId = null) {
        $pdo = getDBConnection();
        $stats = [];
        
        if ($role === 'farmer' && $userId) {
            // Farmer-specific stats
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products WHERE farmer_id = ?");
            $stmt->execute([$userId]);
            $stats['total_products'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total_sales FROM orders WHERE farmer_id = ? AND status = 'completed'");
            $stmt->execute([$userId]);
            $stats['total_sales'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE farmer_id = ? AND status = 'pending'");
            $stmt->execute([$userId]);
            $stats['pending_orders'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as completed_orders FROM orders WHERE farmer_id = ? AND status = 'completed'");
            $stmt->execute([$userId]);
            $stats['completed_orders'] = $stmt->fetchColumn();
            
        } elseif ($role === 'buyer' && $userId) {
            // Buyer-specific stats
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE buyer_id = ?");
            $stmt->execute([$userId]);
            $stats['total_orders'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total_spent FROM orders WHERE buyer_id = ? AND status = 'completed'");
            $stmt->execute([$userId]);
            $stats['total_spent'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE buyer_id = ? AND status = 'pending'");
            $stmt->execute([$userId]);
            $stats['pending_orders'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as completed_orders FROM orders WHERE buyer_id = ? AND status = 'completed'");
            $stmt->execute([$userId]);
            $stats['completed_orders'] = $stmt->fetchColumn();
            
            // Cart items for buyer dashboard
            $stmt = $pdo->prepare("SELECT COUNT(*) as cart_items FROM cart WHERE buyer_id = ?");
            $stmt->execute([$userId]);
            $stats['cart_items'] = $stmt->fetchColumn();
            
        } else {
            // System-wide stats for super admin or default
            $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $stats['farmers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer'")->fetchColumn();
            $stats['buyers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'buyer'")->fetchColumn();
            $stats['super_admins'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
            $stats['products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $stats['total_orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
            $stats['total_sales'] = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'completed'")->fetchColumn();
        }
        
        return $stats;
    }
    
    public static function getRecentActivity($limit = 10, $userId = null) {
        $pdo = getDBConnection();
        
        try {
            // Ensure limit is an integer
            $limit = (int)$limit;
            
            if ($userId) {
                $stmt = $pdo->prepare("SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT " . $limit);
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare("SELECT al.*, u.username FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT " . $limit);
                $stmt->execute([]);
            }
            
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Clean up any corrupted data
            foreach ($activities as &$activity) {
                // Ensure message is valid UTF-8
                if (isset($activity['message'])) {
                    $activity['message'] = mb_convert_encoding($activity['message'], 'UTF-8', 'UTF-8');
                    // Remove any non-printable characters
                    $activity['message'] = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $activity['message']);
                }
                
                // Ensure username is valid
                if (isset($activity['username'])) {
                    $activity['username'] = mb_convert_encoding($activity['username'], 'UTF-8', 'UTF-8');
                    $activity['username'] = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $activity['username']);
                }
            }
            
            return $activities;
        } catch (PDOException $e) {
            // If activity_log table doesn't exist, return empty array
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Table") !== false) {
                return [];
            }
            throw $e;
        }
    }
    
    public static function getProducts($farmerId = null) {
        $pdo = getDBConnection();
        
        if ($farmerId) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE farmer_id = ? ORDER BY created_at DESC");
            $stmt->execute([$farmerId]);
        } else {
            $stmt = $pdo->query("SELECT p.*, u.username as farmer_name, u.farm_name FROM products p JOIN users u ON p.farmer_id = u.id ORDER BY p.created_at DESC");
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getAllUsers() {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getUsersByRole($role) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY created_at DESC");
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getOrders($userId = null, $role = null) {
        $pdo = getDBConnection();
        
        if ($role === 'farmer' && $userId) {
            $stmt = $pdo->prepare("
                SELECT o.*, u.username as buyer_name, u.email as buyer_email 
                FROM orders o 
                JOIN users u ON o.buyer_id = u.id 
                WHERE o.farmer_id = ? 
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$userId]);
        } elseif ($role === 'buyer' && $userId) {
            $stmt = $pdo->prepare("
                SELECT o.*, u.username as farmer_name, u.email as farmer_email 
                FROM orders o 
                JOIN users u ON o.farmer_id = u.id 
                WHERE o.buyer_id = ? 
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$userId]);
        } else {
            // All orders for super admin
            $stmt = $pdo->query("
                SELECT o.*, 
                       ub.username as buyer_name, ub.email as buyer_email, ub.company as buyer_company,
                       uf.username as farmer_name, uf.email as farmer_email, uf.farm_name
                FROM orders o 
                JOIN users ub ON o.buyer_id = ub.id 
                JOIN users uf ON o.farmer_id = uf.id 
                ORDER BY o.created_at DESC
            ");
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getUserActivity($userId, $limit = 50) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getSystemMetrics() {
        $pdo = getDBConnection();
        
        $metrics = [];
        
        // User metrics
        $metrics['users_by_role'] = [];
        $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        while ($row = $stmt->fetch()) {
            $metrics['users_by_role'][$row['role']] = $row['count'];
        }
        
        // Product metrics
        $metrics['products_by_category'] = [];
        $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM products GROUP BY category");
        while ($row = $stmt->fetch()) {
            $metrics['products_by_category'][$row['category']] = $row['count'];
        }
        
        // Order metrics
        $metrics['orders_by_status'] = [];
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
        while ($row = $stmt->fetch()) {
            $metrics['orders_by_status'][$row['status']] = $row['count'];
        }
        
        // Sales metrics
        $metrics['monthly_sales'] = [];
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                   COUNT(*) as orders, 
                   SUM(total) as revenue 
            FROM orders 
            WHERE status = 'completed' 
            GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
            ORDER BY month DESC 
            LIMIT 12
        ");
        $metrics['monthly_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $metrics;
    }
    
    public static function getOrderItems($orderId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name, p.unit
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getCart($buyerId) {
        $pdo = getDBConnection();

        $productsHasExpiresAt = self::tableHasColumn('products', 'expires_at');

        if ($productsHasExpiresAt) {
            $query = "
                SELECT c.*, p.name, p.price, p.unit, p.image, p.category, p.farmer_id,
                       p.expires_at, p.created_at as product_created_at,
                       COALESCE(p.expires_at, DATE_ADD(p.created_at, INTERVAL 3 DAY)) as calculated_expires_at,
                       (COALESCE(p.expires_at, DATE_ADD(p.created_at, INTERVAL 3 DAY)) < NOW()) as is_product_expired,
                       u.username as farmer_name, u.farm_name
                FROM cart c
                JOIN products p ON c.product_id = p.id
                JOIN users u ON p.farmer_id = u.id
                WHERE c.buyer_id = ?
                ORDER BY c.created_at DESC
            ";
        } else {
            $query = "
                SELECT c.*, p.name, p.price, p.unit, p.image, p.category, p.farmer_id,
                       NULL as expires_at, p.created_at as product_created_at,
                       DATE_ADD(p.created_at, INTERVAL 3 DAY) as calculated_expires_at,
                       (DATE_ADD(p.created_at, INTERVAL 3 DAY) < NOW()) as is_product_expired,
                       u.username as farmer_name, u.farm_name
                FROM cart c
                JOIN products p ON c.product_id = p.id
                JOIN users u ON p.farmer_id = u.id
                WHERE c.buyer_id = ?
                ORDER BY c.created_at DESC
            ";
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute([$buyerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
