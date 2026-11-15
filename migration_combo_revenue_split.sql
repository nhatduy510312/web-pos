-- Phân bổ doanh thu cho combo Cafe + Bakery.
-- NULL: tính theo danh mục như trước; có giá trị: số tiền Bakery trong giá niêm yết của món.
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS bakery_revenue_amount DECIMAL(10,0) NULL
    AFTER price;

-- Lưu phần Bakery tại thời điểm bán để đổi giá/cấu hình sau này không làm sai báo cáo cũ.
ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS bakery_revenue_amount DECIMAL(10,0) NULL
    AFTER unit_price;

-- Ghi nhận dữ liệu lịch sử theo cấu hình/danh mục hiện tại trước khi sử dụng combo.
UPDATE order_items oi
JOIN products p ON p.id=oi.product_id
LEFT JOIN categories c ON c.id=p.category_id
SET oi.bakery_revenue_amount = CASE
    WHEN p.bakery_revenue_amount IS NOT NULL THEN p.bakery_revenue_amount
    WHEN LOWER(TRIM(c.name))='bakery' THEN oi.unit_price
    ELSE 0
END
WHERE oi.bakery_revenue_amount IS NULL;

