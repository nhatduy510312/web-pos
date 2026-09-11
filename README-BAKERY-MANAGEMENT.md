# Module quản lý bếp bánh

Module độc lập với POS, dùng role `bakery_admin`, session và thanh menu riêng. Tài khoản bếp bánh không thể truy cập các trang POS.

POS, Bakery và khu vực Mua hàng sử dụng ba cookie phiên riêng, nên có thể đăng nhập đồng thời trong cùng một trình duyệt. Đăng nhập hoặc đăng xuất một khu vực không ảnh hưởng hai khu vực còn lại.

## Cài đặt

1. Sao lưu database và website.
2. Chạy `migration_bakery_management.sql` trong phpMyAdmin.
3. Chép code module lên thư mục gốc website.
4. Đăng nhập POS bằng admin, mở menu **Tài khoản**, chọn khu vực **Bakery** và tạo tài khoản.
5. Mở `bakery_login.php` để đăng nhập. POS và Bakery có thể đăng nhập đồng thời.

Trang `bakery_install.php` tự khóa sau khi đã có tài khoản `bakery_admin`.

## Chức năng

- Tổng quan chi phí đầu tư, thiết bị, mua nguyên liệu và cảnh báo tồn kho.
- Quản lý đầu tư phần thô theo hạng mục, nhà thầu, trạng thái dự kiến/đã thanh toán.
- Quản lý mua sắm thiết bị, số lượng, đơn giá, nhà cung cấp và trạng thái triển khai.
- Quản lý chi phí vận hành như thuê mặt bằng, điện, nước, internet, nhân sự, bảo trì và phí dịch vụ.
- Danh mục nguyên vật liệu, đơn vị tính và mức tồn kho tối thiểu.
- Cho phép xóa nguyên liệu chưa phát sinh nhập/xuất; nguyên liệu đã có lịch sử được giữ lại để bảo toàn báo cáo.
- Mua nguyên vật liệu tự động làm tăng tồn kho.
- Ghi nhận sử dụng/xuất kho, nhập điều chỉnh và xuất điều chỉnh.
- Tồn kho được tính từ toàn bộ lịch sử nhập/xuất; hệ thống chặn xuất vượt số đang có.
- Báo cáo tổng quan, đầu tư, thiết bị, chi phí vận hành, mua nguyên liệu và lịch sử kho đều lọc được từ tháng đến tháng.
- Menu báo cáo tổng hợp cho phép chọn chính xác ngày bắt đầu, ngày kết thúc, lọc từng hạng mục và mở bản ghi gốc để xem hoặc chỉnh sửa.
- Menu doanh thu lấy trực tiếp các món thuộc danh mục `Bakery` trong POS, tổng hợp theo món, theo ngày và chi tiết từng lượt bán; giảm giá hóa đơn được phân bổ theo giá trị món.
- Có nút **VI / EN** trên trang đăng nhập và thanh menu để chuyển toàn bộ giao diện Bakery giữa tiếng Việt và tiếng Anh. Lựa chọn được giữ trong phiên Bakery.
- Mọi tài khoản trong module có cùng quyền quản trị, không có phân cấp quyền nội bộ.
