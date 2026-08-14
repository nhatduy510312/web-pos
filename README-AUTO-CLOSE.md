# Tự động chốt ca lúc 22:00

Hệ thống mặc định dùng múi giờ `Asia/Ho_Chi_Minh` và tự chốt các ca có phát sinh dữ liệu lúc `22:00`.

## Cấu hình Cron Job

Để tác vụ chạy đúng 22:00 ngay cả khi không có ai mở website, cấu hình Cron Job trên hosting:

```cron
0 22 * * * /usr/local/bin/php /duong-dan-tuyet-doi/auto_close_shift.php
```

Thay đường dẫn PHP và đường dẫn website theo thông tin hosting. File chỉ chạy bằng PHP CLI và không thể gọi trực tiếp từ trình duyệt.

Ngoài Cron Job, mọi lượt truy cập website sau 22:00 cũng tự kiểm tra và chốt các ca còn thiếu. Đây là lớp dự phòng nếu Cron Job không chạy.

Có thể đổi giờ và múi giờ bằng biến môi trường:

```text
POS_AUTO_CLOSE_TIME=22:00
POS_TIMEZONE=Asia/Ho_Chi_Minh
```

Sau khi ca đã chốt hoặc đến giờ đóng ca, API bán hàng sẽ từ chối ghi thêm
giao dịch trong ngày đó. Admin có thể mở lại ca để nhân viên chỉnh sửa. Ca đã
mở lại không còn giới hạn 15 phút và không bị tự chốt lại lúc 22:00; ca chỉ khóa
khi nhân viên bấm **Chốt ca ngày này**.

Trạng thái mở lại được lưu trong bảng `cashbook_reopened_shifts`. Code sẽ tự tạo
bảng này trong lần admin mở lại ca đầu tiên; file migration tổng hợp cũng đã
bao gồm lệnh `CREATE TABLE IF NOT EXISTS` để có thể chạy trước khi chép code.
