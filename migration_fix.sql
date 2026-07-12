-- ====================================================
-- Migration cho các fix bảo mật (chạy 1 lần trước khi
-- deploy code mới)
-- ====================================================

-- 1) Thêm cột phân quyền cho users.
--    Mặc định tất cả user hiện có thành 'admin' để không ai
--    bị khoá ngoài hệ thống đột ngột. Sau khi chạy xong,
--    bạn nên tự đổi role của tài khoản nhân viên/thu ngân
--    thường thành 'staff'.
ALTER TABLE users
    ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin'
    AFTER username;

-- Ví dụ đổi 1 tài khoản thành nhân viên thường (không được xoá hoá đơn):
-- UPDATE users SET role='staff' WHERE username='thu_ngan_a';

-- 2) Cho phép trạng thái 'voided' (đơn bị huỷ) thay vì xoá cứng.
--    Nếu cột status đang là ENUM, đổi sang VARCHAR cho linh hoạt.
ALTER TABLE orders
    MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open';

ALTER TABLE orders
    ADD COLUMN voided_by INT NULL AFTER status,
    ADD COLUMN voided_at DATETIME NULL AFTER voided_by;
