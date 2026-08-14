-- Chạy 1 lần trong đúng database POS trước khi chép code mới.
-- Code cũng có thể tự tạo bảng này khi admin mở lại ca lần đầu.

CREATE TABLE IF NOT EXISTS cashbook_reopened_shifts (
    report_date DATE NOT NULL,
    reopened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reopened_by_user_id INT NULL,
    PRIMARY KEY (report_date),
    KEY idx_cashbook_reopened_by_user_id (reopened_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
