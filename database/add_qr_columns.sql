-- Add QR code columns to products table
ALTER TABLE products 
ADD COLUMN qr_code VARCHAR(255) NULL AFTER image,
ADD COLUMN qr_data TEXT NULL AFTER qr_code;

-- Add index for QR code lookups
CREATE INDEX idx_products_qr_code ON products(qr_code);

-- Create QR code scans log table for tracking
CREATE TABLE IF NOT EXISTS qr_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NULL,
    scan_type ENUM('camera', 'file', 'manual') DEFAULT 'camera',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Add index for scan logs
CREATE INDEX idx_qr_scans_product ON qr_scans(product_id);
CREATE INDEX idx_qr_scans_user ON qr_scans(user_id);
CREATE INDEX idx_qr_scans_date ON qr_scans(created_at);