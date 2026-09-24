# Định hướng giới thiệu GHÉ — 12/09/2026

## Thông điệp

**Ghé một chút. Chậm lại một chút.**

GHÉ được giới thiệu như một quán cà phê có sân vườn xanh và không gian trong nhà thoáng mát, tinh tế. Khách có thể đến để học tập, làm việc, gặp gỡ, sẻ chia hoặc đơn giản là dành cho mình một nhịp chậm hơn.

## Trải nghiệm trang chủ

- Trang chủ Việt và Anh dùng chung một bố cục tối giản, nhiều khoảng trắng và ảnh thật.
- Menu trang chủ chỉ còn `Không gian`, `Thực đơn`, `Đến Ghé`; các trang chi tiết cũ vẫn tồn tại cho SEO và liên kết ngữ cảnh.
- Ba hình ảnh chủ đích lần lượt kể về sân vườn, không gian trong nhà và góc học tập/làm việc.
- Phần thực đơn tự hiển thị món từ POS khi có dữ liệu; nếu chưa có món vẫn có lời giới thiệu và liên kết tới thực đơn.
- Địa chỉ, giờ mở cửa, số điện thoại và Google Maps được gom ở cuối trang.
- Nội dung tiếng Việt và tiếng Anh đã được viết lại cùng một tinh thần thương hiệu.

## Các file chính

- `site/home-content.php`: cấu trúc và nội dung trang chủ.
- `assets/home.css`: giao diện máy tính, máy tính bảng và điện thoại.
- `site/public-components.php`: ba khối hình ảnh không gian.
- `site/content-store.php`: nội dung mặc định và bản cập nhật nội dung một lần, không ghi đè dữ liệu CMS đang có trên server.
- `site/header.php`: menu tinh gọn và chuyển ngôn ngữ.

## Cập nhật server

Gói cập nhật không chứa `site/storage/content.json`, thư mục ảnh hay `vendor`. Khi mở website lần đầu, nội dung thương hiệu mới được áp dụng cho cả bản Việt và Anh; ảnh, món, bài viết và cài đặt khác trên server vẫn được giữ nguyên. Lần lưu nội dung tiếp theo trong quản trị sẽ ghi nhận phiên bản cập nhật này vào kho nội dung.

Không có thay đổi cơ sở dữ liệu hoặc câu lệnh SQL cho phần này.
