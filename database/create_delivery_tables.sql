-- Create delivery zone and schedule tables if they don't exist

-- Create farmer_delivery_zones table
CREATE TABLE IF NOT EXISTS farmer_delivery_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    zone_name VARCHAR(255) NOT NULL,
    areas_covered TEXT,
    delivery_fee DECIMAL(10,2) DEFAULT 0.00,
    minimum_order DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create farmer_delivery_schedule table
CREATE TABLE IF NOT EXISTS farmer_delivery_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES farmer_delivery_zones(id) ON DELETE CASCADE,
    UNIQUE KEY unique_schedule (zone_id, day_of_week, time_slot)
);

-- Create indexes for better performance
CREATE INDEX idx_farmer_delivery_zones_farmer_id ON farmer_delivery_zones(farmer_id);
CREATE INDEX idx_farmer_delivery_zones_active ON farmer_delivery_zones(is_active);
CREATE INDEX idx_farmer_delivery_schedule_zone_id ON farmer_delivery_schedule(zone_id);
CREATE INDEX idx_farmer_delivery_schedule_day ON farmer_delivery_schedule(day_of_week);
