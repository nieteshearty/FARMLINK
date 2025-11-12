-- Add expiration functionality to products table
ALTER TABLE products 
ADD COLUMN expires_at DATETIME DEFAULT NULL;

-- Update existing products to expire 3 days from their creation date
UPDATE products 
SET expires_at = DATE_ADD(created_at, INTERVAL 3 DAY) 
WHERE expires_at IS NULL;

-- Create index for better performance on expiration queries
CREATE INDEX idx_products_expires_at ON products(expires_at);
