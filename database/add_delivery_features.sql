-- Add delivery limitation and scheduling features to FARMLINK
-- Run this SQL to add delivery features to existing database

-- Add delivery settings to users table (for farmers)
ALTER TABLE users ADD COLUMN IF NOT EXISTS delivery_radius_km INT DEFAULT 50 COMMENT 'Maximum delivery distance in kilometers';
ALTER TABLE users ADD COLUMN IF NOT EXISTS delivery_fee_per_km DECIMAL(8,2) DEFAULT 5.00 COMMENT 'Delivery fee per kilometer';
ALTER TABLE users ADD COLUMN IF NOT EXISTS min_delivery_fee DECIMAL(8,2) DEFAULT 50.00 COMMENT 'Minimum delivery fee';
ALTER TABLE users ADD COLUMN IF NOT EXISTS free_delivery_threshold DECIMAL(10,2) DEFAULT 500.00 COMMENT 'Free delivery above this amount';
ALTER TABLE users ADD COLUMN IF NOT EXISTS delivery_days VARCHAR(20) DEFAULT '1-2' COMMENT 'Delivery time in days (e.g., 1-2, 2-3, same-day)';
ALTER TABLE users ADD COLUMN IF NOT EXISTS pickup_available BOOLEAN DEFAULT TRUE COMMENT 'Whether pickup is available';
ALTER TABLE users ADD COLUMN IF NOT EXISTS delivery_available BOOLEAN DEFAULT TRUE COMMENT 'Whether delivery is available';
ALTER TABLE users ADD COLUMN IF NOT EXISTS delivery_schedule TEXT COMMENT 'JSON: delivery schedule by day';
ALTER TABLE users ADD COLUMN IF NOT EXISTS pickup_location TEXT COMMENT 'Pickup location details';
ALTER TABLE users ADD COLUMN IF NOT EXISTS delivery_notes TEXT COMMENT 'Special delivery instructions';

-- Add delivery options to orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_method ENUM('delivery', 'pickup') DEFAULT 'delivery' COMMENT 'Delivery or pickup';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_fee DECIMAL(8,2) DEFAULT 0.00 COMMENT 'Calculated delivery fee';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS estimated_delivery_date DATE NULL COMMENT 'Estimated delivery/pickup date';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_time_slot VARCHAR(50) NULL COMMENT 'Preferred delivery time slot';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS pickup_scheduled_date DATETIME NULL COMMENT 'Scheduled pickup date and time';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_distance_km DECIMAL(8,2) NULL COMMENT 'Distance from farmer to buyer';

-- Create delivery zones table for farmers
CREATE TABLE IF NOT EXISTS farmer_delivery_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    zone_name VARCHAR(100) NOT NULL,
    cities TEXT NOT NULL COMMENT 'Comma-separated list of cities covered',
    max_distance_km INT NOT NULL DEFAULT 50,
    delivery_fee DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    delivery_days VARCHAR(20) DEFAULT '1-2',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_farmer_zones (farmer_id),
    INDEX idx_zone_active (is_active)
);

-- Create delivery schedule table with area-based scheduling
CREATE TABLE IF NOT EXISTS farmer_delivery_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    zone_id INT NULL COMMENT 'Links to specific delivery zone',
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    time_slot VARCHAR(50) NOT NULL COMMENT 'Time slot like 7:00 AM - 12:00 PM',
    max_orders_per_slot INT DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES farmer_delivery_zones(id) ON DELETE CASCADE,
    INDEX idx_farmer_schedule (farmer_id),
    INDEX idx_zone_schedule (zone_id),
    INDEX idx_day_active (day_of_week, is_active)
);

-- Add additional columns to delivery zones for better area management
ALTER TABLE farmer_delivery_zones ADD COLUMN IF NOT EXISTS min_order_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Minimum order amount for delivery';
ALTER TABLE farmer_delivery_zones ADD COLUMN IF NOT EXISTS delivery_instructions TEXT COMMENT 'Special instructions for this area';
ALTER TABLE farmer_delivery_zones ADD COLUMN IF NOT EXISTS estimated_delivery_time VARCHAR(50) DEFAULT '1-2 hours' COMMENT 'Estimated delivery time for this zone';

-- Insert default delivery settings for existing farmers
INSERT INTO farmer_delivery_zones (farmer_id, zone_name, cities, max_distance_km, delivery_fee, delivery_days)
SELECT 
    id,
    'Local Area',
    COALESCE(CONCAT(city, ', ', province), location, 'Local Area'),
    50,
    5.00,
    '1-2'
FROM users 
WHERE role = 'farmer' 
AND id NOT IN (SELECT farmer_id FROM farmer_delivery_zones);

-- Insert default delivery schedule for existing farmers (Monday to Saturday)
INSERT INTO farmer_delivery_schedule (farmer_id, day_of_week, time_slots)
SELECT 
    u.id,
    d.day_name,
    '["8:00 AM - 12:00 PM", "1:00 PM - 5:00 PM"]'
FROM users u
CROSS JOIN (
    SELECT 'monday' as day_name UNION ALL
    SELECT 'tuesday' UNION ALL
    SELECT 'wednesday' UNION ALL
    SELECT 'thursday' UNION ALL
    SELECT 'friday' UNION ALL
    SELECT 'saturday'
) d
WHERE u.role = 'farmer'
AND NOT EXISTS (
    SELECT 1 FROM farmer_delivery_schedule fds 
    WHERE fds.farmer_id = u.id AND fds.day_of_week = d.day_name
);

-- Insert sample delivery zones for Biliran areas
INSERT IGNORE INTO farmer_delivery_zones (farmer_id, zone_name, cities, max_distance_km, delivery_fee, delivery_days, min_order_amount, delivery_instructions)
SELECT 
    u.id,
    'Caraycaray Area',
    'Caraycaray, Naval, Biliran',
    15,
    25.00,
    'Monday,Wednesday,Friday',
    200.00,
    'Delivery to Caraycaray area. Please have exact change ready.'
FROM users u
WHERE u.role = 'farmer'
AND NOT EXISTS (
    SELECT 1 FROM farmer_delivery_zones fdz 
    WHERE fdz.farmer_id = u.id AND fdz.zone_name = 'Caraycaray Area'
);

-- Insert sample delivery schedules for area-based delivery
INSERT IGNORE INTO farmer_delivery_schedule (farmer_id, zone_id, day_of_week, time_slot)
SELECT 
    fdz.farmer_id,
    fdz.id as zone_id,
    'monday',
    '7:00 AM - 12:00 PM'
FROM farmer_delivery_zones fdz
WHERE fdz.zone_name = 'Caraycaray Area';

INSERT IGNORE INTO farmer_delivery_schedule (farmer_id, zone_id, day_of_week, time_slot)
SELECT 
    fdz.farmer_id,
    fdz.id as zone_id,
    'wednesday',
    '7:00 AM - 12:00 PM'
FROM farmer_delivery_zones fdz
WHERE fdz.zone_name = 'Caraycaray Area';

INSERT IGNORE INTO farmer_delivery_schedule (farmer_id, zone_id, day_of_week, time_slot)
SELECT 
    fdz.farmer_id,
    fdz.id as zone_id,
    'friday',
    '7:00 AM - 12:00 PM'
FROM farmer_delivery_zones fdz
WHERE fdz.zone_name = 'Caraycaray Area';
