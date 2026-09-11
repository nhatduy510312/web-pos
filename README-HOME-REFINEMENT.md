# Trang chủ tinh gọn — 10/09/2026

## Thay đổi

- Trang chủ Việt và Anh dùng chung bố cục: lời mở đầu, ảnh không gian, tiện ích, món gợi ý, khối liên hệ cuối trang. Bài viết đã xuất bản vẫn hiển thị trên bản Việt nếu có.
- Địa chỉ, giờ mở cửa và liên kết Google Maps chỉ hiển thị một lần ở cuối trang chủ. Bỏ thanh thông tin trên đầu và thanh gọi/chỉ đường cố định trên điện thoại, riêng trang chủ.
- Trang chủ giới thiệu 3 ảnh, không lặp ảnh chính. Trang Không gian vẫn chứa đủ bộ 42 ảnh; trang giới thiệu và các trang khác giữ cách hiển thị hiện tại.
- Tông kem / xanh trầm, khoảng cách thoáng, bố cục ảnh và món phù hợp điện thoại lẫn máy tính.
- Thêm ô “Lời mở đầu ngắn trang chủ” trong quản trị, riêng tiếng Việt và Anh. Nội dung giới thiệu cũ vẫn được giữ cho SEO và trang giới thiệu. Không lặp địa chỉ hoặc giờ mở cửa trong lời mở đầu mới.
- Dữ liệu cấu trúc cho Google vẫn giữ địa chỉ và giờ mở cửa; thay đổi này chỉ gọn phần hiển thị, không xóa dữ liệu SEO.

## Cập nhật hosting

1. Gói `ghe-home-refinement-update.zip` dành cho bản website Việt/Anh + CMS đã cài bộ ảnh `ghe-real-photos-update.zip`.
2. Sao lưu các file đang chạy và thư mục `site/storage` trước khi cập nhật.
3. Giải nén, chép các file PHP và thư mục `assets`, `site` trong gói vào đúng vị trí tương ứng. Không xóa thư mục `site` hiện có: gói chỉ chứa những file cần cập nhật, không chứa ảnh và dữ liệu CMS.
4. Mở trang chủ Việt và Anh, tải lại bằng Ctrl + F5. Nếu hosting/CDN có cache, xóa cache trang chủ.

Không chạy SQL; không đổi cấu hình kết nối; không thay `site/storage/content.json`; không cần tải lại ảnh. Giữ nguyên giá món, dữ liệu bán hàng, bài viết và những thay đổi đang lưu nháp. Các chỉnh sửa nội dung tiếp theo vẫn qua Lưu bản nháp → Xem trước → Xuất bản website.

## Kiểm thử local

`tools/check-home-refinement.cjs` kiểm tra hai ngôn ngữ ở 360, 768 và 1440px: thông tin liên hệ duy nhất cuối trang, không tràn ngang, ảnh tải được, dữ liệu SEO còn nguyên, chỉnh lời mở đầu qua nháp/xem trước/xuất bản và nội dung vẫn dùng được khi tắt JavaScript.

Gói được chuẩn bị trong máy làm việc, không tự triển khai lên hosting.
