-- Chạy 1 lần trong phpMyAdmin trước khi tải code tính năng mua hàng lên server.
-- Có thể chạy lại an toàn trên database đã cập nhật.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'staff' AFTER username;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS employee_id INT NULL AFTER role,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER employee_id,
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN IF NOT EXISTS session_version INT NOT NULL DEFAULT 1 AFTER must_change_password,
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER session_version;

CREATE TABLE IF NOT EXISTS login_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    username VARCHAR(50) NOT NULL,
    result VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_history_user_id (user_id),
    KEY idx_login_history_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS purchase_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_date DATE NOT NULL,
    supplier VARCHAR(150) NOT NULL DEFAULT '',
    item_name VARCHAR(150) NOT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    unit VARCHAR(30) NOT NULL DEFAULT '',
    total_amount DECIMAL(12,2) NOT NULL,
    note VARCHAR(1000) NOT NULL DEFAULT '',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_purchase_entries_date (purchase_date),
    KEY idx_purchase_entries_created_by_date (created_by, purchase_date),
    CONSTRAINT fk_purchase_entries_user
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS purchase_advances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    advance_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    note VARCHAR(1000) NOT NULL DEFAULT '',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_purchase_advances_date (advance_date),
    KEY idx_purchase_advances_created_by_date (created_by, advance_date),
    CONSTRAINT fk_purchase_advances_user
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
