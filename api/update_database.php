<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    // Check if payment_method column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_method'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add payment_method column
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(20) DEFAULT 'cod' AFTER total");
        echo "✅ Added payment_method column to orders table\n";
        
        // Update existing orders to have 'cod' as default payment method
        $pdo->exec("UPDATE orders SET payment_method = 'cod' WHERE payment_method IS NULL");
        echo "✅ Updated existing orders with default payment method\n";
    } else {
        echo "ℹ️ payment_method column already exists\n";
    }
    
    echo "✅ Database migration completed successfully\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
