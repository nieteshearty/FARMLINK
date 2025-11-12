-- FARMLINK Core Features Database Update
-- Run this to add all missing essential tables and columns

USE farmlink;

-- ===================================
-- 1. MESSAGING SYSTEM
-- ===================================

-- Messages table for real-time communication
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    order_id INT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file') DEFAULT 'text',
    file_path VARCHAR(255) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_sender_receiver (sender_id, receiver_id),
    INDEX idx_created_at (created_at)
);

-- Conversations table to track chat threads
CREATE TABLE IF NOT EXISTS conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    order_id INT NULL,
    last_message_id INT NULL,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_conversation (LEAST(user1_id, user2_id), GREATEST(user1_id, user2_id)),
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- ===================================
-- 2. RATING & REVIEW SYSTEM
-- ===================================

-- Reviews table for product and farmer ratings
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    farmer_id INT NOT NULL,
    overall_rating INT NOT NULL CHECK (overall_rating >= 1 AND overall_rating <= 5),
    quality_rating INT NULL CHECK (quality_rating >= 1 AND quality_rating <= 5),
    delivery_rating INT NULL CHECK (delivery_rating >= 1 AND delivery_rating <= 5),
    communication_rating INT NULL CHECK (communication_rating >= 1 AND communication_rating <= 5),
    review_text TEXT NULL,
    review_images JSON NULL,
    is_verified BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_order_product_review (order_id, product_id),
    INDEX idx_product_rating (product_id, overall_rating),
    INDEX idx_farmer_rating (farmer_id, overall_rating)
);

-- Review responses table for farmer replies
CREATE TABLE IF NOT EXISTS review_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    review_id INT NOT NULL,
    farmer_id INT NOT NULL,
    response_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================
-- 3. NOTIFICATION SYSTEM
-- ===================================

-- Notifications table for user alerts
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created_at (created_at)
);

-- ===================================
-- 4. ENHANCED USER DATA
-- ===================================

-- Add missing columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS phone_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL,
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL,
ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS province VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS business_license VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS tax_id VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS total_reviews INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS last_active TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'suspended') DEFAULT 'active';

-- User addresses table for multiple delivery addresses
CREATE TABLE IF NOT EXISTS user_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    address_type ENUM('home', 'work', 'other') DEFAULT 'home',
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================
-- 5. ENHANCED PRODUCT DATA
-- ===================================

