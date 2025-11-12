-- Add payment_method column to orders table
ALTER TABLE orders ADD COLUMN payment_method VARCHAR(20) DEFAULT 'cod' AFTER total;
