-- Bổ sung menu chi phí vận hành cho module bếp bánh.
-- Có thể chạy lại an toàn; không xóa hoặc thay đổi dữ liệu hiện có.

CREATE TABLE IF NOT EXISTS bakery_operating_expenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT '',
    description VARCHAR(200) NOT NULL,
    payee VARCHAR(150) NOT NULL DEFAULT '',
    amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'paid',
    note VARCHAR(1000) NOT NULL DEFAULT '',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bakery_operating_expenses_date (expense_date),
    KEY idx_bakery_operating_expenses_status_date (status, expense_date),
    CONSTRAINT fk_bakery_operating_expenses_user FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
