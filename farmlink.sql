-- ===================================================================
-- FARMLINK COMPLETE DATABASE SETUP
-- ===================================================================
-- This file contains the complete database schema with all features
-- Run this after fresh XAMPP installation to set up everything
-- ===================================================================

-- Create and use database
USE if0_40396777_farmlink;

-- ===================================================================
-- 1. CORE TABLES (Base Schema)
-- ===================================================================

-- Users table with all enhancements
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'farmer', 'buyer') NOT NULL,
    farm_name VARCHAR(100),
    location VARCHAR(100),
    company VARCHAR(100),
    profile_picture VARCHAR(255) DEFAULT NULL,
    
    -- Enhanced user data
    phone_number VARCHAR(20) NULL,
    phone_verified BOOLEAN DEFAULT FALSE,
    email_verified BOOLEAN DEFAULT TRUE,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    business_license VARCHAR(100) NULL,
    tax_id VARCHAR(50) NULL,
    average_rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    last_active TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_location (latitude, longitude),
    INDEX idx_city (city, province)
);

-- Product categories table
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

-- Products table with all enhancements
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    quantity DECIMAL(10,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'kg',
    description TEXT,
    image VARCHAR(255),
    
    -- Enhanced product data
    current_stock DECIMAL(10,2) DEFAULT 0,
    reserved_stock DECIMAL(10,2) DEFAULT 0,
    low_stock_threshold DECIMAL(10,2) DEFAULT 5,
    is_organic BOOLEAN DEFAULT FALSE,
    harvest_date DATE NULL,
    expiry_date DATE NULL,
    keywords VARCHAR(500) NULL,
    minimum_order DECIMAL(10,2) DEFAULT 1,
    maximum_order DECIMAL(10,2) NULL,
    average_rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    total_sales INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    seasonal_availability JSON NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_farmer (farmer_id),
    INDEX idx_category (category),
    INDEX idx_price (price),
    INDEX idx_stock (current_stock),
    INDEX idx_search (name, category),
    INDEX idx_organic (is_organic),
    INDEX idx_status (status)
);

-- Orders table with delivery mapping and payment features
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    farmer_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    
    -- Payment information
    payment_method VARCHAR(50) DEFAULT 'cod',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded', 'partial') DEFAULT 'pending',
    payment_reference VARCHAR(100) NULL,
    
    -- Delivery mapping features (CRITICAL FOR FARMER MAP VIEW)
    delivery_address TEXT NULL,
    delivery_coordinates VARCHAR(100) NULL,
    delivery_instructions TEXT NULL,
    delivery_date DATE NULL,
    delivery_time TIME NULL,
    delivery_fee DECIMAL(10,2) DEFAULT 0,
    
    -- Additional order data
    order_notes TEXT NULL,
    cancellation_reason TEXT NULL,
    estimated_delivery DATE NULL,
    tracking_number VARCHAR(100) NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_buyer (buyer_id),
    INDEX idx_farmer (farmer_id),
    INDEX idx_status (status),
    INDEX idx_delivery_date (delivery_date)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
);

-- Shopping cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (buyer_id, product_id),
    INDEX idx_buyer (buyer_id)
);

-- ===================================================================
-- 2. MESSAGING SYSTEM
-- ===================================================================

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
    UNIQUE KEY unique_conversation (user1_id, user2_id),
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- ===================================================================
-- 3. RATING & REVIEW SYSTEM
-- ===================================================================

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

-- ===================================================================
-- 4. NOTIFICATION SYSTEM
-- ===================================================================

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

-- ===================================================================
-- 5. USER ADDRESSES (Multiple delivery addresses)
-- ===================================================================

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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
);

-- ===================================================================
-- 6. INVENTORY MANAGEMENT
-- ===================================================================

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

-- ===================================================================
-- 7. PAYMENT SYSTEM
-- ===================================================================

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

-- ===================================================================
-- 8. ORDER TRACKING
-- ===================================================================

-- Order status history for tracking changes
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    changed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order (order_id)
);

-- ===================================================================
-- 9. SYSTEM CONFIGURATION
-- ===================================================================

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

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_date (user_id, created_at)
);

-- ===================================================================
-- 10. INSERT DEFAULT DATA
-- ===================================================================

-- Insert default product categories
INSERT IGNORE INTO product_categories (name, description) VALUES
('Vegetables', 'Fresh vegetables and leafy greens'),
('Fruits', 'Fresh fruits and berries'),
('Grains', 'Rice, corn, wheat and other grains'),
('Herbs', 'Fresh herbs and spices'),
('Dairy', 'Milk, cheese and dairy products'),
('Meat', 'Fresh meat and poultry'),
('Seafood', 'Fresh fish and seafood'),
('Organic', 'Certified organic products');

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

