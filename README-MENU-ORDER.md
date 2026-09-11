# Đưa Ăn nhẹ xuống cuối thực đơn

Chép đè file `site/catalog.php` trong gói `ghe-menu-order-update.zip` vào đúng vị trí trên hosting đang dùng bản website Việt/Anh và bộ ảnh thật hiện tại. Sao lưu file cũ trước khi cập nhật.

Nhóm Ăn nhẹ nằm cuối danh sách và thanh liên kết nhóm món ở cả hai ngôn ngữ. Thứ tự các nhóm khác và món trong mỗi nhóm giữ nguyên. Không thay đổi thứ tự ở màn hình bán hàng POS, giá, trạng thái món hoặc dữ liệu trong database. Không chạy SQL.

Kiểm thử local: `tools/check-menu-category-order.php`.
