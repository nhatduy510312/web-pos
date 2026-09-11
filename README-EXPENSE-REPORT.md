# Báo cáo tiền chi theo tháng

Admin vào **Tiền chi** trên thanh menu hoặc **Lịch sử → Báo cáo tiền chi theo tháng**, chọn tháng rồi bấm **Xem báo cáo**. Mặc định là tháng hiện tại. Có thể mở chi tiết từng ngày, xem ghi chú và in báo cáo.

## Cách tính

- Tổng tháng cộng đúng cột `expenses` của `cashbook_history` theo `report_date` trong tháng đã chọn. Không cộng nộp doanh thu, tiền đầu/cuối ca, doanh thu hay khoản chi của ca chưa chốt.
- Chi tiết đọc từ `cash_expenses`, ghép bằng `expense_date`. Không nhân đôi tổng ca khi một ngày có nhiều khoản chi.
- Ca chưa chốt hoặc đã mở lại (không còn bản chốt) hiển thị riêng ở cuối, không cộng vào tổng đã chốt. Khi chốt lại, tải lại báo cáo để cập nhật.
- Nếu thiếu chi tiết hoặc tổng chi tiết khác bản chốt, báo cáo hiển thị cảnh báo và vẫn lấy số đã chốt làm tổng chính; không tự sửa dữ liệu.
- Số tiền DECIMAL được tính bằng đơn vị 1/100 đồng để giữ chính xác cả dữ liệu có phần thập phân.
- Báo cáo chỉ đọc, không tự động chốt ca khi mở trang. Chỉ admin được truy cập; quyền tài khoản được kiểm tra lại với DB, có noindex/no-store.

## Cập nhật

Gói `ghe-monthly-expenses-update.zip` dùng cho phiên bản POS + quản trị website hiện tại trong task này. Sao lưu các file trước khi chép đè. Gói gồm `expense_report.php`, `site/expense-report.php`, `menu.php`, `cashbook_history.php` và tài liệu này. Không cần migration SQL, không sửa dữ liệu cũ. Hai bảng `cashbook_history` và `cash_expenses` phải có sẵn từ chức năng chốt ca.

Tái sử dụng `site/admin-auth.php`, `site/catalog.php`, `auth.php`, `account_security.php`, `csrf.php` đang có trong bản quản trị website. Thông số kết nối là các biến `POS_DB_HOST`, `POS_DB_PORT`, `POS_DB_NAME`, `POS_DB_USER`, `POS_DB_PASS`, giống quản trị website. Không ghi đè cấu hình hosting.

Nếu hosting có thay đổi menu hoặc lịch sử mới hơn bản trong gói, chỉ gộp liên kết `expense_report.php` trong khối admin thay vì chép đè hai file đó. Chưa triển khai trực tiếp lên hosting.

## Kiểm thử

`tools/expense-test-fixture.php` và `tools/check-expense-report.cjs` chỉ chạy với DB thử `ghe_website_test` trên localhost:33077. Kiểm tra tổng/chi tiết, số thập phân, ngày biên tháng/năm nhuận, tháng trống, tháng sai, ca chưa chốt, lệch số, XSS, admin/staff/tài khoản bị thu hồi, giao diện 360/1440px và dữ liệu trước/sau không thay đổi. Không đóng gói dữ liệu thử vào bản cập nhật.
