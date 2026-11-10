-- Chạy một lần trên database cafe_pos trước khi bật trang đặt bánh mì.
-- Có thể chạy lại an toàn; các đơn đã đặt sẽ được giữ nguyên.

CREATE TABLE IF NOT EXISTS bread_preorders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    fulfillment_method ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
    delivery_address VARCHAR(500) NOT NULL DEFAULT '',
    pickup_date DATE NOT NULL,
    pickup_time TIME NOT NULL,
    customer_note VARCHAR(500) NOT NULL DEFAULT '',
    total_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
    status ENUM('pending','confirmed','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bread_preorders_pickup (pickup_date, pickup_time),
    KEY idx_bread_preorders_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS bread_preorder_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    preorder_id BIGINT UNSIGNED NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(150) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,0) NOT NULL,
    line_total DECIMAL(12,0) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_bread_preorder_items_order (preorder_id),
    CONSTRAINT fk_bread_preorder_items_order
        FOREIGN KEY (preorder_id) REFERENCES bread_preorders(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS bread_preorder_products (
    product_id INT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    KEY idx_bread_preorder_products_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS bread_preorder_product_images (
    product_id INT NOT NULL,
    image_id CHAR(32) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    KEY idx_bread_preorder_product_images_image (image_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS bread_daily_products (
    product_id INT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    KEY idx_bread_daily_products_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
