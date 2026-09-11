# Bộ ảnh thật của Ghé — 10/09/2026

## Đã thêm

- Dùng đủ **42 ảnh** trong thư mục CHỌN: 38 ảnh không gian / hoạt động tại quán và 4 ảnh món đã được chủ quán đặt tên.
- Trang chủ và trang giới thiệu hiển thị 6 ảnh đầu theo thứ tự thư viện, kèm liên kết xem toàn bộ. Ảnh sân vườn làm ảnh chính nếu chưa có ảnh chính do admin chọn trước đó.
- Trang **Không gian** (`khong-gian.php`) và bản English **Gallery** (`en.php?view=gallery`) hiển thị đầy đủ ảnh, mở ảnh lớn bằng nút bấm, chuyển ảnh bằng mũi tên và đóng bằng Escape. Không có JavaScript vẫn mở được ảnh gốc đã tối ưu qua liên kết.
- Trang chủ ưu tiên các món có ảnh thật khi admin chưa chọn món gợi ý. Không dùng hình ly nước minh họa cho món chưa có ảnh.
- Bản Việt và Anh có chú thích / alt riêng, canonical / hreflang và trang thư viện trong sitemap.

## Gắn ảnh món

| File chủ quán cung cấp | Món được gắn mặc định | Ghi chú hiển thị |
| --- | --- | --- |
| Bạc sỉu và bánh croissant.jpg | Bạc xỉu, Croissant truyền thống | Ảnh gồm cả hai món |
| Cà phê cốt dừa và cà phê muối.jpg | Sữa dừa / Cà phê cốt dừa, Muối / Cà phê muối | Ảnh gồm cả hai món |
| Sữa chua dâu tằm.jpg | Sữa chua trái cây / Sữa chua dâu tằm | Đây là phiên bản dâu tằm |
| Trà ổi chanh dây.jpg | Ổi chanh dây / Trà ổi chanh dây | Theo tên món |

Tên được đối chiếu không phân biệt hoa/thường; không gắn ảnh vào món lạ theo phỏng đoán. Giá và trạng thái bán vẫn lấy trực tiếp từ POS. Ảnh admin đã chọn được giữ ưu tiên. Trong **Website → Menu từ POS**, có thể đổi ảnh hoặc chọn “Chưa chọn ảnh”; sau khi lưu, lựa chọn riêng được giữ theo ID món.

## Chỉnh ảnh trong quản trị

**Website → Thư viện ảnh** đã có cả bộ ảnh, không cần tải thủ công từng tấm. Đổi ảnh đầu trang, chú thích VI/EN, thứ tự, bật/tắt hiển thị hoặc gỡ ảnh như trước. Lưu và xuất bản để áp dụng những chỉnh sửa tiếp theo. Gỡ ảnh rồi lưu sẽ không tự thêm lại khi tải trang.

## Cập nhật hosting

1. Sao lưu mã nguồn và thư mục `site/storage` hiện tại.
2. Giải nén `ghe-real-photos-update.zip`. Tải thư mục `site/photos/collection-20260910` cùng `site/photo-collection.json` và `site/photo-collection.php` lên trước.
3. Chép đè các file PHP/CSS/JS còn lại đúng cấu trúc. Nếu `.htaccess` trên hosting có tùy chỉnh mới, giữ tùy chỉnh đó và thêm `khong-gian` vào danh sách trang PHP công khai thay vì chép đè toàn bộ.
4. Mở trang chủ và trang Không gian, Ctrl + F5 để lấy CSS/JS mới.

Gói cập nhật dành cho bản website Việt/Anh + CMS hiện tại trong task. Không chứa `site/storage/content.json`, ảnh upload cũ, cấu hình DB hoặc dữ liệu kinh doanh. Không chạy migration SQL.

Bộ ảnh được bổ sung vào bản nội dung công khai và bản nháp hiện có ngay khi đọc dữ liệu sau cập nhật; không xuất bản các sửa đổi khác đang còn ở bản nháp. Nội dung, bài viết, giá, trạng thái món và ảnh chính đã chọn trước đó được giữ nguyên. Lần lưu CMS tiếp theo sẽ lưu dấu phiên bản bộ ảnh cùng dữ liệu; sau đó các thao tác ẩn/gỡ ảnh vẫn được tôn trọng. Không triển khai bằng cách thay `content.json`.

## Dung lượng và bảo quản

- Ảnh gốc khoảng **227,6 MB**, không bị sửa, đổi tên hay xóa khỏi Downloads.
- Bộ web gồm 84 file WebP (42 ảnh lớn tối đa 1600px và 42 ảnh nhỏ tối đa 480px), tổng khoảng **7,05 MB**; xoay theo EXIF, bỏ metadata và không tạo thêm chi tiết bằng AI.
- Trang thư viện tải ảnh nhỏ khi cuộn đến; ảnh lớn chỉ được yêu cầu khi mở xem. Ảnh chính tải ưu tiên. Các ảnh món sử dụng kích thước phù hợp màn hình.
- Ảnh bundled nằm trong thư mục `site/` bị chặn truy cập trực tiếp và được phục vụ qua `ghe-image.php`. Không xóa các file bundled khỏi hosting chỉ vì đã gỡ khỏi bản nháp: bản công khai có thể vẫn đang dùng.
- Không chép gói cập nhật website cũ lên sau gói ảnh vì có thể mất phần hỗ trợ thư viện ảnh.

Kiểm thử: `tools/check-cafe-photos.cjs`, `tools/check-photo-upgrade.php` kiểm tra 42 ảnh, 84 WebP, xoay/kích thước/metadata, quyền xem bản nháp, gắn ảnh theo món, bảo toàn dữ liệu, ẩn/gỡ không tự khôi phục, VI/EN, lightbox, SEO và responsive.
