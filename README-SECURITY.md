# Hướng dẫn triển khai POS an toàn

Trước khi triển khai, hãy cấu hình các biến môi trường của máy chủ web:
`POS_DB_HOST`, `POS_DB_USER`, `POS_DB_PASS` và `POS_DB_NAME`. Tạo
`POS_DB_USER` là tài khoản cơ sở dữ liệu riêng, chỉ có các quyền mà ứng dụng
POS thực sự cần; không dùng tài khoản MySQL `root`.

Đặt `ServerName` Apache rõ ràng theo tên miền công khai. Chuyển hướng HTTPS sử
dụng `SERVER_NAME` để không phản chiếu header `Host` do kẻ tấn công kiểm soát.
Chỉ phục vụ website qua HTTPS.

Ứng dụng phân biệt quyền `staff` (thao tác đơn hàng tại POS) và `admin` (báo
cáo, sổ quỹ, chấm công, nhân viên và quản lý thực đơn). Mỗi tài khoản phải dùng
mật khẩu mạnh và không trùng lặp.

Chức năng giới hạn số lần đăng nhập đi kèm lưu trạng thái ngắn hạn trong thư
mục tạm của PHP. Khi triển khai nhiều máy chủ, hãy thay bằng bộ giới hạn dùng
chung, chẳng hạn Redis hoặc cơ chế lưu trong cơ sở dữ liệu.