-- Insert demo users (password: password123)
INSERT IGNORE INTO users (username, email, password, role, farm_name, location, company, email_verified, status) VALUES
('admin', 'admin@farmlink.app', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, NULL, TRUE, 'active'),
('farmer1', 'farmer1@farmlink.app', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', 'Green Fields Farm', 'Naval, Biliran', NULL, TRUE, 'active'),
('farmer2', 'farmer2@farmlink.app', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', 'Sunshine Farm', 'Tacloban, Leyte', NULL, TRUE, 'active'),
('farmer3', 'farmer3@farmlink.app', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', 'Organic Valley Farm', 'Ormoc, Leyte', NULL, TRUE, 'active'),
('buyer1', 'buyer1@farmlink.app', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', NULL, 'Naval, Biliran', 'Fresh Market', TRUE, 'active'),
('buyer2', 'buyer2@farmlink.app', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', NULL, 'Tacloban, Leyte', 'Local Grocery', TRUE, 'active'),
('superadmin', 'superadmin@farmlink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', NULL, NULL, NULL, TRUE, 'active');

-- Insert sample products
INSERT IGNORE INTO products (farmer_id, name, category, quantity, current_stock, price, unit, description, status) VALUES
(2, 'Fresh Tomatoes', 'Vegetables', 100, 100, 80.00, 'kg', 'Fresh red tomatoes from Naval, Biliran', 'active'),
(2, 'Organic Lettuce', 'Vegetables', 50, 50, 120.00, 'kg', 'Organic lettuce grown without pesticides', 'active'),
(2, 'Sweet Corn', 'Vegetables', 75, 75, 60.00, 'kg', 'Sweet yellow corn, freshly harvested', 'active'),
(3, 'Ripe Bananas', 'Fruits', 200, 200, 45.00, 'kg', 'Sweet ripe bananas from Leyte', 'active'),
(3, 'Fresh Coconuts', 'Fruits', 150, 150, 25.00, 'piece', 'Fresh coconuts with water', 'active'),
(3, 'Pineapples', 'Fruits', 80, 80, 90.00, 'piece', 'Sweet tropical pineapples', 'active'),
(4, 'Brown Rice', 'Grains', 500, 500, 55.00, 'kg', 'Organic brown rice from Ormoc', 'active'),
(4, 'White Rice', 'Grains', 1000, 1000, 45.00, 'kg', 'Premium white rice', 'active');

-- Insert sample orders with delivery information for testing farmer map view
INSERT IGNORE INTO orders (buyer_id, farmer_id, total, status, delivery_address, delivery_coordinates, delivery_instructions, payment_method) VALUES
(5, 2, 320.00, 'pending', 'Larrazabal, Naval, Biliran, Philippines', '11.2421,124.0070', 'Please call when you arrive', 'cod'),
(5, 3, 180.00, 'completed', 'Poblacion, Naval, Biliran, Philippines', '11.2445,124.0055', 'Leave at the gate if no one is home', 'cod'),
(6, 2, 240.00, 'pending', 'Downtown, Tacloban City, Leyte, Philippines', '11.2447,125.0047', 'Apartment building, 2nd floor', 'cod'),
(6, 4, 550.00, 'completed', 'Marasbaras, Tacloban City, Leyte, Philippines', '11.2500,125.0100', 'House with blue gate', 'cod');

-- Insert corresponding order items
INSERT IGNORE INTO order_items (order_id, product_id, quantity, price) VALUES
(1, 1, 2, 80.00),
(1, 2, 2, 120.00),
(2, 4, 4, 45.00),
(3, 1, 3, 80.00),
(4, 7, 10, 55.00);

-- ===================================================================
-- 11. FINAL OPTIMIZATIONS
-- ===================================================================

-- Update products with current stock = quantity for existing data
UPDATE products SET current_stock = quantity WHERE current_stock = 0;

-- Set default categories for existing products
UPDATE products SET category = 'Vegetables' WHERE category IS NULL OR category = '';

-- Ensure all users are marked as active and email verified
UPDATE users SET email_verified = TRUE, status = 'active' WHERE email_verified = FALSE OR status IS NULL;

-- Set default status for existing products
UPDATE products SET status = 'active' WHERE status IS NULL;

-- ===================================================================
-- SETUP COMPLETE!
-- ===================================================================
-- 
-- 🎉 FARMLINK Database Setup Complete!
-- 
-- Features included:
-- ✅ User management (farmers, buyers, admins)
-- ✅ Product catalog with categories and inventory
-- ✅ Shopping cart system
-- ✅ Order processing with delivery mapping
-- ✅ Payment system integration
-- ✅ Messaging between users
-- ✅ Rating and review system
-- ✅ Notification system
-- ✅ Inventory management
-- ✅ Multi-address support
-- ✅ Order tracking and history
-- ✅ System configuration
-- 
-- 🗺️ DELIVERY MAPPING FEATURES:
-- ✅ delivery_address column for full addresses
-- ✅ delivery_coordinates for latitude,longitude
-- ✅ delivery_instructions for special notes
-- ✅ Sample orders with Naval, Biliran coordinates
-- 
-- 📱 Demo Login Credentials:
-- Farmers: farmer1@farmlink.app / password123
-- Buyers:  buyer1@farmlink.app / password123
-- Admin:   admin@farmlink.app / password123
-- 
-- 🚀 Ready to use with enhanced delivery mapping!
-- 
-- ===================================================================

COMMIT;
