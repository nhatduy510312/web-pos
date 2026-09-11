# Khu vực nhập mua hàng outsource

## Cài đặt

1. Sao lưu database.
2. Chạy `migration_purchasing_portal.sql` một lần trong phpMyAdmin.
3. Tải toàn bộ code mới lên server.
4. Đăng nhập POS bằng admin, mở **TK mua hàng**, tạo tên đăng nhập và mật khẩu cho nhân viên mua hàng.
5. Gửi cho nhân viên đường dẫn `purchase_login.php`. Không gửi tài khoản POS.

## Phân quyền

- Tài khoản có role `purchaser` chỉ đăng nhập tại `purchase_login.php`.
- Phiên mua hàng tách khỏi phiên POS. Tài khoản mua hàng đăng nhập ở trang POS sẽ bị từ chối.
- Người nhập chỉ xem, sửa và xóa các dòng do chính tài khoản đó tạo.
- Người nhập có thể thêm, sửa và xóa các lần nhận tiền ứng ngay trên cùng trang mua hàng.
- Admin tạo, đổi tên, đặt lại mật khẩu, khóa hoặc kích hoạt tài khoản từ `purchase_accounts.php`.
- Khi admin khóa tài khoản hoặc đổi mật khẩu, các phiên mua hàng cũ bị thu hồi.

## Báo cáo tiền chi

`expense_report.php` cộng hai nguồn:

- Tiền chi tại quầy: số đã lưu trong `cashbook_history` khi chốt ca.
- Mua hàng outsource: toàn bộ `purchase_entries` theo `purchase_date` trong tháng.

Các khoản mua hàng không thay đổi tiền mặt cuối ca và không phụ thuộc việc ca đã chốt hay chưa. Báo cáo hiển thị riêng từng nguồn và tổng cộng để dễ đối chiếu.

## Số dư tiền ứng

Trang `purchases.php` hiển thị số dư lũy kế của từng tài khoản:

`Số tiền còn lại = Tổng tiền đã ứng − Tổng tiền mua hàng đã chi`

Ba số tổng hợp là lũy kế toàn bộ thời gian để tiền ứng từ tháng trước vẫn được chuyển sang tháng sau. Bộ lọc tháng bên dưới chỉ thay đổi lịch sử và phần tổng hợp phát sinh của tháng. Tiền ứng không được cộng vào báo cáo tiền chi; đây chỉ là nguồn tiền để đối chiếu số dư của nhân viên mua hàng.

Nếu đã cài phiên bản trước chưa có tiền ứng, hãy chạy lại file `migration_purchasing_portal.sql` mới. Các lệnh `CREATE TABLE IF NOT EXISTS` không xóa dữ liệu cũ.
