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
