-- Create newsletter signups table for FARMLINK landing page
CREATE TABLE IF NOT EXISTS newsletter_signups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
);

-- Insert sample data (optional)
INSERT IGNORE INTO newsletter_signups (full_name, email, phone) VALUES
('Juan Dela Cruz', 'juan@example.com', '+63 912 345 6789'),
('Maria Santos', 'maria@example.com', '+63 923 456 7890'),
('Pedro Reyes', 'pedro@example.com', '+63 934 567 8901');
