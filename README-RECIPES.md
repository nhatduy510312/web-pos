# Phiếu chế biến sau hóa đơn

Tính năng này đọc công thức trực tiếp từ `data/recipes.json`. Dữ liệu được tạo từ hai
sheet `ĐỒ UỐNG` và `MÓN ĂN` trong file `CÔNG THỨC GHÉ.xlsx`; sheet `CHECKLIST` không
được sử dụng.

## Triển khai

Đưa các file và thư mục sau lên cùng hệ thống POS:

- `receipt.php`
- `recipe_helpers.php`
- toàn bộ thư mục `data/`

Không cần chạy migration hay tạo bảng công thức trong MySQL. Đơn hàng và sản phẩm vẫn
được lấy từ database hiện tại; PHP dùng tên sản phẩm để tìm công thức tương ứng trong
`data/recipes.json`.

File `data/.htaccess` chặn truy cập trực tiếp vào dữ liệu JSON trên máy chủ Apache.
PHP vẫn có thể đọc file này từ nội bộ hệ thống.

Để menu quản trị có thể lưu thay đổi, tài khoản chạy PHP trên máy chủ phải có quyền
ghi vào file `data/recipes.json` và thư mục `data/` (thư mục cần quyền ghi để tạo file
sao lưu). Trang **Công thức** sẽ hiển thị cảnh báo nếu thiếu quyền.

## Luồng sử dụng

Sau khi thanh toán, trang hóa đơn vẫn tự mở hộp thoại in như trước. Khi nhân viên chọn
**In** hoặc **Hủy**, hệ thống mở Phiếu chế biến. Nút **Tắt · về POS** đóng phiếu và
quay lại trang bán hàng.

Nếu trình duyệt không kích hoạt sự kiện sau khi đóng hộp thoại in, nhân viên có thể
bấm nút **Phiếu chế biến** ngay trên màn hình hóa đơn.

Sản phẩm không có công thức vẫn xuất hiện trên phiếu nhưng được đánh dấu
**Chưa có công thức**.

Menu **Nguyên liệu** dành cho cả admin và nhân viên hiển thị ở chế độ chỉ xem 10 công
thức nguyên liệu nền từ phần cuối sheet `ĐỒ UỐNG`. Trang hỗ trợ tìm kiếm, giao diện
thẻ trên điện thoại và in bảng; dữ liệu `MÓN ĂN` không được trộn vào danh sách này.

## Cập nhật công thức

- Tài khoản có quyền `admin` quản lý sản phẩm và công thức chung trong menu
  **Thực đơn**. Biểu mẫu thêm món cho phép nhập công thức ngay trong một lần lưu; nút
  công thức ở từng dòng sản phẩm mở màn hình chỉnh sửa chi tiết.
- Khi lưu, hệ thống cập nhật `data/recipes.json` và tạo `data/recipes.json.bak` từ dữ
  liệu trước khi sửa.
- Nội dung công thức nằm trong khóa `recipes`.
- Phần liên kết tên món của POS với công thức nằm trong `product_map`.
- Nếu đổi tên sản phẩm trong POS, cần thêm tên mới vào `product_names` của ánh xạ tương
  ứng.
- Giữ file JSON ở mã hóa UTF-8 và kiểm tra JSON hợp lệ trước khi đưa lên hệ thống.
