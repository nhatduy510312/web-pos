-- Chạy một lần trên database hiện có sau khi sao lưu.
-- Các câu lệnh tự bỏ qua nếu index đã tồn tại.

SET @schema_name = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='orders'
       AND index_name='idx_orders_status_paid_at') = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_status_paid_at (status, paid_at)',
    'SELECT 1'
);
PREPARE migration_statement FROM @sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='orders'
       AND index_name='idx_orders_table_status') = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_table_status (table_id, status)',
    'SELECT 1'
);
PREPARE migration_statement FROM @sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema=@schema_name AND table_name='order_items'
       AND index_name='idx_order_items_order_product') = 0,
    'ALTER TABLE order_items ADD INDEX idx_order_items_order_product (order_id, product_id)',
    'SELECT 1'
);
PREPARE migration_statement FROM @sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