-- Add missing columns to products table
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS current_stock DECIMAL(10,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS reserved_stock DECIMAL(10,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS low_stock_threshold DECIMAL(10,2) DEFAULT 5,
ADD COLUMN IF NOT EXISTS is_organic BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS harvest_date DATE NULL,
ADD COLUMN IF NOT EXISTS expiry_date DATE NULL,
ADD COLUMN IF NOT EXISTS keywords TEXT NULL,
ADD COLUMN IF NOT EXISTS minimum_order DECIMAL(10,2) DEFAULT 1,
ADD COLUMN IF NOT EXISTS maximum_order DECIMAL(10,2) NULL,
ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS total_reviews INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS total_sales INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_featured BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
ADD COLUMN IF NOT EXISTS seasonal_availability JSON NULL;

-- Product categories table for better organization
CREATE TABLE IF NOT EXISTS product_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    image VARCHAR(255) NULL,
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL
);

-- Insert default categories
INSERT IGNORE INTO product_categories (name, description) VALUES
('Vegetables', 'Fresh vegetables and leafy greens'),
('Fruits', 'Fresh fruits and berries'),
('Grains', 'Rice, corn, wheat and other grains'),
('Herbs', 'Fresh herbs and spices'),
('Dairy', 'Milk, cheese and dairy products'),
('Meat', 'Fresh meat and poultry'),
('Seafood', 'Fresh fish and seafood'),
('Organic', 'Certified organic products');

-- ===================================
-- 6. ENHANCED ORDER SYSTEM
-- ===================================

-- Add missing columns to orders table
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS delivery_address_id INT NULL,
ADD COLUMN IF NOT EXISTS delivery_address TEXT NULL,
ADD COLUMN IF NOT EXISTS delivery_date DATE NULL,
ADD COLUMN IF NOT EXISTS delivery_time TIME NULL,
ADD COLUMN IF NOT EXISTS delivery_instructions TEXT NULL,
ADD COLUMN IF NOT EXISTS delivery_fee DECIMAL(10,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS payment_status ENUM('pending', 'paid', 'failed', 'refunded', 'partial') DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS order_notes TEXT NULL,
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL,
ADD COLUMN IF NOT EXISTS estimated_delivery DATE NULL,
ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(100) NULL;

-- Order status history for tracking changes
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    changed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================================
-- 7. INVENTORY MANAGEMENT
-- ===================================

-- Inventory logs for stock tracking
CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    type ENUM('in', 'out', 'adjustment', 'reserved', 'released') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    reference_type ENUM('order', 'manual', 'harvest', 'waste', 'return') NOT NULL,
    reference_id INT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_product_date (product_id, created_at)
);

-- Stock alerts for low inventory
CREATE TABLE IF NOT EXISTS stock_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    farmer_id INT NOT NULL,
    alert_type ENUM('low_stock', 'out_of_stock', 'expiring_soon') NOT NULL,
    current_stock DECIMAL(10,2) NOT NULL,
    threshold_stock DECIMAL(10,2) NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================
-- 8. PAYMENT SYSTEM
-- ===================================

-- Payment transactions table
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'PHP',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    gateway_response JSON NULL,
    reference_number VARCHAR(100) NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_order_status (order_id, status)
);

-- ===================================
-- 9. SEARCH & FILTERING INDEXES
-- ===================================

-- Create indexes for better search performance
CREATE INDEX IF NOT EXISTS idx_products_search ON products(name, category, keywords);
CREATE INDEX IF NOT EXISTS idx_products_location ON products(farmer_id);
CREATE INDEX IF NOT EXISTS idx_products_organic ON products(is_organic);
CREATE INDEX IF NOT EXISTS idx_products_price ON products(price);
CREATE INDEX IF NOT EXISTS idx_products_stock ON products(current_stock);
CREATE INDEX IF NOT EXISTS idx_users_location ON users(latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_users_city ON users(city, province);

-- ===================================
-- 10. SYSTEM SETTINGS
-- ===================================

-- System settings for configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'FARMLINK', 'string', 'Website name', true),
('site_description', 'Agricultural Marketplace Platform', 'string', 'Website description', true),
('default_currency', 'PHP', 'string', 'Default currency code', true),
('min_order_amount', '50', 'number', 'Minimum order amount', true),
('max_order_amount', '50000', 'number', 'Maximum order amount', true),
('delivery_fee_per_km', '5', 'number', 'Delivery fee per kilometer', false),
('free_delivery_threshold', '1000', 'number', 'Free delivery minimum amount', true),
('review_required_days', '3', 'number', 'Days after delivery to allow reviews', false),
('low_stock_threshold', '10', 'number', 'Default low stock threshold', false),
('enable_messaging', 'true', 'boolean', 'Enable messaging system', false),
('enable_reviews', 'true', 'boolean', 'Enable review system', false),
('enable_notifications', 'true', 'boolean', 'Enable notification system', false);

-- ===================================
-- UPDATE EXISTING DATA
-- ===================================

-- Update products with current stock = quantity
UPDATE products SET current_stock = quantity WHERE current_stock = 0;

-- Set default categories for existing products
UPDATE products SET category = 'Vegetables' WHERE category IS NULL OR category = '';

-- Mark all existing users as email verified (for existing accounts)
UPDATE users SET email_verified = TRUE WHERE email_verified = FALSE;

-- Set default status for existing users
UPDATE users SET status = 'active' WHERE status IS NULL;

-- Set default status for existing products
UPDATE products SET status = 'active' WHERE status IS NULL;

COMMIT;
