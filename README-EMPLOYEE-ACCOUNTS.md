# Tài khoản riêng và chấm công theo nhân viên

## Thứ tự triển khai

1. Sao lưu cơ sở dữ liệu.
2. Trong phpMyAdmin, chọn đúng database của website và chạy toàn bộ file `migration_employee_accounts.sql`. File này tự bổ sung cả cột `role` nếu database cũ chưa có.
3. Chép đè các file PHP mới lên thư mục gốc website.
4. Đăng nhập bằng admin, mở **Nhân viên**.
5. Với nhân viên hiện có, nhập tên đăng nhập và mật khẩu mới rồi bấm **Lưu**.
6. Với nhân viên mới, nhập tên, tên đăng nhập, mật khẩu và loại hợp đồng trong form **Thêm nhân viên**.

Mỗi nhân viên được tạo với role `staff`. Khi đăng nhập, nhân viên chỉ nhìn thấy, thêm và xóa dữ liệu chấm công của chính mình. Admin vẫn quản lý được toàn bộ dữ liệu chấm công.

Khi thanh toán hóa đơn, hệ thống lưu tài khoản thực hiện. Cột **Nhân viên** trong Báo cáo và file Excel hiển thị tên nhân viên đã bán hóa đơn. Hóa đơn cũ tạo trước cập nhật sẽ hiển thị “—”.

Tài khoản dùng chung cũ không được tự động gắn với nhân viên nào và sẽ không thể chấm công. Sau khi cấp đủ tài khoản riêng, nên ngừng chia sẻ tài khoản cũ.

## Bảo mật tài khoản

- Nhân viên mới hoặc được admin đặt lại mật khẩu phải đổi mật khẩu ở lần đăng nhập kế tiếp.
- Khi admin đặt lại mật khẩu hoặc ngừng hoạt động nhân viên, mọi phiên đăng nhập cũ bị thu hồi.
- Admin có thể ngừng/kích hoạt lại nhân viên mà không xóa lịch sử hóa đơn và chấm công.
- Trang Nhân viên hiển thị lần đăng nhập cuối và 100 sự kiện đăng nhập gần nhất.
- Chống thử mật khẩu khóa tạm theo tên tài khoản và IP sau 5 lần sai trong 15 phút.
- Khi đã cấp đủ tài khoản riêng, admin dùng nút **Khóa tài khoản dùng chung** ở trang Nhân viên.
- Nhân viên không còn quyền truy cập Thực đơn và Danh mục, kể cả gọi URL trực tiếp.
