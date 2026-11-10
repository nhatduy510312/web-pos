# Pre-Order 24boulangerie

1. Trên database `cafe_pos`, chạy `migration_bread_preorders.sql` trước khi đưa các file mới lên máy chủ. Có thể chạy lại an toàn để tạo bảng quản lý sản phẩm Pre-Order nếu đã cài phiên bản trước.
2. Trong POS, tạo/bật sản phẩm bánh thuộc danh mục `Bakery`. Mở **Pre-Order → Sản phẩm Pre-order**, chọn tệp ảnh từ máy tính và bấm **Lưu**. Ảnh JPEG, PNG hoặc WebP tối đa 8 MB được tự tối ưu; ảnh tải lên sẽ ưu tiên hơn ảnh mặc định của món. Chọn **Hiển thị Daily** để đưa món vào mục Sản phẩm hằng ngày (đặt trước ít nhất 1 ngày); các món Pre-Order cần đặt trước ít nhất 3 ngày. Sản phẩm đang tắt trong POS vẫn bị ẩn.
3. Mở **Pre-Order → Đơn đặt bánh online** để xem và cập nhật trạng thái đơn.
4. Đường dẫn công khai là `banh-mi.php`.

Khách chọn ngày nhận/giao trước ít nhất 3 ngày (sau giờ đóng cửa, ngày sớm nhất là sau 4 ngày), khung giờ theo giờ hoạt động, và cung cấp tên cùng số điện thoại. Khách có thể lấy tại cửa hàng hoặc gửi địa chỉ giao hàng. Trang không yêu cầu hoặc nhận thanh toán. Nhân viên 24boulangerie liên hệ khách để xác nhận đơn.

Đơn đặt trước được lưu trong `bread_preorders` và `bread_preorder_items`, không được tạo thành hóa đơn POS và không cộng vào doanh thu trước khi nhân viên xử lý tại quầy.
