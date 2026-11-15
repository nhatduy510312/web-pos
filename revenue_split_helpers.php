<?php

/**
 * SQL tính phần doanh thu Bakery trước giảm giá của một dòng hóa đơn.
 *
 * - Dòng hóa đơn mới: dùng phần Bakery đã lưu tại thời điểm bán.
 * - Dữ liệu cũ có bakery_revenue_amount trên món: phân bổ theo tỷ lệ cấu hình.
 * - Món thường: tiếp tục xác định toàn bộ doanh thu theo danh mục Bakery như cũ.
 */
function revenueSplitBakeryGrossSql(
    string $itemAlias = 'oi',
    string $productAlias = 'p',
    string $categoryAlias = 'c'
): string {
    $configuredBakery = "COALESCE($productAlias.bakery_revenue_amount,0)";
    $productPrice = "COALESCE($productAlias.price,0)";

    return "CASE
        WHEN $itemAlias.bakery_revenue_amount IS NOT NULL
            THEN $itemAlias.qty*$itemAlias.bakery_revenue_amount
        WHEN $productAlias.bakery_revenue_amount IS NOT NULL THEN
            CASE WHEN $productPrice>0
                THEN $itemAlias.qty*$itemAlias.unit_price*$configuredBakery/$productPrice
                ELSE 0 END
        WHEN LOWER(TRIM($categoryAlias.name))='bakery' THEN $itemAlias.qty*$itemAlias.unit_price
        ELSE 0
    END";
}

/** SQL nhận diện sản phẩm có đóng góp doanh thu cho Bakery. */
function revenueSplitHasBakerySql(
    string $productAlias = 'p',
    string $categoryAlias = 'c'
): string {
    return "(
        COALESCE($productAlias.bakery_revenue_amount,0)>0
        OR (
            $productAlias.bakery_revenue_amount IS NULL
            AND LOWER(TRIM($categoryAlias.name))='bakery'
        )
    )";
}

