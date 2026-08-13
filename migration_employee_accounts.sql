-- Chạy file này 1 lần trong phpMyAdmin trước khi chép code mới lên server.
-- Tương thích cả database cũ chưa có cột role.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'staff' AFTER username;

-- Chỉ tài khoản tên admin được cấp quyền quản trị.
UPDATE users
SET role = 'admin'
WHERE LOWER(username) = 'admin';

-- Bảo đảm tài khoản dùng chung cũ không giữ nhầm quyền admin.
UPDATE users
SET role = 'staff'
WHERE LOWER(username) = 'user';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS employee_id INT NULL AFTER role;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER employee_id,
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN IF NOT EXISTS session_version INT NOT NULL DEFAULT 1 AFTER must_change_password,
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER session_version;

ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER employee_type;

CREATE UNIQUE INDEX IF NOT EXISTS uq_users_employee_id
    ON users (employee_id);

-- Ghi nhận tài khoản thực hiện thanh toán cho từng hóa đơn.
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS sold_by_user_id INT NULL AFTER paid_at;

CREATE INDEX IF NOT EXISTS idx_orders_sold_by_user_id
    ON orders (sold_by_user_id);

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

-- Tài khoản admin và tài khoản dùng chung cũ giữ employee_id = NULL.
-- Sau khi admin tạo tài khoản riêng cho từng nhân viên, không dùng tài khoản
-- dùng chung cũ để chấm công nữa.
