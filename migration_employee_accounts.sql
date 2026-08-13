-- Chạy file này 1 lần trong phpMyAdmin trước khi chép code mới lên server.
-- Tương thích cả database cũ chưa có cột role.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'staff' AFTER username;

-- Chỉ tài khoản tên admin được cấp quyền quản trị.
UPDATE users
SET role = 'admin'
WHERE LOWER(username) = 'admin';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS employee_id INT NULL AFTER role;

CREATE UNIQUE INDEX IF NOT EXISTS uq_users_employee_id
    ON users (employee_id);

-- Ghi nhận tài khoản thực hiện thanh toán cho từng hóa đơn.
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS sold_by_user_id INT NULL AFTER paid_at;

CREATE INDEX IF NOT EXISTS idx_orders_sold_by_user_id
    ON orders (sold_by_user_id);

-- Tài khoản admin và tài khoản dùng chung cũ giữ employee_id = NULL.
-- Sau khi admin tạo tài khoản riêng cho từng nhân viên, không dùng tài khoản
-- dùng chung cũ để chấm công nữa.
